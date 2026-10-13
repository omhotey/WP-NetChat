<?php
/** Versioned settings for the single contact form. */
if ( ! defined( 'ABSPATH' ) ) exit;

function netchat_leads_defaults() {
    $admin_email = get_option( 'admin_email', '' );
    return array(
        'enabled'=>0, 'title'=>'Оставить заявку', 'button_text'=>'Отправить',
        'success_text'=>'Спасибо! Мы получили вашу заявку.',
        'fields'=>array(
            'name'=>array('enabled'=>1,'required'=>0,'label'=>'Имя','placeholder'=>'Как к вам обращаться'),
            'email'=>array('enabled'=>1,'required'=>1,'label'=>'Email','placeholder'=>'name@example.com'),
            'phone'=>array('enabled'=>0,'required'=>0,'label'=>'Телефон','placeholder'=>'+7 (900) 123-45-67'),
            'company'=>array('enabled'=>0,'required'=>0,'label'=>'Компания','placeholder'=>''),
            'message'=>array('enabled'=>1,'required'=>1,'label'=>'Сообщение','placeholder'=>'Опишите задачу и желаемый результат'),
        ),
        'consent'=>array('enabled'=>0,'required'=>1,'text'=>'Согласен с условиями обработки персональных данных','url'=>'','version'=>'1'),
        'email'=>array('enabled'=>1,'recipients'=>is_string($admin_email)&&is_email($admin_email)?array($admin_email):array(),'include_message'=>0),
        'retention_days'=>180, 'spam_retention_days'=>30,
    );
}

function netchat_leads_config() { return netchat_get_config()['leads']; }

function netchat_leads_strlen( $value ) {
    if ( function_exists('mb_strlen') ) return mb_strlen($value,'UTF-8');
    return preg_match_all('/./us', $value, $matches);
}

function netchat_leads_normalize_settings( $input, $strict = true ) {
    $defaults = netchat_leads_defaults();
    if ( ! is_array( $input ) ) return $strict ? netchat_invalid_config( 'leads' ) : $defaults;
    $output = $defaults;
    if ( $strict && ( array_diff( array_keys($input), array_keys($defaults) ) || array_diff( array_keys($defaults), array_keys($input) ) ) ) return netchat_invalid_config( 'leads' );
    foreach ( $defaults as $key => $default ) {
        if ( ! array_key_exists( $key, $input ) ) continue;
        $value = $input[$key];
        if ( in_array( $key, array('enabled','retention_days','spam_retention_days'), true ) ) {
            $minimum = 'enabled' === $key ? 0 : 1; $maximum = 'enabled' === $key ? 1 : 3650;
            if ( ! ( (is_int($value)||is_string($value)||('enabled'===$key && is_bool($value))) && preg_match('/^\d+$/',(string)(int)$value) && (is_bool($value)||preg_match('/^\d+$/',(string)$value)) && (int)$value >= $minimum && (int)$value <= $maximum ) ) {
                if ($strict) return netchat_invalid_config('leads.'.$key); continue;
            }
            $output[$key] = (int) $value;
        } elseif ( in_array( $key, array('title','button_text','success_text'), true ) ) {
            $maximum = 'success_text' === $key ? 2000 : 190;
            if ( !is_string($value) || '' === trim($value) || netchat_leads_strlen($value) > $maximum ) { if ($strict) return netchat_invalid_config('leads.'.$key); continue; }
            $output[$key] = sanitize_text_field($value);
        } elseif ( 'fields' === $key ) {
            if ( !is_array($value) || ($strict && (array_diff(array_keys($value),array_keys($default)) || array_diff(array_keys($default),array_keys($value)))) ) { if ($strict) return netchat_invalid_config('leads.fields'); continue; }
            foreach ( $default as $field => $settings ) {
                if (!isset($value[$field])) continue;
                if (!is_array($value[$field]) || ($strict && (array_diff(array_keys($value[$field]),array_keys($settings)) || array_diff(array_keys($settings),array_keys($value[$field]))))) { if($strict)return netchat_invalid_config('leads.fields.'.$field); continue; }
                foreach ($settings as $name=>$fallback) {
                    if (!array_key_exists($name,$value[$field])) continue;
                    $item = $value[$field][$name];
                    if (in_array($name,array('enabled','required'),true)) {
                        if (!in_array($item,array(0,1,'0','1',false,true),true)) { if($strict)return netchat_invalid_config('leads.fields.'.$field.'.'.$name); continue; }
                        $output['fields'][$field][$name]=(int)$item;
                    } else {
                        if (!is_string($item) || netchat_leads_strlen($item)>190 || ('label'===$name && ''===trim($item))) { if($strict)return netchat_invalid_config('leads.fields.'.$field.'.'.$name); continue; }
                        $output['fields'][$field][$name]=sanitize_text_field($item);
                    }
                }
            }
        } elseif ( 'consent' === $key || 'email' === $key ) {
            if (!is_array($value) || ($strict && (array_diff(array_keys($value),array_keys($default)) || array_diff(array_keys($default),array_keys($value))))) { if($strict)return netchat_invalid_config('leads.'.$key); continue; }
            foreach ($default as $name=>$fallback) {
                if(!array_key_exists($name,$value))continue;
                $item=$value[$name];
                if(in_array($name,array('enabled','required','include_message'),true)) {
                    if(!in_array($item,array(0,1,'0','1',false,true),true)) {if($strict)return netchat_invalid_config('leads.'.$key.'.'.$name);continue;}
                    $output[$key][$name]=(int)$item;
                } elseif('recipients'===$name) {
                    if(!is_array($item)||count($item)>5) {if($strict)return netchat_invalid_config('leads.email.recipients');continue;}
                    $recipients=array();
                    foreach($item as $email) { if(!is_string($email)||!is_email($email)||preg_match('/[\r\n]/',$email)) {if($strict)return netchat_invalid_config('leads.email.recipients');continue;} $recipients[]=$email; }
                    $output[$key][$name]=array_values(array_unique($recipients));
                } elseif('url'===$name) {
                    if(!is_string($item)||strlen($item)>2048||(''!==$item && (!filter_var($item,FILTER_VALIDATE_URL)||!in_array(strtolower(parse_url($item,PHP_URL_SCHEME)),array('http','https'),true)))) {if($strict)return netchat_invalid_config('leads.consent.url');continue;}
                    $output[$key][$name]=esc_url_raw($item);
                } else {
                    $maximum='text'===$name?2000:190;
                    if(!is_string($item)||netchat_leads_strlen($item)>$maximum||''===trim($item)) {if($strict)return netchat_invalid_config('leads.consent.'.$name);continue;}
                    $output[$key][$name]=sanitize_text_field($item);
                }
            }
        }
    }
    // Sanitization can turn nonempty markup into an empty string; never persist it.
    foreach (array('title','button_text','success_text') as $key) if ('' === trim($output[$key])) return netchat_invalid_config('leads.'.$key);
    foreach ($output['fields'] as $field=>$settings) if ('' === trim($settings['label'])) return netchat_invalid_config('leads.fields.'.$field.'.label');
    foreach (array('text','version') as $key) if ('' === trim($output['consent'][$key])) return netchat_invalid_config('leads.consent.'.$key);
    if($output['enabled'] && (!$output['fields']['email']['enabled'] && !$output['fields']['phone']['enabled'])) return netchat_invalid_config('leads.fields.contact');
    if($output['enabled'] && $output['email']['enabled'] && !$output['email']['recipients']) return netchat_invalid_config('leads.email.recipients');
    return $output;
}
