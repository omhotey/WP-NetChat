<?php
/**
 * NetChat — JSON file based settings storage
 * No WP options, no transients, no cache — just a JSON file.
 * Author: omhotey
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'NETCHAT_DATA', WP_CONTENT_DIR . '/netchat-data.json' );

/** Read config from JSON file */
function netchat_get_config( $force_refresh = false ) {
    static $cache = null;
    if ( $force_refresh ) {
        $cache = null;
    }
    if ( $cache !== null ) {
        return $cache;
    }
    if ( ! file_exists( NETCHAT_DATA ) ) {
        $cache = netchat_defaults();
        return $cache;
    }
    $raw = @file_get_contents( NETCHAT_DATA );
    if ( ! $raw ) {
        $cache = netchat_defaults();
        return $cache;
    }
    $data = json_decode( $raw, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        error_log( '[NetChat] JSON decode error: ' . json_last_error_msg() . ' in ' . NETCHAT_DATA );
    }
    $cache = is_array( $data ) ? array_merge( netchat_defaults(), $data ) : netchat_defaults();
    return $cache;
}

/** Save config to JSON file */
function netchat_save_config( $config ) {
    // Clear static cache before saving
    netchat_get_config( true );
    $json = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    if ( ! $json ) {
        error_log( '[NetChat] json_encode failed' );
        return false;
    }
    $result = @file_put_contents( NETCHAT_DATA, $json, LOCK_EX );
    if ( $result === false ) {
        error_log( '[NetChat] file_put_contents failed for ' . NETCHAT_DATA );
    }
    return $result !== false;
}

/** Delete config (full reset to defaults) */
function netchat_delete_config() {
    return file_exists( NETCHAT_DATA ) ? @unlink( NETCHAT_DATA ) : true;
}

/** Default config — ALL OFF, ALL EMPTY */
function netchat_defaults() {
    return [
        'enabled'   => [ 'telegram'=>0,'whatsapp'=>0,'messenger'=>0,'max'=>0,'vk'=>0,'viber'=>0,'email'=>0,'phone'=>0 ],
        'links'     => [ 'telegram'=>'','whatsapp'=>'','messenger'=>'','max'=>'','vk'=>'','viber'=>'','email'=>'','phone'=>'' ],
        'position'  => [ 'top'=>'','right'=>'','bottom'=>'30','left'=>'' ],
        'size'=>56, 'animation'=>300, 'desktop'=>1, 'mobile'=>1,
        'hint'=>0, 'hint_text'=>'', 'hint_delay'=>2,
        'order'=>'telegram,whatsapp,messenger,max,vk,viber,email,phone',
        'color'=>'#2563eb', 'lang'=>'ru',
    ];
}

/** Check if any messenger is active */
function netchat_is_active() {
    $c = netchat_get_config();
    foreach ( $c['enabled'] as $k => $v )
        if ( $v && ! empty( $c['links'][$k] ) ) return true;
    return false;
}

/** Messenger list */
function netchat_messengers() {
    return [
        'telegram'  =>['name'=>'Telegram','icon'=>'telegram','scheme'=>'https://t.me/','color'=>'#0088cc'],
        'whatsapp'  =>['name'=>'WhatsApp','icon'=>'whatsapp','scheme'=>'https://wa.me/','color'=>'#25D366'],
        'messenger' =>['name'=>'Facebook','icon'=>'messenger','scheme'=>'https://m.me/','color'=>'#0084FF'],
        'max'       =>['name'=>'MAX','icon'=>'max','scheme'=>'https://max.ru/','color'=>'#FF6B35'],
        'vk'        =>['name'=>'ВКонтакте','icon'=>'vk','scheme'=>'https://vk.me/','color'=>'#0077FF'],
        'viber'     =>['name'=>'Viber','icon'=>'viber','scheme'=>'viber://chat?number=','color'=>'#7360F2'],
        'email'     =>['name'=>'Email','icon'=>'email','scheme'=>'mailto:','color'=>'#EA4335'],
        'phone'     =>['name'=>'Телефон','icon'=>'phone','scheme'=>'tel:','color'=>'#34C759'],
    ];
}

function netchat_clear_cache() {
    netchat_get_config( true );
    return netchat_delete_config();
}

