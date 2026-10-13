<?php
/**
 * Plugin Name: NetChat
 * Plugin URI:  https://github.com/omhotey/WP-NetChat
 * Description: Плавающая кнопка связи с мессенджерами. Telegram, WhatsApp, VK, MAX, Viber, Messenger, Email, Телефон. Автор: omhotey.
 * Version:     1.2.0
 * Author:      omhotey
 * Author URI:  https://github.com/omhotey
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: netchat
 * Domain Path: /languages
 * Requires PHP: 7.2
 * Requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NETCHAT_FILE', __FILE__ );
define( 'NETCHAT_DIR', plugin_dir_path( __FILE__ ) );
define( 'NETCHAT_URL', plugin_dir_url( __FILE__ ) );
define( 'NETCHAT_VER', '1.2.0' );

// Load translations
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'netchat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Load plugin files
require_once NETCHAT_DIR . 'includes/leads-settings.php';
require_once NETCHAT_DIR . 'includes/helpers.php';
require_once NETCHAT_DIR . 'includes/admin.php';
require_once NETCHAT_DIR . 'includes/frontend.php';
foreach ( array('leads.php','service-forms.php','service-forms-validation.php','service-forms-admin.php','data-transfer.php','leads-admin.php','leads-notifications.php','leads-privacy.php','leads-frontend.php') as $netchat_module ) {
    require_once NETCHAT_DIR . 'includes/' . $netchat_module;
}

add_action( 'plugins_loaded', 'netchat_upgrade_storage', 5 );
function netchat_upgrade_storage() {
    netchat_get_config( true );
    if ( function_exists('netchat_leads_install') && ! netchat_get_migration_error() ) netchat_leads_install();
    if (!netchat_get_migration_error()) netchat_service_initialize();
}

// Settings link on plugins page — direct to our standalone menu
add_filter( 'plugin_action_links_' . plugin_basename( NETCHAT_FILE ), function( $links ) {
    array_unshift( $links, '<a href="' . admin_url( 'admin.php?page=netchat' ) . '">' . esc_html__( 'Settings', 'netchat' ) . '</a>' );
    return $links;
} );

// Meta links
add_filter( 'plugin_row_meta', function( $links, $file ) {
    if ( plugin_basename( NETCHAT_FILE ) !== $file ) return $links;
    $links[] = '<a href="https://github.com/omhotey/WP-NetChat" target="_blank">GitHub</a>';
    return $links;
}, 10, 2 );

/**
 * Activation - create default options
 */
register_activation_hook( NETCHAT_FILE, 'netchat_activate' );
function netchat_activate( $network_wide = false ) {
    if ( $network_wide ) wp_die( 'NetChat supports single-site activation only.' );
    netchat_get_config( true );
    if ( function_exists('netchat_leads_install') && ! netchat_get_migration_error() ) netchat_leads_install();
    if (!netchat_get_migration_error()) netchat_service_initialize();
}
/**
 * Deactivation - do nothing (preserve settings)
 */
register_deactivation_hook( NETCHAT_FILE, 'netchat_deactivate' );
function netchat_deactivate() {
    wp_clear_scheduled_hook('netchat_leads_send_notifications');
    wp_clear_scheduled_hook('netchat_leads_retention_cleanup');
    // Do not delete config on deactivation
    // Settings are preserved for re-activation
}
