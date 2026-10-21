#!/usr/bin/env bash
# Exact public 1.2.0 -> candidate 1.2.2, disposable hosted core only.
set -euo pipefail
[[ ${CI:-} == true && ${GITHUB_ACTIONS:-} == true ]] || { echo 'Hosted disposable worker required' >&2; exit 1; }
core=${NETCHAT_CORE_DIR:?}; release=${NETCHAT_RELEASE_DIR:?}
fixture=$(mktemp -d /tmp/netchat-release-121-XXXXXX)
prefix="nc_release_$(basename "$fixture" | tr -cd a-zA-Z0-9)_"
cleanup() {
 status=$?; trap - EXIT
 if [[ -f "$fixture/wp-config.php" ]]; then
  wp --path="$fixture" --skip-plugins --skip-themes eval '
   global $wpdb;
   if(strpos($wpdb->prefix,"nc_release_netchatrelease121")!==0)WP_CLI::error("Unexpected fixture prefix");
   $tables=$wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s",$wpdb->esc_like($wpdb->prefix)."%"));
   if($wpdb->last_error)WP_CLI::error("Cleanup inventory failed");
   foreach($tables as $table){if(strpos($table,$wpdb->prefix)!==0)WP_CLI::error("Foreign table");if($wpdb->query("DROP TABLE `".esc_sql($table)."`")===false)WP_CLI::error("Cleanup failed");}
  ' >/dev/null || status=1
 fi
 case "$fixture" in /tmp/netchat-release-121-*) rm -rf -- "$fixture" ;; *) status=1 ;; esac
 exit "$status"
}
trap cleanup EXIT
cp -R "$core/wp-admin" "$core/wp-includes" "$fixture/"
cp "$core"/*.php "$fixture/"
mkdir -p "$fixture/wp-content/plugins" "$fixture/wp-content/themes"
wp --path="$fixture" config set table_prefix "$prefix" --type=variable >/dev/null
wp --path="$fixture" config set WP_DEBUG_LOG false --raw >/dev/null
wp --path="$fixture" config set DISABLE_WP_CRON true --raw >/dev/null
wp --path="$fixture" core install --url=http://netchat-upgrade.test --title='Synthetic 1.2.0 upgrade' --admin_user=upgrade_fixture --admin_password=ci-only-disposable --admin_email=upgrade@example.test --skip-email >/dev/null
cat > "$fixture/state.php" <<'PHP'
<?php
global $wpdb;
if(strpos($wpdb->prefix,'nc_release_netchatrelease121')!==0)WP_CLI::error('Wrong state prefix');
$state=array();
foreach(array('netchat_settings','netchat_service_forms','active_plugins')as$key){$raw=$wpdb->get_var($wpdb->prepare('SELECT option_value FROM '.$wpdb->options.' WHERE option_name=%s',$key));if($wpdb->last_error)WP_CLI::error('Option read failed');$state[$key]=hash('sha256',(string)$raw);}
foreach(array('netchat_leads','netchat_lead_keys','netchat_lead_limits','users')as$suffix){$rows=$wpdb->get_results('SELECT * FROM '.$wpdb->prefix.$suffix,ARRAY_A);if($wpdb->last_error)WP_CLI::error('Table read failed');$bytes=array_map('serialize',$rows);sort($bytes,SORT_STRING);$state[$suffix]=array(count($rows),hash('sha256',implode("\n",$bytes)));}
$file=ABSPATH.'retained-state.json';
if(!is_file($file))file_put_contents($file,json_encode($state));
elseif(json_decode(file_get_contents($file),true)!==$state)WP_CLI::error('Upgrade changed retained data');
PHP
for root in netchat WP-NetChat-main; do
 if [[ $root == netchat ]]; then
  name=netchat-wp; digest=c84114486aabe3d4e8f7fd6fbf40041a31a13f3d3dda36b304558fb62c19bdd8
 else
  name=netchat-upgrade-WP-NetChat-main; digest=9e353705a52e15bcae96c5849930b2af23bfe36e0b146e9cda78a3dc55845292
 fi
 old="$fixture/$name-1.2.0.zip"
 curl --fail --location --retry 3 --output "$old" "https://github.com/omhotey/WP-NetChat/releases/download/v1.2.0/$name.zip"
 echo "$digest  $old" | sha256sum --check
 wp --path="$fixture" plugin install "$old" --activate >/dev/null
 wp --path="$fixture" eval '
  if(NETCHAT_VER!=="1.2.0")WP_CLI::error("Wrong upgrade input");
  $c=netchat_get_config();$c["enabled"]["telegram"]=1;$c["links"]["telegram"]="https://t.me/synthetic_upgrade";$c["hint"]=1;$c["hint_text"]="Synthetic retained greeting";$c["hint_delay"]=17;$c["button_scroll_percent"]=23;$c["hint_scroll_percent"]=67;$c["leads"]["enabled"]=1;$c["leads"]["email"]["enabled"]=0;
  if(is_wp_error(netchat_save_config($c,netchat_get_revision())))WP_CLI::error("Fixture settings failed");
  $f=netchat_service_forms_config();$f["installed"]["nc_primary_v1"]["title"]="Synthetic retained custom title";update_option("netchat_service_forms",$f,false);
  $nonce=time().".".bin2hex(random_bytes(16));$lead=netchat_leads_create(array("request_key"=>bin2hex(random_bytes(16)),"token"=>$nonce.".".netchat_leads_token_signature($nonce),"email"=>"fixture@example.test","message"=>"Synthetic retained request"));if(is_wp_error($lead))WP_CLI::error("Fixture lead failed");
 ' >/dev/null
 wp --path="$fixture" eval-file "$fixture/state.php" >/dev/null
 if [[ $root != netchat ]]; then
  wp --path="$fixture" plugin deactivate "$root" >/dev/null
  [[ ! -e "$fixture/wp-content/plugins/netchat" ]] || exit 1
  mv "$fixture/wp-content/plugins/$root" "$fixture/wp-content/plugins/netchat"
  wp --path="$fixture" plugin activate netchat >/dev/null
  # Only the expected basename changes during the explicit folder transition.
  wp --path="$fixture" --skip-plugins eval '$p=ABSPATH."retained-state.json";$s=json_decode(file_get_contents($p),true);$s["active_plugins"]=hash("sha256",serialize(array("netchat/netchat.php")));file_put_contents($p,json_encode($s));'
 fi
 wp --path="$fixture" plugin install "$release/netchat-wp-1.2.2.zip" --force >/dev/null
 wp --path="$fixture" eval-file "$fixture/state.php" >/dev/null
 wp --path="$fixture" eval '
  if(NETCHAT_VER!=="1.2.2")WP_CLI::error("Wrong updated version");
  $c=netchat_get_config();if($c["hint_mobile"]!==0||$c["button_delay"]!==0||$c["hint_delay"]!==17||$c["button_scroll_percent"]!==23||$c["hint_scroll_percent"]!==67)WP_CLI::error("Old values/defaults changed");
  $c["hint_mobile"]=1;$c["button_delay"]=600;$c["hint_delay"]=600;
  if(is_wp_error(netchat_save_config($c,netchat_get_revision())))WP_CLI::error("New settings save failed");
  $new=netchat_get_config(true);if($new["hint_mobile"]!==1||$new["button_delay"]!==600||$new["hint_delay"]!==600)WP_CLI::error("New settings readback failed");
 ' >/dev/null
 echo "PASS 1.2.0 -> 1.2.2 from $root: raw settings/forms/data preserved; canonical basename; new settings persist."
 wp --path="$fixture" plugin deactivate netchat >/dev/null
 wp --path="$fixture" plugin uninstall netchat >/dev/null
 # Each root starts from old-version settings, never a downgrade of 1.2.2 fields.
 # Only the exact synthetic fixture core/prefix allocated above is affected.
 wp --path="$fixture" --skip-plugins option delete netchat_settings >/dev/null
 wp --path="$fixture" --skip-plugins option delete netchat_service_forms >/dev/null
 rm "$fixture/retained-state.json"
done