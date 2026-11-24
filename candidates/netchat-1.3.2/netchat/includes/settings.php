<?php
/** Fresh widget settings. No previous option, file, or schema is imported. */
if (!defined('ABSPATH')) exit;
function netchat_defaults() {
 $keys=array('telegram','whatsapp','messenger','max','vk','viber','email','phone');
 return array('enabled'=>array_fill_keys($keys,0),'links'=>array_fill_keys($keys,''),'position'=>array('top'=>'','right'=>'20','bottom'=>'30','left'=>''),'size'=>56,'animation'=>300,'desktop'=>1,'mobile'=>1,'hint'=>0,'hint_text'=>'','hint_delay'=>2,'hint_mobile'=>0,'button_delay'=>0,'button_scroll_percent'=>0,'hint_scroll_percent'=>0,'order'=>implode(',',$keys),'color'=>'#2563eb','lang'=>'ru','launcher_mode'=>'channels');
}
function netchat_error($code,$en,$ru,$status=400) {
 $raw=get_option('netchat_settings_v3',null);$en_lang=is_array($raw)&&isset($raw['settings'])&&is_array($raw['settings'])&&isset($raw['settings']['lang'])&&$raw['settings']['lang']==='en';
 return new WP_Error($code,$en_lang?$en:$ru,array('status'=>$status));
}
function netchat_invalid_setting($key) {return netchat_error('netchat_invalid_setting','Invalid widget setting: '.$key,'Некорректная настройка виджета: '.$key,400);}
function netchat_invalidate_settings(){foreach(array('netchat_settings_v3','alloptions','notoptions')as$key)wp_cache_delete($key,'options');}
function netchat_normalize_config($input,$strict=true) {
 $defaults=netchat_defaults();if(!is_array($input))return $strict?netchat_invalid_setting('document'):$defaults;
 if($strict && (array_diff(array_keys($input),array_keys($defaults)) || array_diff(array_diff(array_keys($defaults),array_keys($input)),array('launcher_mode'))))return netchat_invalid_setting('fields');
 $out=$defaults;
 foreach($defaults as $key=>$fallback){
  if(!array_key_exists($key,$input))continue;$v=$input[$key];
  if(in_array($key,array('enabled','links','position'),true)){
   if(!is_array($v)||array_diff(array_keys($v),array_keys($fallback))||($strict&&array_diff(array_keys($fallback),array_keys($v)))){if($strict)return netchat_invalid_setting($key);continue;}
   foreach($fallback as $sub=>$def){if(!array_key_exists($sub,$v))continue;$x=$v[$sub];
    if($key==='enabled'){if(!in_array($x,array(0,1,'0','1',false,true),true)){if($strict)return netchat_invalid_setting($key.'.'.$sub);continue;}$out[$key][$sub]=(int)$x;}
    elseif($key==='links'){if(!is_string($x)){if($strict)return netchat_invalid_setting($key.'.'.$sub);continue;}$u=netchat_validate_url($x,$sub);if(trim($x)!==''&&$u===''){if($strict)return netchat_invalid_setting($key.'.'.$sub);continue;}$out[$key][$sub]=$u;}
    else{if($x===''){$out[$key][$sub]='';continue;}if(!(is_int($x)||is_string($x))||!preg_match('/^\d+$/D',(string)$x)||(int)$x>10000){if($strict)return netchat_invalid_setting($key.'.'.$sub);continue;}$out[$key][$sub]=(string)(int)$x;}
   }
  }elseif(in_array($key,array('desktop','mobile','hint','hint_mobile'),true)){
   if(!in_array($v,array(0,1,'0','1',false,true),true)){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=(int)$v;
  }elseif(in_array($key,array('size','animation','hint_delay','button_delay','button_scroll_percent','hint_scroll_percent'),true)){
   $limits=array('size'=>array(40,80),'animation'=>array(0,1000),'hint_delay'=>array(0,600),'button_delay'=>array(0,600),'button_scroll_percent'=>array(0,100),'hint_scroll_percent'=>array(0,100));
   if(!(is_int($v)||is_string($v))||!preg_match('/^\d+$/D',(string)$v)||(int)$v<$limits[$key][0]||(int)$v>$limits[$key][1]){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=(int)$v;
  }elseif($key==='hint_text'){
   if(!is_string($v)||strlen($v)>4000){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=wp_kses_post($v);
  }elseif($key==='color'){
   if(!is_string($v)||!preg_match('/^#[a-f0-9]{6}$/iD',$v)){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=strtolower($v);
  }elseif($key==='lang'){
   if(!in_array($v,array('ru','en'),true)){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=$v;
  }elseif($key==='launcher_mode'){
   if(!in_array($v,array('channels','form'),true)){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=$v;
  }elseif($key==='order'){
   if(!is_string($v)){if($strict)return netchat_invalid_setting($key);continue;}$order=array_map('trim',explode(',',$v));$keys=array_keys($defaults['enabled']);
   if(count($order)!==8||count(array_unique($order))!==8||array_diff($order,$keys)){if($strict)return netchat_invalid_setting($key);continue;}$out[$key]=implode(',',$order);
  }
 }
 return $out;
}
function netchat_read_settings() {
 $value=get_option('netchat_settings_v3',null);
 if($value===null)return array('schema_version'=>1,'revision'=>0,'settings'=>netchat_defaults());
 if(!is_array($value)||array_diff(array_keys($value),array('schema_version','revision','settings'))||!isset($value['schema_version'],$value['revision'],$value['settings'])||$value['schema_version']!==1||!is_int($value['revision'])||$value['revision']<1||$value['revision']>=PHP_INT_MAX)return netchat_error('netchat_invalid_storage','Unsupported widget settings. No data was changed.','Неподдерживаемые настройки виджета. Данные не изменены.',409);
 $valid=netchat_normalize_config($value['settings']);if(is_wp_error($valid))return $valid;
 return $value;
}
function netchat_get_config() {$state=netchat_read_settings();return is_wp_error($state)?netchat_defaults():netchat_normalize_config($state['settings']);}
function netchat_save_settings($input,$revision,$nonce) {
 if(!current_user_can('manage_options'))return netchat_error('netchat_forbidden','Administrator permission required.','Требуются права администратора.',403);
 if(!is_string($nonce)||!wp_verify_nonce($nonce,'netchat_save_settings'))return netchat_error('netchat_nonce','Invalid nonce. Reload the page.','Проверка безопасности не пройдена. Обновите страницу.',403);
 if(!(is_int($revision)||is_string($revision))||!preg_match('/^\d+$/D',(string)$revision))return netchat_invalid_setting('revision');
 $current=netchat_read_settings();if(is_wp_error($current))return $current;
 if((int)$revision!==$current['revision'])return netchat_error('netchat_conflict','Settings changed. Reload before saving.','Настройки изменились. Обновите страницу перед сохранением.',409);
 $settings=netchat_normalize_config($input);if(is_wp_error($settings))return $settings;
 $next=array('schema_version'=>1,'revision'=>$current['revision']+1,'settings'=>$settings);
 global $wpdb;
 netchat_invalidate_settings();
 if($current['revision']===0){
  $ok=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_name=option_name",'netchat_settings_v3',maybe_serialize($next)));
 }else{
  $ok=$wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",maybe_serialize($next),'netchat_settings_v3',maybe_serialize($current)));
 }
 netchat_invalidate_settings();
 if($ok!==1)return netchat_error($ok===false?'netchat_storage_failed':'netchat_conflict','Settings were not saved. Reload and retry.','Настройки не сохранены. Обновите страницу и повторите.', $ok===false?503:409);
 $stored=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'netchat_settings_v3'));
 if($stored!==maybe_serialize($next))return netchat_error('netchat_conflict','Settings changed during saving. Reload the page.','Настройки изменились при сохранении. Обновите страницу.',409);
 return $next;
}
function nc_t($en,$ru) { $c=netchat_get_config();return $c['lang']==='ru'?$ru:$en; }
function netchat_text($key){$c=netchat_get_config();$catalog=require NETCHAT_DIR.'languages/'.$c['lang'].'.php';return isset($catalog[$key])?$catalog[$key]:$key;}
