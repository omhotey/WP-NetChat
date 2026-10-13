<?php
/** NetChat single-site lead intake, database schema and atomic replay/rate controls. */
if ( ! defined( 'ABSPATH' ) ) exit;

function netchat_leads_table() { global $wpdb; return $wpdb->prefix . 'netchat_leads'; }
function netchat_leads_keys_table() { global $wpdb; return $wpdb->prefix . 'netchat_lead_keys'; }
function netchat_leads_limits_table() { global $wpdb; return $wpdb->prefix . 'netchat_lead_limits'; }
function netchat_leads_error( $code, $message, $status = 400 ) { return new WP_Error( 'netchat_leads_' . $code, $message, array( 'status' => $status ) ); }
function netchat_leads_now() { return gmdate( 'Y-m-d H:i:s' ); }

function netchat_leads_install() {
    global $wpdb;
    if ( is_multisite() ) return netchat_leads_error( 'multisite', 'Lead intake supports single-site WordPress only.', 503 );
    if ( '3' === (string) get_option( 'netchat_leads_schema', '' ) ) return true;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = netchat_leads_table(); $keys = netchat_leads_keys_table(); $limits = netchat_leads_limits_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta( "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_key varchar(64) NOT NULL,
        public_code varchar(16) DEFAULT NULL,
        request_hash char(64) NOT NULL,
        form_key varchar(32) NOT NULL DEFAULT 'contact',
        status varchar(20) NOT NULL DEFAULT 'new',
        name varchar(190) NOT NULL DEFAULT '',
        email varchar(254) NOT NULL DEFAULT '',
        phone varchar(64) NOT NULL DEFAULT '',
        company varchar(190) NOT NULL DEFAULT '',
        message longtext NOT NULL,
        submission_json longtext DEFAULT NULL,
        context varchar(190) NOT NULL DEFAULT '',
        page_url text NOT NULL,
        page_title text NOT NULL,
        referrer text NOT NULL,
        utm longtext NOT NULL,
        consent_presented tinyint(1) unsigned NOT NULL DEFAULT 0,
        consent_accepted tinyint(1) unsigned NOT NULL DEFAULT 0,
        consent_version varchar(190) NOT NULL DEFAULT '',
        consent_at datetime DEFAULT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        notification_status varchar(20) NOT NULL DEFAULT 'disabled',
        notification_attempts int(10) unsigned NOT NULL DEFAULT 0,
        notification_last_attempt_at datetime DEFAULT NULL,
        notification_next_attempt_at datetime DEFAULT NULL,
        notification_claimed_at datetime DEFAULT NULL,
        notification_error varchar(64) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY request_key (request_key),
        UNIQUE KEY public_code (public_code),
        KEY status_created (status,created_at,id),
        KEY created (created_at,id),
        KEY email (email(190)),
        KEY notification_due (notification_status,notification_next_attempt_at)
    ) ENGINE=InnoDB $charset;" );
    // Tombstones hold only a server-keyed request identifier, no contact/body fingerprint.
    dbDelta( "CREATE TABLE $keys (
        key_hash char(64) NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY  (key_hash),
        KEY expires (expires_at)
    ) ENGINE=InnoDB $charset;" );
    // 1024 shared IP slots per purpose + one global slot: bounded cardinality, collision-safe rejection.
    dbDelta( "CREATE TABLE $limits (
        bucket varchar(32) NOT NULL,
        window_start bigint(20) unsigned NOT NULL,
        hits int(10) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (bucket)
    ) ENGINE=InnoDB $charset;" );
    foreach ( array( $table, $keys, $limits ) as $name ) {
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) );
        if ( $found !== $name || $wpdb->last_error ) return netchat_leads_error( 'storage', 'Lead storage is unavailable.', 503 );
    }
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table", 0);
    if (array_diff(array('id','request_key','request_hash','notification_status','notification_attempts','notification_claimed_at','notification_next_attempt_at','consent_version'), $columns)) return netchat_leads_error('storage','Lead schema is incomplete.',503);
    $indexes = $wpdb->get_results("SHOW INDEX FROM $table", ARRAY_A);
    $unique = false;
    foreach ($indexes as $index) if ('request_key' === $index['Key_name'] && 0 === (int)$index['Non_unique'] && 'request_key' === $index['Column_name']) $unique = true;
    if (!$unique || $wpdb->last_error) return netchat_leads_error('storage','Lead replay index is unavailable.',503);
    if (!in_array('submission_json', $columns, true)) return netchat_leads_error('storage','Lead schema is incomplete.',503);
    $public_unique = false;
    foreach ($indexes as $index) if ('public_code' === $index['Key_name'] && 0 === (int)$index['Non_unique']) $public_unique = true;
    if (!in_array('public_code', $columns, true) || !$public_unique) return netchat_leads_error('storage','Public reference storage is unavailable.',503);
    update_option( 'netchat_leads_schema', 3, false );
    return '3' === (string) get_option( 'netchat_leads_schema' ) ? true : netchat_leads_error( 'storage', 'Lead schema version could not be saved.', 503 );
}

