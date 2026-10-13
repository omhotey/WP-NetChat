<?php
/** Opt-in preset registry and adapters for the existing lead module. */
if(!defined('ABSPATH'))exit;
function netchat_service_builtin(){static $forms;if($forms===null)$forms=json_decode(file_get_contents(NETCHAT_DIR.'includes/service-presets.json'),true);return is_array($forms)?$forms:array();}
function netchat_service_initialize(){
    if(get_option('netchat_service_forms',null)!==null)return;
    $config=netchat_service_forms_config();$config['installed']=netchat_service_builtin();$first=reset($config['installed']);$config['primary_form']=isset($config['installed']['nc_primary_v1'])?'nc_primary_v1':($first['id']??'');
    add_option('netchat_service_forms',$config,'',false);
}
function netchat_service_forms_config(){
    $defaults=array('installed'=>array(),'enabled'=>0,'documents'=>array('document_url'=>'','policy_url'=>'','text_version'=>'1','document_version'=>'','policy_version'=>''),'bindings'=>array(),'retention_confirmed'=>0,'deleted'=>array(),'primary_form'=>'');
    $saved=get_option('netchat_service_forms',array());if(!is_array($saved))$saved=array();
    $c=array_merge($defaults,$saved);$c['documents']=array_merge($defaults['documents'],is_array($c['documents'])?$c['documents']:array());unset($c['entry']);return $c;
}
function netchat_service_install_presets(){
    $old=get_option('netchat_service_forms',null);$c=netchat_service_forms_config();foreach(netchat_service_builtin()as$id=>$schema)if(!isset($c['installed'][$id])){$schema['schema_version']=max($schema['schema_version'],(int)($c['deleted'][$id]['schema_version']??0)+1);$c['installed'][$id]=$schema;unset($c['deleted'][$id]);}
    return netchat_service_admin_commit($old,$c);
}
function netchat_service_form_enabled($form){return !array_key_exists('enabled',$form)||!empty($form['enabled']);}
function netchat_service_question_steps($form){return isset($form['question_steps'])?(int)$form['question_steps']:max(1,...array_column($form['fields'],'step'));}
function netchat_service_active_forms(){return array_filter(netchat_service_forms_config()['installed'],'netchat_service_form_enabled');}
function netchat_service_form($id){$c=netchat_service_forms_config();return is_string($id)&&isset($c['installed'][$id])&&is_array($c['installed'][$id])&&netchat_service_form_enabled($c['installed'][$id])?$c['installed'][$id]:null;}
function netchat_service_ready(){
    $c=netchat_service_forms_config();if(empty(netchat_leads_config()['enabled'])||empty($c['enabled'])||!netchat_service_active_forms()||empty($c['retention_confirmed']))return false;
    foreach(array('document_url','policy_url')as$key){$url=$c['documents'][$key];if(!is_string($url)||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower(wp_parse_url($url,PHP_URL_SCHEME)),array('http','https'),true))return false;}
    foreach(array('text_version','document_version','policy_version')as$key)if(!is_string($c['documents'][$key])||trim($c['documents'][$key])==='')return false;
    return (int)netchat_leads_config()['retention_days']>0;
}
function netchat_service_same_origin($url){$a=wp_parse_url($url);$b=wp_parse_url(home_url('/'));if(!$a||!$b||isset($a['user'])||isset($a['pass']))return false;foreach(array('scheme','host')as$key)if(strtolower($a[$key]??'')!==strtolower($b[$key]??''))return false;return ($a['port']??($a['scheme']==='https'?443:80))===($b['port']??($b['scheme']==='https'?443:80));}
function netchat_service_page_code(){
    if(!is_singular())return ''; $id=get_queried_object_id();$url=netchat_leads_source_url(get_permalink($id));$c=netchat_service_forms_config();
    $codes=array_column(netchat_service_active_forms(),'service_code');
    foreach($c['bindings']as$code=>$binding)if(in_array($code,$codes,true)&&is_array($binding)&&netchat_service_same_origin($binding['url']??'')&&netchat_leads_source_url($binding['url'])===$url&&(!($binding['page_id']??0)||(int)$binding['page_id']===$id))return $code;return '';
}
function netchat_service_context_token($id,$url){$data=(int)$id.'|'.netchat_leads_source_url($url).'|'.time();return base64_encode($data).'.'.hash_hmac('sha256','nc-context|'.$data,wp_salt('nonce'));}
function netchat_service_context_page($context){
    if(!is_array($context)||!is_string($context['context_token']??null))return null;$parts=explode('.',$context['context_token']);if(count($parts)!==2)return null;$data=base64_decode($parts[0],true);if($data===false||!hash_equals(hash_hmac('sha256','nc-context|'.$data,wp_salt('nonce')),$parts[1]))return null;
    $bits=explode('|',$data);if(count($bits)!==3||!ctype_digit($bits[0])||!ctype_digit($bits[2])||(int)$bits[2]>time()+30)return null;
    if((int)($context['page_id']??0)!==(int)$bits[0]||netchat_leads_source_url($context['page_url']??'')!==$bits[1]||!netchat_service_same_origin($bits[1]))return null;return (int)$bits[0]>0?(int)$bits[0]:null;
}
function netchat_service_token_rate($token){
    global $wpdb;$table=netchat_leads_limits_table();$window=(int)floor(time()/60)*60;$bucket='sf:'.substr(hash_hmac('sha256',$token,wp_salt('auth')),0,29);
    $result=$wpdb->query($wpdb->prepare("INSERT INTO $table (bucket,window_start,hits) VALUES (%s,%d,1) ON DUPLICATE KEY UPDATE hits=IF(window_start<VALUES(window_start),1,LEAST(hits+1,1000000)),window_start=GREATEST(window_start,VALUES(window_start))",$bucket,$window));
    if($result===false)return netchat_leads_error('storage','Не удалось сохранить обращение. Попробуйте ещё раз позже',503);
    $hits=$wpdb->get_var($wpdb->prepare("SELECT hits FROM $table WHERE bucket=%s",$bucket));if($hits===null||$wpdb->last_error)return netchat_leads_error('storage','Не удалось сохранить обращение. Попробуйте ещё раз позже',503);$wpdb->query($wpdb->prepare("DELETE FROM $table WHERE window_start<%d LIMIT 100",time()-DAY_IN_SECONDS));
    if((int)$hits>3)return new WP_Error('netchat_leads_rate_limit','Слишком много попыток. Попробуйте через 1 минуту',array('status'=>429,'retry_after'=>60-(time()%60)));return true;
}
function netchat_service_token_valid($token,$form){
    if(!is_string($token)||!preg_match('/^(\d{10})\.([a-f0-9]{32})\.([a-f0-9]{64})$/',$token,$m))return false;
    return (int)$m[1]<=time()+30&&(int)$m[1]>=time()-1200&&hash_equals(netchat_leads_token_signature($m[1].'.'.$m[2].'|'.$form['id'].'|'.$form['schema_version']),$m[3]);
}
add_action('wp_enqueue_scripts',function(){
    if(is_admin()||!netchat_service_ready())return;$c=netchat_service_forms_config();
    wp_enqueue_style('netchat-services',NETCHAT_URL.'assets/css/service-forms.css',array('netchat-leads'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/service-forms.css'));
    wp_enqueue_script('netchat-services',NETCHAT_URL.'assets/js/service-forms.js',array('netchat-leads'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/js/service-forms.js'),true);
    $id=is_singular()?get_queried_object_id():0;$url=$id?get_permalink($id):home_url('/');
    $channel='';$cfg=netchat_get_config();foreach(array('telegram','email','phone','whatsapp')as$key)if(!empty($cfg['enabled'][$key])&&!empty($cfg['links'][$key])){$channel=$cfg['links'][$key];break;}
    wp_localize_script('netchat-services','netchatServices',array('forms'=>array_values(netchat_service_active_forms()),'documents'=>$c['documents'],'primary_form'=>$c['primary_form'],'page_service'=>netchat_service_page_code(),'page_id'=>$id?:null,'context_token'=>netchat_service_context_token($id,$url),'endpoint'=>rest_url('netchat/v1/leads'),'channel_url'=>$channel));
},30);
