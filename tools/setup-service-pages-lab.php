<?php
/** Explicit, repeatable local setup requested by the user. */
if(!defined('WP_CLI')||!WP_CLI||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))throw new RuntimeException('Local WordPress laboratory required.');
if(get_option('permalink_structure','')==='')throw new RuntimeException('Readable permalinks required; site settings were not changed.');
$old=get_option('netchat_service_forms',null);$config=netchat_service_forms_config();
if(empty($config['installed']))throw new RuntimeException('No installed forms.');
$pages=array();
foreach($config['installed']as$id=>$form){
    if($id===($config['primary_form']??''))continue;
    $existing=get_posts(array('post_type'=>'page','post_status'=>'any','numberposts'=>2,'meta_key'=>'_netchat_lab_form_id','meta_value'=>$id));
    if(count($existing)>1)throw new RuntimeException('Duplicate laboratory pages for '.$id);
    if($existing&&($existing[0]->post_content!==''||$existing[0]->post_status!=='publish'))throw new RuntimeException('Existing page was edited: '.$id.'. Its content was preserved.');
    $pages[$id]=$existing?$existing[0]->ID:0;
}
$result=array();
foreach($config['installed']as$id=>$form){
    if($id===($config['primary_form']??''))continue;
    $page_id=$pages[$id];
    if(!$page_id){
        $page_id=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$form['service_label'],'post_name'=>'service-'.sanitize_title(strtolower(str_replace('_','-',$form['service_code']))),'post_content'=>'','meta_input'=>array('_netchat_lab_form_id'=>$id)),true);
        if(is_wp_error($page_id))throw new RuntimeException($page_id->get_error_message());
    }
    $url=get_permalink($page_id);$binding=netchat_service_admin_binding(array('url'=>$url,'page_id'=>(string)$page_id),$config,$form['service_code']);
    if(is_wp_error($binding))throw new RuntimeException($binding->get_error_message());
    $config['bindings'][$form['service_code']]=$binding;
    $result[]=array('form_id'=>$id,'service_code'=>$form['service_code'],'title'=>$form['service_label'],'page_id'=>$page_id,'url'=>$url);
}
if(!netchat_service_admin_commit($old,$config))throw new RuntimeException('Bindings changed concurrently; new pages were kept. Reload before retry.');
WP_CLI::line(wp_json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
WP_CLI::success('Empty service pages are published and bound; existing page content and form schemas were preserved.');
