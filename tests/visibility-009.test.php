<?php
if(defined("ABSPATH"))throw new RuntimeException("Standalone doubles only; WordPress bootstrap forbidden.");
/** Read-only assertions for candidate functions. Standalone mode uses explicit in-memory WP doubles, not WordPress acceptance. */
$nc_fixture_mode=!defined('ABSPATH');
if($nc_fixture_mode){
 define('ABSPATH','fixture/');
 define('NETCHAT_DIR','fixture/');define('NETCHAT_URL','https://visibility.invalid/netchat/');define('NETCHAT_VER','1.2.1');
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
 function esc_attr__($v,$domain){return esc_attr($v);}
 function esc_url($v,$protocols=null){return $v;} function esc_url_raw($v){return $v;}
 function wp_allowed_protocols(){return array('http','https','mailto','tel');}
 function is_admin(){return false;}
 function netchat_get_config($force=false){return $GLOBALS['nc_fixture_config'];}
 function netchat_save_config($config,$revision){if($revision!==$GLOBALS['nc_fixture_revision'])return new WP_Error('conflict','conflict');$GLOBALS['nc009_saves']++;$GLOBALS['nc_fixture_config']=$config;$GLOBALS['nc_fixture_revision']++;return true;}
 function netchat_get_revision(){return $GLOBALS['nc_fixture_revision'];}
}

