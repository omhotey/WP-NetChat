<?php
/**
 * Plugin Name: NetChat
 * Plugin URI:  https://github.com/omhotey/WP-NetChat
 * Description: Плавающая кнопка связи с мессенджерами. Telegram, WhatsApp, VK, MAX, Viber, Messenger, Email, Телефон. Автор: omhotey.
 * Version:     1.0.0
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
define( 'NETCHAT_VER', '1.0.0' );

// Load translations
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'netchat', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Load plugin files
require_once NETCHAT_DIR . 'includes/helpers.php';
require_once NETCHAT_DIR . 'includes/admin.php';
require_once NETCHAT_DIR . 'includes/frontend.php';

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
function netchat_activate() {
    $defaults = array(
        'enabled'   => array(
            'telegram'=>0, 'whatsapp'=>0, 'messenger'=>0, 'max'=>0,
            'vk'=>0, 'viber'=>0, 'email'=>0, 'phone'=>0
        ),
        'links'     => array(
            'telegram'=>'', 'whatsapp'=>'', 'messenger'=>'', 'max'=>'',
            'vk'=>'', 'viber'=> '', 'email'=>'', 'phone'=>''
        ),
        'position'  => array( 'top'=>'', 'right'=>'', 'bottom'=>'30', 'left'=>'' ),
        'size'      => 56,
        'animation' => 300,
        'desktop'   => 1,
        'mobile'    => 1,
        'hint'      => 0,
        'hint_text' => '',
        'hint_delay'=> 2,
        'order'     => 'telegram,whatsapp,messenger,max,vk,viber,email,phone',
        'color'     => '#2563eb',
        'lang'      => 'ru',
    );

    // Always overwrite with fresh defaults on activation — use JSON file storage
    netchat_delete_config();
    netchat_save_config( $defaults );
}

/**
 * Deactivation - do nothing (preserve settings)
 */
register_deactivation_hook( NETCHAT_FILE, 'netchat_deactivate' );
function netchat_deactivate() {
    // Do not delete config on deactivation
    // Settings are preserved for re-activation
}

/**
 * Uninstall - remove all data
 */
register_uninstall_hook( NETCHAT_FILE, 'netchat_uninstall' );
function netchat_uninstall() {
    delete_option( 'netchat_config' );
    netchat_delete_config();
}
