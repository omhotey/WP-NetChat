<?php
/** WP-CLI --skip-plugins=netchat eval-file. Isolated options and database prefix. */
if(!defined('ABSPATH')||!defined('WP_CLI')||!WP_CLI)exit(1);
if(function_exists('netchat_get_config'))WP_CLI::error('Run with --skip-plugins=netchat.');
global $wpdb;
$original_options=$wpdb->options;$original_prefix=$wpdb->prefix;$isolated='nc_leads_test_'.bin2hex(random_bytes(5)).'_';$options=$isolated.'options';
if(false===$wpdb->query("CREATE TEMPORARY TABLE `$options` LIKE `$original_options`"))WP_CLI::error('Cannot create isolated options.');
$wpdb->options=$options;$wpdb->prefix=$isolated;wp_cache_flush();
$fixture=tempnam(sys_get_temp_dir(),'ncl-json-');unlink($fixture);define('NETCHAT_DATA',$fixture);
require dirname(__DIR__).'/netchat.php';
$checks=0;
function ncl_assert($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function ncl_payload($replace=array()){
    $nonce=time().'.'.bin2hex(random_bytes(16));
    return array_merge(array('request_key'=>bin2hex(random_bytes(16)),'token'=>$nonce.'.'.netchat_leads_token_signature($nonce),'form_key'=>'contact','email'=>'visitor@example.test','message'=>'Help test NetChat','name'=>'Test Visitor','page_url'=>'https://example.test/order?secret=password#token','page_title'=>'Example page','referrer'=>'https://referrer.test/path?secret=hidden','context'=>'AUDIT','utm'=>array('utm_source'=>'test'),'website'=>'','consent'=>false),$replace);
}
function ncl_save($c){ncl_assert(true===netchat_save_config($c,netchat_get_revision()),'isolated settings save');}
function ncl_reset_rate(){global $wpdb;$wpdb->query('DELETE FROM '.netchat_leads_limits_table());}
$created=0;$changed=0;$filtered=0;
$fixture_from=function(){return 'netchat-fixture@example.test';};
add_action('netchat_lead_created',function()use(&$created){$created++;},1);
add_action('netchat_lead_status_changed',function()use(&$changed){$changed++;},1);
add_filter('netchat_lead_data',function($row)use(&$filtered){$filtered++;return $row;},1);
try{
    netchat_activate();ncl_assert(true===netchat_leads_install(),'schema install idempotent');
    $c=netchat_get_config();ncl_assert($c['leads']['enabled']===0,'form disabled by default');
    $bad_settings=$c;$bad_settings['leads']['title']='<b></b>';ncl_assert(is_wp_error(netchat_normalize_config($bad_settings)),'markup-only title cannot corrupt stored settings');
    ncl_assert(is_wp_error(netchat_leads_create(ncl_payload())),'disabled server rejection');
    $c['leads']['enabled']=1;$c['leads']['email']['enabled']=0;$c['leads']['fields']['phone']['enabled']=1;$c['leads']['fields']['email']['required']=0;ncl_save($c);
    $payload=ncl_payload();$first=netchat_leads_create($payload);ncl_assert(!is_wp_error($first)&&$first['created'],'insert lead');$id=$first['id'];$row=netchat_leads_get($id);
    ncl_assert($row['page_url']==='https://example.test/order'&&$row['referrer']==='https://referrer.test','clean source URLs');
    ncl_assert(!isset($row['ip'])&&!isset($row['user_agent']),'no raw IP or UA');
    ncl_assert($row['notification_status']==='disabled','persisted disabled email');
    $repeat=netchat_leads_create($payload);ncl_assert(!is_wp_error($repeat)&&!$repeat['created']&&$repeat['id']===$id,'idempotent replay');
    ncl_assert($created===1&&$filtered===1,'replay invokes no hooks');
    $c['leads']['consent']['version']='revised';ncl_save($c);$repeat=netchat_leads_create($payload);ncl_assert(!is_wp_error($repeat)&&!$repeat['created'],'settings change does not alter replay fingerprint');
    $other=$payload;$other['message']='Changed';$conflict=netchat_leads_create($other);ncl_assert(is_wp_error($conflict)&&$conflict->get_error_data()['status']===409,'changed replay conflict');
    foreach(array(array('email'=>'bad'),array('email'=>'','phone'=>''),array('name'=>array('x')),array('name'=>str_repeat('Я',191)),array('token'=>'bad'),array('website'=>'spam'))as$bad)ncl_assert(is_wp_error(netchat_leads_create(ncl_payload($bad))),'invalid input rejected');
    $expired=(time()-1300).'.'.bin2hex(random_bytes(16));ncl_assert(is_wp_error(netchat_leads_create(ncl_payload(array('token'=>$expired.'.'.netchat_leads_token_signature($expired))))),'expired token');
    ncl_assert(true===netchat_leads_update_status($id,'viewed'),'explicit status change');ncl_assert(true===netchat_leads_update_status($id,'viewed')&&$changed===1,'unchanged status no hook');
    ncl_assert(is_wp_error(netchat_leads_update_status($id,'bad')),'invalid status');
    $phone=netchat_leads_create(ncl_payload(array('email'=>'','phone'=>'+7 (900) 123-45-67')));ncl_assert(!is_wp_error($phone),'phone alternative');
    $invalid_hook=function($row){$row['email']='bad';return $row;};add_filter('netchat_lead_data',$invalid_hook);ncl_assert(is_wp_error(netchat_leads_create(ncl_payload())),'filter revalidated');remove_filter('netchat_lead_data',$invalid_hook);
    $throwing=function(){throw new RuntimeException('fixture hook failure');};add_action('netchat_lead_created',$throwing,99);$stored=netchat_leads_create(ncl_payload());remove_action('netchat_lead_created',$throwing,99);ncl_assert(!is_wp_error($stored)&&netchat_leads_get($stored['id']),'post-insert exception preserves lead');
    ncl_reset_rate();$c['leads']['consent']['enabled']=1;$c['leads']['consent']['required']=1;$c['leads']['consent']['version']='test-v1';ncl_save($c);
    ncl_assert(is_wp_error(netchat_leads_create(ncl_payload())),'consent enforced');$agreed=netchat_leads_create(ncl_payload(array('consent'=>true)));ncl_assert(!is_wp_error($agreed),'consent accepted');$row=netchat_leads_get($agreed['id']);ncl_assert($row['consent_version']==='test-v1'&&!empty($row['consent_at']),'consent UTC/version');
    $c['leads']['consent']['enabled']=0;ncl_save($c);
    ncl_assert(true===netchat_leads_delete($id)&&netchat_leads_get($id)===null,'deletion');$replay=netchat_leads_create($payload);ncl_assert(is_wp_error($replay)&&$replay->get_error_data()['status']===410,'deleted request cannot resurrect');
    ncl_reset_rate();$_SERVER['REMOTE_ADDR']='198.51.100.12';for($i=0;$i<5;$i++)ncl_assert(!is_wp_error(netchat_leads_create(ncl_payload())),'allowed submission');$limited=netchat_leads_create(ncl_payload());ncl_assert(is_wp_error($limited)&&$limited->get_error_data()['status']===429,'sixth submission limited');ncl_assert((int)$wpdb->get_var('SELECT COUNT(*) FROM '.netchat_leads_limits_table())<=2050,'bounded counters');
    ncl_reset_rate();$table=netchat_leads_table();$fault=function($sql)use($table){return strpos($sql,"INSERT INTO `$table` ")===0?'BROKEN SQL FOR TEST':$sql;};$suppressed=$wpdb->suppress_errors(true);add_filter('query',$fault);$failed=netchat_leads_create(ncl_payload());remove_filter('query',$fault);$wpdb->suppress_errors($suppressed);ncl_assert(is_wp_error($failed)&&$failed->get_error_data()['status']===503,'insert failure retryable');
    // Real wp_mail uses deterministic local sendmail fixtures; no external delivery.
    // WP5 validates From before phpmailer_init; CLI SERVER_NAME may be empty.
    add_filter('wp_mail_from',$fixture_from);
    $c['leads']['email']['enabled']=1;$c['leads']['email']['recipients']=array('owner@example.test');ncl_save($c);ncl_reset_rate();$queued=netchat_leads_create(ncl_payload());ncl_assert(!is_wp_error($queued),'pending notification saved');$qid=$queued['id'];
    $fail_mail=function($mailer){$mailer->isSendmail();$mailer->Sendmail='/bin/false';};add_action('phpmailer_init',$fail_mail);try{netchat_leads_send_one_notification($qid);}finally{remove_action('phpmailer_init',$fail_mail);}$mailrow=netchat_leads_get($qid);ncl_assert($mailrow['notification_status']==='pending'&&(int)$mailrow['notification_attempts']===1&&!empty($mailrow['notification_next_attempt_at']),'mail failure retains lead/backoff');
    $wpdb->update($table,array('notification_next_attempt_at'=>null),array('id'=>$qid));$mail_calls=0;$sent_mail=function($mailer)use(&$mail_calls){$mail_calls++;$mailer->isSendmail();$mailer->Sendmail='/bin/true';};add_action('phpmailer_init',$sent_mail);try{netchat_leads_send_one_notification($qid);netchat_leads_send_one_notification($qid);}finally{remove_action('phpmailer_init',$sent_mail);}ncl_assert(netchat_leads_get($qid)['notification_status']==='sent'&&$mail_calls===1,'successful handoff not repeated');
    ncl_assert(true===netchat_leads_update_status($qid,'spam')&&netchat_leads_get($qid)['notification_status']==='disabled','spam cancels pending mail');
    $previous_user=$GLOBALS['current_user'];$admin=new WP_User();$admin->ID=999999;$admin->allcaps=array('manage_options'=>true,'read'=>true);$GLOBALS['current_user']=$admin;
    $render_settings=$c['leads'];$render_settings['title']='<script>unsafe()</script>';ob_start();netchat_leads_render_settings($render_settings);$html=ob_get_clean();ncl_assert(strpos($html,'<script>unsafe()')===false&&strpos($html,'&lt;script&gt;')!==false,'admin settings escaped');
    $wpdb->update($table,array('name'=>'<script>unsafe</script>'),array('id'=>$qid));$saved_get=$_GET;$_GET=array('lead'=>$qid);ob_start();netchat_leads_admin_page();$html=ob_get_clean();$_GET=$saved_get;
    ncl_assert(strpos($html,'<script>unsafe</script>')===false&&strpos($html,'&lt;script&gt;unsafe')!==false,'admin card escapes visitor text');
    ncl_assert(strpos($html,'_wpnonce')!==false&&strpos($html,'method="post"')!==false,'admin mutation requires POST nonce');
    ncl_assert(netchat_leads_get($qid)['status']==='spam','card GET preserves status');
    ncl_assert(true===netchat_leads_update_status($qid,'processed')&&true===netchat_leads_retry_notification($qid,false),'administrator retry');ncl_assert(true===netchat_leads_retry_notification($qid,false),'pending retry idempotent');
    $retry_calls=0;$retry_mail=function($mailer)use(&$retry_calls){$retry_calls++;$mailer->isSendmail();$mailer->Sendmail='/bin/true';};add_action('phpmailer_init',$retry_mail);try{netchat_leads_retry_notification($qid);}finally{remove_action('phpmailer_init',$retry_mail);}ncl_assert($retry_calls===1&&netchat_leads_get($qid)['notification_status']==='sent','manual retry sends without cron');
    $GLOBALS['current_user']=$previous_user;wp_set_current_user(0);ncl_assert(is_wp_error(netchat_leads_retry_notification($qid)),'guest retry forbidden');ob_start();netchat_leads_render_settings($c['leads']);$forbidden=ob_get_clean();ncl_assert($forbidden==='','guest settings renderer denied');
    // 52 rows exercise erasure across batches without OFFSET skips.
    for($i=0;$i<52;$i++){$wpdb->insert($table,array('request_key'=>bin2hex(random_bytes(16)),'request_hash'=>str_repeat('a',64),'form_key'=>'contact','email'=>'privacy@example.test','message'=>'Synthetic privacy fixture','context'=>'','page_url'=>'','page_title'=>'','referrer'=>'','utm'=>'{}','created_at'=>gmdate('Y-m-d H:i:s'),'updated_at'=>gmdate('Y-m-d H:i:s'),'notification_status'=>'disabled'));}
    $export1=netchat_leads_privacy_export('privacy@example.test',1);$export2=netchat_leads_privacy_export('privacy@example.test',2);ncl_assert(count($export1['data'])===50&&!$export1['done']&&count($export2['data'])===2&&$export2['done'],'bounded privacy export');
    $erase1=netchat_leads_privacy_erase('privacy@example.test',1);$erase2=netchat_leads_privacy_erase('privacy@example.test',2);ncl_assert($erase1['items_removed']&&!$erase1['done']&&$erase2['done']&&!netchat_leads_privacy_rows('privacy@example.test'),'privacy erasure skips no rows');
    $wpdb->update($table,array('created_at'=>gmdate('Y-m-d H:i:s',time()-200*DAY_IN_SECONDS)),array('id'=>$qid));netchat_leads_retention_cleanup();ncl_assert(netchat_leads_get($qid)===null,'retention removes expired requests');
    $c['leads']['enabled']=0;ncl_save($c);ncl_assert(is_wp_error(netchat_leads_create(ncl_payload())),'cached form rejected after disabling');
    do_action('rest_api_init');wp_set_current_user(0);ncl_assert(rest_do_request(new WP_REST_Request('GET','/netchat/v1/leads/token'))->get_status()===403,'disabled token issuance');ncl_assert(rest_do_request(new WP_REST_Request('GET','/netchat/v1/leads/1'))->get_status()!==200,'no public lead reading');
    WP_CLI::success($GLOBALS['checks'].' isolated lead and mail/privacy assertions passed.');
}finally{
    remove_filter('wp_mail_from',$fixture_from);
    foreach(array(netchat_leads_keys_table(),netchat_leads_limits_table(),netchat_leads_table())as$table){if(strpos($table,$isolated)!==0)throw new RuntimeException('Unsafe fixture cleanup');$wpdb->query("DROP TABLE IF EXISTS `$table`");}
    if(file_exists($fixture))unlink($fixture);$wpdb->options=$original_options;$wpdb->prefix=$original_prefix;wp_cache_flush();
}