/** Translate */
function nc_t( $en, $ru ) {
    return ( netchat_get_config()['lang'] ?? 'ru' ) === 'ru' ? $ru : $en;
}

/** Validate URL — per messenger rules */
function netchat_validate_url( $url, $type ) {
    $url = trim( $url );
    if ( empty( $url ) ) return '';
    switch ( $type ) {
        case 'telegram':
            // Accept both raw username and full URL
            if ( preg_match('/^https?:\/\/t\.me\//i', $url) ) return $url;
            $u = ltrim( $url, '@' );
            return preg_match('/^[\w]{3,32}$/', $u) ? 'https://t.me/'.$u : '';
        case 'whatsapp':
            if ( preg_match('/^https?:\/\/wa\.me\//i', $url) ) return $url;
            $d = preg_replace('/[^\d]/','',$url);
            return preg_match('/^\d{10,15}$/',$d) ? 'https://wa.me/'.$d : '';
        case 'vk':
            if ( preg_match('/^https?:\/\/vk\.me\//i', $url) ) return $url;
            $u = preg_replace('/^(https?:\/\/)?(vk\.com\/|vk\.me\/)/','',$url);
            return preg_match('/^[\w]{1,30}$/',$u) ? 'https://vk.me/'.$u : '';
        case 'max':
            if ( preg_match('/^https?:\/\/max\.ru\//i',$url) ) return $url;
            if ( preg_match('/^[\w\/\-\.\?\=\#\&\+\~]+$/',$url) ) return 'https://max.ru/'.$url;
            return $url;
        case 'viber':
            if ( preg_match('/^viber:\/\//i', $url) ) return $url;
            $d = preg_replace('/[^\d+]/','',$url);
            return preg_match('/^\+?\d{10,15}$/',$d) ? 'viber://chat?number=%2B'.ltrim($d,'+') : '';
        case 'messenger':
            if ( preg_match('/^https?:\/\/m\.me\//i', $url) ) return $url;
            $u = preg_replace('/^(https?:\/\/)?(m\.me\/|fb\.me\/)/','',$url);
            return preg_match('/^[\w.]{1,50}$/',$u) ? 'https://m.me/'.$u : '';
        case 'email':
            // Strip mailto: prefix if present
            $e = preg_replace('/^mailto:/i','',$url);
            return filter_var($e,FILTER_VALIDATE_EMAIL) ? 'mailto:'.$e : '';
        case 'phone':
            // Strip tel: prefix if present
            $p = preg_replace('/^tel:/i','',$url);
            return preg_match('/^\+?\d[\d\-]{6,14}$/',$p) ? 'tel:'.preg_replace('/[^\d+]/','',$p) : '';
    }
    return filter_var($url,FILTER_VALIDATE_URL) ? esc_url_raw($url) : '';
}

