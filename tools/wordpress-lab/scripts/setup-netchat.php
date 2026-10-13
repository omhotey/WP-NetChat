<?php
if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }
if ( ! get_page_by_path( 'netchat-test', OBJECT, 'page' ) ) {
    $result = wp_insert_post( array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'NetChat Test',
        'post_name' => 'netchat-test',
        'post_content' => '<!-- wp:heading --><h2 class="wp-block-heading">Проверка NetChat</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Настройте каналы в меню NetChat. Эта страница используется для проверки виджета на desktop и mobile.</p><!-- /wp:paragraph -->',
    ), true );
    if ( is_wp_error( $result ) ) { WP_CLI::error( $result->get_error_message() ); }
}
if ( ! get_option( 'permalink_structure' ) ) {
    update_option( 'permalink_structure', '/%postname%/' );
    flush_rewrite_rules();
}
WP_CLI::success( 'NetChat laboratory page is ready.' );
