<?php
/** WordPress settings storage with a read-only legacy import. */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'NETCHAT_SETTINGS_OPTION' ) ) define( 'NETCHAT_SETTINGS_OPTION', 'netchat_settings' );
if ( ! defined( 'NETCHAT_DATA' ) ) define( 'NETCHAT_DATA', WP_CONTENT_DIR . '/netchat-data.json' );

function netchat_storage_error( $code, $message, $status = 503 ) {
    return new WP_Error( $code, $message, array( 'status' => $status ) );
}

function netchat_storage_invalidate() {
    unset( $GLOBALS['netchat_storage_cache'] );
    wp_cache_delete( NETCHAT_SETTINGS_OPTION, 'options' );
    wp_cache_delete( 'alloptions', 'options' );
    wp_cache_delete( 'notoptions', 'options' );
}

/** Fetch directly for CAS: a cached value cannot decide write ownership. */
function netchat_storage_read() {
    global $wpdb;
    $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", NETCHAT_SETTINGS_OPTION ) );
    if ( $wpdb->last_error ) return netchat_storage_error( 'netchat_storage_unavailable', 'NetChat could not read its settings database.' );
    if ( null === $raw ) return null;
    $data = maybe_unserialize( $raw );
    if ( ! is_array( $data ) || ! isset( $data['schema_version'], $data['revision'], $data['config'] ) || ! in_array( $data['schema_version'], array(1,2), true ) || ! is_int( $data['revision'] ) || $data['revision'] < 1 || ! is_array( $data['config'] ) ) {
        return netchat_storage_error( 'netchat_storage_invalid', 'NetChat settings are damaged or use an unsupported schema. Existing data has been retained.' );
    }
    $check = $data['config'];
    if ( 1 === $data['schema_version'] && ! array_key_exists('leads', $check) ) $check['leads'] = netchat_leads_defaults();
    $warnings = isset( $data['migration_warnings'] ) && is_array( $data['migration_warnings'] ) ? $data['migration_warnings'] : array();
    if ( isset( $check['links'] ) && is_array( $check['links'] ) ) {
        foreach ( array_keys( netchat_defaults()['links'] ) as $channel ) {
            if ( isset( $check['links'][$channel] ) && is_string( $check['links'][$channel] ) && '' !== $check['links'][$channel] && '' === netchat_validate_url( $check['links'][$channel], $channel ) ) {
                if ( ! in_array( $channel, $warnings, true ) ) return netchat_storage_error( 'netchat_storage_invalid', 'NetChat contains a damaged contact. Existing data has been retained.' );
                $check['links'][$channel] = '';
            }
        }
    }
    if ( is_wp_error( netchat_normalize_config( $check, true ) ) ) return netchat_storage_error( 'netchat_storage_invalid', 'NetChat settings are damaged. Existing data has been retained.' );
    return $data;
}

/** JSON has priority over netchat_config; conflicting sources are never merged. */
function netchat_storage_initialize() {
    global $wpdb;
    $existing = netchat_storage_read();
    if ( is_array($existing) && 1 === $existing['schema_version'] ) {
        $upgraded = $existing;
        $upgraded['schema_version'] = 2;
        $upgraded['revision']++;
        $upgraded['config']['leads'] = netchat_leads_defaults();
        $result = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize($upgraded), NETCHAT_SETTINGS_OPTION, maybe_serialize($existing)));
        netchat_storage_invalidate();
        if (false === $result) return netchat_storage_error('netchat_upgrade_failed','NetChat could not upgrade settings. Existing data has been retained.');
        $existing = netchat_storage_read();
        if (is_array($existing) && 2 !== $existing['schema_version']) return netchat_storage_error('netchat_upgrade_failed','Settings changed during upgrade. Retry this request.');
    }
    if ( null !== $existing ) return $existing;
    if ( is_multisite() ) return netchat_storage_error( 'netchat_multisite_unsupported', 'Automatic legacy migration is supported on single-site WordPress only.' );
    $source = 'defaults';
    if ( file_exists( NETCHAT_DATA ) ) {
        $raw = @file_get_contents( NETCHAT_DATA );
        if ( false === $raw ) return netchat_storage_error( 'netchat_migration_read_failed', 'NetChat could not read its legacy JSON. Existing data has been retained.' );
        $legacy = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $legacy ) || '{' !== substr( ltrim( $raw ), 0, 1 ) ) return netchat_storage_error( 'netchat_migration_invalid_json', 'NetChat legacy JSON is damaged. Repair or restore the source before saving settings.' );
        $source = 'json';
    } else {
        $legacy = get_option( 'netchat_config', null );
        if ( null === $legacy ) $legacy = array();
        elseif ( ! is_array( $legacy ) ) return netchat_storage_error( 'netchat_migration_invalid_option', 'NetChat legacy option is damaged. Existing data has been retained.' );
        else $source = 'legacy_option';
    }
    $config = netchat_normalize_config( $legacy, false );
    $warnings = array();
    foreach ( $config['links'] as $channel => $link ) {
        if ( '' !== $link && '' === netchat_validate_url( $link, $channel ) ) $warnings[] = $channel;
    }
    $data = array( 'schema_version' => 2, 'revision' => 1, 'config' => $config, 'migration_source' => $source, 'migration_warnings' => $warnings );
    // Core add_option() upserts option_value on a duplicate key; it cannot gate an import.
    // This insert does not update existing values, even if another importer won the race.
    netchat_storage_invalidate();
    $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_name = option_name", NETCHAT_SETTINGS_OPTION, maybe_serialize( $data ) ) );
    netchat_storage_invalidate();
    if ( false === $result ) return netchat_storage_error( 'netchat_migration_write_failed', 'NetChat could not store settings. The legacy source has been retained.' );
    $stored = netchat_storage_read();
    if ( null === $stored ) return netchat_storage_error( 'netchat_migration_write_failed', 'NetChat could not store settings. The legacy source has been retained.' );
    return $stored;
}

