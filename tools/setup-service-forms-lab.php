<?php
/** Explicit local stand setup; never run on a public hostname. WP-CLI eval-file. */
if(!defined('WP_CLI') || !WP_CLI || !in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)) throw new RuntimeException('Local WordPress laboratory required.');
function nc_lab_document($slug,$title){
    $page=get_page_by_path($slug);
    if($page) return get_permalink($page);
    $id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$title,'post_content'=>'Тестовый документ локального стенда NetChat. Предназначен только для синтетических проверок формы. Не использовать для приёма реальных персональных данных.'),true);
    if(is_wp_error($id)) throw new RuntimeException('Could not create laboratory document.');
    return get_permalink($id);
}
if(!netchat_service_install_presets()) throw new RuntimeException('Could not install service presets.');
$c=netchat_get_config();$c['leads']['enabled']=1;
if(is_wp_error(netchat_save_config($c,netchat_get_revision()))) throw new RuntimeException('Could not enable laboratory intake.');
$forms=netchat_service_forms_config();
$forms['installed']=array_merge($forms['installed'],json_decode(file_get_contents(NETCHAT_DIR.'tests/fixtures-demo-forms.json'),true));
if(empty($forms['documents']['document_url'])) $forms['documents']['document_url']=nc_lab_document('netchat-test-consent','NetChat — тестовое согласие');
if(empty($forms['documents']['policy_url'])) $forms['documents']['policy_url']=nc_lab_document('netchat-test-privacy','NetChat — тестовая политика');
foreach(array('document_version','policy_version')as$key)if(empty($forms['documents'][$key]))$forms['documents'][$key]='lab-2026-10-12';
$forms['enabled']=1;$forms['retention_confirmed']=1;
update_option('netchat_service_forms',$forms,false);
$page=get_page_by_path('netchat-test');
if($page && strpos($page->post_content,'netchat-seven-service-fixture')===false){
    $content="\n<!-- netchat-seven-service-fixture -->\n<h2>Синтетические формы — локальная проверка</h2>\n<p>Используйте только вымышленные контакты. Документы на стенде тестовые.</p>\n";
    $labels=array('TEST_A'=>'Тестовая форма A','TEST_B'=>'Тестовая форма B','TEST_C'=>'Тестовая форма C','TEST_D'=>'Тестовая форма D','TEST_E'=>'Тестовая форма E','TEST_F'=>'Тестовая форма F','TEST_G'=>'Тестовая форма G');
    foreach($labels as$code=>$label)$content.='<p>[netchat_button service="'.$code.'" cta="hero" text="'.$label.'"]</p>' . "\n";
    $result=wp_update_post(array('ID'=>$page->ID,'post_content'=>$page->post_content.$content),true);
    if(is_wp_error($result))throw new RuntimeException('Could not add local test buttons.');
}
WP_CLI::success('seven synthetic forms installed on the local laboratory with test documents; existing leads retained.');
