<?php
/** Read-only assertions for candidate functions. Standalone mode uses explicit in-memory WP doubles, not WordPress acceptance. */
$nc_fixture_mode=!defined('ABSPATH');
if($nc_fixture_mode){
 define('ABSPATH','fixture/');
 class WP_Error{public $message;function __construct($code,$message,$data=array()){$this->message=$message;}function get_error_message(){return $this->message;}}
 function is_wp_error($v){return $v instanceof WP_Error;}
 function netchat_storage_error($code,$message,$status=400){return new WP_Error($code,$message,array('status'=>$status));}
 function add_action(){ } function add_shortcode(){ }
 function get_option($key,$default=false){return $key==='admin_email'?'fixture@example.test':$default;}
 function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
 function sanitize_email($v){return $v;}
 function sanitize_text_field($v){return trim(strip_tags($v));}
 function sanitize_textarea_field($v){return trim(strip_tags($v));}
 function wp_kses_post($v){return $v;} function wp_json_encode($v){return json_encode($v);}
 function wp_parse_url($v){return parse_url($v);}
 function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
 function esc_html($v){return esc_attr($v);}
 function esc_url($v,$protocols=null){return $v;} function esc_url_raw($v){return $v;}
 function wp_allowed_protocols(){return array('http','https','mailto','tel');}
 function is_admin(){return false;}
 function netchat_get_config($force=false){return $GLOBALS['nc_fixture_config'];}
 function netchat_save_config($config,$revision){if($revision!==$GLOBALS['nc_fixture_revision'])return new WP_Error('conflict','conflict');$GLOBALS['nc_fixture_config']=$config;$GLOBALS['nc_fixture_revision']++;return true;}
 function netchat_get_revision(){return $GLOBALS['nc_fixture_revision'];}
}
/* LOAD_CANDIDATE */
if(!$nc_fixture_mode){global $wpdb;if(empty($GLOBALS["nc007_isolated_options"])||$GLOBALS["nc007_isolated_options"]!==$wpdb->options||strpos($wpdb->options,"nc007_")!==0)throw new RuntimeException("Requires preloaded candidate and session-owned TEMPORARY options table; never run against shared options.");}
$count=0;
function nc007_check($value,$message){global $count;if(!$value)throw new RuntimeException($message);$count++;}
$base=netchat_defaults();
nc007_check($base['form_presentation']==='modal'&&$base['form_drawer_side']==='left','defaults modal/left');
$legacy=$base;unset($legacy['form_presentation'],$legacy['form_drawer_side']);
$normalized=netchat_normalize_config($legacy,true);
nc007_check(!is_wp_error($normalized)&&$normalized['form_presentation']==='modal'&&$normalized['form_drawer_side']==='left','missing legacy keys');
foreach(array('modal','drawer')as$p)foreach(array('left','right')as$side){
 $input=$base;$input['form_presentation']=$p;$input['form_drawer_side']=$side;$input['button_scroll_percent']=31;$input['hint_scroll_percent']=72;
 $out=netchat_normalize_config($input,true);
 nc007_check(!is_wp_error($out)&&$out===$input,'enum and independent percent roundtrip');
 $doc=array('product'=>'netchat','schema_version'=>1,'type'=>'settings','timestamp'=>'2026-10-15T00:00:00Z','settings'=>$input);
 nc007_check(netchat_transfer_decode(wp_json_encode($doc))['settings']===$input,'new settings transfer roundtrip');
}
foreach(array('form_presentation','form_drawer_side')as$key)foreach(array(null,true,0,1,array(),'','DRAWER','inline','bottom')as$v){
 $bad=$base;$bad[$key]=$v;nc007_check(is_wp_error(netchat_normalize_config($bad,true)),'strict invalid enum');
 $doc=array('product'=>'netchat','schema_version'=>1,'type'=>'settings','timestamp'=>'2026-10-15T00:00:00Z','settings'=>$bad);nc007_check(is_wp_error(netchat_transfer_decode(wp_json_encode($doc))),'transfer rejects invalid enum');
 $out=netchat_normalize_config($bad,false);nc007_check($out[$key]===$base[$key],'read fallback without mutation');
}
$doc=array('product'=>'netchat','schema_version'=>1,'type'=>'settings','timestamp'=>'2026-10-15T00:00:00Z','settings'=>$legacy);
nc007_check(netchat_transfer_decode(wp_json_encode($doc))['settings']===$base,'old transfer receives defaults');
if($nc_fixture_mode){
 $GLOBALS['nc_fixture_config']=$base;$GLOBALS['nc_fixture_config']['form_presentation']='drawer';$GLOBALS['nc_fixture_config']['form_drawer_side']='right';$GLOBALS['nc_fixture_revision']=1;
 $old=$legacy;$old['revision']=1;
 $request=new class($old){private $data;function __construct($d){$this->data=$d;}function get_json_params(){return $this->data;}};
 $result=netchat_rest_save($request);nc007_check(!is_wp_error($result)&&$result['config']['form_presentation']==='drawer'&&$result['config']['form_drawer_side']==='right','REST omission preserves current');
 $GLOBALS['nc_fixture_config']['enabled']['telegram']=1;$GLOBALS['nc_fixture_config']['links']['telegram']='https://t.me/example_test';
 $markup=netchat_contact_alternatives();nc007_check(strpos($markup,'<details')===false&&strpos($markup,'<summary')===false&&strpos($markup,'data-contact-channel="telegram"')!==false,'always expanded validated links');
 $GLOBALS['nc_fixture_config']['enabled']['telegram']=0;nc007_check(netchat_contact_alternatives()==='','no contacts no empty block');
 $GLOBALS['nc_fixture_config']['leads']['enabled']=1;ob_start();netchat_leads_render_form();$markup=ob_get_clean();
 nc007_check(strpos($markup,'data-form-presentation="drawer"')!==false&&strpos($markup,'data-form-drawer-side="right"')!==false,'drawer attributes render');
}