function netchat_get_config( $force_refresh = false ) {
    if ( $force_refresh ) unset( $GLOBALS['netchat_storage_cache'] );
    if ( ! isset( $GLOBALS['netchat_storage_cache'] ) ) $GLOBALS['netchat_storage_cache'] = netchat_storage_initialize();
    $data = $GLOBALS['netchat_storage_cache'];
    // Rendering remains available; writes are blocked until the source error is fixed.
    return is_wp_error( $data ) ? netchat_defaults() : netchat_normalize_config( $data['config'], false );
}

function netchat_get_revision() {
    netchat_get_config();
    $data = $GLOBALS['netchat_storage_cache'];
    return is_wp_error( $data ) ? 0 : $data['revision'];
}

function netchat_get_migration_error() {
    netchat_get_config();
    return is_wp_error( $GLOBALS['netchat_storage_cache'] ) ? $GLOBALS['netchat_storage_cache'] : null;
}

function netchat_get_migration_warnings() {
    netchat_get_config();
    $data = $GLOBALS['netchat_storage_cache'];
    return is_wp_error( $data ) || empty( $data['migration_warnings'] ) ? array() : $data['migration_warnings'];
}

function netchat_save_config( $config, $expected_revision = null ) {
    global $wpdb;
    $normalized = netchat_normalize_config( $config, true );
    if ( is_wp_error( $normalized ) ) return $normalized;
    $old = netchat_storage_initialize();
    if ( is_wp_error( $old ) ) return $old;
    if ( null !== $expected_revision && ( ! is_int( $expected_revision ) || $expected_revision !== $old['revision'] ) ) return netchat_storage_error( 'netchat_revision_conflict', 'Settings were changed in another session. Reload before saving.', 409 );
    if ( $normalized === $old['config'] ) {
        $GLOBALS['netchat_storage_cache'] = $old;
        return true;
    }
    $new = $old;
    $new['config'] = $normalized;
    $new['revision']++;
    $new['migration_warnings'] = array();
    // Compare serialized bytes, not the case-insensitive options table collation.
    $result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s, autoload = 'no' WHERE option_name = %s AND BINARY option_value = BINARY %s", maybe_serialize( $new ), NETCHAT_SETTINGS_OPTION, maybe_serialize( $old ) ) );
    netchat_storage_invalidate();
    if ( false === $result ) return netchat_storage_error( 'netchat_storage_write_failed', 'NetChat could not save settings. Previous settings have been retained.' );
    if ( 1 !== $result ) return netchat_storage_error( 'netchat_revision_conflict', 'Settings were changed in another session. Reload before saving.', 409 );
    $readback = netchat_storage_read();
    if ( is_wp_error( $readback ) ) return $readback;
    if ( null === $readback || $readback['revision'] < $new['revision'] ) return netchat_storage_error( 'netchat_storage_write_failed', 'NetChat could not verify saved settings.' );
    $GLOBALS['netchat_storage_cache'] = $readback;
    return true;
}

/** Explicit reset; activation never calls this and the legacy source is retained. */
function netchat_delete_config() {
    return netchat_save_config( netchat_defaults(), netchat_get_revision() );
}

function netchat_clear_cache() {
    netchat_storage_invalidate();
    return true;
}
