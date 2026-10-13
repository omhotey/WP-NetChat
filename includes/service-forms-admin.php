<?php
/** Site-owned opt-in service presets; edits use compare-and-swap. */
if(!defined('ABSPATH'))exit;
add_action('admin_menu',function(){add_submenu_page('netchat','NetChat — Формы услуг','Формы услуг','manage_options','netchat-service-forms','netchat_service_admin_page');});
add_action('admin_enqueue_scripts',function($hook){if($hook==='netchat_page_netchat-service-forms')wp_enqueue_style('netchat-popup-preview',NETCHAT_URL.'assets/css/popup.css',array('netchat-services-workspace','netchat-service-alternatives'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/popup.css'));},30);
add_action('admin_enqueue_scripts',function($hook){if($hook==='netchat_page_netchat-service-forms')wp_enqueue_style('netchat-service-alternatives',NETCHAT_URL.'assets/css/contact-alternatives.css',array('netchat-services-admin'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/contact-alternatives.css'));});
add_action('admin_enqueue_scripts',function($hook){if($hook!=='netchat_page_netchat-service-forms')return;wp_enqueue_style('netchat-services-preview',NETCHAT_URL.'assets/css/service-forms.css',array(),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/service-forms.css'));wp_enqueue_style('netchat-services-admin',NETCHAT_URL.'assets/css/service-forms-admin.css',array('netchat-services-preview'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/service-forms-admin.css'));wp_enqueue_style('netchat-services-workspace',NETCHAT_URL.'assets/css/service-forms-workspace.css',array('netchat-services-admin'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/service-forms-workspace.css'));wp_enqueue_script('netchat-services-admin',NETCHAT_URL.'assets/js/service-forms-admin.js',array(),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/js/service-forms-admin.js'),true);});
add_action('admin_post_netchat_service_settings','netchat_service_admin_save');
function netchat_service_admin_revision($raw){return hash_hmac('sha256',maybe_serialize($raw),wp_salt('nonce'));}
function netchat_service_admin_commit($old,$new){
    global $wpdb;
    if($old===null)return add_option('netchat_service_forms',$new,'',false);
    if($old===$new)return get_option('netchat_service_forms',null)===$old;
    $changed=$wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET option_value=%s WHERE option_name='netchat_service_forms' AND BINARY option_value=BINARY %s",maybe_serialize($new),maybe_serialize($old)));
    if($changed!==1)return false;
    wp_cache_delete('netchat_service_forms','options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');return true;
}
function netchat_service_admin_text($raw,$max){return is_string($raw)&&preg_match('//u',$raw)&&netchat_leads_strlen(trim($raw))<=$max?trim(wp_strip_all_tags($raw)):null;}
function netchat_service_admin_save(){
    if(!current_user_can('manage_options'))wp_die('Insufficient permissions.','',array('response'=>403));
    check_admin_referer('netchat_service_settings');
    $post=wp_unslash($_POST);$old=get_option('netchat_service_forms',null);$c=netchat_service_forms_config();
    if(!is_string($post['revision']??null)||!hash_equals(netchat_service_admin_revision($old),$post['revision']))wp_die('Настройки изменились в другой вкладке. Обновите страницу.','',array('response'=>409));
    $action=$post['operation']??'';
    if(in_array($action,array('install','create','duplicate','toggle','delete'),true)){
        $result=netchat_service_admin_lifecycle($c,$action,$post);
        if(is_wp_error($result))wp_die(esc_html($result->get_error_message()),'',array('response'=>$result->get_error_data()['status']??400));
        $c=$result['config'];$id=$result['id'];
        if($action==='toggle'&&($post['return_to_editor']??'')==='1')$id=$post['preset_id'];
    }elseif($action==='reset'){
        $id=$post['preset_id']??'';$builtin=netchat_service_builtin();
        if(!is_string($id)||!isset($builtin[$id])||($post['confirm_reset']??'')!=='1')wp_die('Подтвердите сброс выбранной формы.','',array('response'=>400));
        if(!isset($c['installed'][$id]))wp_die('Форма не установлена.','',array('response'=>404));
        $version=(int)$c['installed'][$id]['schema_version']+1;
        $enabled=netchat_service_form_enabled($c['installed'][$id]);
        $c['installed'][$id]=$builtin[$id];$c['installed'][$id]['schema_version']=$version;$c['installed'][$id]['enabled']=$enabled?1:0;
    }elseif($action==='save_form'){
        $id=$post['preset_id']??'';
        if(!is_string($id)||!isset($c['installed'][$id]))wp_die('Форма не найдена.','',array('response'=>404));
        $schema=netchat_service_admin_edit_schema($c['installed'][$id],$post);
        if(is_wp_error($schema))wp_die(esc_html($schema->get_error_message()),'',array('response'=>400));
        $binding=netchat_service_admin_binding($post['binding']??array(),$c,$schema['service_code']);
        if(is_wp_error($binding))wp_die(esc_html($binding->get_error_message()),'',array('response'=>400));
        $c['installed'][$id]=$schema;
        if(isset($post['is_primary'])&&$post['is_primary']!=='1')wp_die('Некорректная отметка общей формы.','',array('response'=>400));
        if(isset($post['is_primary']))$c['primary_form']=$id;
        elseif(($c['primary_form']??'')===$id)$c['primary_form']='';
        unset($c['bindings'][$schema['service_code']]);
        if($binding)$c['bindings'][$schema['service_code']]=$binding;
    }elseif($action==='save_global'){
        foreach(array('bindings','presets')as$key)if(isset($post[$key])&&!is_array($post[$key]))wp_die('Некорректный формат настроек.','',array('response'=>400));
        $c['enabled']=isset($post['enabled'])?1:0;$c['retention_confirmed']=isset($post['retention_confirmed'])?1:0;
        foreach(array('document_url','policy_url')as$key){
            $url=netchat_service_admin_text($post[$key]??'',500);$parts=$url!==null?wp_parse_url($url):false;
            if($url===null||($url!==''&&(!netchat_service_url_valid($url)||isset($parts['fragment']))))wp_die('Укажите корректные URL документов.','',array('response'=>400));
            $c['documents'][$key]=$url;
        }
        foreach(array('text_version','document_version','policy_version')as$key){$value=netchat_service_admin_text($post[$key]??'',190);if($value===null)wp_die('Версия документа слишком длинная.','',array('response'=>400));$c['documents'][$key]=$value;}
        if($c['enabled']){
            if(empty($c['installed'])||!$c['retention_confirmed']||empty(netchat_leads_config()['enabled']))wp_die('Установите набор, включите приём заявок и подтвердите срок хранения.','',array('response'=>400));
            foreach($c['documents']as$value)if($value==='')wp_die('Для включения требуются опубликованные документы и все версии.','',array('response'=>400));
        }
    }else wp_die('Unknown operation.','',array('response'=>400));
    if(!netchat_service_admin_commit($old,$c))wp_die('Настройки изменились или не сохранились. Обновите страницу и повторите.','',array('response'=>409));
    $destination='admin.php?page=netchat-service-forms&saved=1';
    if(in_array($action,array('save_form','reset','create','duplicate'),true)||($action==='toggle'&&($post['return_to_editor']??'')==='1'))$destination.='&form='.rawurlencode($id);
    elseif($action==='save_global')$destination.='&view=settings';
    wp_safe_redirect(admin_url($destination));exit;
}
function netchat_service_admin_form_start($operation,$raw){echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('netchat_service_settings');echo '<input type="hidden" name="action" value="netchat_service_settings"><input type="hidden" name="operation" value="'.esc_attr($operation).'"><input type="hidden" name="revision" value="'.esc_attr(netchat_service_admin_revision($raw)).'">';}
require_once NETCHAT_DIR.'includes/service-forms-editor.php';
require_once NETCHAT_DIR.'includes/service-forms-lifecycle.php';
