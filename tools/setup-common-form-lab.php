<?php
/** Add the explicitly requested common form to the local catalog. */
if(!defined('WP_CLI')||!WP_CLI||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))throw new RuntimeException('Local laboratory required.');
$old=get_option('netchat_service_forms',null);$config=netchat_service_forms_config();
if(isset($config['installed'][$config['primary_form']??''])){WP_CLI::success('Existing common form kept: '.$config['primary_form']);return;}
$result=netchat_service_admin_lifecycle($config,'create',array('form_name'=>'Общая форма','template_id'=>'','question_steps'=>'1'));
if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
$config=$result['config'];$id=$result['id'];$config['installed'][$id]['enabled']=1;$config['installed'][$id]['title']='Расскажите о вашей задаче';$config['primary_form']=$id;
if(!netchat_service_admin_commit($old,$config))throw new RuntimeException('Forms changed concurrently; common form was not saved.');
WP_CLI::success('Common form created and selected: '.$id);
