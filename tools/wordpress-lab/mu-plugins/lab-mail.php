<?php
/** Local laboratory SMTP capture; this file is not part of NetChat. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
// localhost points at this container, not at the published host port.
add_filter('cron_request', function($request) {
    if (!getenv('WORDPRESS_LAB_SMTP_HOST')) return $request;
    $host=wp_parse_url($request['url'],PHP_URL_HOST);
    if (!in_array($host,array('localhost','127.0.0.1'),true)) return $request;
    $query=wp_parse_url($request['url'],PHP_URL_QUERY);
    $request['url']='http://wordpress/wp-cron.php'.($query?'?'.$query:'');
    return $request;
});
add_filter( 'wp_mail_from', function ( $address ) {
    return getenv( 'WORDPRESS_LAB_SMTP_HOST' ) ? 'wordpress@lab.example.test' : $address;
} );
add_action( 'phpmailer_init', function ( $mailer ) {
    $host = getenv( 'WORDPRESS_LAB_SMTP_HOST' );
    if ( ! $host ) { return; }
    $mailer->isSMTP();
    $mailer->Host = $host;
    $mailer->Port = 1025;
    $mailer->SMTPAuth = false;
    $mailer->SMTPAutoTLS = false;
    $mailer->SMTPSecure = '';
} );
