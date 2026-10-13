<?php
/** Run with WP-CLI --skip-plugins=netchat eval-file. Uses a session-local options table. */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb;
if (function_exists('netchat_get_config')) { WP_CLI::error('Run with --skip-plugins=netchat to isolate options before loading NetChat.'); }
$original_options = $wpdb->options;
$original_prefix = $wpdb->prefix;
$wpdb->prefix = 'nc_options_test_' . bin2hex(random_bytes(5)) . '_';
$test_table = $wpdb->prefix . 'netchat_integration_' . bin2hex(random_bytes(5));
if ($wpdb->query("CREATE TEMPORARY TABLE `$test_table` LIKE `$original_options`") === false) { WP_CLI::error('Cannot create temporary test table.'); }
$wpdb->options = $test_table;
wp_cache_flush();
$fixture = tempnam(sys_get_temp_dir(), 'netchat-fixture-');
unlink($fixture);
define('NETCHAT_DATA', $fixture);
require dirname(__DIR__) . '/netchat.php';
$GLOBALS['netchat_test_checks'] = 0;
function nc_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    $GLOBALS['netchat_test_checks']++;
}
function nc_reset_fixture($content = null) {
    global $wpdb;
    $wpdb->query("DELETE FROM `$wpdb->options`");
    wp_cache_flush();
    if (file_exists(NETCHAT_DATA)) { unlink(NETCHAT_DATA); }
    if ($content !== null) { file_put_contents(NETCHAT_DATA, $content); }
    netchat_clear_cache();
}
try {
    foreach (json_decode(file_get_contents(__DIR__ . '/fixtures-normalization.json'), true) as $example) {
        nc_assert(netchat_validate_url($example['raw'], $example['type']) === $example['url'], 'shared PHP link fixture: ' . $example['type']);
        if ($example['url']) nc_assert(netchat_validate_url($example['url'], $example['type']) === $example['url'], 'shared PHP idempotence: ' . $example['type']);
    }
    nc_reset_fixture();
    netchat_activate();
    $original_v1 = array('schema_version'=>1,'revision'=>7,'config'=>netchat_defaults());
    unset($original_v1['config']['leads']);
    $original_v1['config']['links']['telegram']='https://t.me/durov';
    $original_v1['config']['enabled']['telegram']=1;
    $wpdb->update($test_table,array('option_value'=>maybe_serialize($original_v1)),array('option_name'=>NETCHAT_SETTINGS_OPTION));
    netchat_clear_cache(); netchat_upgrade_storage();
    nc_assert(netchat_storage_read()['schema_version']===2 && netchat_get_revision()===8, 'schema v1 upgrade to v2 increments revision');
    nc_assert(netchat_get_config()['links']['telegram']==='https://t.me/durov' && netchat_get_config()['leads']['enabled']===0, 'schema upgrade retains channel and form stays off');
    nc_reset_fixture(); netchat_activate();
    nc_assert(netchat_get_config() === netchat_defaults(), 'clean install defaults');
    nc_assert(netchat_get_revision() >= 1, 'clean install revision');
    $c = netchat_defaults();
    $raw = array('telegram'=>'durov', 'whatsapp'=>'+7 (900) 123-45-67', 'messenger'=>'test.page', 'max'=>'https://max.ru/u/test-profile', 'vk'=>'test_profile', 'viber'=>'+79001234567', 'email'=>'netchat-test@example.test', 'phone'=>'+7 (900) 123-45-67');
    foreach ($raw as $key => $value) { $c['enabled'][$key] = 1; $c['links'][$key] = $value; }
    $c['animation'] = 0; $c['hint_delay'] = 0; $c['position']['left'] = '0'; $c['position']['right'] = '';
    $normalized = netchat_normalize_config($c);
    nc_assert(!is_wp_error($normalized), 'all channel inputs accepted');
    nc_assert(netchat_normalize_config($normalized) === $normalized, 'PHP normalization idempotent');
    $revision = netchat_get_revision();
    nc_assert(netchat_save_config($normalized, $revision) === true, 'save via real Options API');
    nc_assert(netchat_get_config() === $normalized, 'read immediately after save');
    $new_revision = netchat_get_revision();
    nc_assert(netchat_save_config($normalized, $new_revision) === true, 'unchanged save success');
    nc_assert(netchat_get_revision() === $new_revision, 'unchanged save revision stable');
    netchat_deactivate(); netchat_activate();
    nc_assert(netchat_get_config() === $normalized, 'reactivation preserves config');
    netchat_clear_cache();
    nc_assert(netchat_get_config() === $normalized, 'clear cache does not delete data');
    $changed = $normalized; $changed['color'] = '#123456';
    nc_assert(netchat_save_config($changed, $new_revision) === true, 'changed config saved');
    $conflict = netchat_save_config($normalized, $new_revision);
    nc_assert(is_wp_error($conflict) && $conflict->get_error_data()['status'] === 409, 'stale save conflict');
    nc_assert(netchat_get_config()['color'] === '#123456', 'conflict preserved newer config');
    foreach(array('button_scroll_percent','hint_scroll_percent')as$key){
        foreach(array(null,true,false,-1,101,25.5,'25.5','-1','101','bad',array(),1.0)as$value){$bad=$normalized;$bad[$key]=$value;nc_assert(is_wp_error(netchat_normalize_config($bad)), 'invalid scroll percentage '.$key.' '.gettype($value));}
        foreach(array(0,25,50,100,'0','25','100')as$value){$good=$normalized;$good[$key]=$value;nc_assert(netchat_normalize_config($good)[$key]===(int)$value,'integer scroll representation '.$key);}
    }
    $legacy_storage=netchat_storage_read();unset($legacy_storage['config']['button_scroll_percent'],$legacy_storage['config']['hint_scroll_percent']);
    $wpdb->update($test_table,array('option_value'=>maybe_serialize($legacy_storage)),array('option_name'=>NETCHAT_SETTINGS_OPTION));netchat_clear_cache();
    $raw_before=$wpdb->get_var("SELECT option_value FROM `$test_table` WHERE option_name='netchat_settings'");
    $effective=netchat_get_config(true);nc_assert($effective['button_scroll_percent']===0&&$effective['hint_scroll_percent']===0,'legacy percentages default zero');
    nc_assert($wpdb->get_var("SELECT option_value FROM `$test_table` WHERE option_name='netchat_settings'")===$raw_before&&netchat_get_revision()===$legacy_storage['revision'],'legacy reads retain raw bytes and revision');
    $effective['button_scroll_percent']=25;$effective['hint_scroll_percent']=50;nc_assert(netchat_save_config($effective,netchat_get_revision())===true,'explicit percentages save');
    $request=new WP_REST_Request('POST','/netchat/v1/settings');$older=$effective;unset($older['button_scroll_percent'],$older['hint_scroll_percent']);$older['revision']=netchat_get_revision();$request->set_body(wp_json_encode($older));$request->set_header('Content-Type','application/json');
    $response=netchat_rest_save($request);nc_assert(!is_wp_error($response)&&$response['config']['button_scroll_percent']===25&&$response['config']['hint_scroll_percent']===50,'older REST client preserves current percentages');
    nc_assert(is_wp_error(netchat_rest_save($request))===false,'unchanged older REST save stable');
    $bad = $normalized; $bad['mobile'] = array();
    foreach(array(null,'invalid',array())as$mode){$invalid_mode=$normalized;$invalid_mode['launcher_mode']=$mode;nc_assert(is_wp_error(netchat_normalize_config($invalid_mode)),'invalid launcher mode rejected');}
    $menu_mode=$normalized;$menu_mode['launcher_mode']='menu';nc_assert(netchat_normalize_config($menu_mode)['launcher_mode']==='menu','menu launcher mode accepted');
    $old_mode=$normalized;unset($old_mode['launcher_mode']);nc_assert(netchat_normalize_config($old_mode)['launcher_mode']==='form','old settings accept optional launcher mode');
    nc_assert(is_wp_error(netchat_normalize_config($bad)), 'invalid type rejected');
    $bad = $normalized; unset($bad['links']['email']);
    nc_assert(is_wp_error(netchat_normalize_config($bad)), 'incomplete document rejected');
    $bad = $normalized; $bad['links']['max'] = 'https://example.test/profile';
    nc_assert(is_wp_error(netchat_normalize_config($bad)), 'wrong MAX domain rejected');
    $off = $normalized; foreach ($off['enabled'] as $key => $value) { $off['enabled'][$key] = 0; }
    $off['desktop'] = 0; $off['mobile'] = 0; $off['hint'] = 0;
    nc_assert(netchat_save_config($off, netchat_get_revision()) === true, 'disabled values saved');
    netchat_clear_cache(); $read = netchat_get_config();
    nc_assert($read['links'] === $normalized['links'] && $read['enabled'] === $off['enabled'], 'disabled contacts retained');
    nc_assert($read['desktop'] === 0 && $read['mobile'] === 0 && $read['hint'] === 0, 'zero flags persisted');
    nc_reset_fixture(wp_json_encode(array('enabled'=>array('telegram'=>1), 'links'=>array('telegram'=>'https://t.me/durov'), 'animation'=>0)));
    $source_hash = hash_file('sha256', NETCHAT_DATA);
    netchat_activate(); $migrated = netchat_get_config();
    nc_assert($migrated['enabled']['telegram'] === 1 && $migrated['links']['telegram'] === 'https://t.me/durov', 'partial JSON migration');
    nc_assert(count($migrated['links']) === 8 && $migrated['animation'] === 0, 'nested defaults and zero retained');
    nc_assert(hash_file('sha256', NETCHAT_DATA) === $source_hash, 'source JSON unchanged');
    $migrated['color'] = '#abcdef'; netchat_save_config($migrated, netchat_get_revision());
    file_put_contents(NETCHAT_DATA, '{broken'); netchat_activate();
    nc_assert(netchat_get_config()['color'] === '#abcdef', 'existing DB wins over later corrupt JSON');
    nc_reset_fixture('{broken'); netchat_activate();
    nc_assert(get_option('netchat_settings', false) === false, 'corrupt JSON did not create defaults');
    nc_assert(is_wp_error(netchat_get_migration_error()), 'corrupt JSON reports error');
    nc_assert(file_get_contents(NETCHAT_DATA) === '{broken', 'corrupt source retained');
    file_put_contents(NETCHAT_DATA, wp_json_encode($normalized)); netchat_activate();
    nc_assert(netchat_get_config() === $normalized, 'retry migration after repair');
    $before = netchat_get_config();
    $failed_query = function ($query) use ($test_table) {
        return strpos($query, 'UPDATE ' . $test_table . ' SET option_value') === 0 ? 'INVALID SQL FOR NETCHAT TEST' : $query;
    };
    $previous_suppression = $wpdb->suppress_errors(true);
    add_filter('query', $failed_query);
    $failure = netchat_save_config(array_replace($before, array('color'=>'#112233')), netchat_get_revision());
    remove_filter('query', $failed_query);
    $wpdb->suppress_errors($previous_suppression);
    nc_assert(is_wp_error($failure) && $failure->get_error_data()['status'] === 503, 'SQL write failure reported');
    nc_assert(netchat_get_config() === $before, 'SQL failure preserves prior config');
    $inflight = false;
    $race_query = function ($query) use ($test_table, &$inflight, $wpdb) {
        if (!$inflight && strpos($query, 'UPDATE ' . $test_table . ' SET option_value') === 0) {
            $inflight = true;
            $winner = netchat_storage_read(); $winner['revision']++; $winner['config']['color'] = '#aabbcc';
            $wpdb->update($test_table, array('option_value'=>maybe_serialize($winner)), array('option_name'=>NETCHAT_SETTINGS_OPTION));
        }
        return $query;
    };
    add_filter('query', $race_query);
    $loser = netchat_save_config(array_replace($before, array('color'=>'#112233')), netchat_get_revision());
    remove_filter('query', $race_query);
    nc_assert($inflight && is_wp_error($loser) && $loser->get_error_data()['status'] === 409, 'in-flight CAS race rejected');
    nc_assert(netchat_get_config()['color'] === '#aabbcc', 'CAS winner retained');
    nc_reset_fixture(wp_json_encode($normalized));
    $import_raced = false;
    $import_query = function ($query) use ($test_table, &$import_raced, $wpdb, $normalized) {
        if (!$import_raced && strpos($query, 'INSERT INTO ' . $test_table) === 0) {
            $import_raced = true;
            $winner = array('schema_version'=>2, 'revision'=>2, 'config'=>array_replace($normalized, array('color'=>'#aabbcc')));
            $wpdb->insert($test_table, array('option_name'=>NETCHAT_SETTINGS_OPTION, 'option_value'=>maybe_serialize($winner), 'autoload'=>'no'));
        }
        return $query;
    };
    add_filter('query', $import_query); netchat_activate(); remove_filter('query', $import_query);
    nc_assert($import_raced && netchat_get_revision() === 2 && netchat_get_config()['color'] === '#aabbcc', 'concurrent importer cannot overwrite existing option');
    nc_reset_fixture(wp_json_encode(array('links'=>array('max'=>'https://example.test/legacy'))));
    netchat_activate();
    nc_assert(netchat_get_config()['links']['max'] === 'https://example.test/legacy', 'questionable legacy contact preserved');
    nc_assert(in_array('max', netchat_get_migration_warnings(), true), 'questionable contact warning');
    nc_reset_fixture(wp_json_encode(array('color'=>'#abcdef')));
    add_option('netchat_config', array('color'=>'#112233')); netchat_activate();
    nc_assert(netchat_get_config()['color'] === '#abcdef', 'JSON has explicit precedence over old option');
    nc_reset_fixture(); add_option('netchat_config', array('color'=>'#112233')); netchat_activate();
    nc_assert(netchat_get_config()['color'] === '#112233', 'old option fallback migration');
    nc_reset_fixture(wp_json_encode($normalized));
    $fail_insert = function ($query) use ($test_table) {
        return strpos($query, 'INSERT INTO ' . $test_table) === 0 ? 'INVALID SQL FOR NETCHAT TEST' : $query;
    };
    $previous_suppression = $wpdb->suppress_errors(true); add_filter('query', $fail_insert); netchat_activate(); remove_filter('query', $fail_insert); $wpdb->suppress_errors($previous_suppression);
    nc_assert(is_wp_error(netchat_get_migration_error()) && get_option(NETCHAT_SETTINGS_OPTION, false) === false, 'failed import creates no defaults');
    netchat_activate(); nc_assert(netchat_get_config() === $normalized, 'failed import safely retries');
    nc_reset_fixture(wp_json_encode($normalized));
    chmod(NETCHAT_DATA, 0000); netchat_activate();
    nc_assert(is_wp_error(netchat_get_migration_error()) && get_option(NETCHAT_SETTINGS_OPTION, false) === false, 'unreadable JSON creates no defaults');
    chmod(NETCHAT_DATA, 0600); netchat_activate();
    nc_assert(netchat_get_config() === $normalized, 'unreadable JSON retry after repair');
    $damaged = netchat_storage_read(); unset($damaged['config']['links']['telegram']);
    $wpdb->update($test_table, array('option_value'=>maybe_serialize($damaged)), array('option_name'=>NETCHAT_SETTINGS_OPTION));
    netchat_clear_cache();
    nc_assert(is_wp_error(netchat_get_migration_error()), 'incomplete existing DB envelope reports corruption');
    nc_assert(is_wp_error(netchat_save_config($normalized, 1)), 'corrupt DB not overwritten with defaults');
    nc_reset_fixture(); netchat_activate();
    do_action('rest_api_init');
    wp_set_current_user(0);
    $request = new WP_REST_Request('POST', '/netchat/v1/settings');
    $request->set_header('Content-Type', 'application/json');
    $request->set_body(wp_json_encode($normalized + array('revision'=>netchat_get_revision())));
    nc_assert(in_array(rest_do_request($request)->get_status(), array(401,403), true), 'REST guest denied');
    $subscriber = new WP_User(); $subscriber->ID = 999999; $subscriber->allcaps = array('read'=>true); $GLOBALS['current_user'] = $subscriber;
    nc_assert(rest_do_request($request)->get_status() === 403, 'REST authenticated user without capability denied');
    WP_CLI::success($GLOBALS['netchat_test_checks'] . ' real WordPress assertions passed; production options were not changed by fixtures.');
} finally {
    if (file_exists($fixture)) { unlink($fixture); }
    foreach (array('netchat_leads','netchat_lead_keys','netchat_lead_limits') as $suffix) $wpdb->query('DROP TABLE IF EXISTS `' . $wpdb->prefix . $suffix . '`');
    $wpdb->prefix = $original_prefix;
    $wpdb->options = $original_options;
    wp_cache_flush();
}