if(!$nc_fixture_mode){
 global $wpdb;
 $settings=array('schema_version'=>2,'revision'=>1,'config'=>$base);
 $settings['config']['form_presentation']='drawer';$settings['config']['form_drawer_side']='right';
 update_option(NETCHAT_SETTINGS_OPTION,$settings,false);netchat_storage_invalidate();
 $request=new WP_REST_Request('POST','/netchat/v1/settings');$old=$legacy;$old['revision']=1;$request->set_body(wp_json_encode($old));$request->set_header('content-type','application/json');
 $result=netchat_rest_save($request);nc007_check(!is_wp_error($result)&&$result['config']['form_presentation']==='drawer'&&$result['config']['form_drawer_side']==='right','real core REST omission preserves current');
 $stale=netchat_rest_save($request);nc007_check(is_wp_error($stale)&&$stale->get_error_code()==='netchat_revision_conflict','real CAS rejects stale revision');
 $read=netchat_storage_read();unset($read['config']['form_presentation'],$read['config']['form_drawer_side']);update_option(NETCHAT_SETTINGS_OPTION,$read,false);netchat_storage_invalidate();
 $sql=$wpdb->prepare("SELECT option_value FROM ".$wpdb->options." WHERE option_name=%s",NETCHAT_SETTINGS_OPTION);
 $raw=$wpdb->get_var($sql);
 $normalized=netchat_get_config();nc007_check($normalized['form_presentation']==='modal'&&$normalized['form_drawer_side']==='left','real legacy read defaults');
 nc007_check($raw===$wpdb->get_var($sql),'legacy read does not rewrite raw SQL');
 $export=netchat_transfer_export('settings');nc007_check(!is_wp_error($export)&&$export['settings']['form_presentation']==='modal'&&$export['settings']['form_drawer_side']==='left','real export canonical new defaults');
}
echo json_encode(array('status'=>'PASS','layer'=>$nc_fixture_mode?'isolated_php_doubles':'read_only_wordpress_functions','assertions'=>$count)),PHP_EOL;
