<?php
/** Adapter for installed service presets. Reuses the existing private lead table. */
if ( ! defined( 'ABSPATH' ) ) exit;

function netchat_service_validation_error( $fields ) {
    return new WP_Error( 'netchat_leads_validation', 'Проверьте заполнение формы', array( 'status' => 400, 'fields' => $fields ) );
}

/** Preserve literal text; rendering code is responsible for escaping it. */
function netchat_service_plain_text( $value, $max, $path, &$errors, $min = 0 ) {
    if ( ! is_string( $value ) || ! preg_match( '//u', $value ) || preg_match( '/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $value ) ) {
        $errors[$path] = 'Некорректное значение';
        return '';
    }
    $value = trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
    $length = netchat_leads_strlen( $value );
    if ( $length > $max ) $errors[$path] = 'Не больше ' . $max . ' символов';
    elseif ( $length < $min ) $errors[$path] = 10 === $min && $length > 0 ? 'Добавьте немного контекста: не менее 10 символов' : 'Заполните это поле';
    return $value;
}

function netchat_service_url_valid( $value ) {
    $parts = wp_parse_url( $value );
    return ! preg_match( '/[\x00-\x20\x7f]/', $value ) && is_array( $parts ) && isset( $parts['scheme'], $parts['host'] ) && '' !== $parts['host'] && in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
}

function netchat_service_field_active( $field, $answers ) {
    if ( empty( $field['condition'] ) ) return true;
    $condition = $field['condition'];
    $value = $answers[$condition['key']] ?? null;
    return is_array( $value ) ? (bool) array_intersect( $value, $condition['values'] ) : in_array( $value, $condition['values'], true );
}

function netchat_service_answers( $input, $schema, &$errors ) {
    if ( ! is_array( $input ) ) { $errors['answers'] = 'Некорректное значение'; return array(); }
    $known = array();
    foreach ( $schema['fields'] as $field ) $known[$field['key']] = true;
    foreach ( array_diff( array_keys( $input ), array_keys( $known ) ) as $key ) $errors['answers.' . $key] = 'Неизвестное поле';
    $answers = array();
    // Validate controlling answers first, independently of the presentation order.
    $ordered=array();$pending=$schema['fields'];$known_keys=array();
    while($pending){$progress=false;foreach($pending as$i=>$field){if(empty($field['condition'])||isset($known_keys[$field['condition']['key']])){$ordered[]=$field;$known_keys[$field['key']]=true;unset($pending[$i]);$progress=true;}}if(!$progress){$errors['answers']='Некорректная зависимость вопросов';break;}}
    foreach ( $ordered as $field ) {
        if ( ! netchat_service_field_active( $field, $answers ) ) continue;
        $key = $field['key']; $path = 'answers.' . $key; $required = ! empty( $field['required'] );
        $raw = array_key_exists( $key, $input ) ? $input[$key] : ( 'multi' === $field['type'] ? array() : '' );
        $type = $field['type'];
        if ( 'multi' === $type ) {
            if ( ! is_array( $raw ) || ( count( $raw ) && array_keys( $raw ) !== range( 0, count( $raw ) - 1 ) ) ) { $errors[$path] = 'Выберите вариант из списка'; continue; }
            $values = array();
            foreach ( $raw as $value ) {
                if ( ! is_string( $value ) || ! array_key_exists( $value, $field['options'] ) ) { $errors[$path] = 'Выберите вариант из списка'; continue; }
                $values[$value] = true;
            }
            if ( isset( $values['unknown'] ) && count( $values ) > 1 ) $errors[$path] = 'Выберите «Пока не знаю» или конкретные варианты';
            $ordered = array_values( array_intersect( array_keys( $field['options'] ), array_keys( $values ) ) );
            if ( $required && ! $ordered ) $errors[$path] = 'Выберите хотя бы один вариант';
            if ( $ordered ) $answers[$key] = $ordered;
        } elseif ( 'radio' === $type ) {
            if ( '' === $raw && ! $required ) continue;
            if ( '' === $raw ) $errors[$path] = 'Выберите один вариант';
            elseif ( ! is_string( $raw ) || ! array_key_exists( $raw, $field['options'] ) ) $errors[$path] = 'Выберите вариант из списка';
            else $answers[$key] = $raw;
        } else {
            $max = 'text' === $type ? 2000 : ( 'url' === $type ? 500 : 120 );
            $min = $required ? ( 'text' === $type ? 10 : 1 ) : 0;
            $value = netchat_service_plain_text( $raw, $max, $path, $errors, $min );
            if ( '' === $value ) continue;
            if ( 'url' === $type && ! netchat_service_url_valid( $value ) ) $errors[$path] = 'Укажите полную ссылку, начинающуюся с http:// или https://';
            $answers[$key] = $value;
        }
    }
    return $answers;
}

function netchat_service_contact( $input, &$errors ) {
    if ( ! is_array( $input ) ) { $errors['contact'] = 'Некорректное значение'; return array(); }
    foreach ( array_diff( array_keys( $input ), array( 'name', 'company', 'channel', 'value', 'note' ) ) as $key ) $errors['contact.' . $key] = 'Неизвестное поле';
    $contact = array( 'name' => netchat_service_plain_text( array_key_exists( 'name', $input ) ? $input['name'] : '', 80, 'contact.name', $errors, 1 ) );
    foreach ( array( 'company' => 120, 'note' => 200 ) as $key => $max ) {
        $value = netchat_service_plain_text( array_key_exists( $key, $input ) ? $input[$key] : '', $max, 'contact.' . $key, $errors );
        if ( '' !== $value ) $contact[$key] = $value;
    }
    $channel = $input['channel'] ?? '';
    if ( ! in_array( $channel, array( 'telegram', 'email', 'phone' ), true ) ) $errors['contact.channel'] = '' === $channel ? 'Выберите один вариант' : 'Выберите вариант из списка';
    $contact['channel'] = is_string( $channel ) ? $channel : '';
    $value = netchat_service_plain_text( array_key_exists( 'value', $input ) ? $input['value'] : '', 254, 'contact.value', $errors, 1 );
    if ( 'telegram' === $channel ) {
        $username = $value;
        if ( preg_match( '/^https:\/\/t\.me\/([A-Za-z][A-Za-z0-9_]{4,31})$/', $value, $match ) ) $username = $match[1];
        elseif ( isset( $username[0] ) && '@' === $username[0] ) $username = substr( $username, 1 );
        if ( ! preg_match( '/^[A-Za-z][A-Za-z0-9_]{4,31}$/', $username ) ) $errors['contact.value'] = 'Укажите Telegram в формате @username или https://t.me/username';
        else $value = '@' . $username;
    } elseif ( 'email' === $channel ) {
        if ( ! is_email( $value ) || preg_match( '/[\x00-\x20\x7f]/', $value ) ) $errors['contact.value'] = 'Проверьте адрес email';
        else { $at = strrpos( $value, '@' ); $value = substr( $value, 0, $at + 1 ) . strtolower( substr( $value, $at + 1 ) ); }
    } elseif ( 'phone' === $channel ) {
        $number = preg_replace( '/[ ()-]/', '', $value );
        if ( ! preg_match( '/^\+[1-9][0-9]{7,14}$/', $number ) ) $errors['contact.value'] = 'Укажите телефон с кодом страны, например +7 999 123-45-67';
        else $value = $number;
    }
    $contact['value'] = $value;
    return $contact;
}

function netchat_service_context( $input, &$errors ) {
    if ( ! is_array( $input ) ) { $errors['context'] = 'Некорректное значение'; return array(); }
    $allowed = array( 'page_id', 'page_url', 'origin_service', 'cta_position', 'entrypoint', 'referrer_origin', 'utm', 'context_token' );
    foreach ( array_diff( array_keys( $input ), $allowed ) as $key ) $errors['context.' . $key] = 'Неизвестное поле';
    $url = netchat_service_plain_text( $input['page_url'] ?? '', 500, 'context.page_url', $errors );
    $parts = wp_parse_url( $url ); $home = wp_parse_url( home_url( '/' ) );
    if ( '' === $url || ! netchat_service_url_valid( $url ) || ! $home || ! netchat_service_same_origin( $url ) ) {
        $errors['context.page_url'] = 'Укажите адрес текущей страницы сайта';
        $page_url = '';
    } else $page_url = netchat_leads_source_url( $url );
    if ( isset( $input['page_id'] ) && null !== $input['page_id'] && ( ! is_int( $input['page_id'] ) || $input['page_id'] < 1 ) ) $errors['context.page_id'] = 'Некорректное значение';
    if ( isset( $input['context_token'] ) && ( ! is_string( $input['context_token'] ) || strlen( $input['context_token'] ) > 2048 ) ) $errors['context.context_token'] = 'Некорректное значение';
    $page_id = null;
    if ( ! isset( $errors['context.page_url'] ) && ! isset( $errors['context.page_id'] ) && ! isset( $errors['context.context_token'] ) && function_exists( 'netchat_service_context_page' ) ) $page_id = netchat_service_context_page( $input );
    if ( ! is_int( $page_id ) || $page_id < 1 ) $page_id = null;
    $origin = $input['origin_service'] ?? null;
    $codes = array_column(netchat_service_active_forms(),'service_code');
    if ( null !== $origin && ! in_array( $origin, $codes, true ) ) $errors['context.origin_service'] = 'Выберите вариант из списка';
    $cta = $input['cta_position'] ?? 'other'; $entry = $input['entrypoint'] ?? 'service_cta';
    if ( ! in_array( $cta, array( 'hero', 'middle', 'bottom', 'widget', 'other' ), true ) ) $errors['context.cta_position'] = 'Выберите вариант из списка';
    if ( ! in_array( $entry, array( 'service_cta', 'widget', 'service_selector' ), true ) ) $errors['context.entrypoint'] = 'Выберите вариант из списка';
    $referrer = netchat_service_plain_text( $input['referrer_origin'] ?? '', 255, 'context.referrer_origin', $errors );
    if ( '' !== $referrer && ! netchat_service_url_valid( $referrer ) ) $errors['context.referrer_origin'] = 'Некорректное значение';
    $referrer = '' !== $referrer ? netchat_leads_source_url( $referrer, true ) : null;
    $utm = $input['utm'] ?? array(); $safe_utm = array();
    if ( ! is_array( $utm ) ) $errors['context.utm'] = 'Некорректное значение';
    else foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ) as $key ) {
        if ( ! array_key_exists( $key, $utm ) ) continue;
        $value = netchat_service_plain_text( $utm[$key], 100, 'context.utm.' . $key, $errors );
        if ( '' !== $value ) $safe_utm[$key] = $value;
    }
    return array( 'page_id' => $page_id, 'page_url' => $page_url, 'origin_service' => $origin, 'cta_position' => $cta, 'entrypoint' => $entry, 'referrer_origin' => $referrer, 'utm' => $safe_utm );
}

