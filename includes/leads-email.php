<?php
/** Notification presentation only; transport identity belongs to the site mailer. */
if ( ! defined( 'ABSPATH' ) ) exit;

function netchat_email_text( $value ) {
    return is_scalar( $value ) ? (string) $value : '';
}
function netchat_email_line( $value ) {
    return trim( preg_replace( '/[\r\n\x00-\x1f\x7f]+/u', ' ', wp_strip_all_tags( netchat_email_text( $value ) ) ) );
}
function netchat_email_service_sections( $service ) {
    $snapshot = isset( $service['snapshot'] ) && is_array( $service['snapshot'] ) ? $service['snapshot'] : array();
    $contact = isset( $service['contact'] ) && is_array( $service['contact'] ) ? $service['contact'] : array();
    $labels = isset( $snapshot['contact'] ) && is_array( $snapshot['contact'] ) ? $snapshot['contact'] : array();
    $contacts = array();
    foreach ( array( 'name' => 'Имя', 'company' => 'Компания или проект', 'value' => 'Контакт', 'note' => 'Когда и как удобнее связаться' ) as $key => $fallback ) {
        $value = netchat_email_text( $contact[$key] ?? '' );
        if ( $value !== '' ) $contacts[] = array( netchat_email_text( $labels[$key] ?? $fallback ), $value );
    }
    $answers = array();
    foreach ( (array) ( $service['answers'] ?? array() ) as $key => $value ) {
        $field = isset( $snapshot['fields'][$key] ) && is_array( $snapshot['fields'][$key] ) ? $snapshot['fields'][$key] : array();
        $values = is_array( $value ) ? $value : array( $value );
        $readable = array();
        foreach ( $values as $item ) {
            if ( ! is_scalar( $item ) ) continue;
            $readable[] = netchat_email_text( $field['options'][$item] ?? $item );
        }
        $answers[] = array( netchat_email_text( $field['label'] ?? $key ), implode( ', ', $readable ) );
    }
    $context = isset( $service['context'] ) && is_array( $service['context'] ) ? $service['context'] : array();
    $source = array();
    foreach ( array( 'page_url' => 'Страница', 'origin_service' => 'Исходная услуга', 'cta_position' => 'Место обращения', 'referrer_origin' => 'Источник перехода' ) as $key => $label ) {
        $value = netchat_email_text( $context[$key] ?? '' );
        if ( $key === 'cta_position' ) {
            $positions = array( 'widget' => 'Виджет сайта', 'shortcode' => 'Форма на странице', 'cta' => 'Кнопка на странице' );
            $value = $positions[$value] ?? $value;
        }
        if ( $value !== '' ) $source[] = array( $label, $value );
    }
    foreach ( (array) ( $context['utm'] ?? array() ) as $key => $value ) {
        if ( is_scalar( $value ) && (string) $value !== '' ) $source[] = array( netchat_email_text( $key ), (string) $value );
    }
    return array( 'Контакты' => $contacts, 'Ответы на вопросы' => $answers, 'Источник обращения' => $source );
}
function netchat_email_sections_text( $sections ) {
    $text = '';
    foreach ( $sections as $title => $rows ) {
        if ( ! $rows ) continue;
        $text .= "\n" . $title . "\n";
        foreach ( $rows as $row ) $text .= $row[0] . ":\n" . $row[1] . "\n\n";
    }
    return $text;
}
function netchat_email_sections_html( $sections ) {
    $html = '';
    foreach ( $sections as $title => $rows ) {
        if ( ! $rows ) continue;
        $html .= '<h2 style="margin:28px 0 16px;font-size:19px;line-height:1.35;color:#183b56">' . esc_html( $title ) . '</h2>';
        foreach ( $rows as $row ) {
            $html .= '<div style="margin:0 0 18px;overflow-wrap:anywhere;word-break:break-word"><p style="margin:0 0 5px;color:#546473;font-size:14px;line-height:1.5">' . esc_html( $row[0] ) . '</p><p style="margin:0;color:#182b3a;font-size:16px;line-height:1.6">' . nl2br( esc_html( $row[1] ), false ) . '</p></div>';
        }
    }
    return $html;
}
/** Both representations use the same immutable snapshot and visibility policy. */
function netchat_leads_build_email( $lead, $config, $link ) {
    $code = netchat_email_line( $lead['public_code'] ?? '' );
    $site = netchat_email_line( html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) );
    if ( $site === '' ) $site = 'Сайт';
    $date = get_date_from_gmt( $lead['created_at'], 'd.m.Y H:i' );
    $timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (string) get_option( 'timezone_string', '' );
    if ( $timezone === '' ) {
        $offset = (float) get_option( 'gmt_offset', 0 );
        $minutes = (int) round( abs( $offset ) * 60 );
        $timezone = sprintf( 'UTC%s%02d:%02d', $offset < 0 ? '-' : '+', (int) floor( $minutes / 60 ), $minutes % 60 );
    }
    $date .= ' (' . $timezone . ')';
    $service = ! empty( $lead['submission_json'] ) ? json_decode( $lead['submission_json'], true ) : null;
    $is_service = is_array( $service ) && isset( $service['snapshot'], $service['contact'], $service['answers'] ) && is_array( $service['snapshot'] ) && is_array( $service['contact'] ) && is_array( $service['answers'] );
    $form = $is_service ? netchat_email_text( $service['snapshot']['title'] ?? 'Форма сайта' ) : 'Контактная форма';
    if ( $is_service ) $sections = netchat_email_service_sections( $service );
    else {
        $contacts = array();
        foreach ( array( 'name' => 'Имя', 'email' => 'Email', 'phone' => 'Телефон' ) as $key => $label ) {
            $value = netchat_email_text( $lead[$key] ?? '' );
            if ( $value !== '' ) $contacts[] = array( $label, $value );
        }
        $sections = array( 'Контакты' => $contacts );
        if ( ! empty( $config['include_message'] ) && netchat_email_text( $lead['message'] ?? '' ) !== '' ) $sections['Сообщение'] = array( array( 'Текст обращения', netchat_email_text( $lead['message'] ) ) );
        $source = array();
        foreach ( array( 'context' => 'Контекст', 'page_url' => 'Страница', 'referrer' => 'Источник перехода' ) as $key => $label ) {
            $value = netchat_email_text( $lead[$key] ?? '' );
            if ( $value !== '' ) $source[] = array( $label, $value );
        }
        $sections['Источник обращения'] = $source;
    }
    $subject = '[NetChat] Новая заявка №' . $code;
    $intro = 'Новая заявка №' . $code;
    $footer = 'Автоматическое уведомление сайта «' . $site . '» для его владельца. Для просмотра и обработки заявки требуется вход в WordPress.';
    $plain = $intro . "\nСайт: " . $site . "\nПолучена: " . $date . "\nФорма: " . $form . "\n\nОткрыть заявку:\n" . $link . "\n" . netchat_email_sections_text( $sections ) . "\n" . $footer . "\n";
    $html = '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $intro ) . '</title></head><body style="margin:0;padding:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;color:#182b3a"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2f5"><tr><td align="center" style="padding:24px 12px"><table role="presentation" width="620" cellspacing="0" cellpadding="0" style="width:100%;max-width:620px;background:#ffffff;border:1px solid #dce4eb"><tr><td style="padding:26px 24px;background:#183b56;color:#ffffff;overflow-wrap:anywhere;word-break:break-word"><p style="margin:0 0 12px;font-size:14px;line-height:1.5">' . esc_html( $site ) . '</p><h1 style="margin:0 0 12px;font-size:26px;line-height:1.3;color:#ffffff">' . esc_html( $intro ) . '</h1><p style="margin:0;font-size:14px;line-height:1.6;color:#dbe7f0">Получена: ' . esc_html( $date ) . '</p></td></tr><tr><td style="padding:24px;overflow-wrap:anywhere;word-break:break-word"><p style="margin:0 0 20px;font-size:17px;line-height:1.5"><strong>Форма:</strong> ' . esc_html( $form ) . '</p><p style="margin:0 0 24px"><a href="' . esc_url( $link ) . '" style="display:inline-block;padding:14px 20px;background:#1769a7;color:#ffffff;text-decoration:none;font-size:16px;font-weight:bold;line-height:1.4;border-radius:4px">Открыть заявку</a></p>' . netchat_email_sections_html( $sections ) . '<p style="margin:28px 0 0;padding-top:20px;border-top:1px solid #dce4eb;color:#546473;font-size:12px;line-height:1.6">' . esc_html( $footer ) . '</p></td></tr></table></td></tr></table></body></html>';
    return array( 'subject' => $subject, 'html' => $html, 'text' => $plain );
}
/** Match only this message; remove the hook even on false/short-circuit/exception. */
function netchat_leads_mail_message( $recipients, $message, $headers ) {
    $matched_mailer = null;
    $previous_alternative = '';
    $alternative = function ( $mailer ) use ( $message, &$matched_mailer, &$previous_alternative ) {
        if ( $mailer->Body === $message['html'] && $mailer->Subject === $message['subject'] ) {
            $matched_mailer = $mailer;
            $previous_alternative = $mailer->AltBody;
            $mailer->AltBody = $message['text'];
        }
    };
    add_action( 'phpmailer_init', $alternative, PHP_INT_MAX );
    try {
        return wp_mail( $recipients, $message['subject'], $message['html'], $headers );
    } finally {
        remove_action( 'phpmailer_init', $alternative, PHP_INT_MAX );
        // WordPress reuses PHPMailer: never leave this lead in the next message.
        if ( $matched_mailer && $matched_mailer->AltBody === $message['text'] ) $matched_mailer->AltBody = $previous_alternative;
    }
}
