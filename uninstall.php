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

// Delete plugin options.
delete_option( 'netchat_config' );

// Delete JSON config file.
if ( defined( 'WP_CONTENT_DIR' ) ) {
    $json_file = WP_CONTENT_DIR . '/netchat-data.json';
    if ( file_exists( $json_file ) ) {
        @unlink( $json_file );
    }
}

// Remove any custom capabilities or roles if added in the future.
// Currently NetChat uses only 'manage_options' which is a core capability.