function netchat_storage_read(){return array('schema_version'=>2,'revision'=>$GLOBALS['nc_fixture_revision'],'config'=>$GLOBALS['nc_fixture_config']);}
function nc009_filemtime($path){return 1;}
function wp_enqueue_style(){ }function wp_enqueue_script(){ }
function wp_localize_script($handle,$name,$value){$GLOBALS['nc009_localized'][$name]=$value;}
/* LOAD_CANDIDATE */
$checks=0;$GLOBALS['nc009_saves']=0;
function nc009_check($ok,$message){global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function nc009_request($config,$revision){$config['revision']=$revision;return new class($config){private $data;function __construct($data){$this->data=$data;}function get_json_params(){return $this->data;}};}
$base=netchat_defaults();$GLOBALS['nc_fixture_config']=$base;$GLOBALS['nc_fixture_revision']=1;
nc009_check($base['hint_mobile']===0&&$base['button_delay']===0&&$base['hint_delay']===2,'new defaults and prior hint value');
foreach(array('button_delay','hint_delay')as$key){
 foreach(array(0,1,30,31,599,600,'0','600')as$value){$input=$base;$input[$key]=$value;$result=netchat_normalize_config($input,true);nc009_check(!is_wp_error($result)&&$result[$key]===(int)$value,'valid seconds '.$key);}
 foreach(array(null,true,false,'',-1,601,1.5,'1.5','NaN','abc','-1',array())as$value){$input=$base;$input[$key]=$value;nc009_check(is_wp_error(netchat_normalize_config($input,true)),'invalid seconds '.$key);$before=$GLOBALS['nc009_saves'];nc009_check(is_wp_error(netchat_rest_save(nc009_request($input,1)))&&$GLOBALS['nc009_saves']===$before,'invalid REST rejected before save '.$key);$doc=array('product'=>'netchat','schema_version'=>1,'type'=>'settings','timestamp'=>'2026-10-20T00:00:00Z','settings'=>$input);nc009_check(is_wp_error(netchat_transfer_decode(wp_json_encode($doc))),'invalid transfer seconds '.$key);}
}
foreach(array(0,1,'0','1',false,true)as$value){$input=$base;$input['hint_mobile']=$value;$out=netchat_normalize_config($input,true);nc009_check(!is_wp_error($out)&&$out['hint_mobile']===(int)$value,'strict toggle canonicalizes supported bool/0/1');}
foreach(array(null,2,-1,'false','yes','',array(),1.5)as$value){$input=$base;$input['hint_mobile']=$value;nc009_check(is_wp_error(netchat_normalize_config($input,true)),'invalid mobile flag');}
$legacy=$base;unset($legacy['hint_mobile'],$legacy['button_delay']);$raw=serialize($legacy);$out=netchat_normalize_config($legacy,true);
nc009_check(!is_wp_error($out)&&$out===$base&&serialize($legacy)===$raw,'legacy defaults in memory, input unchanged');
$old=$legacy;$old['hint_delay']=30;$old['hint_scroll_percent']=83;$old['button_scroll_percent']=17;
$doc=array('product'=>'netchat','schema_version'=>1,'type'=>'settings','timestamp'=>'2026-10-20T00:00:00Z','settings'=>$old);
$out=netchat_transfer_decode(wp_json_encode($doc));nc009_check(!is_wp_error($out)&&$out['settings']['hint_mobile']===0&&$out['settings']['button_delay']===0&&$out['settings']['hint_delay']===30&&$out['settings']['hint_scroll_percent']===83,'old JSON defaults mobile0/button0 and preserves previous values');
$doc['settings']=$base;$doc['settings']['hint_mobile']=false;nc009_check(is_wp_error(netchat_transfer_decode(wp_json_encode($doc))),'JSON requires canonical integer flag, not false');
foreach(array(0,1)as$mobile)foreach(array(0,600)as$button)foreach(array(0,600)as$hint){
 $current=$base;$current['hint_mobile']=$mobile;$current['button_delay']=$button;$current['hint_delay']=$hint;$current['button_scroll_percent']=37;$current['hint_scroll_percent']=91;
 $GLOBALS['nc_fixture_config']=$current;$export=netchat_transfer_export('settings');$out=netchat_transfer_decode(wp_json_encode($export));nc009_check(!is_wp_error($out)&&$out['settings']===$current,'new settings export/import roundtrip');
}
$current=$base;$current['hint_mobile']=1;$current['button_delay']=42;$GLOBALS['nc_fixture_config']=$current;$GLOBALS['nc_fixture_revision']=1;
$result=netchat_rest_save(nc009_request($legacy,1));nc009_check(!is_wp_error($result)&&$result['config']['hint_mobile']===1&&$result['config']['button_delay']===42,'REST omitted keys retain target current values');
$before=$GLOBALS['nc_fixture_config'];$writes=$GLOBALS['nc009_saves'];nc009_check(is_wp_error(netchat_rest_save(nc009_request($base,1)))&&$GLOBALS['nc_fixture_config']===$before&&$GLOBALS['nc009_saves']===$writes,'stale revision conflict does not update fixture state');
$all=$base;$all['hint_mobile']=1;$all['button_delay']=600;$all['hint_delay']=600;$result=netchat_rest_save(nc009_request($all,2));nc009_check(!is_wp_error($result)&&$result['config']===$all,'new REST settings save');
$GLOBALS['nc_fixture_config']=$base;$GLOBALS['nc_fixture_config']['enabled']['telegram']=1;$GLOBALS['nc_fixture_config']['links']['telegram']='https://t.me/example_test';$GLOBALS['nc_fixture_config']['button_delay']=10;$GLOBALS['nc_fixture_config']['hint_delay']=600;$GLOBALS['nc_fixture_config']['hint']=1;$GLOBALS['nc_fixture_config']['hint_text']='Тестовая подсказка';
netchat_enqueue();$localized=$GLOBALS['nc009_localized']['netchat'];nc009_check($localized['buttonDelay']===10000&&$localized['hintDelay']===600000&&$localized['hintMobile']===0,'PHP frontend localization units and flag');
ob_start();netchat_render();$markup=ob_get_clean();nc009_check(preg_match('/id="nc-toggle"[^>]* hidden/',$markup)===1&&strpos($markup,'nc-hint-no-mobile')!==false&&strpos($markup,'id="nc-hint" hidden')!==false,'server initial hidden for delay and mobile CSS class');
eval('function ncf_workspace_active(){return true;}');$GLOBALS['nc009_localized']=array();netchat_enqueue();ob_start();netchat_render();$markup=ob_get_clean();nc009_check($markup===''&&$GLOBALS['nc009_localized']===array(),'NCF008 guard preserved');
echo json_encode(array('status'=>'PASS','layer'=>'standalone_explicit_WP_doubles_no_SQL_no_WordPress','assertions'=>$checks)),PHP_EOL;
