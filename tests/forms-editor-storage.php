<?php
/** Isolated WordPress tests of editable schemas and compare-and-swap. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
global $wpdb;
$original=$wpdb->options;$table='nc_editor_test_'.bin2hex(random_bytes(5));
$wpdb->query("CREATE TEMPORARY TABLE `$table` LIKE `$original`");$wpdb->options=$table;wp_cache_flush();
require dirname(__DIR__).'/netchat.php';
require_once dirname(__DIR__).'/includes/service-forms-admin.php';
$GLOBALS['checks']=0;
function editor_assert($value,$message){global $checks;if(!$value)throw new RuntimeException($message);$checks++;}
function editor_post($schema){
    $post=array('texts'=>array(),'fields'=>array(),'order'=>array());
    foreach(array('title','intro','submit_text','success_text')as$key)$post['texts'][$key]=$schema[$key];
    foreach($schema['fields']as$field){$raw=array('label'=>$field['label'],'placeholder'=>$field['placeholder']??'','helper'=>$field['helper']??'');if($field['required'])$raw['required']='1';if(isset($field['options']))$raw['options']=$field['options'];$post['fields'][$field['key']]=$raw;$post['order'][$field['step']][]=$field['key'];}
    return $post;
}
try {
    add_option('home','https://example.test');add_option('siteurl','https://example.test');
    foreach(json_decode(file_get_contents(__DIR__.'/fixtures-demo-forms.json'),true)as$schema){
        $post=editor_post($schema);$result=netchat_service_admin_edit_schema($schema,$post);
        editor_assert(!is_wp_error($result)&&$result['schema_version']===$schema['schema_version'],'unchanged version '.$schema['id']);
        $post['texts']['title']='Изменённый заголовок';$first=$schema['fields'][0]['key'];$post['fields'][$first]['label']='Новый вопрос';$post['fields'][$first]['helper']='Подсказка';unset($post['fields'][$first]['required']);
        $result=netchat_service_admin_edit_schema($schema,$post);
        editor_assert(!is_wp_error($result)&&$result['schema_version']===$schema['schema_version']+1,'version increments');
        editor_assert($result['fields'][0]['label']==='Новый вопрос'&&!$result['fields'][0]['required'],'editable question');
        editor_assert($result['id']===$schema['id']&&$result['service_code']===$schema['service_code'],'identity preserved');
        foreach($result['fields']as$i=>$field)editor_assert(($field['condition']??null)===($schema['fields'][$i]['condition']??null)&&$field['type']===$schema['fields'][$i]['type'],'conditions and types preserved');
        $post=editor_post($schema);$post['order'][1]=array_reverse($post['order'][1]);$result=netchat_service_admin_edit_schema($schema,$post);
        editor_assert(!is_wp_error($result)&&$result['fields'][0]['key']===$post['order'][1][0],'reorder');
        $post=editor_post($schema);$post['order'][1][0]=array('bad');editor_assert(is_wp_error(netchat_service_admin_edit_schema($schema,$post)),'nested order rejected');
        $post=editor_post($schema);$post['fields'][$first]['label']='';editor_assert(is_wp_error(netchat_service_admin_edit_schema($schema,$post)),'empty label rejected');
        $post=editor_post($schema);$post['fields'][$first]['helper']=array('bad');editor_assert(is_wp_error(netchat_service_admin_edit_schema($schema,$post)),'invalid text type');
        $post=editor_post($schema);$post['order'][1][]=$first;editor_assert(is_wp_error(netchat_service_admin_edit_schema($schema,$post)),'duplicate order rejected');
    }
    $config=array('bindings'=>array('TEST_F'=>array('url'=>'https://example.test/audit','page_id'=>0)));
    foreach(array('https://other.test/page','https://example.test/audit','https://example.test/page?secret=1','https://example.test/page#anchor')as$url)editor_assert(is_wp_error(netchat_service_admin_binding(array('url'=>$url),$config,'TEST_G')),'invalid/conflicting binding');
    editor_assert(netchat_service_admin_binding(array('url'=>''),$config,'TEST_G')===array(),'remove binding');
    editor_assert(netchat_service_admin_binding(array('url'=>'https://example.test/consult','page_id'=>'12'),$config,'TEST_G')['page_id']===12,'valid binding');
    editor_assert(netchat_service_admin_commit(null,array('enabled'=>0)),'initial CAS');
    $old=get_option('netchat_service_forms');editor_assert(netchat_service_admin_commit($old,array('enabled'=>1)),'valid CAS');
    editor_assert(!netchat_service_admin_commit($old,array('enabled'=>0)),'stale CAS rejected');
    WP_CLI::success($GLOBALS['checks'].' isolated form editor assertions passed.');
}finally{$wpdb->options=$original;wp_cache_flush();}