/** Persistent random reference, independent of ID, date and request volume. */
function netchat_leads_public_code( $id ) {
    global $wpdb;
    $table = netchat_leads_table();
    $letters = array('Б','Г','Д','Ж','З','К','Л','М','Н','П','Р','С','Т','Ф','Ч','Ш');
    for ($attempt=0; $attempt<8; $attempt++) {
        $code = $wpdb->get_var($wpdb->prepare("SELECT public_code FROM {$table} WHERE id=%d",$id));
        if ($wpdb->last_error) return netchat_leads_error('storage','Reference is unavailable.',503);
        if ($code) return $code;
        $candidate = $letters[random_int(0,count($letters)-1)].random_int(2,9).$letters[random_int(0,count($letters)-1)].'-'.random_int(1000,9999);
        $previous = $wpdb->suppress_errors(true);
        try { $changed = $wpdb->query($wpdb->prepare("UPDATE {$table} SET public_code=%s WHERE id=%d AND public_code IS NULL",$candidate,$id)); }
        finally { $wpdb->suppress_errors($previous); }
        if ($changed===1) return $candidate;
        if ($changed===0 && !$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id=%d",$id))) break;
        // A unique-index collision or a concurrent assignment is resolved on the next attempt.
    }
    return netchat_leads_error('storage','Reference is unavailable. Please retry.',503);
}

function netchat_leads_length( $text ) { return netchat_leads_strlen( $text ); }
function netchat_leads_text( $value, $limit, $field, $multiline = false ) {
    if ( ! is_string( $value ) || ! preg_match( '//u', $value ) || netchat_leads_length( $value ) > $limit ) return netchat_leads_error( 'validation', 'Invalid field: ' . $field . '.' );
    return trim( $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value ) );
}

/** Store an origin + path only, dropping query/fragment/userinfo; referrer stores just origin. */
function netchat_leads_source_url( $value, $origin_only = false ) {
    if ( '' === $value ) return '';
    $parts = wp_parse_url( $value );
    if ( ! $parts || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) return '';
    $host = strtolower( $parts['host'] );
    if ( ! preg_match( '/^(?:[a-z0-9.-]+|\[[a-f0-9:]+\])$/i', $host ) ) return '';
    $origin = strtolower( $parts['scheme'] ) . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
    return esc_url_raw( $origin . ( $origin_only ? '' : ( $parts['path'] ?? '/' ) ), array( 'http', 'https' ) );
}

