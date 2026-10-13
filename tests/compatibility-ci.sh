#!/usr/bin/env bash
# Hosted workers only: never bootstrap or alter the owner's local lab.
set -euo pipefail
[[ ${CI:-} == true && ${GITHUB_ACTIONS:-} == true ]] || { echo 'Hosted GitHub Actions worker required' >&2; exit 1; }
source_dir=$(cd "$(dirname "$0")/.." && pwd)
release_dir="$source_dir/dist/release-1.2.0"
case "${NETCHAT_WP_VERSION:?}/${NETCHAT_PHP_VERSION:?}" in 5.0/7.2|7.1.3/8.3) ;; *) echo 'Unreviewed runtime pair' >&2; exit 1 ;; esac
[[ ${NETCHAT_DB_NAME:?} == netchat_ci ]] || { echo 'Unexpected disposable database' >&2; exit 1; }
php -r 'if (PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION !== getenv("NETCHAT_PHP_VERSION")) { fwrite(STDERR,"PHP version mismatch\n"); exit(1); } echo "PHP " . PHP_VERSION . "\n";'
python3 - "$release_dir" "$source_dir" <<'PY'
import hashlib, json, pathlib, sys, zipfile
release, source = map(pathlib.Path, sys.argv[1:])
for name, root in [('netchat-wp', 'netchat'), ('netchat-upgrade-WP-NetChat-main', 'WP-NetChat-main')]:
    manifest = json.loads((release / (name + '.manifest.json')).read_text())
    archive = release / (name + '.zip')
    assert manifest['version'] == '1.2.0' and manifest['plugin_root'] == root
    assert manifest['archive'] == archive.name
    assert hashlib.sha256(archive.read_bytes()).hexdigest() == manifest['sha256']
    expected = {f['path']: f for f in manifest['files']}
    assert len(expected) == len(manifest['files'])
    with zipfile.ZipFile(archive) as z:
        assert len(z.namelist()) == len(expected) and set(z.namelist()) == set(expected)
        for name, record in expected.items():
            path = pathlib.PurePosixPath(name)
            assert path.parts[0] == root and '..' not in path.parts
            relative = pathlib.Path(*path.parts[1:])
            allowed = str(relative) in ['netchat.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'includes/service-presets.json'] or (relative.parts[0] == 'includes' and len(relative.parts) == 2 and relative.suffix == '.php') or (relative.parts[0] in ['assets', 'languages'] and relative.suffix in ['.php', '.css', '.js', '.svg', '.mo', '.po', '.pot'])
            assert allowed, name
            content = z.read(name)
            assert len(content) == record['size']
            assert hashlib.sha256(content).hexdigest() == record['sha256']
            assert content == (source / relative).read_bytes(), name
    print('PASS exact package/source/manifest:', archive.name, manifest['sha256'])
PY
work=$(mktemp -d "${RUNNER_TEMP:?}/netchat-ci-XXXXXX")
core="$work/core"
server_pid=''
mkdir -p "$core" "$work/bin"
cleanup() {
  status=$?
  trap - EXIT
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  if [[ -f "$core/wp-config.php" ]]; then
    if ! wp --path="$core" --skip-plugins --skip-themes db drop --yes >/dev/null; then status=1; fi
  fi
  case "$work" in "$RUNNER_TEMP"/netchat-ci-*) rm -rf -- "$work" ;; *) status=1 ;; esac
  exit "$status"
}
trap cleanup EXIT
curl --fail --location --retry 3 --output "$work/wp-cli.phar" https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar
echo "ce34ddd838f7351d6759068d09793f26755463b4a4610a5a5c0a97b68220d85c  $work/wp-cli.phar" | sha256sum --check
printf '#!/usr/bin/env bash\nexec php "%s" "$@"\n' "$work/wp-cli.phar" > "$work/bin/wp"
chmod +x "$work/bin/wp"
export PATH="$work/bin:$PATH"
wp --version
# Official ZIP preserves paths longer than tar's legacy 100-byte name field.
# Exact core version and every official file checksum remain mandatory below.
wp --path="$core" core download "https://wordpress.org/wordpress-${NETCHAT_WP_VERSION}.zip"
wp --path="$core" core verify-checksums --version="$NETCHAT_WP_VERSION" --locale=en_US
[[ $(wp --path="$core" core version) == "$NETCHAT_WP_VERSION" ]] || { echo 'Core version mismatch' >&2; exit 1; }
wp --path="$core" config create --dbname="$NETCHAT_DB_NAME" --dbuser="${NETCHAT_DB_USER:?}" --dbpass="${NETCHAT_DB_PASSWORD:?}" --dbhost="${NETCHAT_DB_HOST:?}" --dbprefix=nc_ci_ --skip-check
wp --path="$core" config set WP_DEBUG_LOG false --raw
wp --path="$core" core install --url=http://netchat-ci.test --title='NetChat CI' --admin_user=netchat_ci --admin_password=ci-disposable-only --admin_email=ci@example.test --skip-email
wp --path="$core" plugin install "$release_dir/netchat-wp.zip"
# Suites load the real installed candidate after isolating their own options/tables.
cp -R "$source_dir/tests" "$core/wp-content/plugins/netchat/tests"
find "$source_dir/includes" -name '*.php' -print0 | xargs -0 -n 1 php -l
php -l "$source_dir/netchat.php"
php -l "$source_dir/uninstall.php"
php -l "$source_dir/tests/leads-storage.php"
php -l "$source_dir/tests/service-forms-storage.php"
[[ -x /bin/true && -x /bin/false ]] || { echo 'Deterministic mail fixtures unavailable' >&2; exit 1; }
/bin/true
if /bin/false; then echo 'Failure mail fixture returned success' >&2; exit 1; fi
node "$source_dir/tests/normalization.test.cjs"
node "$source_dir/tests/distribution-privacy.test.cjs"
for suite in wp-integration.php leads-storage.php service-forms-storage.php forms-editor-storage.php forms-lifecycle-storage.php data-transfer-storage.php; do
  wp --path="$core" --skip-plugins=netchat eval-file "$core/wp-content/plugins/netchat/tests/$suite"
