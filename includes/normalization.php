<?php
/** Canonical contact links and complete settings validation. */
if ( ! defined( 'ABSPATH' ) ) exit;

function netchat_invalid_config( $field ) {
    return netchat_storage_error( 'netchat_invalid_config', 'Invalid NetChat setting: ' . $field . '.', 400 );
}

function netchat_validate_url( $url, $type ) {
    if ( ! is_string( $url ) || strlen( $url ) > 2048 || preg_match( '/[\x00-\x1f\x7f]/', $url ) ) return '';
    $url = trim( $url );
    if ( '' === $url ) return '';
    if ( preg_match( '/\s/', $url ) && ! in_array( $type, array( 'phone', 'whatsapp', 'viber' ), true ) ) return '';
    if ( 'email' === $type ) {
        $email = preg_replace( '/^mailto:/i', '', $url );
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? 'mailto:' . $email : '';
    }
    if ( in_array( $type, array( 'phone', 'whatsapp', 'viber' ), true ) ) {
        $number = $url;
        if ( 'phone' === $type ) $number = preg_replace( '/^tel:/i', '', $number );
        elseif ( 'whatsapp' === $type && preg_match( '/^https?:\/\//i', $number ) ) {
            $parts = parse_url( $number );
            if ( ! $parts || strtolower( $parts['host'] ?? '' ) !== 'wa.me' || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) || preg_match( '/\s/', $number ) ) return '';
            $number = trim( $parts['path'] ?? '', '/' );
            if ( ! preg_match( '/^\d{10,15}$/', $number ) ) return '';
            $query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
            return 'https://wa.me/' . $number . $query;
        } elseif ( 'viber' === $type && preg_match( '/^viber:/i', $number ) ) {
            if ( ! preg_match( '/^viber:\/\/chat\?number=(%2B|\+)?(\d{10,15})$/i', $number, $match ) ) return '';
            return 'viber://chat?number=%2B' . $match[2];
        }
        if ( ! preg_match( '/^\+?[\d ().-]+$/', $number ) ) return '';
        $digits = preg_replace( '/\D/', '', $number );
        $minimum = 'phone' === $type ? 7 : 10;
        if ( strlen( $digits ) < $minimum || strlen( $digits ) > 15 ) return '';
        if ( 'phone' === $type ) return 'tel:' . ( strpos( $number, '+' ) === 0 ? '+' : '' ) . $digits;
        return 'whatsapp' === $type ? 'https://wa.me/' . $digits : 'viber://chat?number=%2B' . $digits;
    }
    $hosts = array( 'telegram'=>'t.me', 'messenger'=>'m.me', 'vk'=>'vk.me', 'max'=>'max.ru' );
    if ( ! isset( $hosts[$type] ) ) return '';
    $host = $hosts[$type]; $path = $url; $suffix = '';
    if ( preg_match( '/^https?:\/\//i', $url ) ) {
        $parts = parse_url( $url );
        if ( ! $parts || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) return '';
        $accepted = array( $host );
        if ( 'vk' === $type ) $accepted[] = 'vk.com';
        if ( 'messenger' === $type ) $accepted[] = 'fb.me';
        if ( ! in_array( strtolower( $parts['host'] ?? '' ), $accepted, true ) ) return '';
        $path = trim( $parts['path'] ?? '', '/' );
        $suffix = ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' ) . ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
    } else {
        $path = preg_replace( '/^(?:' . preg_quote( $host, '/' ) . '\/)/i', '', $path );
        if ( 'vk' === $type ) $path = preg_replace( '/^vk\.com\//i', '', $path );
        if ( 'messenger' === $type ) $path = preg_replace( '/^fb\.me\//i', '', $path );
        if ( 'telegram' === $type ) $path = ltrim( $path, '@' );
    }
    if ( 'telegram' === $type ) $valid = preg_match( '/^(?:[A-Za-z0-9_]{3,32}|\+[A-Za-z0-9_-]+|joinchat\/[A-Za-z0-9_-]+|[A-Za-z0-9_]{3,32}\/\d+)$/', $path );
    elseif ( 'vk' === $type ) $valid = preg_match( '/^[A-Za-z0-9_]{1,100}$/', $path );
    elseif ( 'messenger' === $type ) $valid = preg_match( '/^[A-Za-z0-9_.]{1,100}$/', $path );
    else $valid = preg_match( '/^[A-Za-z0-9_+~.-]+(?:\/[A-Za-z0-9_+~.-]+)*$/', $path );
    return $valid ? 'https://' . $host . '/' . $path . $suffix : '';
}

