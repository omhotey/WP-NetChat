<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
if ( ! defined( 'NETCHAT_VER' ) || ! function_exists( 'netchat_get_config' ) ) {
    WP_CLI::error( 'NetChat is not loaded.' );
}
$config = netchat_get_config();
if ( ! is_array( $config ) || ! isset( $config['enabled'], $config['links'] ) ) {
    WP_CLI::error( 'NetChat configuration could not be read.' );
}
if ( ! get_page_by_path( 'netchat-test', OBJECT, 'page' ) ) {
    WP_CLI::error( 'NetChat test page is missing.' );
}
if ( ! wp_mail( 'netchat-test@example.test', 'NetChat laboratory SMTP check', 'Local development test. No visitor data.' ) ) {
    WP_CLI::error( 'Local SMTP test failed.' );
}
WP_CLI::success( 'NetChat loaded, config readable, test page present, SMTP accepted a test message.' );
WP_CLI::log( 'PHP ' . PHP_VERSION . '; WordPress ' . get_bloginfo( 'version' ) . '; NetChat ' . NETCHAT_VER );
