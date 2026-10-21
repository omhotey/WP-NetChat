<?php
/** WP-CLI --skip-plugins=netchat eval-file. Every write is under an isolated table/options prefix. */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI ) exit( 1 );
if ( function_exists( 'netchat_get_config' ) ) WP_CLI::error( 'Run with --skip-plugins=netchat.' );
global $wpdb;
$sf_original_options = $wpdb->options; $sf_original_prefix = $wpdb->prefix;
$sf_prefix = 'nc_services_test_' . bin2hex( random_bytes( 5 ) ) . '_'; $sf_options = $sf_prefix . 'options';
if ( false === $wpdb->query( "CREATE TEMPORARY TABLE `$sf_options` LIKE `$sf_original_options`" ) ) WP_CLI::error( 'Cannot create isolated options.' );
$wpdb->options = $sf_options; $wpdb->prefix = $sf_prefix; wp_cache_flush();
$sf_fixture = tempnam( sys_get_temp_dir(), 'ncs-json-' ); unlink( $sf_fixture ); define( 'NETCHAT_DATA', $sf_fixture );
require dirname( __DIR__ ) . '/netchat.php';
if ( ! function_exists( 'netchat_service_validate' ) ) require dirname( __DIR__ ) . '/includes/service-forms-validation.php';
if ( ! function_exists( 'netchat_service_builtin' ) ) require dirname( __DIR__ ) . '/includes/service-forms.php';
$GLOBALS['sf_checks'] = 0;
function sf_assert( $ok, $label ) { global $sf_checks; if ( ! $ok ) throw new RuntimeException( $label ); $sf_checks++; }
function sf_data( $code ) {
    $minimum = array(
        'TEST_A' => array( 'stage' => 'idea', 'idea' => 'Пока не знаю', 'next_result' => 'unknown' ),
        'TEST_B' => array( 'product' => 'Пока не знаю', 'growth_focus' => array( 'unknown' ), 'current_situation' => 'Пока не знаю', 'data_state' => 'none' ),
        'TEST_C' => array( 'process' => 'Пока не знаю', 'pain_points' => array( 'unknown' ), 'desired_result' => 'Пока не знаю', 'systems_state' => 'no' ),
        'TEST_D' => array( 'project' => 'Пока не знаю', 'problems' => array( 'unknown' ), 'situation' => 'Пока не знаю', 'urgency' => 'planned' ),
        'TEST_E' => array( 'product' => 'Пока не знаю', 'lead_need' => array( 'unknown' ), 'expected_result' => 'Пока не знаю', 'team_state' => 'none', 'authority' => 'no' ),
        'TEST_F' => array( 'audit_object' => 'unknown', 'object_description' => 'Пока не знаю', 'audit_questions' => 'Пока не знаю', 'decision' => 'Пока не знаю' ),
        'TEST_G' => array( 'question' => 'Пока не знаю', 'has_options' => 'unknown' ),
    );
    return array( 'service_code' => $code, 'form_id' => 'demo_' . strtolower( $code ) . '_v1', 'schema_version' => 1, 'answers' => $minimum[$code], 'contact' => array( 'name' => 'Ю', 'channel' => 'email', 'value' => 'Visitor+synthetic@EXAMPLE.test' ), 'consent' => array( 'accepted' => true ), 'context' => array( 'page_url' => 'https://example.test/services?token=private#fragment', 'page_id' => 113, 'origin_service' => $code, 'cta_position' => 'hero', 'entrypoint' => 'service_cta', 'referrer_origin' => 'https://referrer.test/path?secret=private', 'utm' => array( 'utm_source' => 'synthetic', 'private_token' => 'should disappear' ) ) );
}
function sf_validate( $data ) { return netchat_service_validate( $data, netchat_leads_config() ); }
function sf_payload( $data ) {
    $form = netchat_service_form( $data['form_id'] );
    $nonce = ( time() - 3 ) . '.' . bin2hex( random_bytes( 16 ) );
    $data['request_id'] = wp_generate_uuid4();
    $data['token'] = $nonce . '.' . netchat_leads_token_signature( $nonce . '|' . $form['id'] . '|' . $form['schema_version'] );
    $data['website'] = '';
    return $data;
}
function sf_reset_rate() { global $wpdb; $wpdb->query( 'DELETE FROM ' . netchat_leads_limits_table() ); }
function sf_field_error( $result, $path ) { return is_wp_error( $result ) && isset( $result->get_error_data()['fields'][$path] ); }
function sf_token_age( $data, $age, $scope = null ) {
    $form = netchat_service_form( $data['form_id'] ); $nonce = ( time() - $age ) . '.' . bin2hex( random_bytes( 16 ) );
    $data['token'] = $nonce . '.' . netchat_leads_token_signature( $nonce . '|' . ( $scope ?? $form['id'] ) . '|' . $form['schema_version'] );
    return $data;
}
try {
    add_option( 'home', 'https://example.test' ); add_option( 'siteurl', 'https://example.test' );
    netchat_activate();
    $cfg = netchat_get_config(); $cfg['leads']['enabled'] = 1; $cfg['leads']['email']['enabled'] = 0;
    sf_assert( true === netchat_save_config( $cfg, netchat_get_revision() ), 'enable isolated base forms' );
    sf_assert( netchat_service_install_presets(), 'install service presets' );
    $service_settings = netchat_service_forms_config(); $service_settings['installed']=json_decode(file_get_contents(__DIR__.'/fixtures-demo-forms.json'),true); $service_settings['enabled'] = 1; $service_settings['retention_confirmed'] = 1;
    $service_settings['documents'] = array( 'document_url' => 'https://example.test/test-consent', 'policy_url' => 'https://example.test/test-policy', 'text_version' => 'synthetic-text-v1', 'document_version' => 'synthetic-document-v1', 'policy_version' => 'synthetic-policy-v1' );
    update_option( 'netchat_service_forms', $service_settings, false );
    sf_assert( netchat_service_ready(), 'test documents and retention enable intake' );
    foreach ( array( 'TEST_A', 'TEST_B', 'TEST_C', 'TEST_D', 'TEST_E', 'TEST_F', 'TEST_G' ) as $code ) {
        $data = sf_data( $code ); $row = sf_validate( $data );
        sf_assert( ! is_wp_error( $row ), 'minimal form: ' . $code );
        $stored = json_decode( $row['submission_json'], true );
        sf_assert( $stored['service_code'] === $code && $row['form_key'] === $data['form_id'], 'correct service identity: ' . $code );
        sf_assert( $stored['contact']['value'] === 'Visitor+synthetic@example.test', 'email normalization: ' . $code );
        sf_assert( $stored['context']['page_url'] === 'https://example.test/services' && null === $stored['context']['page_id'], 'clean URL and untrusted page ID: ' . $code );
        sf_assert( $stored['context']['referrer_origin'] === 'https://referrer.test' && ! isset( $stored['context']['utm']['private_token'] ), 'source minimization: ' . $code );
        sf_assert( $stored['consent']['accepted'] === true && $stored['consent']['document_version'] === 'synthetic-document-v1' && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $stored['consent']['accepted_at'] ), 'server consent snapshot: ' . $code );
        sf_assert( isset( $stored['snapshot']['title'] ) && ! isset( $stored['context']['context_token'] ), 'snapshot without security token: ' . $code );
        $mail_body = netchat_leads_service_email($stored);
        sf_assert(strpos($mail_body,$stored['contact']['name'])!==false && strpos($mail_body,$stored['contact']['value'])!==false, 'service mail contains contact: '.$code);
        foreach ($stored['answers'] as $answer_key=>$answer_value) {
            $field_snapshot = $stored['snapshot']['fields'][$answer_key];
            sf_assert(strpos($mail_body,$field_snapshot['label'])!==false, 'service mail question label: '.$code.'.'.$answer_key);
            foreach ((is_array($answer_value)?$answer_value:array($answer_value)) as $answer_item) sf_assert(strpos($mail_body,(string)($field_snapshot['options'][$answer_item]??$answer_item))!==false, 'service mail readable answer: '.$code.'.'.$answer_key);
        }
        sf_reset_rate(); $payload = sf_payload( $data ); $created = netchat_leads_create( $payload );
        sf_assert( ! is_wp_error( $created ) && $created['created'], 'SQL service insert: ' . $code );
        $lead = netchat_leads_get( $created['id'] );
        sf_assert( $lead['form_key'] === $data['form_id'] && json_decode( $lead['submission_json'], true )['service_code'] === $code, 'SQL snapshot persisted: ' . $code );
        $replayed = netchat_leads_create( $payload );
        sf_assert( ! is_wp_error( $replayed ) && ! $replayed['created'] && $replayed['id'] === $created['id'], 'same request ID replay: ' . $code );
        $payload['contact']['name'] = 'Изменённое имя'; $conflict = netchat_leads_create( $payload );
        sf_assert( is_wp_error( $conflict ) && 409 === $conflict->get_error_data()['status'], 'different body conflict: ' . $code );
    }
    $data = sf_data( 'TEST_A' ); $data['answers']['existing_work'] = array( 'not even a string' ); $data['answers']['product_url'] = 'javascript:bad';
    $row = sf_validate( $data ); $saved = json_decode( $row['submission_json'], true );
    sf_assert( ! isset( $saved['answers']['existing_work'], $saved['answers']['product_url'] ), 'hidden fields stripped before type checks' );
    foreach ( array( 'research', 'concept', 'prototype', 'development' ) as $stage ) {
        $data = sf_data( 'TEST_A' ); $data['answers']['stage'] = $stage; $data['answers']['existing_work'] = 'Есть подготовленные материалы'; $data['answers']['product_url'] = 'https://example.test/prototype';
        $saved = json_decode( sf_validate( $data )['submission_json'], true );
        sf_assert( isset( $saved['answers']['existing_work'] ) && isset( $saved['answers']['product_url'] ) === in_array( $stage, array( 'prototype', 'development' ), true ), 'new product branch: ' . $stage );
    }
    foreach ( array( 'available', 'partial', 'none', 'unknown' ) as $state ) {
        $data = sf_data( 'TEST_B' ); $data['answers']['data_state'] = $state; $data['answers']['known_metrics'] = 'Наблюдаем небольшое изменение';
        $saved = json_decode( sf_validate( $data )['submission_json'], true );
        sf_assert( isset( $saved['answers']['known_metrics'] ) === in_array( $state, array( 'available', 'partial' ), true ), 'growth data branch: ' . $state );
    }
    $data = sf_data( 'TEST_B' ); $data['answers']['growth_focus'] = array( 'sales', 'monetization', 'sales' ); $data['answers']['monetization_model'] = 'Подписка';
    $saved = json_decode( sf_validate( $data )['submission_json'], true );
    sf_assert( $saved['answers']['growth_focus'] === array( 'monetization', 'sales' ) && $saved['answers']['monetization_model'] === 'Подписка', 'ordered multi dedup and monetization branch' );
    $data['answers']['growth_focus'] = array( 'unknown', 'monetization' ); sf_assert( sf_field_error( sf_validate( $data ), 'answers.growth_focus' ), 'unknown multi exclusive' );
    foreach ( array( 'yes', 'partial', 'no', 'unknown' ) as $state ) {
        $data = sf_data( 'TEST_C' ); $data['answers']['systems_state'] = $state; $data['answers']['systems'] = 'Существующие системы компании'; $data['answers']['integration_need'] = 'yes';
        $saved = json_decode( sf_validate( $data )['submission_json'], true );
        sf_assert( isset( $saved['answers']['systems'] ) === in_array( $state, array( 'yes', 'partial' ), true ), 'digitalization branch: ' . $state );
    }
    $data = sf_data( 'TEST_D' ); $data['answers']['urgency'] = 'critical'; sf_assert( sf_field_error( sf_validate( $data ), 'answers.critical_impact' ), 'critical impact conditionally required' );
    $data['answers']['critical_impact'] = 'Продукт не работает у клиентов'; sf_assert( ! is_wp_error( sf_validate( $data ) ), 'critical impact accepted' );
    $data['answers']['urgency'] = 'planned'; sf_assert( ! isset( json_decode( sf_validate( $data )['submission_json'], true )['answers']['critical_impact'] ), 'critical impact stripped when hidden' );
    foreach ( array( 'own', 'external', 'mixed', 'forming', 'none' ) as $team ) {
        $data = sf_data( 'TEST_E' ); $data['answers']['team_state'] = $team; $data['answers']['team_context'] = 'Есть разработчики и дизайнер';
        sf_assert( isset( json_decode( sf_validate( $data )['submission_json'], true )['answers']['team_context'] ) === ( 'none' !== $team ), 'product team condition: ' . $team );
    }
    foreach ( array( 'yes', 'partial', 'no', 'discuss' ) as $authority ) {
        $data = sf_data( 'TEST_E' ); $data['answers']['authority'] = $authority; $data['answers']['authority_limits'] = 'Ограничения принимаемых решений';
        sf_assert( isset( json_decode( sf_validate( $data )['submission_json'], true )['answers']['authority_limits'] ) === ( 'partial' === $authority ), 'authority branch: ' . $authority );
    }
    foreach ( array( 'research', 'requirements', 'metrics', 'prototype', 'technical', 'participants', 'unknown' ) as $material ) {
        $data = sf_data( 'TEST_F' ); $data['answers']['materials_state'] = array( $material ); $data['answers']['materials_url'] = 'https://example.test/materials';
        sf_assert( isset( json_decode( sf_validate( $data )['submission_json'], true )['answers']['materials_url'] ) === ! in_array( $material, array( 'participants', 'unknown' ), true ), 'audit material branch: ' . $material );
    }
    $data = sf_data( 'TEST_G' ); $data['answers']['has_options'] = 'yes'; sf_assert( sf_field_error( sf_validate( $data ), 'answers.options' ), 'consultation alternatives required' );
    $data['answers']['options'] = 'Вариант первый или вариант второй'; sf_assert( ! is_wp_error( sf_validate( $data ) ), 'consultation alternatives accepted' );
    foreach ( array( 'no', 'unknown' ) as $choice ) { $data['answers']['has_options'] = $choice; sf_assert( ! isset( json_decode( sf_validate( $data )['submission_json'], true )['answers']['options'] ), 'consultation hidden alternatives: ' . $choice ); }
    foreach ( array( 9, 10, 2000, 2001 ) as $length ) { $data = sf_data( 'TEST_G' ); $data['answers']['question'] = str_repeat( 'Я', $length ); sf_assert( is_wp_error( sf_validate( $data ) ) === in_array( $length, array( 9, 2001 ), true ), 'Unicode textarea boundary: ' . $length ); }
    foreach ( array( 0, 1, 80, 81 ) as $length ) { $data = sf_data( 'TEST_G' ); $data['contact']['name'] = str_repeat( 'Ю', $length ); sf_assert( is_wp_error( sf_validate( $data ) ) === in_array( $length, array( 0, 81 ), true ), 'name Unicode boundary: ' . $length ); }
    $data = sf_data( 'TEST_G' ); $data['answers']['question'] = "  <script>alert('literal')</script>\r\nСледующая строка  ";
    $saved = json_decode( sf_validate( $data )['submission_json'], true ); sf_assert( $saved['answers']['question'] === "<script>alert('literal')</script>\nСледующая строка", 'HTML literal and CRLF preserved as plain text' );
    foreach ( array( false, 'true', 1, null ) as $consent ) { $data = sf_data( 'TEST_G' ); $data['consent']['accepted'] = $consent; sf_assert( sf_field_error( sf_validate( $data ), 'consent.accepted' ), 'literal boolean consent' ); }
    foreach ( array( '@tester_name', 'tester_name', 'https://t.me/tester_name' ) as $value ) { $data = sf_data( 'TEST_G' ); $data['contact']['channel'] = 'telegram'; $data['contact']['value'] = $value; sf_assert( json_decode( sf_validate( $data )['submission_json'], true )['contact']['value'] === '@tester_name', 'Telegram canonical input: ' . $value ); }
    foreach ( array( '@abcd', '@1tester', 'https://t.me/tester_name?token=x', 'https://t.me/tester_name/another' ) as $value ) { $data = sf_data( 'TEST_G' ); $data['contact']['channel'] = 'telegram'; $data['contact']['value'] = $value; sf_assert( sf_field_error( sf_validate( $data ), 'contact.value' ), 'Telegram invalid input' ); }
    $data = sf_data( 'TEST_G' ); $data['contact']['channel'] = 'phone'; $data['contact']['value'] = '+7 (999) 123-45-67'; sf_assert( json_decode( sf_validate( $data )['submission_json'], true )['contact']['value'] === '+79991234567', 'international phone normalization' );
    $data['contact']['value'] = '89991234567'; sf_assert( sf_field_error( sf_validate( $data ), 'contact.value' ), 'phone needs country plus' );
    $data = sf_data( 'TEST_G' ); $data['answers']['invented'] = 'value'; sf_assert( sf_field_error( sf_validate( $data ), 'answers.invented' ), 'unknown answer rejected' );
    $data = sf_data( 'TEST_G' ); $data['answers']['context'] = null; sf_assert( sf_field_error( sf_validate( $data ), 'answers.context' ), 'optional null type rejected' );
    $data = sf_data( 'TEST_G' ); $data['schema_version'] = '1'; sf_assert( sf_field_error( sf_validate( $data ), 'schema_version' ), 'schema version exact integer' );
    $data = sf_data( 'TEST_G' ); $data['service_code'] = 'TEST_F'; sf_assert( sf_field_error( sf_validate( $data ), 'service_code' ), 'service and preset mismatch' );
    $data = sf_data( 'TEST_G' ); $data['context']['page_url'] = 'https://elsewhere.test/private'; sf_assert( sf_field_error( sf_validate( $data ), 'context.page_url' ), 'foreign page host rejected' );
    $data = sf_data( 'TEST_G' ); $data['context']['page_url'] = array( 'invalid' ); sf_assert( sf_field_error( sf_validate( $data ), 'context.page_url' ), 'context malformed type is not a fatal exception' );
    $data = sf_data( 'TEST_G' ); $data['context']['context_token'] = netchat_service_context_token( 113, $data['context']['page_url'] ); sf_assert( json_decode( sf_validate( $data )['submission_json'], true )['context']['page_id'] === 113, 'server signed page context accepted' );
    $data['context']['page_id'] = 114; sf_assert( null === json_decode( sf_validate( $data )['submission_json'], true )['context']['page_id'], 'forged page ID not trusted' );
    $data = sf_data( 'TEST_G' ); $before = sf_validate( $data ); $settings = netchat_service_forms_config(); $settings['documents']['document_version'] = 'new-version'; $settings['installed'][$data['form_id']]['title'] = 'Revised current title'; update_option( 'netchat_service_forms', $settings, false ); $after = sf_validate( $data );
    sf_assert( netchat_service_fingerprint( $before ) === netchat_service_fingerprint( $after ), 'document/title updates do not affect replay fingerprint' );
    sf_assert( json_decode( $before['submission_json'], true )['snapshot']['title'] !== json_decode( $after['submission_json'], true )['snapshot']['title'], 'immutable old snapshot distinct from updated title' );
    sf_reset_rate(); $payload = sf_payload( $data ); $created = netchat_leads_create( $payload ); sf_assert( ! is_wp_error( $created ), 'lead before privacy delete' ); sf_assert( true === netchat_leads_delete( $created['id'] ), 'privacy delete removes service row' ); sf_assert( null === netchat_leads_get( $created['id'] ), 'private snapshot removed with lead' ); $retry = netchat_leads_create( $payload ); sf_assert( is_wp_error( $retry ) && 410 === $retry->get_error_data()['status'], 'privacy deletion cannot resurrect via replay' );
    foreach ( array( 0 => 400, 1300 => 403 ) as $age => $status ) {
        sf_reset_rate(); $attempt = netchat_leads_create( sf_token_age( sf_payload( sf_data( 'TEST_G' ) ), $age ) );
        sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === $status, 'server token age: ' . $age );
    }
    sf_reset_rate(); $attempt = netchat_leads_create( sf_token_age( sf_payload( sf_data( 'TEST_G' ) ), 3, 'demo_test_f_v1' ) );
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === 403, 'token cannot be reused for another service' );
    sf_reset_rate(); $payload = sf_payload( sf_data( 'TEST_G' ) ); $payload['website'] = 'filled'; $attempt = netchat_leads_create( $payload );
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_code() === 'netchat_leads_spam', 'honeypot cannot return fake success' );
    sf_reset_rate(); $payload = sf_payload( sf_data( 'TEST_G' ) ); $payload['answers']['question'] = str_repeat( 'x', 33000 ); $attempt = netchat_leads_create( $payload );
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === 413, 'service JSON body bounded at 32KiB' );
    sf_reset_rate(); $payload = sf_payload( sf_data( 'TEST_G' ) );
    for ( $i = 0; $i < 3; $i++ ) sf_assert( ! is_wp_error( netchat_leads_create( $payload ) ), 'three token attempts permitted' );
    $attempt = netchat_leads_create( $payload );
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === 429 && $attempt->get_error_data()['retry_after'] > 0, 'fourth token attempt rate limited with retry delay' );
    sf_reset_rate(); $_SERVER['REMOTE_ADDR'] = '198.51.100.90';
    for ( $i = 0; $i < 5; $i++ ) sf_assert( ! is_wp_error( netchat_leads_create( sf_payload( sf_data( 'TEST_G' ) ) ) ), 'five independent source attempts permitted' );
    $attempt = netchat_leads_create( sf_payload( sf_data( 'TEST_G' ) ) );
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === 429 && $attempt->get_error_data()['retry_after'] > 0, 'sixth source attempt rate limited' );
    sf_reset_rate(); $lead_table = netchat_leads_table();
    $fault = function( $sql ) use ( $lead_table ) { return strpos( $sql, "INSERT INTO `$lead_table` " ) === 0 ? 'BROKEN SQL FOR ISOLATED SERVICE TEST' : $sql; };
    $previous_suppression = $wpdb->suppress_errors( true ); add_filter( 'query', $fault );
    try { $attempt = netchat_leads_create( sf_payload( sf_data( 'TEST_G' ) ) ); }
    finally { remove_filter( 'query', $fault ); $wpdb->suppress_errors( $previous_suppression ); }
    sf_assert( is_wp_error( $attempt ) && $attempt->get_error_data()['status'] === 503, 'failed service SQL insert never reports success' );
    sf_reset_rate(); do_action( 'rest_api_init' ); wp_set_current_user( 0 ); $payload = sf_payload( sf_data( 'TEST_G' ) );
    $request = new WP_REST_Request( 'POST', '/netchat/v1/leads' ); $request->set_header( 'Content-Type', 'application/json' ); $request->set_body( wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ) );
    $response = rest_do_request( $request ); $response_data = $response->get_data();
    sf_assert( $response->get_status() === 201 && ! empty( $response_data['submission_id'] ), 'public service POST confirms saved submission ID' );
    sf_assert( preg_match('/^[БГДЖЗКЛМНПРСТФЧШ][2-9][БГДЖЗКЛМНПРСТФЧШ]-[1-9][0-9]{3}$/u', $response_data['reference']), 'readable nonsequential public reference' );
    $retry = rest_do_request( $request );
    sf_assert( $retry->get_status() === 200 && $retry->get_data()['submission_id'] === $response_data['submission_id'], 'public HTTP retry returns original ID' );
    sf_assert( $retry->get_data()['reference'] === $response_data['reference'], 'public reference remains stable on retry' );
    $legacy_id = $response_data['submission_id'];
    $wpdb->query($wpdb->prepare('UPDATE '.netchat_leads_table().' SET public_code=NULL WHERE id=%d',$legacy_id));
    $old = netchat_leads_get($legacy_id);
    sf_assert( !empty($old['public_code']) && netchat_leads_get($legacy_id)['public_code']===$old['public_code'], 'legacy nullable references are assigned once' );
    $unique_codes = array();
    foreach ($wpdb->get_col('SELECT id FROM '.netchat_leads_table()) as $code_id) $unique_codes[] = netchat_leads_public_code($code_id);
    sf_assert(count(array_unique($unique_codes))===count($unique_codes), 'stored references are unique across existing records');
    $captured_service_mail = null;
    $capture_mail = function($atts)use(&$captured_service_mail){$captured_service_mail=$atts;return $atts;};
    $fixture_transport = function($mailer){$mailer->isSendmail();$mailer->Sendmail='/bin/true';};
    $fixture_from = function(){return 'netchat-fixture@example.test';};
    $mail_cfg = netchat_get_config(); $mail_cfg['leads']['email']['enabled']=1; $mail_cfg['leads']['email']['include_message']=0; $mail_cfg['leads']['email']['recipients']=array('synthetic-owner@example.test');
    sf_assert(netchat_save_config($mail_cfg,netchat_get_revision())===true,'enable isolated service mail');
    $wpdb->update(netchat_leads_table(),array('notification_status'=>'pending','notification_next_attempt_at'=>null),array('id'=>$legacy_id));
    add_filter('wp_mail',$capture_mail); add_filter('wp_mail_from',$fixture_from); add_action('phpmailer_init',$fixture_transport);
    try { netchat_leads_send_one_notification($legacy_id); } finally { remove_filter('wp_mail',$capture_mail); remove_filter('wp_mail_from',$fixture_from); remove_action('phpmailer_init',$fixture_transport); }
    sf_assert(netchat_leads_get($legacy_id)['notification_status']==='sent','real service mail transport handoff');
    sf_assert(is_array($captured_service_mail) && strpos($captured_service_mail['message'],'Ответы на вопросы')!==false && strpos($captured_service_mail['message'],'Пока не знаю')!==false,'real mail path includes service answers even with legacy message toggle off');
    sf_assert(strpos($captured_service_mail['subject'],$old['public_code'])!==false && strpos($captured_service_mail['message'],'Visitor+synthetic@example.test')!==false,'mail contains stable reference and contact');
    foreach ( array( '/netchat/v1/leads', '/netchat/v1/leads/' . $response_data['submission_id'] ) as $route ) sf_assert( rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_status() !== 200, 'public GET cannot read service records' );
    $multi_checks = 0;
    foreach ( json_decode(file_get_contents(__DIR__.'/fixtures-demo-forms.json'),true) as $schema ) foreach ( $schema['fields'] as $field ) {
        if ( 'multi' !== $field['type'] || ! isset( $field['options']['unknown'] ) ) continue;
        $data = sf_data( $schema['service_code'] ); $choices = array_keys( $field['options'] ); $data['answers'][$field['key']] = array( 'unknown', $choices[0] );
        sf_assert( sf_field_error( sf_validate( $data ), 'answers.' . $field['key'] ), 'every multi enforces unknown exclusivity: ' . $schema['service_code'] );
        $multi_checks++;
    }
    sf_assert($multi_checks===5,'all five synthetic multi exclusivity cases run');
    $data = sf_data( 'TEST_G' ); $data['context']['page_url'] = 'http://example.test/services'; sf_assert( sf_field_error( sf_validate( $data ), 'context.page_url' ), 'context requires current scheme' );
    $data['context']['page_url'] = 'https://example.test:444/services'; sf_assert( sf_field_error( sf_validate( $data ), 'context.page_url' ), 'context requires current port' );
    WP_CLI::success( $GLOBALS['sf_checks'] . ' isolated service validation/storage assertions passed.' );
} finally {
    foreach ( array( netchat_leads_keys_table(), netchat_leads_limits_table(), netchat_leads_table() ) as $table ) { if ( strpos( $table, $sf_prefix ) !== 0 ) throw new RuntimeException( 'Unsafe cleanup prefix.' ); $wpdb->query( "DROP TABLE IF EXISTS `$table`" ); }
    if ( file_exists( $sf_fixture ) ) unlink( $sf_fixture );
    $wpdb->options = $sf_original_options; $wpdb->prefix = $sf_original_prefix; wp_cache_flush();
}