function netchat_normalize_config( $input, $strict = true ) {
    $defaults = netchat_defaults();
    if ( ! is_array( $input ) ) return $strict ? netchat_invalid_config( 'document' ) : $defaults;
    if ( $strict && array_diff( array_keys( $input ), array_keys( $defaults ) ) ) return netchat_invalid_config( 'unknown field' );
    $result = $defaults;
    foreach ( $defaults as $key => $default ) {
        if ( ! array_key_exists( $key, $input ) ) { if ( $strict && ! in_array( $key, array( 'launcher_mode', 'button_scroll_percent', 'hint_scroll_percent', 'form_presentation', 'form_drawer_side', 'hint_mobile', 'button_delay' ), true ) ) return netchat_invalid_config( $key ); continue; }
        $value = $input[$key];
        if ( 'leads' === $key ) {
            $leads = netchat_leads_normalize_settings( $value, $strict );
            if ( is_wp_error( $leads ) ) return $leads;
            $result[$key] = $leads;
        } elseif ( in_array( $key, array( 'enabled', 'links', 'position' ), true ) ) {
            if ( ! is_array( $value ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            if ( $strict && array_diff( array_keys( $value ), array_keys( $default ) ) ) return netchat_invalid_config( $key );
            foreach ( $default as $sub => $fallback ) {
                if ( ! array_key_exists( $sub, $value ) ) { if ( $strict ) return netchat_invalid_config( $key . '.' . $sub ); continue; }
                $item = $value[$sub];
                if ( 'enabled' === $key ) {
                    if ( ! in_array( $item, array( 0, 1, '0', '1', false, true ), true ) ) { if ( $strict ) return netchat_invalid_config( $key . '.' . $sub ); continue; }
                    $result[$key][$sub] = (int) $item;
                } elseif ( 'links' === $key ) {
                    if ( ! is_string( $item ) ) { if ( $strict ) return netchat_invalid_config( $key . '.' . $sub ); continue; }
                    $normalized = netchat_validate_url( $item, $sub );
                    if ( $strict && trim( $item ) !== '' && $normalized === '' ) return netchat_invalid_config( $key . '.' . $sub );
                    $result[$key][$sub] = $normalized !== '' ? $normalized : trim( $item );
                } else {
                    if ( '' === $item ) { $result[$key][$sub] = ''; continue; }
                    if ( ! ( ( is_int( $item ) || is_string( $item ) ) && preg_match( '/^\d+$/', (string) $item ) && (int) $item <= 10000 ) ) { if ( $strict ) return netchat_invalid_config( $key . '.' . $sub ); continue; }
                    $result[$key][$sub] = (string) (int) $item;
                }
            }
        } elseif ( in_array( $key, array( 'desktop', 'mobile', 'hint', 'hint_mobile' ), true ) ) {
            if ( ! in_array( $value, array( 0, 1, '0', '1', false, true ), true ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $result[$key] = (int) $value;
        } elseif ( in_array( $key, array( 'size', 'animation', 'hint_delay', 'button_delay', 'button_scroll_percent', 'hint_scroll_percent' ), true ) ) {
            $bounds = array( 'size'=>array(40,80), 'animation'=>array(0,1000), 'hint_delay'=>array(0,600), 'button_delay'=>array(0,600), 'button_scroll_percent'=>array(0,100), 'hint_scroll_percent'=>array(0,100) );
            if ( ! ( ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^\d+$/', (string) $value ) ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $number = (int) $value;
            if ( $strict && ( $number < $bounds[$key][0] || $number > $bounds[$key][1] ) ) return netchat_invalid_config( $key );
            $result[$key] = max( $bounds[$key][0], min( $bounds[$key][1], $number ) );
        } elseif ( 'hint_text' === $key ) {
            if ( ! is_string( $value ) || strlen( $value ) > 4000 ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $result[$key] = wp_kses_post( $value );
        } elseif ( 'color' === $key ) {
            if ( ! is_string( $value ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $result[$key] = strtolower( $value );
        } elseif ( in_array( $key, array('form_presentation','form_drawer_side'), true ) ) {
            $allowed = $key === 'form_presentation' ? array('modal','drawer') : array('left','right');
            if (!in_array($value,$allowed,true)) { if($strict)return netchat_invalid_config($key);continue; }
            $result[$key]=$value;
        } elseif ( 'launcher_mode' === $key ) {
            if(!in_array($value,array('form','menu'),true)){if($strict)return netchat_invalid_config($key);continue;}
            $result[$key]=$value;
        } elseif ( 'lang' === $key ) {
            if ( ! in_array( $value, array( 'ru', 'en' ), true ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $result[$key] = $value;
        } elseif ( 'order' === $key ) {
            if ( ! is_string( $value ) ) { if ( $strict ) return netchat_invalid_config( $key ); continue; }
            $order = array_map( 'trim', explode( ',', $value ) ); $channels = array_keys( $defaults['enabled'] );
            if ( $strict && ( count( $order ) !== count( $channels ) || count( array_unique( $order ) ) !== count( $channels ) || array_diff( $order, $channels ) ) ) return netchat_invalid_config( $key );
            $result[$key] = implode( ',', array_values( array_unique( array_merge( array_intersect( $order, $channels ), $channels ) ) ) );
        }
    }
    return $result;
}