/** SVG icons */
function netchat_svg_icon( $type ) {
    $icons = [
        'telegram'  => '<svg viewBox="0 0 77.01 77.01"><path fill="#fff" d="M38.51,0C17.24,0,0,17.24,0,38.51s17.24,38.51,38.51,38.51,38.51-17.24,38.51-38.51S59.77,0,38.51,0ZM62.62,19.9l-8.5,40.78c-.19.93-.76,1.61-1.48,1.93-.91.41-1.89.38-2.69-.21l-12.93-9.48-2.65,2.7-4.05,4.11c-.51.51-1.26.64-1.97.49.16-.35.15-.64.18-1.03l.77-11.54c.03-.39.06-.71.41-1.02l23.11-20.46c.32-.28.42-.6.18-.94-.21-.29-.55-.29-.91-.07l-27.1,16.83c-.54.33-.9.76-.74,1.44l3.77,16.64c-.68-.23-1.04-.79-1.25-1.46l-4.69-14.91-12.89-3.83c-.78-.23-1.14-1.13-1.1-1.82.04-.86.61-1.37,1.42-1.68l49.73-19.39c.92-.36,1.88-.12,2.57.48.67.58,1.02,1.51.83,2.46Z"/></svg>',
        'whatsapp'  => '<svg viewBox="0 0 78.22 78.73"><path fill="#fff" d="M66.66,11.3C52.62-2.47,30.78-3.78,15.45,8.07-.02,20.02-4.39,41.13,5.3,58.5L0,78.73l20.58-5.33c17.68,9.42,38.93,4.23,50.31-11.55,11.24-15.58,9.35-37.22-4.23-50.55ZM53.09,57.21c-4.52,1.35-8.98-.1-13.3-2.16-8.41-4.03-15.22-11.1-19.29-19.52-2.06-4.26-1.25-8.89,1.43-12.49,1.53-2.05,5.72-3.27,7.05-.88,1.59,2.87,3.12,6.12,3.55,9.13-.24,1.86-2.22,3.21-3.23,4.85,3.08,6.04,8.22,10.41,14.58,12.86,2.26-1.37,3.08-3.23,5.06-5.53,3.11,1.61,7.41,2.54,9.54,4.92,1.8,3.55-1.8,7.73-5.39,8.8Z"/></svg>',
        'vk'        => '<svg viewBox="0 0 79.26 78.73"><path fill="#fff" d="M58.49,78.64l-35.82.1c-5.06.01-10.36-.72-14.67-3.32-4.96-3-7.19-9.12-7.48-14.96-.69-14.13-.7-27.94,0-42.06C.8,12.69,2.87,6.67,7.54,3.58,11.71.81,16.91,0,21.92,0l36.13.03c4.76,0,9.7.94,13.66,3.56,4.67,3.09,6.73,8.98,7.03,14.67.72,13.92.63,27.52.07,41.46-.22,5.34-1.83,11-5.86,14.43-3.81,3.24-9.09,4.48-14.46,4.5ZM63.13,30.87c1.15-1.84,1.85-3.31,1.74-5.79-3.06-.38-6.57-.85-9.58.38-2.57,5.54-4.98,11.06-10.27,15.14-1.9-5.21-.28-10.15-1.05-15.49-3.74-.26-8.06-.7-11.59.13-1.27,1.28,1.04,3.14,1.27,4.2.88,4.15,1.17,9.25-.21,13.55-6.21-4.8-7.88-11.75-10.72-17.93-3.32-.26-6.2-.71-9.63.46,3.43,15.13,15.54,34.03,30.92,29.94l.29-8.51c5.67-.48,8.57,6.87,12.61,8.77,2.59,1.22,6.28.41,8.99.13.43-3.04-1.44-5.11-3.26-7.12l-6.3-6.96,6.79-10.89Z"/></svg>',
        'max'       => '<svg viewBox="0 0 79.08 78.73"><path fill="#fff" fill-rule="evenodd" d="M40.39,78.51c-7.76,0-11.37-1.13-17.64-5.66-3.97,5.1-16.52,9.08-17.07,2.27,0-5.12-1.13-9.44-2.42-14.16-1.53-5.82-3.27-12.29-3.27-21.68C0,16.86,18.39,0,40.18,0s38.9,17.69,38.9,39.48c.07,21.45-17.24,38.91-38.69,39.03M40.71,19.37c-10.61-.55-18.88,6.8-20.71,18.32-1.51,9.54,1.17,21.15,3.46,21.75,1.1.26,3.85-1.96,5.57-3.68,2.84,1.96,6.15,3.14,9.59,3.42,11,.53,20.39-7.84,21.13-18.82.43-11.01-8.04-20.33-19.03-20.96v-.02Z"/></svg>',
        'viber'     => '<svg viewBox="0 0 77.03 77.01"><path fill="#fff" d="M67.69,77H9.4c-5.09,0-9.4-4.32-9.4-9.4V9.37C0,4.35,4.24,0,9.31,0h58.41c5.07,0,9.31,4.35,9.31,9.37v58.24c0,5-4.25,9.39-9.34,9.39ZM65.79,39.17c.84.1,1.68-.4,1.69-1.31v-2.08c.06-14.13-12.07-26.95-26.24-27.36l-1.63-.05c-.98-.03-1.59.61-1.67,1.57-.05.7.32,1.37,1.11,1.46,2.22.23,4.37.62,6.55,1.19,6.52,1.7,11.93,4.88,15.14,10.96,2.05,3.88,3.08,8.21,3.58,12.59.14,1.22-.26,2.82,1.47,3.03ZM54.66,46.15c-1.9-.63-3.83-.31-5.32.94-.81.68-1.41,1.47-1.99,2.35-1.15,1.72-3.21,2.39-5.19,1.6-7.23-2.86-13.31-8.51-15.91-15.89-.78-2.21-.05-4.41,1.75-5.76.85-.64,1.67-1.19,2.41-1.93,1.18-1.2,1.58-2.84.97-4.41-1.38-3.58-4.69-8.11-7.33-10.97-2.16-2.34-3.91-3.65-7.14-2.47-1.8.66-3.41,1.53-4.81,2.82-2.34,2.15-3.42,5.16-3.04,8.33.18,1.51.59,2.81,1.18,4.22,5.45,13.08,13.65,24.09,25.07,32.59,5.63,4.19,11.73,7.64,18.18,10.34.87.36,1.72.46,2.63.73,4.45-.09,8.79-3.54,10.9-7.37,1.5-2.71,1.42-5.26-.98-7.28-2.54-2.14-5.19-4.02-7.91-5.93-1.09-.77-2.14-1.45-3.46-1.89ZM58.78,36.38c.81-.05,1.41-.51,1.42-1.31.02-1.18.06-2.36-.07-3.55-.85-7.77-7.33-14.09-14.94-15.62-1.11-.22-2.17-.36-3.25-.48-.99-.11-1.65.53-1.76,1.45s.31,1.63,1.27,1.79c2.75.45,5.44,1.14,7.98,2.33,2.84,1.34,4.88,3.56,6.05,6.47,1.01,2.53,1.5,5.16,1.76,7.83.08.81.81,1.13,1.55,1.08ZM50.33,33.09c.15.72.91,1.16,1.45,1.11.69-.07,1.29-.48,1.43-1.2.75-3.89-2.22-7.89-5.71-9.58-1.27-.61-2.62-.82-3.96-.54-.73.15-1.12.74-1.23,1.44-.1.63.16,1.25.86,1.42l2.34.58c2.23.55,3.87,2.28,4.34,4.52l.47,2.26Z"/></svg>',
        'messenger' => '<svg viewBox="0 0 78.75 78.82"><path fill="#fff" d="M28.44,70.38l-20.47,8.44,4.4-13.92C6.43,59.21,1.44,52.76.33,44.27-2.43,23.13,12.37,3.72,33.48.62c9.8-1.44,19.55-.5,28.09,4.04,11.89,6.32,17.5,15.9,17.17,28.08-.34,12.48-6.07,23.49-14.9,30.47-10.05,7.95-22.6,10.56-35.4,7.16ZM44.4,48.72l18.71-20.69c-6.96,1.46-10.99,6.01-16.87,8.21l-10.28-10.93-20.76,22.8,18.48-9.92,10.72,10.54Z"/></svg>',
        'email'     => '<svg viewBox="0 0 98.45 78.73"><path fill="#fff" d="M88.2,78.73l-78.4-.05C3.84,78.68-.01,73.7,0,68.05L.11,9.49C.12,4.32,4.53.01,9.79,0h78.87c5.92-.01,9.79,4.97,9.79,10.58l-.02,57.45c0,5.62-3.77,10.69-10.23,10.69ZM88.88,19.48l-.6-9.51-38.84,24.29L10.12,9.98l-.08,9.87,38.95,24.37,39.88-24.74Z"/></svg>',
        'phone'     => '<svg viewBox="0 0 78.37 78.37"><path fill="#fff" d="M67.56,0H10.8C4.84,0,0,4.84,0,10.8v56.76c0,5.97,4.84,10.8,10.8,10.8h56.76c5.97,0,10.8-4.84,10.8-10.8V10.8c0-5.97-4.84-10.8-10.8-10.8ZM66.57,63.2c0,2.13-1.26,3.41-3.42,3.4-28.59-.17-51.52-23.45-51.37-52.02,0-1.52,1.36-2.7,2.75-2.71l10.33-.09c5.23-.05,2.85,5.64,5.26,13.45.52,1.68-.14,3.2-1.28,4.36l-5.9,5.95c4.32,8.7,11.29,15.49,19.92,19.93l5.77-5.74c1.29-1.29,2.88-1.97,4.52-1.44,7.65,2.44,13.48.14,13.46,5.21l-.03,9.73Z"/></svg>',
    ];
    return $icons[ $type ] ?? '';
}
