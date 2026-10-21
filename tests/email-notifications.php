<?php
/* Pure synthetic tests. No WordPress bootstrap, database, or mail transport. */
$core=getenv('NETCHAT_TEST_CORE');
if(!$core||!is_file($core.'/wp-includes/version.php'))throw new RuntimeException('Existing test core path required; no bootstrap is performed');
define('ABSPATH',rtrim($core,'/').'/');
$base=dirname(__DIR__);
if(is_file(ABSPATH.'wp-includes/PHPMailer/PHPMailer.php')){
 require ABSPATH.'wp-includes/PHPMailer/Exception.php';
 require ABSPATH.'wp-includes/PHPMailer/PHPMailer.php';
}else{
 require ABSPATH.'wp-includes/class-phpmailer.php';
 class_alias('PHPMailer','PHPMailer\PHPMailer\PHPMailer');
}
$hooks=array();$checks=0;$sent=array();$mode='success';$legacy_timezone=false;
function check($condition,$label){global$checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function wp_strip_all_tags($s){return strip_tags($s);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function esc_url($s){return preg_match('~^https?://~i',$s)?htmlspecialchars($s,ENT_QUOTES,'UTF-8'):'';}
function get_bloginfo($key){return 'Демонстрационный сайт';}
function get_option($key,$default=''){return $key==='timezone_string'?'Europe/Moscow':($key==='gmt_offset'?3:$default);}
function get_date_from_gmt($s,$f){return(new DateTimeImmutable($s,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Moscow'))->format($f);}
function add_action($name,$fn,$priority=10,$argc=1){global$hooks;$hooks[$name][$priority][]=$fn;}
function remove_action($name,$fn,$priority=10){global$hooks;foreach($hooks[$name][$priority]??array() as$k=>$h)if($h===$fn)unset($hooks[$name][$priority][$k]);}
function hook($name,$arg){global$hooks;$list=$hooks[$name]??array();ksort($list);foreach($list as$group)foreach($group as$fn)$fn($arg);}
function hook_count(){global$hooks;return array_sum(array_map('count',$hooks['phpmailer_init']??array()));}
function wp_mail($to,$subject,$body,$headers){
 global$sent,$mode,$phpmailer;
 if($mode==='short')return true;
 if(!isset($phpmailer))$phpmailer=new PHPMailer\PHPMailer\PHPMailer(true);
 $phpmailer->clearAllRecipients();$phpmailer->setFrom('site@example.test','Демонстрационный сайт');
 foreach($to as$email)$phpmailer->addAddress($email);
 $phpmailer->Subject=$subject;$phpmailer->Body=$body;$phpmailer->CharSet='UTF-8';$phpmailer->isHTML(true);
 hook('phpmailer_init',$phpmailer);
 if($mode==='throw')throw new RuntimeException('Synthetic transport exception');
 $phpmailer->preSend();$sent[]=array('to'=>$to,'subject'=>$subject,'html'=>$body,'text'=>$phpmailer->AltBody,'mime'=>$phpmailer->getSentMIMEMessage(),'headers'=>$headers);
 return $mode!=='false';
}
function netchat_leads_table(){return 'synthetic_leads';}
function netchat_get_config(){global$config;return array('leads'=>array('email'=>$config));}
function is_email($s){return filter_var($s,FILTER_VALIDATE_EMAIL);}
function is_wp_error($v){return false;}
function netchat_leads_get($id){global$lead;return $lead;}
function admin_url($path){return 'https://example.test/wp-admin/'.$path;}
function add_query_arg($args,$url){return$url.'?'.http_build_query($args);}
function wp_schedule_single_event($time,$event){global$scheduled;$scheduled[]=array($time,$event);}
class FakeDB{
 function prepare($sql,...$args){return$sql;}
 function query($sql){global$lead;if($lead['notification_status']!=='pending')return 0;$lead['notification_attempts']++;return 1;}
 function update($table,$data,$where,$formats=null,$whereformats=null){global$lead;$lead=array_merge($lead,$data);return 1;}
}
$wpdb=new FakeDB();
require $base.'/includes/leads-notifications.php';
$service=array('service_code'=>'synthetic','snapshot'=>array('title'=>'Обсудить задачу','contact'=>array('name'=>'Как к вам обращаться?','value'=>'Контакт для ответа'),'fields'=>array('goal'=>array('label'=>'Что нужно изменить?'),'pick'=>array('label'=>'Выбранные варианты','options'=>array('a'=>'Первый вариант','b'=>'Второй вариант')))),'contact'=>array('name'=>'Тестовый посетитель','company'=>'Демо-проект','value'=>'visitor@example.test','note'=>'После обеда'),'answers'=>array('goal'=>"Первый абзац\nВторой абзац",'pick'=>array('a','b')),'context'=>array('page_url'=>'https://example.test/task','cta_position'=>'widget','referrer_origin'=>'https://example.test','utm'=>array('utm_source'=>'demo')));
$lead=array('id'=>1,'public_code'=>'Т7Е-1234','created_at'=>'2026-10-20 22:17:18','submission_json'=>json_encode($service,JSON_UNESCAPED_UNICODE),'name'=>'Тестовый посетитель','email'=>'visitor@example.test','phone'=>'+70000000000','message'=>'Скрытое сообщение','status'=>'new','notification_status'=>'pending','notification_attempts'=>0);
$config=array('enabled'=>1,'recipients'=>array('owner@example.test'),'include_message'=>0);
$message=netchat_leads_build_email($lead,$config,'https://example.test/wp-admin/admin.php?page=netchat-leads&lead=1');
check($message['subject']==='[NetChat] Новая заявка №Т7Е-1234','Russian subject with public reference');
check(strpos($message['text'],'21.10.2026 01:17 (Europe/Moscow)')!==false,'UTC converted into configured site timezone');
foreach(array('Обсудить задачу','Как к вам обращаться?','Тестовый посетитель','Демо-проект','visitor@example.test','После обеда','Что нужно изменить?','Первый абзац','Второй абзац','Первый вариант, Второй вариант','Виджет сайта','https://example.test/task','utm_source','demo')as$value){check(strpos($message['html'],esc_html($value))!==false,'HTML includes synthetic field '.$value);check(strpos($message['text'],$value)!==false,'plain includes synthetic field '.$value);}
foreach(array('Contact request','New contact','Created (UTC)','Open in the protected','Message:')as$english)check(strpos($message['text'].$message['html'].$message['subject'],$english)===false,'no English service copy');
check(strpos($message['html'],'<br>')!==false,'answer line breaks retained');
check(strpos($message['html'],'<img')===false&&strpos($message['html'],'<script')===false,'no external images or scripts');
check(strpos($message['html'],'javascript:')===false,'no script URI');
check(strpos($message['html'],'&amp;lead=1')!==false,'admin link escaped');
check(strpos($message['text'],'Скрытое сообщение')===false,'service uses immutable snapshot not legacy text');
$unsafe=$service;$unsafe['contact']['name']='<img src=x onerror=alert(1)>';$unsafe['snapshot']['fields']['goal']['label']='<script>label</script>';$unsafe['answers']['goal']="<svg onload=alert(1)>\nhttps://example.test/";
$unsafeLead=$lead;$unsafeLead['submission_json']=json_encode($unsafe);$unsafeLead['public_code']="Тест\r\nBcc: victim@example.test";
$hostile=netchat_leads_build_email($unsafeLead,$config,'javascript:alert(1)');
check(strpos($hostile['html'],'<img')===false&&strpos($hostile['html'],'<svg')===false&&strpos($hostile['html'],'<script')===false,'all visitor fields and labels escaped');
check(strpos($hostile['html'],'&lt;img')!==false,'hostile value retained as text');
check(strpos($hostile['subject'],"\r")===false&&strpos($hostile['subject'],"\n")===false,'header controls removed');
check(strpos($hostile['html'],'href="javascript:')===false,'unsafe CTA scheme removed');
$legacy=$lead;$legacy['submission_json']='';$legacy['page_url']='https://example.test/contact';
$private=netchat_leads_build_email($legacy,$config,'https://example.test/admin');
check(strpos($private['html'].$private['text'],'Скрытое сообщение')===false,'legacy include_message off respected');
$visible=netchat_leads_build_email($legacy,array('include_message'=>1),'https://example.test/admin');
check(strpos($visible['html'],'Скрытое сообщение')!==false&&strpos($visible['text'],'Скрытое сообщение')!==false,'legacy include_message on in both alternatives');
$legacy['submission_json']='{broken';check(netchat_leads_build_email($legacy,$config,'https://example.test/admin')['subject']===$message['subject'],'malformed snapshot does not crash');
foreach(array('success','false','throw','short')as$case){
 $mode=$case;$before=hook_count();$caught=false;
 try{$ok=netchat_leads_mail_message(array('owner@example.test'),$message,array('Content-Type: text/html; charset=UTF-8'));}catch(Throwable$e){$caught=true;}
 check(hook_count()===$before,'scoped hook removed after '.$case);
 check(!isset($phpmailer)||$phpmailer->AltBody==='','no leaked personal AltBody after '.$case);
 check($caught===($case==='throw'),'expected exception semantics '.$case);
 if(!$caught)check($ok===($case!=='false'),'result preserved '.$case);
}
check(strpos($sent[0]['mime'],'multipart/alternative')!==false,'real PHPMailer multipart alternative');
check(strpos($sent[0]['mime'],'Content-Type: text/plain; charset=UTF-8')!==false&&strpos($sent[0]['mime'],'Content-Type: text/html; charset=UTF-8')!==false,'MIME has both UTF-8 parts');
check($sent[0]['text']===$message['text'],'real mailer received plain fallback');
// Ensure only this exact Body+Subject are affected, and existing plugin hooks survive.
$mode='success';
$others=array((object)array('Body'=>$message['html'],'Subject'=>'Other','AltBody'=>'Existing alternative'),(object)array('Body'=>'Unrelated body','Subject'=>$message['subject'],'AltBody'=>'Existing alternative'));
$otherhook=function($mailer)use($others){foreach($GLOBALS['hooks']['phpmailer_init'][PHP_INT_MAX]??array()as$fn)foreach($others as$other)$fn($other);};
add_action('phpmailer_init',$otherhook,5);$before=hook_count();netchat_leads_mail_message(array('owner@example.test'),$message,array());check(hook_count()===$before,'other plugin hook preserved');remove_action('phpmailer_init',$otherhook,5);
foreach($others as$other)check($other->AltBody==='Existing alternative','only exact Body AND Subject may receive the lead alternative');
$phpmailer->AltBody='Previous plugin alternative';netchat_leads_mail_message(array('owner@example.test'),$message,array());check($phpmailer->AltBody==='Previous plugin alternative','restore pre-existing nonempty AltBody');$phpmailer->AltBody='';
wp_mail(array('owner@example.test'),'Unrelated mail','Unrelated body',array());check(end($sent)['text']==='','subsequent unrelated mail carries no lead text');
$mode='success';$lead['notification_status']='pending';$lead['notification_attempts']=0;
check(netchat_leads_send_one_notification(1)===true&&$lead['notification_status']==='sent','existing notification path records success');
$last=end($sent);check($last['subject']===$message['subject']&&strpos($last['html'],'Открыть заявку')!==false,'notification path uses Russian HTML template');
check(in_array('Content-Type: text/html; charset=UTF-8',$last['headers'],true),'HTML header scoped to notification');
check(in_array('Reply-To: visitor@example.test',$last['headers'],true),'visitor remains Reply-To');
check(count(array_filter($last['headers'],function($h){return stripos($h,'From:')===0;}))===0,'site From hooks not overridden');
$count=count($sent);check(netchat_leads_send_one_notification(1)===false&&count($sent)===$count,'already sent not duplicated');
foreach(array('false','throw')as$case){$mode=$case;$lead['notification_status']='pending';$lead['notification_attempts']=0;check(netchat_leads_send_one_notification(1)===false&&$lead['notification_status']==='pending'&&$lead['notification_error']==='transport_failed'&&$lead['notification_attempts']===1,'transport backoff retained '.$case);check(hook_count()===0&&$phpmailer->AltBody==='','cleanup on worker failure '.$case);}
$mode='success';$lead['notification_status']='pending';$lead['notification_attempts']=0;$lead['email']="bad\r\nBcc:bad@example.test";netchat_leads_send_one_notification(1);check(count(end($sent)['headers'])===1,'invalid visitor address cannot inject header');
// Render only synthetic examples; the screenshot supplied by the owner is never read here.
if(in_array('--preview',$argv??array(),true))echo json_encode(array('service'=>$message,'contact'=>$visible,'unsafe'=>$hostile),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
else echo json_encode(array('status'=>'PASS','checks'=>$checks,'wordpress_bootstrapped'=>false,'database_queries'=>0,'external_mail'=>0),JSON_UNESCAPED_UNICODE)."\n";
