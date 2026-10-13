<?php
/**
 * NetChat Uninstall
 * 
 * Removes all plugin data when deleted from WordPress.
 * 
 * @package NetChat
 * @since 3.11.0
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Keep data unless the site owner explicitly opts into deletion in wp-config.php:
// define( 'NETCHAT_DELETE_DATA', true );
wp_clear_scheduled_hook('netchat_leads_send_notifications');
wp_clear_scheduled_hook('netchat_leads_retention_cleanup');
if ( ! defined( 'NETCHAT_DELETE_DATA' ) || true !== NETCHAT_DELETE_DATA ) return;

delete_option( 'netchat_settings' );
delete_option( 'netchat_config' );
delete_option( 'netchat_leads_schema' );
delete_option( 'netchat_service_forms' );
global $wpdb;
foreach (array('netchat_leads','netchat_lead_keys','netchat_lead_limits') as $suffix) {
    $table = $wpdb->prefix . $suffix;
    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`','``',$table) . '`');
}

// Delete JSON config file.
if ( defined( 'WP_CONTENT_DIR' ) ) {
    $json_file = WP_CONTENT_DIR . '/netchat-data.json';
    if ( file_exists( $json_file ) ) {
        @unlink( $json_file );
    }
}

// Remove any custom capabilities or roles if added in the future.
// Currently NetChat uses only 'manage_options' which is a core capability.
