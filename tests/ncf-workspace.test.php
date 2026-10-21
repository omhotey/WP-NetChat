<?php
/** Standalone PHP fixture with explicit WP doubles; never load WordPress or a database.
 * The stdin bundler replaces filemtime with nc_fixture_filemtime (asset metadata only).
 */
if(defined('ABSPATH'))throw new RuntimeException('Standalone fixture only; do not bootstrap WordPress.');
define('ABSPATH','fixture/');define('NETCHAT_DIR',dirname(__DIR__).DIRECTORY_SEPARATOR);define('NETCHAT_URL','https://example.test/netchat/');define('NETCHAT_VER','1.2.1');
$mode=$argv[1]??'absent';
if(!in_array($mode,array('absent','false','true','latechange','latefunction'),true))throw new RuntimeException('Unknown scenario');
$GLOBALS['workspace']=$mode==='true';
function nc_define_marker(){eval('function ncf_workspace_active(){return $GLOBALS["workspace"];}');}
if(in_array($mode,array('false','true','latechange'),true))nc_define_marker();
$GLOBALS['nc_hooks']=array();$GLOBALS['nc_shortcodes']=array();$GLOBALS['nc_effects']=array();$GLOBALS['nc_reads']=0;
function add_action($name,$callback,$priority=10){$GLOBALS['nc_hooks'][$name][$priority][]=$callback;}
function add_shortcode($tag,$callback){$GLOBALS['nc_shortcodes'][$tag]=$callback;}
function is_admin(){return false;}
function nc_fixture_filemtime($path){return 1;}
function netchat_is_active(){return true;}
function netchat_get_config(){$GLOBALS['nc_reads']++;return $GLOBALS['nc_config'];}
function netchat_leads_config(){return netchat_get_config()['leads'];}
function get_option($name,$default=false){$GLOBALS['nc_reads']++;return $name==='netchat_service_forms'?$GLOBALS['nc_forms']:$default;}
function wp_enqueue_style(){ $GLOBALS['nc_effects']['styles'][]=func_get_args(); }
function wp_enqueue_script(){ $GLOBALS['nc_effects']['scripts'][]=func_get_args(); }
function wp_add_inline_style(){ $GLOBALS['nc_effects']['inline'][]=func_get_args(); }
function wp_localize_script($handle,$name,$value){if($name==='netchatServices')$value['context_token']='time-varying-token-normalized';$GLOBALS['nc_effects']['localized'][]=array($handle,$name,$value);}
function nc_t($en,$ru){return $ru;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_html($v){return esc_attr($v);}
function esc_attr__($v,$domain){return esc_attr($v);}
function esc_url($v,$protocols=null){return $v;}
function wp_kses_post($v){return $v;}
function wp_strip_all_tags($v){return strip_tags($v);}
function shortcode_atts($defaults,$values){return array_merge($defaults,$values);}
function wp_allowed_protocols(){return array('https','http','mailto','tel');}
function netchat_messengers(){return array('telegram'=>array('name'=>'Telegram','color'=>'#0088cc'));}
function netchat_validate_url($url,$type){return $url;}
function netchat_svg_icon($key){return '<svg aria-hidden="true"></svg>';}
function rest_url($path){return 'https://example.test/wp-json/'.$path;}
function home_url($path){return 'https://example.test'.$path;}
function is_singular(){return false;}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function netchat_leads_source_url($url){return $url;}
function wp_salt($type){return 'synthetic-fixture-salt';}
$GLOBALS['nc_config']=array(
 'enabled'=>array('telegram'=>1),'links'=>array('telegram'=>'https://t.me/example_test'),
 'position'=>array('top'=>'','left'=>'','right'=>'20','bottom'=>'30'),'order'=>'telegram',
 'size'=>56,'color'=>'#2563eb','animation'=>300,'desktop'=>1,'mobile'=>1,'launcher_mode'=>'menu',
 'button_scroll_percent'=>31,'hint_scroll_percent'=>72,'hint_delay'=>2,'hint'=>1,'hint_text'=>'Синтетическое приветствие',
 'form_presentation'=>'modal','form_drawer_side'=>'left',
 'leads'=>array('enabled'=>1,'title'=>'Синтетическая форма','button_text'=>'Отправить','success_text'=>'Принято','retention_days'=>180,
 'fields'=>array('email'=>array('enabled'=>1,'required'=>1,'label'=>'Email','placeholder'=>'')),
 'consent'=>array('enabled'=>0))
);
foreach(array('name','phone','company','message')as$field)$GLOBALS['nc_config']['leads']['fields'][$field]=array('enabled'=>0);
$GLOBALS['nc_forms']=array('enabled'=>1,'retention_confirmed'=>1,'primary_form'=>'fixture_a','installed'=>array('fixture_a'=>array('id'=>'fixture_a','service_code'=>'PRIMARY','enabled'=>1,'question_steps'=>1,'fields'=>array())),
 'documents'=>array('document_url'=>'https://example.test/consent','policy_url'=>'https://example.test/policy','text_version'=>'1','document_version'=>'1','policy_version'=>'1'));
/* LOAD_CANDIDATE */
if(!function_exists('netchat_enqueue')){
 require dirname(__DIR__).'/includes/frontend.php';
 require dirname(__DIR__).'/includes/service-forms.php';
 require dirname(__DIR__).'/includes/leads-frontend.php';
}
$count=0;
function nc_check($ok,$message){global $count;if(!$ok)throw new RuntimeException($message);$count++;}
function nc_guard(){return function_exists('netchat_ncf_workspace_active')&&netchat_ncf_workspace_active();}
function nc_capture(){
 $GLOBALS['nc_effects']=array();$GLOBALS['nc_reads']=0;
 $hooks=$GLOBALS['nc_hooks']['wp_enqueue_scripts'];ksort($hooks);foreach($hooks as$callbacks)foreach($callbacks as$callback)call_user_func($callback);
 ob_start();$hooks=$GLOBALS['nc_hooks']['wp_footer'];ksort($hooks);foreach($hooks as$callbacks)foreach($callbacks as$callback)call_user_func($callback);$html=ob_get_clean();
 $ctas=array();foreach($GLOBALS['nc_shortcodes']as$tag=>$callback){$ctas[$tag]=call_user_func($callback,array('text'=>'Синтетический CTA'));$ctas[$tag.'_service']=call_user_func($callback,array('text'=>'Сервисный CTA','service'=>'PRIMARY'));}
 return array('effects'=>$GLOBALS['nc_effects'],'html'=>$html,'ctas'=>$ctas,'reads'=>$GLOBALS['nc_reads']);
}
function nc_assert_snapshot($snapshot,$blocked){
 if($blocked){
  nc_check($snapshot['effects']===array(),'workspace: no public asset/localize/inline side effects');
  nc_check($snapshot['html']==='','workspace: no launcher/hint/form popup HTML');
  foreach($snapshot['ctas']as$cta)nc_check($cta==='','workspace: no contact/service shortcode CTA');
  nc_check($snapshot['reads']===0,'workspace: guard precedes config/options access');
 }else{
  $styles=array_column($snapshot['effects']['styles'],0);$scripts=array_column($snapshot['effects']['scripts'],0);
  foreach(array('netchat-front','netchat-leads','netchat-contact-alternatives','netchat-services','netchat-popup')as$handle)nc_check(in_array($handle,$styles,true),'public CSS '.$handle);
  foreach(array('netchat-front','netchat-leads','netchat-services')as$handle)nc_check(in_array($handle,$scripts,true),'public JS '.$handle);
  nc_check(count($snapshot['effects']['inline'])===1&&count($snapshot['effects']['localized'])===3,'public inline/localized retained');
  foreach(array('id="netchat"','id="nc-hint"','id="nc-leads-modal"','id="nc-contact-alternatives"')as$marker)nc_check(strpos($snapshot['html'],$marker)!==false,'public markup '.$marker);
  foreach($snapshot['ctas']as$cta)nc_check(strpos($cta,'data-netchat-open="form"')!==false,'public shortcode CTA retained');
 }
}
nc_check(count($GLOBALS['nc_shortcodes'])===2,'both NC shortcode aliases registered');
nc_check(count($GLOBALS['nc_hooks']['wp_footer'][99])===1&&count($GLOBALS['nc_hooks']['wp_footer'][100])===1,'two footer entry points registered');
if($mode==='latechange'||$mode==='latefunction'){
 nc_check(nc_guard()===false,'early marker false/absent');
 $first=nc_capture();nc_assert_snapshot($first,false);
 if($mode==='latefunction')nc_define_marker();
 $GLOBALS['workspace']=true;nc_check(nc_guard()===true,'late marker observed, no early false cache');
 $closed=nc_capture();nc_assert_snapshot($closed,true);
 $GLOBALS['workspace']=false;nc_check(nc_guard()===false,'marker reset observed');
 $last=nc_capture();nc_assert_snapshot($last,false);
 nc_check($first===$last,'public behavior fully restored within request');
 $snapshot=$closed;
}else{
 nc_check(nc_guard()===($mode==='true'),'marker state');
 $snapshot=nc_capture();nc_assert_snapshot($snapshot,$mode==='true');
}
echo json_encode(array('status'=>'PASS','layer'=>'standalone_PHP_doubles_no_WordPress','mode'=>$mode,'assertions'=>$count,'snapshot_sha256'=>hash('sha256',serialize($snapshot)),'styles'=>isset($snapshot['effects']['styles'])?count($snapshot['effects']['styles']):0,'scripts'=>isset($snapshot['effects']['scripts'])?count($snapshot['effects']['scripts']):0,'html_bytes'=>strlen($snapshot['html']))),PHP_EOL;