/** Revalidated after the lead_data hook; disabled and unknown fields never enter the stored row. */
function netchat_leads_validate( $input, $settings ) {
    if ( ! is_array( $input ) ) return netchat_leads_error( 'validation', 'Invalid form data.' );
    if (isset($input['form_id']) || isset($input['service_code'])) return netchat_service_validate($input, $settings);
    $allowed = array( 'form_key', 'name', 'email', 'phone', 'company', 'message', 'context', 'page_url', 'page_title', 'referrer', 'utm', 'consent' );
    if ( array_diff( array_keys( $input ), $allowed ) ) return netchat_leads_error( 'validation', 'Unknown form field.' );
    if ( isset( $input['form_key'] ) && 'contact' !== $input['form_key'] ) return netchat_leads_error( 'validation', 'Unknown form.' );
    $row = array( 'form_key' => 'contact' );
    foreach ( array( 'name' => 190, 'email' => 254, 'phone' => 64, 'company' => 190, 'message' => 10000 ) as $field => $limit ) {
        $value = $input[$field] ?? '';
        // Validate client types even for disabled fields; do not save their values.
        $clean = netchat_leads_text( $value, $limit, $field, 'message' === $field );
        if ( is_wp_error( $clean ) ) return $clean;
        if ( empty( $settings['fields'][$field]['enabled'] ) ) { $row[$field] = ''; continue; }
        if ( ! empty( $settings['fields'][$field]['required'] ) && '' === $clean ) return netchat_leads_error( 'validation', 'Required field: ' . $field . '.' );
        if ( 'email' === $field && '' !== $clean && ( $clean !== trim( $value ) || ! is_email( $clean ) ) ) return netchat_leads_error( 'validation', 'Enter a valid email address.' );
        if ( 'phone' === $field && '' !== $clean && ! preg_match( '/^\+?[0-9\s().-]{7,64}$/', $clean ) ) return netchat_leads_error( 'validation', 'Enter a valid phone number.' );
        if ( 'phone' === $field && '' !== $clean ) {
            $digits = preg_replace( '/\D/', '', $clean );
            if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 ) return netchat_leads_error( 'validation', 'Enter a valid phone number.' );
        }
        $row[$field] = $clean;
    }
    if ( '' === $row['email'] && '' === $row['phone'] ) return netchat_leads_error( 'validation', 'Provide an email address or phone number.' );
    foreach ( array( 'context' => 190, 'page_url' => 2048, 'page_title' => 2048, 'referrer' => 2048 ) as $field => $limit ) {
        $clean = netchat_leads_text( $input[$field] ?? '', $limit, $field );
        if ( is_wp_error( $clean ) ) return $clean;
        $row[$field] = in_array( $field, array( 'page_url', 'referrer' ), true ) ? netchat_leads_source_url( $clean, 'referrer' === $field ) : $clean;
    }
    $utm = $input['utm'] ?? array();
    if ( ! is_array( $utm ) || array_diff( array_keys( $utm ), array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) ) ) return netchat_leads_error( 'validation', 'Invalid campaign fields.' );
    $clean_utm = array();
    foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $field ) {
        if ( ! array_key_exists( $field, $utm ) ) continue;
        $value = netchat_leads_text( $utm[$field], 190, $field );
        if ( is_wp_error( $value ) ) return $value;
        if ( '' !== $value ) $clean_utm[$field] = $value;
    }
    $row['utm'] = wp_json_encode( $clean_utm );
    $accepted = $input['consent'] ?? false;
    if ( ! in_array( $accepted, array( 0, 1, false, true ), true ) ) return netchat_leads_error( 'validation', 'Invalid consent.' );
    $row['consent_presented'] = empty( $settings['consent']['enabled'] ) ? 0 : 1;
    $row['consent_accepted'] = $row['consent_presented'] && $accepted ? 1 : 0;
    if ( $row['consent_presented'] && ! empty( $settings['consent']['required'] ) && ! $row['consent_accepted'] ) return netchat_leads_error( 'validation', 'Please confirm your consent.' );
    $row['consent_version'] = $row['consent_presented'] ? $settings['consent']['version'] : '';
    $row['consent_at'] = $row['consent_accepted'] ? netchat_leads_now() : null;
    return $row;
}

