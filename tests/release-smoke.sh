#!/bin/sh
# Run in transient wpcli container. Core/database fixtures use a unique prefix.
set -eu
core_dir=${NETCHAT_CORE_DIR:-/var/www/html}
release_dir=${NETCHAT_RELEASE_DIR:-/var/www/html/wp-content/plugins/netchat/dist/release-1.2.0}
archive="$release_dir/netchat-wp.zip"
previous_archive="$release_dir/historical-v1.0.0.zip"
upgrade_archive="$release_dir/netchat-upgrade-WP-NetChat-main.zip"
# Verify the public historical asset before touching the fixture database.
echo "ead4077f9a746370715d286546927f449e2b28ef3434f03c1a9c587fd1d38b7d  $previous_archive" | sha256sum --check
fixture=$(mktemp -d /tmp/netchat-release-XXXXXX)
prefix="nc_release_$(basename "$fixture" | tr -cd 'a-zA-Z0-9')_"
cleanup() {
  if [ -f "$fixture/wp-config.php" ]; then
    wp --path="$fixture" --skip-plugins --skip-themes eval '
      global $wpdb;
      if (strpos($wpdb->prefix, "nc_release_") !== 0) { WP_CLI::error("Unexpected fixture prefix."); }
      $tables = array_merge($wpdb->tables("all", true), array($wpdb->prefix . "netchat_leads", $wpdb->prefix . "netchat_lead_keys", $wpdb->prefix . "netchat_lead_limits"));
      foreach ($tables as $table) {
        if (strpos($table, $wpdb->prefix) === 0) { $wpdb->query("DROP TABLE IF EXISTS `" . esc_sql($table) . "`"); }
      }
    ' >/dev/null 2>&1 || true
  fi
  # The directory was allocated by mktemp above and only contains this fixture.
  case "$fixture" in /tmp/netchat-release-*) rm -rf -- "$fixture" ;; esac
}
trap cleanup EXIT HUP INT TERM
cp -R "$core_dir/wp-admin" "$core_dir/wp-includes" "$fixture/"
cp "$core_dir"/*.php "$fixture/"
mkdir -p "$fixture/wp-content/plugins" "$fixture/wp-content/themes"
wp --path="$fixture" config set table_prefix "$prefix" --type=variable >/dev/null
wp --path="$fixture" config set WP_DEBUG_LOG false --raw >/dev/null
wp --path="$fixture" core install --url=http://netchat-release.test --title='NetChat package fixture' --admin_user=netchat_fixture --admin_password=fixture-only-not-public --admin_email=fixture@example.test --skip-email >/dev/null
wp --path="$fixture" plugin install "$archive" --activate >/dev/null
wp --path="$fixture" eval '
  if (NETCHAT_VER !== "1.2.0") { WP_CLI::error("Wrong package version."); }
  $forms=netchat_service_forms_config();if(count($forms["installed"])!==2||array_keys($forms["installed"])!==array("nc_primary_v1","nc_example_v1")||$forms["primary_form"]!=="nc_primary_v1"||$forms["enabled"]!==0)WP_CLI::error("Fresh public defaults differ");
  $config = netchat_defaults(); $config["links"]["telegram"] = "https://t.me/durov"; $config["enabled"]["telegram"] = 1;
  if (is_wp_error(netchat_save_config($config, netchat_get_revision()))) { WP_CLI::error("Fixture save failed."); }
  $config["leads"]["enabled"]=1; $config["leads"]["email"]["enabled"]=0;
  if (is_wp_error(netchat_save_config($config, netchat_get_revision()))) { WP_CLI::error("Lead fixture settings failed."); }
  $forms["installed"]["nc_primary_v1"]["title"]="Synthetic retained edit";update_option("netchat_service_forms",$forms,false);
  $nonce=time().".".bin2hex(random_bytes(16));
  $lead=netchat_leads_create(array("request_key"=>bin2hex(random_bytes(16)),"token"=>$nonce.".".netchat_leads_token_signature($nonce),"email"=>"fixture@example.test","message"=>"Package lead retention fixture"));
  if (is_wp_error($lead)) { WP_CLI::error("Package lead fixture failed."); }
' >/dev/null
# Reinstalling the same ZIP checks the WordPress upgrade path.
wp --path="$fixture" plugin install "$archive" --force >/dev/null
wp --path="$fixture" plugin deactivate netchat >/dev/null
wp --path="$fixture" plugin activate netchat >/dev/null
wp --path="$fixture" eval '
  $config = netchat_get_config();
  if ($config["enabled"]["telegram"] !== 1 || $config["links"]["telegram"] !== "https://t.me/durov") { WP_CLI::error("Package upgrade/reactivation lost contact."); }
  if(netchat_service_forms_config()["installed"]["nc_primary_v1"]["title"]!=="Synthetic retained edit")WP_CLI::error("Reactivation overwrote existing form");
' >/dev/null
wp --path="$fixture" plugin deactivate netchat >/dev/null
wp --path="$fixture" plugin uninstall netchat >/dev/null
wp --path="$fixture" --skip-plugins eval 'if (!get_option("netchat_settings")) { WP_CLI::error("Default uninstall lost settings."); }' >/dev/null
wp --path="$fixture" --skip-plugins eval 'global $wpdb; if ((int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."netchat_leads") !== 1) { WP_CLI::error("Default uninstall lost request."); }' >/dev/null
# Upgrade the original code while it remains active, with legacy JSON present.
if [ ! -f "$previous_archive" ] || [ ! -f "$upgrade_archive" ]; then echo "Missing actual historical/compatible upgrade ZIP" >&2; exit 1; fi
if [ -f "$previous_archive" ]; then
  wp --path="$fixture" --skip-plugins option delete netchat_settings >/dev/null
  wp --path="$fixture" plugin install "$previous_archive" --activate >/dev/null
  wp --path="$fixture" eval '
    $config = netchat_defaults(); $config["links"]["telegram"] = "https://t.me/durov"; $config["enabled"]["telegram"] = 1;
    if (!netchat_save_config($config)) { WP_CLI::error("Legacy save failed."); }
  ' >/dev/null
  wp --path="$fixture" plugin install "$upgrade_archive" --force >/dev/null
  wp --path="$fixture" eval '
    if (NETCHAT_VER !== "1.2.0" || !is_file(NETCHAT_DATA)) { WP_CLI::error("Legacy upgrade source missing."); }
    $config = netchat_get_config();
    global $wpdb;if((int)$wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."netchat_leads")!==1)WP_CLI::error("Upgrade lost request");
    if(get_option("active_plugins")!==array("WP-NetChat-main/netchat.php"))WP_CLI::error("Upgrade activated a different/second basename");
    if ($config["links"]["telegram"] !== "https://t.me/durov" || $config["enabled"]["telegram"] !== 1 || !get_option("netchat_settings")) { WP_CLI::error("Active 1.0.0 upgrade lost settings."); }
  ' >/dev/null
  echo 'PASS ZIP: active original 1.0.0 upgrade migrated JSON and retained contact.'
fi
wp --path="$fixture" config set NETCHAT_DELETE_DATA true --raw >/dev/null
wp --path="$fixture" plugin deactivate WP-NetChat-main >/dev/null
wp --path="$fixture" plugin uninstall WP-NetChat-main >/dev/null
wp --path="$fixture" --skip-plugins eval '
  global $wpdb;
  if (get_option("netchat_settings") || get_option("netchat_leads_schema")) { WP_CLI::error("Opt-in purge retained options."); }
  foreach (array("netchat_leads","netchat_lead_keys","netchat_lead_limits") as $suffix) {
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$wpdb->esc_like($wpdb->prefix.$suffix)))) { WP_CLI::error("Opt-in purge retained lead table."); }
  }
  if (wp_next_scheduled("netchat_leads_send_notifications") || wp_next_scheduled("netchat_leads_retention_cleanup")) { WP_CLI::error("Uninstall retained worker schedule."); }
' >/dev/null
echo 'PASS ZIP: default uninstall retained request; explicit purge removed settings, lead tables and workers.'
echo 'PASS ZIP: clean install, reinstall upgrade, reactivation and default uninstall retention.'