done
curl --fail --location --retry 3 --output "$release_dir/historical-v1.0.0.zip" https://github.com/omhotey/WP-NetChat/releases/download/v1.0.0/WP-NetChat-main.zip
echo "ead4077f9a746370715d286546927f449e2b28ef3434f03c1a9c587fd1d38b7d  $release_dir/historical-v1.0.0.zip" | sha256sum --check
NETCHAT_CORE_DIR="$core" NETCHAT_RELEASE_DIR="$release_dir" bash "$source_dir/tests/release-smoke.sh"
wp --path="$core" --skip-plugins --skip-themes eval 'global $wpdb; $tables=$wpdb->get_col($wpdb->prepare("SHOW TABLES LIKE %s","nc_release_%")); if ($wpdb->last_error || $tables) { WP_CLI::error("Smoke fixture cleanup failed"); } echo "PASS smoke database cleanup\n";'
# Every browser record/page below lives only in this disposable core/database.
wp --path="$core" option update home http://localhost:8080
wp --path="$core" option update siteurl http://localhost:8080
wp --path="$core" plugin activate netchat
wp --path="$core" user create netchat_subscriber subscriber@example.test --role=subscriber --user_pass=ci-disposable-only
wp --path="$core" eval '
  $c=netchat_get_config();$c["leads"]["enabled"]=1;$c["leads"]["email"]["enabled"]=0;if(is_wp_error(netchat_save_config($c,netchat_get_revision())))WP_CLI::error("Browser config failed");
  $f=netchat_service_forms_config();$f["installed"]=array_merge($f["installed"],json_decode(file_get_contents(NETCHAT_DIR."tests/fixtures-demo-forms.json"),true));$f["enabled"]=1;$f["retention_confirmed"]=1;$f["documents"]=array("document_url"=>"http://localhost:8080/consent","policy_url"=>"http://localhost:8080/policy","text_version"=>"synthetic","document_version"=>"synthetic","policy_version"=>"synthetic");update_option("netchat_service_forms",$f,false);
  $content="";foreach($f["installed"]as$form)$content.="[netchat_button service=\"".$form["service_code"]."\" text=\"Test form\"]\n";
  $content.="<div id=\"nc-scroll-fixture\" style=\"height:4000px\" aria-hidden=\"true\"></div>";
  $id=wp_insert_post(array("post_type"=>"page","post_status"=>"publish","post_name"=>"netchat-test","post_title"=>"Synthetic browser fixture","post_content"=>$content),true);if(is_wp_error($id))WP_CLI::error("Fixture page failed");
'
wp --path="$core" rewrite structure '/%postname%/'
cat > "$work/router.php" <<'PHP'
<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(is_file($_SERVER['DOCUMENT_ROOT'].$path))return false;
require $_SERVER['DOCUMENT_ROOT'].'/index.php';
PHP
php -S localhost:8080 -t "$core" "$work/router.php" > "$work/server.log" 2>&1 &
server_pid=$!
for attempt in {1..30}; do if curl --fail --silent http://localhost:8080/wp-login.php >/dev/null; then break; fi; sleep 1; done
curl --fail --silent http://localhost:8080/wp-login.php >/dev/null
cd "$source_dir"
node tests/service-forms-e2e.mjs
node tests/data-transfer-e2e.mjs
node tests/scroll-visibility-e2e.mjs
echo "PASS hosted compatibility: WordPress $NETCHAT_WP_VERSION / PHP $NETCHAT_PHP_VERSION"
