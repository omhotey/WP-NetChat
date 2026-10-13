<?php
/** Isolated real WordPress lifecycle, dynamic schema and submission checks. */
if(!defined('WP_CLI')||!WP_CLI)exit(1);
global $wpdb;
$original_options=$wpdb->options;$original_prefix=$wpdb->prefix;$prefix='nc_form_life_'.bin2hex(random_bytes(5)).'_';
$wpdb->query("CREATE TEMPORARY TABLE `{$prefix}options` LIKE `$original_options`");$wpdb->options=$prefix.'options';$wpdb->prefix=$prefix;wp_cache_flush();
$fixture=tempnam(sdemo_get_temp_dir(),'ncf-life-');unlink($fixture);define('NETCHAT_DATA',$fixture);
require dirname(__DIR__).'/netchat.php';
require_once dirname(__DIR__).'/includes/service-forms-admin.php';
$GLOBALS['life_checks']=0;
function life_assert($ok,$label){if(!$ok)throw new RuntimeException($label);$GLOBALS['life_checks']++;}
function life_post($schema){
    $post=array('texts'=>array(),'fields'=>array(),'order'=>array());
    foreach(array('title','intro','submit_text','success_text')as$key)$post['texts'][$key]=$schema[$key];
    foreach($schema['fields']as$field){$raw=array('label'=>$field['label'],'placeholder'=>$field['placeholder']??'','helper'=>$field['helper']??'');if($field['required'])$raw['required']='1';if(isset($field['options']))$raw['options']=$field['options'];$post['fields'][$field['key']]=$raw;$post['order'][$field['step']][]=$field['key'];}
    return $post;
}
try{
    add_option('home','https://example.test');add_option('siteurl','https://example.test');netchat_activate();
    $cfg=netchat_get_config();$cfg['leads']['enabled']=1;$cfg['leads']['email']['enabled']=0;life_assert(netchat_save_config($cfg,netchat_get_revision())===true,'enable isolated leads');
    netchat_service_install_presets();$config=netchat_service_forms_config();$config['enabled']=1;$config['retention_confirmed']=1;
    $config['documents']=array('document_url'=>'https://example.test/consent','policy_url'=>'https://example.test/policy','text_version'=>'1','document_version'=>'1','policy_version'=>'1');update_option('netchat_service_forms',$config,false);
    life_assert(is_wp_error(netchat_service_admin_lifecycle($config,'create',array('form_name'=>''))),'empty name rejected');
    life_assert(is_wp_error(netchat_service_admin_lifecycle($config,'create',array('form_name'=>'Test','template_id'=>'bogus'))),'unknown template rejected');
    $created=netchat_service_admin_lifecycle($config,'create',array('form_name'=>'Тестовая форма','question_steps'=>'2'));life_assert(!is_wp_error($created),'create');$id=$created['id'];$config=$created['config'];$schema=$config['installed'][$id];
    life_assert($schema['enabled']===0&&netchat_service_question_steps($schema)===2,'draft two steps');life_assert(!isset($config['bindings'][$schema['service_code']]),'create no binding');
    update_option('netchat_service_forms',$config,false);life_assert(netchat_service_form($id)===null,'draft unavailable to public');
    do_action('rest_api_init');$token_request=new WP_REST_Request('GET','/netchat/v1/leads/token');$token_request->set_param('form_id',$id);life_assert(rest_do_request($token_request)->get_status()===403,'draft token rejected');
    $post=life_post($schema);
    foreach(array('short','text','url','radio','multi')as$i=>$type){$key='q_'.str_pad(dechex($i+1),16,'0',STR_PAD_LEFT);$raw=array('label'=>'Вопрос '.$i,'helper'=>'','placeholder'=>'','type'=>$type,'step'=>'1');if(in_array($type,array('radio','multi'),true))$raw['options']=array('o_111111111111'=>'Первый','o_222222222222'=>'Второй');$post['fields'][$key]=$raw;$post['order'][1][]=$key;}
    $edited=netchat_service_admin_edit_schema($schema,$post);life_assert(!is_wp_error($edited)&&count($edited['fields'])===6,'all five question types added');
    $post=life_post($edited);$starter=$schema['fields'][0]['key'];$post['deleted']=array($starter);unset($post['fields'][$starter]);$post['order'][1]=array_values(array_diff($post['order'][1],array($starter)));
    $edited=netchat_service_admin_edit_schema($edited,$post);life_assert(!is_wp_error($edited)&&count($edited['fields'])===5,'question deletion');
    $post=life_post($edited);$radio=$edited['fields'][3]['key'];$post['fields'][$radio]['options']['o_333333333333']='Третий';$edited=netchat_service_admin_edit_schema($edited,$post);life_assert(!is_wp_error($edited)&&count($edited['fields'][3]['options'])===3,'option addition');
    $post=life_post($edited);unset($post['fields'][$radio]['options']['o_111111111111']);$post['fields'][$radio]['deleted_options']=array('o_111111111111');$edited=netchat_service_admin_edit_schema($edited,$post);life_assert(!is_wp_error($edited)&&count($edited['fields'][3]['options'])===2,'option deletion');
    $post=life_post($edited);unset($post['fields'][$radio]['options']['o_222222222222']);life_assert(is_wp_error(netchat_service_admin_edit_schema($edited,$post)),'one option rejected');
    $config['installed'][$id]=$edited;$config=netchat_service_admin_lifecycle($config,'toggle',array('preset_id'=>$id,'target_enabled'=>'1'))['config'];update_option('netchat_service_forms',$config,false);$form=netchat_service_form($id);life_assert($form!==null,'publish');
    $answers=array();foreach($form['fields']as$field)$answers[$field['key']]=$field['type']==='multi'?array('o_111111111111'):($field['type']==='radio'?'o_222222222222':($field['type']==='url'?'https://example.test/product':'Синтетический ответ'));
    $data=array('form_id'=>$id,'service_code'=>$form['service_code'],'schema_version'=>$form['schema_version'],'answers'=>$answers,'contact'=>array('name'=>'Синтетический посетитель','channel'=>'email','value'=>'synthetic@example.test'),'consent'=>array('accepted'=>true),'context'=>array('page_url'=>'https://example.test/page','origin_service'=>$form['service_code'],'entrypoint'=>'service_cta','cta_position'=>'hero'));
    $nonce=(time()-3).'.'.bin2hex(random_bytes(16));$data['token']=$nonce.'.'.netchat_leads_token_signature($nonce.'|'.$id.'|'.$form['schema_version']);$data['request_id']=wp_generate_uuid4();$data['website']='';
    $request=new WP_REST_Request('POST','/netchat/v1/leads');$request->set_header('content-type','application/json');$request->set_body(wp_json_encode($data));$response=rest_do_request($request);life_assert($response->get_status()===201,'custom public submit: '.wp_json_encode($response->get_data()));$lead_id=$response->get_data()['submission_id'];$lead=netchat_leads_get($lead_id);$snapshot=$lead['submission_json'];
    $config['bindings'][$form['service_code']]=array('url'=>'https://example.test/page','page_id'=>0);
    $copy=netchat_service_admin_lifecycle($config,'duplicate',array('preset_id'=>$id));life_assert(!is_wp_error($copy),'duplicate');$copy_id=$copy['id'];$config=$copy['config'];
    $primary_fixture=$config;$primary_fixture['primary_form']=$id;
    $primary_copy=netchat_service_admin_lifecycle($primary_fixture,'duplicate',array('preset_id'=>$id));life_assert($primary_copy['config']['primary_form']===$id,'copy does not replace the common form');
    $primary_removed=netchat_service_admin_lifecycle($primary_fixture,'delete',array('preset_id'=>$id,'confirm_delete'=>'1'));life_assert($primary_removed['config']['primary_form']==='','delete clears common marker');
    life_assert($copy_id!==$id&&$config['installed'][$copy_id]['service_code']!==$form['service_code'],'copy independent identity');life_assert($config['installed'][$copy_id]['enabled']===0&&!isset($config['bindings'][$config['installed'][$copy_id]['service_code']]),'copy disabled no binding');life_assert($config['installed'][$copy_id]['fields']===$form['fields'],'copy preserves questions');
    $config=netchat_service_admin_lifecycle($config,'toggle',array('preset_id'=>$id,'target_enabled'=>'0'))['config'];update_option('netchat_service_forms',$config,false);life_assert(netchat_service_form($id)===null,'disable');life_assert(rest_do_request($token_request)->get_status()===403,'disabled token rejected');life_assert(netchat_leads_shortcode(array('service'=>$form['service_code']))==='','disabled shortcode hidden');
    life_assert(is_wp_error(netchat_service_admin_lifecycle($config,'delete',array('preset_id'=>$id))),'delete requires confirmation');
    $removed=netchat_service_admin_lifecycle($config,'delete',array('preset_id'=>$id,'confirm_delete'=>'1'));$config=$removed['config'];update_option('netchat_service_forms',$config,false);life_assert(!isset($config['installed'][$id],$config['bindings'][$form['service_code']]),'form and binding removed');life_assert(isset($config['deleted'][$id]),'history label retained');life_assert(netchat_leads_get($lead_id)['submission_json']===$snapshot,'submission immutable after disable and deletion');life_assert(rest_do_request($token_request)->get_status()===403,'deleted token rejected');
    $replay=rest_do_request($request);life_assert($replay->get_status()!==201,'deleted form cannot produce new submission');
    $builtin_id='nc_primary_v1';$version=$config['installed'][$builtin_id]['schema_version'];$config=netchat_service_admin_lifecycle($config,'delete',array('preset_id'=>$builtin_id,'confirm_delete'=>'1'))['config'];$config=netchat_service_admin_lifecycle($config,'install',array())['config'];life_assert($config['installed'][$builtin_id]['schema_version']>$version,'builtin restore new version');
    // Deleting a source used by remaining conditional fields is rejected.
    $preset=json_decode(file_get_contents(__DIR__.'/fixtures-demo-forms.json'),true)['demo_test_a_v1'];$post=life_post($preset);$post['deleted']=array('stage');unset($post['fields']['stage']);$post['order'][1]=array_values(array_diff($post['order'][1],array('stage')));life_assert(is_wp_error(netchat_service_admin_edit_schema($preset,$post)),'conditional dependency deletion rejected');
    // Presentation order no longer suppresses an active conditional answer on the server.
    $preset['fields']=array_reverse($preset['fields']);$errors=array();$parsed=netchat_service_answers(array('stage'=>'prototype','idea'=>'Достаточно длинный ответ','next_result'=>'unknown','existing_work'=>'Тестовый вариант 4','product_url'=>'https://example.test/p'),$preset,$errors);life_assert(!$errors&&isset($parsed['existing_work'],$parsed['product_url']),'reordered conditions validate independently');
    WP_CLI::success($GLOBALS['life_checks'].' isolated form lifecycle assertions passed.');
}finally{
    foreach(array(netchat_leads_kedemo_table(),netchat_leads_limits_table(),netchat_leads_table())as$table){if(strpos($table,$prefix)!==0)throw new RuntimeException('Unsafe test cleanup');$wpdb->query("DROP TABLE IF EXISTS `$table`");}
    if(file_exists($fixture))unlink($fixture);$wpdb->options=$original_options;$wpdb->prefix=$original_prefix;wp_cache_flush();
}
