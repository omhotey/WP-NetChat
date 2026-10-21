<?php
/** Persistent email attempts. A successful transport handoff is not delivery. */
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/leads-email.php';
add_action( 'netchat_lead_created', 'netchat_leads_schedule_notification', 10, 1 );
add_action( 'netchat_leads_send_notifications', 'netchat_leads_send_notifications' );
add_action( 'init', 'netchat_leads_schedule_workers' );
function netchat_leads_schedule_workers() {
    if ( ! wp_next_scheduled( 'netchat_leads_send_notifications' ) ) wp_schedule_event( time() + 60, 'hourly', 'netchat_leads_send_notifications' );
}
function netchat_leads_schedule_notification( $id ) {
    // The pending database row remains authoritative if scheduling fails.
    if ( ! wp_next_scheduled( 'netchat_leads_send_notifications' ) || wp_next_scheduled( 'netchat_leads_send_notifications' ) > time() + 60 ) {
        wp_schedule_single_event( time() + 5, 'netchat_leads_send_notifications' );
    }
}
function netchat_leads_email_config() {
    $config = netchat_get_config();
    return isset( $config['leads']['email'] ) ? $config['leads']['email'] : array( 'enabled' => 0, 'recipients' => array(), 'include_message' => 0 );
}
function netchat_leads_email_recipients( $config ) {
    $recipients = isset( $config['recipients'] ) && is_array( $config['recipients'] ) ? $config['recipients'] : array();
    if ( ! $recipients ) $recipients = array( get_option( 'admin_email' ) );
    $valid = array();
    foreach ( array_slice( $recipients, 0, 5 ) as $email ) {
        if ( is_string( $email ) && is_email( $email ) && ! preg_match( '/[\r\n]/', $email ) ) $valid[] = $email;
    }
    return array_values( array_unique( $valid ) );
}
/** Plain-text notification built from the immutable submission snapshot. */
function netchat_leads_service_email( $service ) {
    $lines = array('Услуга: '.($service['snapshot']['title'] ?? $service['service_code']), '', 'КОНТАКТЫ');
    $contact = $service['contact'];
    $labels = $service['snapshot']['contact'] ?? array();
    foreach (array('name'=>'Имя','company'=>'Компания или проект','value'=>'Контакт','note'=>'Когда и как удобнее связаться') as $key=>$fallback) {
        if (isset($contact[$key]) && $contact[$key] !== '') $lines[] = ($labels[$key] ?? $fallback).': '.$contact[$key];
    }
    $lines[] = ''; $lines[] = 'ОТВЕТЫ НА ВОПРОСЫ';
    foreach ($service['answers'] as $key=>$value) {
        $field = $service['snapshot']['fields'][$key] ?? array('label'=>$key,'options'=>array());
        $values = is_array($value) ? $value : array($value);
        $readable = array_map(function($item)use($field){return $field['options'][$item] ?? $item;},$values);
        $lines[] = ''; $lines[] = $field['label']; $lines[] = implode(', ', $readable);
    }
    $lines[] = ''; $lines[] = 'СТРАНИЦА ОБРАЩЕНИЯ';
    foreach (array('page_url'=>'Страница','origin_service'=>'Исходная услуга','cta_position'=>'Кнопка','referrer_origin'=>'Источник') as $key=>$label) {
        if (!empty($service['context'][$key])) $lines[] = $label.': '.$service['context'][$key];
    }
    foreach (($service['context']['utm'] ?? array()) as $key=>$value) $lines[] = $key.': '.$value;
    return implode("\n", $lines)."\n";
}
function netchat_leads_send_notifications() {
    global $wpdb;
    $table = netchat_leads_table();
    $now = gmdate( 'Y-m-d H:i:s' );
    $stale = gmdate( 'Y-m-d H:i:s', time() - 600 );
    $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE notification_status = 'pending' AND notification_attempts < 3 AND (notification_next_attempt_at IS NULL OR notification_next_attempt_at <= %s) AND (notification_claimed_at IS NULL OR notification_claimed_at < %s) ORDER BY id ASC LIMIT 20", $now, $stale ) );
    foreach ( $ids as $id ) netchat_leads_send_one_notification( (int) $id );
    // A crash after the final claim must not leave an eternal pending row.
    $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET notification_status='failed', notification_error='attempts_exhausted', notification_claimed_at=NULL WHERE notification_status='pending' AND notification_attempts >= 3 AND notification_claimed_at < %s", $stale ) );
}
function netchat_leads_send_one_notification( $id ) {
    global $wpdb;
    $table = netchat_leads_table();
    $claimed = gmdate( 'Y-m-d H:i:s' );
    $stale = gmdate( 'Y-m-d H:i:s', time() - 600 );
    $claim = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET notification_claimed_at=%s, notification_last_attempt_at=%s, notification_attempts=notification_attempts+1 WHERE id=%d AND notification_status='pending' AND notification_attempts < 3 AND (notification_next_attempt_at IS NULL OR notification_next_attempt_at <= %s) AND (notification_claimed_at IS NULL OR notification_claimed_at < %s)", $claimed, $claimed, $id, $claimed, $stale ) );
    if ( $claim !== 1 ) return false;
    $lead = netchat_leads_get( $id );
    if ( is_wp_error($lead) || ! $lead || 'spam' === $lead['status'] ) return false;
    $config = netchat_leads_email_config();
    $recipients = netchat_leads_email_recipients( $config );
    $state = 'failed'; $error = ''; $next = null;
    if ( empty( $config['enabled'] ) ) { $state = 'disabled'; $error = 'disabled'; }
    elseif ( ! $recipients ) { $error = 'missing_recipient'; }
    else {
        $link = add_query_arg( array( 'page' => 'netchat-leads', 'lead' => $id ), admin_url( 'admin.php' ) );
        $message = netchat_leads_build_email( $lead, $config, $link );
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        // Let the site's mailer hooks configure From. Never impersonate the visitor.
        if ( ! empty( $lead['email'] ) && is_email( $lead['email'] ) && ! preg_match( '/[\r\n]/', $lead['email'] ) ) $headers[] = 'Reply-To: ' . $lead['email'];
        try { $sent = netchat_leads_mail_message( $recipients, $message, $headers ); }
        catch ( Throwable $exception ) { $sent = false; }
        if ( $sent ) $state = 'sent';
        else { $error = 'transport_failed'; if ( (int) $lead['notification_attempts'] < 3 ) { $state = 'pending'; $next = gmdate( 'Y-m-d H:i:s', time() + ( (int) $lead['notification_attempts'] === 1 ? 300 : 1800 ) ); } }
    }
    // A deleted row cannot be resurrected; stale workers cannot overwrite newer claims.
    $result = $wpdb->update( $table, array( 'notification_status' => $state, 'notification_error' => $error, 'notification_next_attempt_at' => $next, 'notification_claimed_at' => null ), array( 'id' => $id, 'notification_claimed_at' => $claimed ), array( '%s', '%s', '%s', '%s' ), array( '%d', '%s' ) );
    if ( $next ) wp_schedule_single_event( strtotime( $next . ' UTC' ), 'netchat_leads_send_notifications' );
    return $result !== false && $state === 'sent';
}
function netchat_leads_retry_notification( $id, $send_now = true ) {
    global $wpdb;
    if ( ! current_user_can( 'manage_options' ) ) return new WP_Error( 'netchat_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
    if ( empty( netchat_leads_email_config()['enabled'] ) ) return new WP_Error( 'netchat_email_disabled', 'Enable email notifications before retrying.', array( 'status' => 400 ) );
    $lead = netchat_leads_get( $id );
    if ( is_wp_error($lead) ) return $lead;
    if ( ! $lead ) return new WP_Error( 'netchat_not_found', 'Request not found.', array( 'status' => 404 ) );
    if ( 'spam' === $lead['status'] ) return new WP_Error('netchat_spam','Change the spam status before retrying notification.',array('status'=>400));
    $table = netchat_leads_table();
    $stale = gmdate( 'Y-m-d H:i:s', time() - 600 );
    $changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET notification_status='pending',notification_attempts=0,notification_error='',notification_next_attempt_at=NULL,notification_claimed_at=NULL WHERE id=%d AND (notification_claimed_at IS NULL OR notification_claimed_at < %s)", $id, $stale ) );
    if ( $changed === false ) return new WP_Error( 'netchat_email_retry_failed', 'Could not schedule notification.', array( 'status' => 503 ) );
    if ( ! $changed ) {
        $current = netchat_leads_get($id);
        if ( is_wp_error($current) || !$current || !empty($current['notification_claimed_at']) ) return new WP_Error( 'netchat_email_busy', 'Notification is currently being sent.', array( 'status' => 409 ) );
    }
    netchat_leads_schedule_notification( $id );
    // Explicit administrator retry must work even when loopback WP-Cron is unavailable.
    if ( $send_now ) netchat_leads_send_one_notification( (int) $id );
    return true;
}