function netchat_leads_rate( $purpose, $ip_limit, $global_limit ) {
    global $wpdb;
    $table = netchat_leads_limits_table();
    $window = (int) floor( time() / 600 ) * 600;
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) && filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    // Scope salt and bucket to this site and window; do not store raw IPs or persistent IP hashes.
    $digest = hash_hmac( 'sha256', get_current_blog_id() . '|' . $window . '|' . $ip, wp_salt( 'auth' ) );
    $slot = hexdec( substr( $digest, 0, 3 ) ) % 1024;
    if ('service' === $purpose) $slot = substr($digest,0,20);
    foreach ( array( $purpose . ':global' => $global_limit, $purpose . ':ip:' . $slot => $ip_limit ) as $bucket => $limit ) {
        $updated = $wpdb->query( $wpdb->prepare( "INSERT INTO $table (bucket,window_start,hits) VALUES (%s,%d,1) ON DUPLICATE KEY UPDATE hits=IF(window_start<VALUES(window_start),1,IF(window_start=VALUES(window_start),LEAST(hits+1,1000000),hits)),window_start=GREATEST(window_start,VALUES(window_start))", $bucket, $window ) );
        if ( false === $updated ) return netchat_leads_error( 'storage', 'Please try again later.', 503 );
        $hits = $wpdb->get_var( $wpdb->prepare( "SELECT hits FROM $table WHERE bucket=%s AND window_start=%d", $bucket, $window ) );
        if ( null === $hits || $wpdb->last_error ) return netchat_leads_error( 'storage', 'Please try again later.', 503 );
        if ( (int) $hits > $limit ) return new WP_Error('netchat_leads_rate_limit','Слишком много попыток. Попробуйте через ' . (int)ceil((600-(time()%600))/60) . ' минут',array('status'=>429,'retry_after'=>600-(time()%600)));
    }
    if ('service' === $purpose) $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE window_start<%d LIMIT 100",time()-DAY_IN_SECONDS));
    return true;
}

function netchat_leads_token_signature( $data ) { return hash_hmac( 'sha256', 'netchat-lead|' . get_current_blog_id() . '|' . $data, wp_salt( 'nonce' ) ); }
function netchat_leads_token_valid( $token ) {
    if ( ! is_string( $token ) || ! preg_match( '/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/', $token, $match ) ) return false;
    $issued = (int) $match[1];
    return $issued <= time() + 30 && $issued >= time() - 1200 && hash_equals( netchat_leads_token_signature( $match[1] . '.' . $match[2] ), $match[3] );
}
function netchat_leads_token( $request ) {
    if ( ! empty( netchat_get_migration_error() ) ) return netchat_leads_error( 'storage', 'The form is temporarily unavailable.', 503 );
    if ( empty( netchat_leads_config()['enabled'] ) ) return netchat_leads_error( 'disabled', 'The form is unavailable.', 403 );
    $ready = netchat_leads_install(); if ( is_wp_error( $ready ) ) return $ready;
    $rate = netchat_leads_rate( 'token', 30, 2000 ); if ( is_wp_error( $rate ) ) return $rate;
    $issued = time(); $data = $issued . '.' . bin2hex( random_bytes( 16 ) );
    $form_id = $request->get_param('form_id');
    $scope = $data;
    if ($form_id !== null) {
        $form = is_string($form_id) ? netchat_service_form($form_id) : null;
        if (!$form || !netchat_service_ready()) return netchat_leads_error('disabled','Эта форма сейчас недоступна. Используйте другой способ связи',403);
        $scope .= '|' . $form['id'] . '|' . $form['schema_version'];
    }
    $response = new WP_REST_Response( array( 'token' => $data . '.' . netchat_leads_token_signature( $scope ), 'issued_at'=>$issued,'min_submit_at'=>$issued+2,'expires_at' => $issued + 1200 ), 200 );
    if ($form_id !== null) { $response_data=$response->get_data();$response_data['documents']=netchat_service_forms_config()['documents'];$response->set_data($response_data); }
    $response->header( 'Cache-Control', 'no-store, private, max-age=0' );
    return $response;
}

