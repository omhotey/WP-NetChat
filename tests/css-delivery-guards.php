<?php
namespace {
if (defined('ABSPATH')) throw new \RuntimeException('Standalone only');
define('ABSPATH','memory://'); define('NETCHAT_DIR','/candidate/'); define('NETCHAT_URL','https://example.test/wp-content/plugins/netchat/'); define('NETCHAT_VER','1.2.3');
function netchat_ncf_workspace_active(){return !empty($GLOBALS['workspace']);}
/* PAYLOAD */
}
namespace NC_Test {
function apply_filters($name,$value){return array_key_exists('filter',$GLOBALS)?$GLOBALS['filter']:$value;}
function add_filter($name,$call,$priority,$args){$GLOBALS['registered']=array($name,$call,$priority,$args);}
function is_admin(){return !empty($GLOBALS['admin']);} function is_feed(){return !empty($GLOBALS['feed']);}
function netchat_ncf_workspace_active(){return \netchat_ncf_workspace_active();}
function wp_parse_url($url){return parse_url($url);}
function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES,'UTF-8');}
function realpath($p){if(!empty($GLOBALS['outsidebase'])&&strpos($p,'/assets/css')!==false)return '/elsewhere/css'.substr($p,strlen('/candidate/assets/css'));return isset($GLOBALS['missing'])?false:(!empty($GLOBALS['outside'])&&substr($p,-4)==='.css'?'/elsewhere/style.css':rtrim($p,'/'));}
function is_file($p){return true;} function is_readable($p){return empty($GLOBALS['unreadable']);}
function filesize($p){return isset($GLOBALS['size'])?$GLOBALS['size']:strlen($GLOBALS['files'][basename($p)]);}
function file_get_contents($p){return !empty($GLOBALS['readfail'])?false:$GLOBALS['files'][basename($p)];}
function filemtime($p){return 123;}
function check($ok,$message){$GLOBALS['checks']++;if(!$ok)throw new \RuntimeException($message);}
function tag($handle,$href,$media='all'){return '<link rel="stylesheet" id="'.$handle.'-css" href="'.esc_attr($href).'" type="text/css" media="'.$media.'" />';}
function call($handle='netchat-front',$href=null,$media='all',$html=null){$href=$href??NETCHAT_URL.'assets/css/style.css?ver=1.2.3.123';$html=$html??tag($handle,$href,$media);return netchat_style_delivery($html,$handle,$href,$media);}
/* MODULE */
$GLOBALS['checks']=0;
check($GLOBALS['registered']===array('style_loader_tag','netchat_style_delivery',10,4),'four-argument hook');
$base=NETCHAT_URL.'assets/css/style.css?ver=1.2.3.123';$original=tag('netchat-front',$base);
check(strpos(call(),'<style id="netchat-front-css"')===0,'default inline');
foreach(array('admin','feed','workspace','unreadable','missing','outside','outsidebase','readfail')as $flag){$GLOBALS[$flag]=true;check(call()===$original,$flag);unset($GLOBALS[$flag]);}
foreach(array(false,null,1,'true','inline')as $v){$GLOBALS['filter']=$v;check(call()===$original,'strict filter');}unset($GLOBALS['filter']);
foreach(array('https://cdn.test/wp-content/plugins/netchat/assets/css/style.css?ver=1.2.3.123','http://example.test/wp-content/plugins/netchat/assets/css/style.css','https://example.test:444/wp-content/plugins/netchat/assets/css/style.css','https://user:pass@example.test/wp-content/plugins/netchat/assets/css/style.css','https://example.test/wp-content/plugins/netchat/assets/css/style.css#x',NETCHAT_URL.'assets/css/other.css',NETCHAT_URL.'assets/css/style.css?other=1',NETCHAT_URL.'assets/css/style.css?ver=old')as $url)check(call('netchat-front',$url)===tag('netchat-front',$url),'URL guard');
$relative=preg_replace('~^https:~','',$base);check(strpos(call('netchat-front',$relative),'<style')===0,'protocol-relative same origin');
check(call('other')===tag('other',$base),'unknown handle');check(call('netchat-front',null,'print')===tag('netchat-front',$base,'print'),'media');
foreach(array('nonce="abc"','integrity="sha256-x"','crossorigin="anonymous"','title="alternate"','onload="this.media=all"','data-optimized="1"','disabled="disabled"')as $attr){$html=str_replace('<link ','<link '.$attr.' ',$original);check(call('netchat-front',null,'all',$html)===$html,'custom attribute '.$attr);}
foreach(array(str_replace('stylesheet','preload',$original),str_replace('netchat-front-css','other-css',$original),$original.'<link rel="stylesheet" />',str_replace('type="text/css"','type="text/plain"',$original),str_replace('media="all"','media="print"',$original),str_replace('href="','href="wrong',$original),str_replace('rel="stylesheet"','rel="stylesheet" rel="stylesheet"',$original),'<style>.a{color:red}</style>')as $html)check(call('netchat-front',null,'all',$html)===$html,'modified tag');
$saved=$GLOBALS['files']['style.css'];
foreach(array('',str_repeat('a',32769),'.a{background:url(foo)}','.a{background:uRL /**/(foo)}','@IMPORT "foo";','@charset "utf-8";','</STYLE >','<!--','.a{background:u\\72l(foo)}')as $css){$GLOBALS['files']['style.css']=$css;check(call()===$original,'unsafe/empty/oversize');}
$GLOBALS['files']['style.css']=$saved;$GLOBALS['size']=strlen($saved)+1;check(call()===$original,'changed during read');unset($GLOBALS['size']);
$mapping=array('netchat-front'=>'style.css','netchat-leads'=>'leads.css','netchat-contact-alternatives'=>'contact-alternatives.css','netchat-services'=>'service-forms.css','netchat-popup'=>'popup.css');$render=array();
foreach($mapping as $handle=>$file){$href=NETCHAT_URL.'assets/css/'.$file.'?ver=1.2.3.123';$inline=call($handle,$href);check(strpos($inline,'<style ')===0,'real CSS '.$file);check(strpos($inline,'>'.$GLOBALS['files'][$file].'</style>')!==false,'exact bytes '.$file);$render[]=array('handle'=>$handle,'file'=>$file,'inline'=>$inline,'external'=>tag($handle,$href));}
check(strpos(call('netchat-front',null,''),'media="all"')!==false,'empty media normalized');
define('NETCHAT_INLINE_STYLES',false);$GLOBALS['filter']=true;check(call()===$original,'kill switch takes priority');
echo json_encode(array('status'=>'PASS','assertions'=>$GLOBALS['checks'],'php_version'=>PHP_VERSION,'mode'=>'standalone in-memory filesystem and WordPress doubles; no actual WP boot','render'=>$render),JSON_UNESCAPED_UNICODE);
}
