<?php
if(getenv('NC_NCF_SYNTHETIC')!=='isolated-container-v1')exit(2);
define('WP_INSTALLING',true);$_SERVER['HTTP_HOST']='127.0.0.1';require '/var/www/html/wp-load.php';
if(DB_NAME!=='ncncf_synthetic'||$wpdb->prefix!=='nc_joint_')exit(3);
require_once ABSPATH.'wp-admin/includes/upgrade.php';wp_install('Synthetic','synthetic_admin','admin@example.test',false,'','synthetic-only-password');
update_option('siteurl',getenv('NC_TEST_URL'));update_option('home',getenv('NC_TEST_URL'));
require '/var/www/html/wp-content/plugins/netchat/netchat.php';require '/var/www/html/wp-content/plugins/netchat-ysm/netchat-ysm.php';
ncf_intake_activate();update_option('active_plugins',array('netchat/netchat.php','netchat-ysm/netchat-ysm.php'));
$nc=netchat_defaults();$nc['launcher_mode']='form';$nc['enabled']['telegram']=1;$nc['links']['telegram']='https://t.me/example';$nc['enabled']['email']=1;$nc['links']['email']='mailto:hello@example.test';add_option('netchat_settings_v3',array('schema_version'=>1,'revision'=>1,'settings'=>$nc),'',false);
$settings=get_option('ncf_intake_settings');$settings['config']['leads']['enabled']=1;$settings['config']['leads']['email']['enabled']=0;update_option('ncf_intake_settings',$settings,false);
$make=function($id,$code,$title){return array('id'=>$id,'service_code'=>$code,'schema_version'=>1,'service_label'=>$title,'title'=>$title,'intro'=>'Тестовая форма','submit_text'=>'Отправить','success_text'=>'Спасибо','enabled'=>1,'question_steps'=>1,'fields'=>array(array('key'=>'question','label'=>'Вопрос','type'=>'short','required'=>false,'step'=>1)));};
$first=wp_insert_post(array('post_title'=>'Тест выбора','post_status'=>'publish','post_type'=>'page','post_content'=>'[netchat_forms]'));
$second=wp_insert_post(array('post_title'=>'Тест привязки','post_status'=>'publish','post_type'=>'page','post_content'=>'[netchat_forms]'));
$forms=ncf_intake_service_forms_config();$forms['installed']=array('alpha'=>$make('alpha','ALPHA','Форма Альфа'),'beta'=>$make('beta','BETA','Форма Бета'));$forms['enabled']=1;$forms['retention_confirmed']=1;$forms['primary_form']='';$forms['documents']=array('document_url'=>'https://example.test/consent','policy_url'=>'https://example.test/privacy','text_version'=>'1','document_version'=>'1','policy_version'=>'1');$forms['bindings']=array('BETA'=>array('page_id'=>$second,'url'=>get_permalink($second)));update_option('ncf_intake_service_forms',$forms,false);
file_put_contents('/var/www/html/wp-content/mu-plugins/fixture.php','<?php add_filter("pre_wp_mail",function(){return true;}); if(isset($_GET["standalone"]))add_filter("option_active_plugins",function($plugins){return array_values(array_diff($plugins,array("netchat/netchat.php")));}); add_action("template_redirect",function(){if(is_admin())return;header("Content-Type: text/html; charset=UTF-8");echo "<!doctype html><html><head>";wp_head();echo "</head><body>";while(have_posts()){the_post();the_content();}wp_footer();echo "</body></html>";exit;});');
echo json_encode(array('selection'=>$first,'bound'=>$second,'url'=>getenv('NC_TEST_URL')));