function netchat_service_validate( $input, $settings ) {
    if ( ! is_array( $input ) ) return netchat_service_validation_error( array( 'form' => 'Некорректное значение' ) );
    $errors = array();
    foreach ( array_diff( array_keys( $input ), array( 'service_code', 'form_id', 'schema_version', 'answers', 'contact', 'consent', 'context' ) ) as $key ) $errors[$key] = 'Неизвестное поле';
    $id = $input['form_id'] ?? null;
    $schema = is_string( $id ) ? netchat_service_form( $id ) : null;
    if ( ! $schema ) return netchat_service_validation_error( array( 'form_id' => 'Эта форма сейчас недоступна. Используйте другой способ связи' ) );
    if ( ( $input['service_code'] ?? null ) !== $schema['service_code'] ) $errors['service_code'] = 'Выберите вариант из списка';
    if ( ! isset( $input['schema_version'] ) || ! is_int( $input['schema_version'] ) || $input['schema_version'] !== $schema['schema_version'] ) $errors['schema_version'] = 'Эта форма сейчас недоступна. Используйте другой способ связи';
    $ready = netchat_service_ready();
    if ( is_wp_error( $ready ) ) return $ready;
    if ( true !== $ready ) return new WP_Error( 'netchat_leads_disabled', 'Эта форма сейчас недоступна. Используйте другой способ связи', array( 'status' => 403 ) );
    $answers = netchat_service_answers( $input['answers'] ?? null, $schema, $errors );
    $contact = netchat_service_contact( $input['contact'] ?? null, $errors );
    $context = netchat_service_context( $input['context'] ?? array(), $errors );
    $consent = $input['consent'] ?? null;
    if ( ! is_array( $consent ) || array_diff( array_keys( $consent ), array( 'accepted' ) ) ) $errors['consent'] = 'Некорректное значение';
    if ( ! is_array( $consent ) || true !== ( $consent['accepted'] ?? null ) ) $errors['consent.accepted'] = 'Подтвердите согласие на обработку персональных данных';
    if ( $errors ) return netchat_service_validation_error( $errors );
    $docs = netchat_service_forms_config()['documents'];
    $accepted = netchat_leads_now();
    $snapshot = array( 'title' => $schema['title'], 'fields' => array(), 'contact' => array( 'name' => 'Как к вам обращаться?', 'company' => 'Компания или проект', 'channel' => 'Где вам удобно получить ответ?', 'value' => array( 'telegram' => 'Ваш Telegram', 'email' => 'Ваш email', 'phone' => 'Ваш телефон' )[$contact['channel']], 'note' => 'Когда и как удобнее связаться?' ), 'consent' => 'Я даю согласие на обработку моих персональных данных для ответа на обращение' );
    $message = array();
    foreach ( $schema['fields'] as $field ) {
        if ( ! array_key_exists( $field['key'], $answers ) ) continue;
        $value = $answers[$field['key']];
        $selected = array();
        if ( in_array( $field['type'], array( 'radio', 'multi' ), true ) ) foreach ( is_array( $value ) ? $value : array( $value ) as $choice ) $selected[$choice] = $field['options'][$choice];
        $snapshot['fields'][$field['key']] = array( 'label' => $field['label'], 'type' => $field['type'], 'options' => $selected );
        $message[] = $field['label'] . ': ' . ( $selected ? implode( ', ', array_values( $selected ) ) : $value );
    }
    $payload = array( 'service_code' => $schema['service_code'], 'form_id' => $id, 'schema_version' => $schema['schema_version'], 'answers' => $answers, 'contact' => $contact, 'consent' => array_merge( array( 'accepted' => true, 'accepted_at' => $accepted ), $docs ), 'context' => $context, 'snapshot' => $snapshot );
    return array( 'form_key' => $id, 'name' => $contact['name'], 'email' => 'email' === $contact['channel'] ? $contact['value'] : '', 'phone' => 'phone' === $contact['channel'] ? $contact['value'] : '', 'company' => $contact['company'] ?? '', 'message' => implode( "\n\n", $message ), 'context' => $schema['service_code'], 'page_url' => $context['page_url'], 'page_title' => '', 'referrer' => $context['referrer_origin'] ?? '', 'utm' => wp_json_encode( $context['utm'] ), 'consent_presented' => 1, 'consent_accepted' => 1, 'consent_version' => $docs['text_version'], 'consent_at' => $accepted, 'submission_json' => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
}

/** Fingerprint only semantic client values; document/schema labels and acceptance time are snapshots. */
function netchat_service_fingerprint_order( $value ) {
    if ( ! is_array( $value ) ) return $value;
    if ( $value && array_keys( $value ) === range( 0, count( $value ) - 1 ) ) {
        // Multiple selections are a set; stored presentation order still follows the schema.
        sort( $value, SORT_STRING );
        return $value;
    }
    ksort( $value, SORT_STRING );
    foreach ( $value as $key => $item ) $value[$key] = netchat_service_fingerprint_order( $item );
    return $value;
}

function netchat_service_fingerprint( $row ) {
    $data = json_decode( $row['submission_json'], true );
    $client = array( 'service_code' => $data['service_code'], 'form_id' => $data['form_id'], 'schema_version' => $data['schema_version'], 'answers' => $data['answers'], 'contact' => $data['contact'], 'consent' => array( 'accepted' => true ), 'context' => $data['context'] );
    return hash_hmac( 'sha256', wp_json_encode( netchat_service_fingerprint_order( $client ) ), wp_salt( 'auth' ) );
}