function netchat_leads_get( $id ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . netchat_leads_table() . ' WHERE id=%d', (int) $id ), ARRAY_A );
    if ( $wpdb->last_error ) return netchat_leads_error( 'storage', 'Lead storage is unavailable.', 503 );
    if ($row && empty($row['public_code'])) {
        $code = netchat_leads_public_code((int)$row['id']);
        if (is_wp_error($code)) return $code;
        $row['public_code'] = $code;
    }
    return $row ? $row : null;
}
function netchat_leads_find_key( $key ) {
    global $wpdb;
    $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . netchat_leads_table() . ' WHERE request_key=%s', $key ), ARRAY_A );
    return $wpdb->last_error ? netchat_leads_error( 'storage', 'Lead storage is unavailable.', 503 ) : $row;
}
function netchat_leads_lock_name( $key ) { return 'nc_' . substr( hash( 'sha256', netchat_leads_table() . '|' . $key ), 0, 48 ); }
function netchat_leads_lock( $key ) { global $wpdb; return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,2)', netchat_leads_lock_name( $key ) ) ); }
function netchat_leads_unlock( $key ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', netchat_leads_lock_name( $key ) ) ); }
function netchat_leads_key_hash( $key ) { return hash_hmac( 'sha256', 'deleted|' . $key, wp_salt( 'auth' ) ); }

function netchat_leads_create( $input ) {
    global $wpdb;
    $settings = netchat_leads_config();
    if ( netchat_get_migration_error() ) return netchat_leads_error( 'storage', 'The form is temporarily unavailable.', 503 );
    if ( empty( $settings['enabled'] ) ) return netchat_leads_error( 'disabled', 'The form is unavailable.', 403 );
    if ( ! is_array( $input ) || strlen( wp_json_encode( $input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) > 65536 ) return netchat_leads_error( 'validation', 'The form is too large.' );
    $service = array_key_exists('form_id',$input) || array_key_exists('service_code',$input) || array_key_exists('request_id',$input);
    if ($service && array_key_exists('request_key',$input)) return netchat_leads_error('validation','Unknown form field.');
    if ($service && strlen(wp_json_encode($input,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) > 32768) return netchat_leads_error('validation','The form is too large.',413);
    $request_key = $service ? ($input['request_id'] ?? '') : ($input['request_key'] ?? '');
    if ($service && (!is_string($request_key) || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i',$request_key))) return netchat_leads_error('validation','Invalid request identifier.');
    if ( ! is_string( $request_key ) || ! preg_match( '/^(?:[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12})$/i', $request_key ) ) return netchat_leads_error( 'validation', 'Invalid request identifier.' );
    $request_key = strtolower( $request_key );
    $service_form = $service && is_string($input['form_id'] ?? null) ? netchat_service_form($input['form_id']) : null;
    $valid_token = $service ? ($service_form && netchat_service_token_valid($input['token'] ?? null,$service_form)) : netchat_leads_token_valid($input['token'] ?? null);
    if (!$valid_token) return netchat_leads_error('token','The form session expired. Please retry.',403);
    if ($service) {
        $ready=netchat_leads_install(); if(is_wp_error($ready)) return $ready;
        $rate=netchat_service_token_rate($input['token']); if(is_wp_error($rate)) return $rate;
        $rate=netchat_leads_rate('service',5,500); if(is_wp_error($rate)) return $rate;
        if ((int)explode('.',$input['token'])[0] > time()-2) return netchat_leads_error('early','Подождите немного и повторите отправку.');
    }
    if ( isset( $input['website'] ) && ( ! is_string( $input['website'] ) || '' !== trim( $input['website'] ) ) ) return netchat_leads_error( 'spam', 'The request could not be accepted.' );
    unset( $input['request_key'], $input['token'], $input['website'] );
    if ($service) unset($input['request_id']);
    // Fingerprint client data independently of changes to settings and integration hooks.
    $canonical_settings = $settings;
    foreach ($canonical_settings['fields'] as &$field) { $field['enabled']=1; $field['required']=0; } unset($field);
    $canonical_settings['consent'] = array('enabled'=>1,'required'=>0,'version'=>'');
    $row = netchat_leads_validate( $input, $canonical_settings ); if ( is_wp_error( $row ) ) return $row;
    // Acceptance timestamp is server data and must not change a replay fingerprint.
    $fingerprint = $row; unset( $fingerprint['consent_at'], $fingerprint['consent_version'], $fingerprint['consent_presented'] );
    $hash = hash_hmac( 'sha256', wp_json_encode( $fingerprint ), wp_salt( 'auth' ) );
    if ($service) $hash = netchat_service_fingerprint($row);
    $ready = netchat_leads_install(); if ( is_wp_error( $ready ) ) return $ready;
    if ( ! netchat_leads_lock( $request_key ) ) return netchat_leads_error( 'busy', 'Please retry the request.', 503 );
    $result = null;
    try {
        $deleted = $wpdb->get_var( $wpdb->prepare( 'SELECT key_hash FROM ' . netchat_leads_keys_table() . ' WHERE key_hash=%s AND expires_at>%s', netchat_leads_key_hash( $request_key ), netchat_leads_now() ) );
        if ( $wpdb->last_error ) return netchat_leads_error( 'storage', 'The request could not be saved.', 503 );
        if ( $deleted ) return netchat_leads_error( 'deleted', 'This request was deleted. Start a new request.', 410 );
        $existing = netchat_leads_find_key( $request_key ); if ( is_wp_error( $existing ) ) return $existing;
        if ( $existing ) return hash_equals( $existing['request_hash'], $hash ) ? array( 'id' => (int) $existing['id'], 'created' => false ) : netchat_leads_error( 'conflict', 'This identifier belongs to a different request.', 409 );
        if (!$service) { $rate = netchat_leads_rate( 'submit', 5, 500 ); if ( is_wp_error( $rate ) ) return $rate; }
        $row = netchat_leads_validate($input, $settings); if (is_wp_error($row)) return $row;
        $hook_input = $row;
        $hook_input['utm'] = json_decode($row['utm'], true);
        $hook_input['consent'] = (bool) $row['consent_accepted'];
        unset($hook_input['consent_presented'], $hook_input['consent_accepted'], $hook_input['consent_version'], $hook_input['consent_at']);
        if ($service) $hook_input = $input;
        try { $filtered = apply_filters( 'netchat_lead_data', $hook_input ); }
        catch ( Throwable $error ) { return netchat_leads_error( 'validation', 'The request could not be accepted.' ); }
        $row = netchat_leads_validate( $filtered, $settings ); if ( is_wp_error( $row ) ) return $row;
        $row['request_key'] = $request_key; $row['request_hash'] = $hash; $row['status'] = 'new';
        $row['created_at'] = $row['updated_at'] = netchat_leads_now();
        $row['notification_status'] = empty( $settings['email']['enabled'] ) ? 'disabled' : 'pending';
        $row['notification_next_attempt_at'] = empty( $settings['email']['enabled'] ) ? null : $row['created_at'];
        // Database diagnostics must not print SQL containing visitor answers or contacts.
        $previous_errors = $wpdb->suppress_errors(true);
        try { $inserted = $wpdb->insert( netchat_leads_table(), $row ); }
        finally { $wpdb->suppress_errors($previous_errors); }
        if ( false === $inserted ) return netchat_leads_error( 'storage', 'The request could not be saved. Please retry.', 503 );
        $result = array( 'id' => (int) $wpdb->insert_id, 'created' => true );
    } finally { netchat_leads_unlock( $request_key ); }
    // Stored request and pending mail remain durable even if an integration hook fails.
    try { do_action( 'netchat_lead_created', $result['id'] ); } catch ( Throwable $error ) { /* No contact data is logged. */ }
    return $result;
}

function netchat_leads_public_submit( $request ) {
    if ( strlen( $request->get_body() ) > 65536 ) return netchat_leads_error( 'validation', 'The form is too large.' );
    if ( stripos( (string) $request->get_header( 'content-type' ), 'application/json' ) !== 0 ) return netchat_leads_error( 'validation', 'Send a JSON form.' );
    $input = $request->get_json_params();
    if(is_array($input) && (isset($input['form_id']) || isset($input['service_code']) || isset($input['request_id'])) && strlen($request->get_body())>32768) return netchat_leads_error('validation','The form is too large.',413);
    $result = netchat_leads_create( $input ); if ( is_wp_error( $result ) ) return $result;
    $public_code = netchat_leads_public_code($result['id']);
    if (is_wp_error($public_code)) return $public_code;
    $response = new WP_REST_Response( array( 'success' => true, 'submission_id'=>$result['id'], 'reference'=>$public_code, 'created' => $result['created'] ), $result['created'] ? 201 : 200 );
    $response->header( 'Cache-Control', 'no-store, private' );
    return $response;
}

function netchat_leads_update_status( $id, $status ) {
    global $wpdb;
    if ( ! in_array( $status, array( 'new', 'viewed', 'processed', 'spam' ), true ) ) return netchat_leads_error( 'validation', 'Invalid lead status.' );
    $row = netchat_leads_get( $id ); if ( is_wp_error( $row ) ) return $row;
    if ( ! $row ) return netchat_leads_error( 'not_found', 'Lead not found.', 404 );
    if ( $status === $row['status'] ) return true;
    $mail_sql = 'spam' === $status ? ",notification_status='disabled',notification_next_attempt_at=NULL,notification_claimed_at=NULL" : '';
    $changed = $wpdb->query( $wpdb->prepare( 'UPDATE ' . netchat_leads_table() . ' SET status=%s,updated_at=%s' . $mail_sql . ' WHERE id=%d AND status=%s', $status, netchat_leads_now(), (int) $id, $row['status'] ) );
    if ( false === $changed ) return netchat_leads_error( 'storage', 'The status could not be saved.', 503 );
    if ( 0 === $changed ) return netchat_leads_error( 'conflict', 'The lead changed in another session.', 409 );
    try { do_action( 'netchat_lead_status_changed', (int) $id, $status, $row['status'] ); } catch ( Throwable $error ) { /* Status is already saved. */ }
    return true;
}

function netchat_leads_delete( $id ) {
    global $wpdb;
    $row = netchat_leads_get( $id ); if ( is_wp_error( $row ) ) return $row;
    if ( ! $row ) return true;
    if ( ! netchat_leads_lock( $row['request_key'] ) ) return netchat_leads_error( 'busy', 'Please retry deletion.', 503 );
    try {
        $now = netchat_leads_now(); $expires = gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS );
        $tombstone = $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . netchat_leads_keys_table() . ' (key_hash,expires_at) VALUES (%s,%s) ON DUPLICATE KEY UPDATE expires_at=VALUES(expires_at)', netchat_leads_key_hash( $row['request_key'] ), $expires ) );
        if ( false === $tombstone ) return netchat_leads_error( 'storage', 'The lead could not be deleted.', 503 );
        $deleted = $wpdb->delete( netchat_leads_table(), array( 'id' => (int) $id ), array( '%d' ) );
        if ( false === $deleted ) return netchat_leads_error( 'storage', 'The lead could not be deleted.', 503 );
        wp_clear_scheduled_hook( 'netchat_lead_notify', array( (int) $id ) );
        // At most 100 expired tombstones per delete, avoiding an unbounded maintenance query.
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . netchat_leads_keys_table() . ' WHERE expires_at<%s LIMIT 100', $now ) );
        return true;
    } finally { netchat_leads_unlock( $row['request_key'] ); }
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'netchat/v1', '/leads/token', array( 'methods' => 'GET', 'callback' => 'netchat_leads_token', 'permission_callback' => '__return_true' ) );
    register_rest_route( 'netchat/v1', '/leads', array( 'methods' => 'POST', 'callback' => 'netchat_leads_public_submit', 'permission_callback' => '__return_true' ) );
} );
