<?php
/** Public channel extension API v1. Pure rendering; no storage side effects. */
if (!defined('ABSPATH')) exit;
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


function netchat_native_channels() {
 return array('telegram'=>'Telegram','whatsapp'=>'WhatsApp','messenger'=>'Facebook Messenger','max'=>'MAX','vk'=>nc_t('VK','ВКонтакте'),'viber'=>'Viber','email'=>'Email','phone'=>nc_t('Phone','Телефон'));
}
function netchat_safe_link($url, $channel = '') {
 if (!is_string($url) || strlen($url)>2048 || preg_match('/[\x00-\x20\x7f\\\\]|%(?:00|0a|0d)/i',$url)) return '';
 if ($channel !== '') return array_key_exists($channel,netchat_native_channels()) ? netchat_validate_url($url,$channel) : '';
 $p=parse_url($url);
 if (!$p || !isset($p['scheme'],$p['host']) || !in_array(strtolower($p['scheme']),array('https','http'),true) || isset($p['user']) || isset($p['pass']) || !filter_var($url,FILTER_VALIDATE_URL)) return '';
 return $url;
}
function netchat_sanitize_icon($svg) {
 if (!is_string($svg) || strlen($svg)>20000 || !preg_match('/^\s*<svg\b[^>]*>[\s\S]*<\/svg>\s*$/i',$svg)) return '';
 if (preg_match('/<\s*(?:script|foreignObject|image|use|animate|set|style|iframe)\b|\bon[a-z]+\s*=|\b(?:href|style|src)\s*=|url\s*\(|javascript:|data:|<\?|<!|&/i',$svg)) return '';
 $attrs=array('d'=>true,'fill'=>true,'stroke'=>true,'stroke-width'=>true,'stroke-linecap'=>true,'stroke-linejoin'=>true,'fill-rule'=>true,'clip-rule'=>true,'transform'=>true,'cx'=>true,'cy'=>true,'r'=>true,'rx'=>true,'ry'=>true,'x'=>true,'y'=>true,'x1'=>true,'y1'=>true,'x2'=>true,'y2'=>true,'width'=>true,'height'=>true,'points'=>true,'opacity'=>true,'fill-opacity'=>true,'stroke-opacity'=>true);
 $allowed=array();foreach(array('svg','g','path','circle','ellipse','rect','line','polyline','polygon') as $tag)$allowed[$tag]=$attrs;
 $allowed['svg']['viewbox']=true;$allowed['svg']['aria-hidden']=true;$allowed['svg']['focusable']=true;
 return wp_kses($svg,$allowed,array());
}
/** Enabled native contacts for an optional form plugin; no extension actions. */
function netchat_get_contacts() {
 $c=netchat_get_config();$native=array();$names=netchat_native_channels();
 foreach(explode(',',$c['order']) as $key){
  if(empty($c['enabled'][$key]))continue;
  $url=netchat_validate_url($c['links'][$key],$key);if($url==='')continue;
  $native[$key]=array('label'=>$names[$key],'icon'=>netchat_svg_icon($key),'enabled'=>true,'url'=>$url,'channel'=>$key);
 }
 return $native;
}
function netchat_get_channels() {
 $native=netchat_get_contacts();$filtered=apply_filters('netchat_channels',$native);if(!is_array($filtered))$filtered=$native;
 $result=array();
 foreach($filtered as $key=>$item){
  if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_-]{0,31}$/D',$key)||!is_array($item)||!isset($item['enabled'])||$item['enabled']!==true||!isset($item['label'])||!is_string($item['label']))continue;
  $label=trim(wp_strip_all_tags($item['label']));if($label===''||strlen($label)>240||preg_match('/[\x00-\x1f\x7f]/',$label))continue;
  $has_url=array_key_exists('url',$item);$has_action=array_key_exists('action',$item);if($has_url===$has_action)continue;
  $clean=array('label'=>$label,'icon'=>netchat_sanitize_icon($item['icon']??''),'enabled'=>true);
  if($has_action){if(!is_string($item['action'])||!preg_match('/^[a-z][a-z0-9_-]{0,63}$/D',$item['action']))continue;$clean['action']=$item['action'];}
  else{
   $type=$item['channel']??'';if(!is_string($type))continue;$link=netchat_safe_link($item['url'],$type);if($link==='')continue;
   $clean['url']=$link;$clean['channel']=$type;
  }
  $result[$key]=$clean;
 }
 return $result;
}
