<?php
/** WordPress personal data tools and bounded retention cleanup. */
if(!defined('ABSPATH'))exit;
add_filter('wp_privacy_personal_data_exporters',function($items){$items['netchat-leads']=array('exporter_friendly_name'=>'NetChat contact requests','callback'=>'netchat_leads_privacy_export');return $items;});
add_filter('wp_privacy_personal_data_erasers',function($items){$items['netchat-leads']=array('eraser_friendly_name'=>'NetChat contact requests','callback'=>'netchat_leads_privacy_erase');return $items;});
add_action('netchat_leads_retention_cleanup','netchat_leads_retention_cleanup');
add_action('init',function(){if(!wp_next_scheduled('netchat_leads_retention_cleanup'))wp_schedule_event(time()+120,'daily','netchat_leads_retention_cleanup');});
add_action('admin_init',function(){
    if(function_exists('wp_add_privacy_policy_content'))wp_add_privacy_policy_content('NetChat','<p>При включённой форме сайт сохраняет контактные поля, сообщение, контекст, очищенный адрес страницы, UTM и сведения о согласии. Выбранные владельцем сайта получатели могут получать email: для обычной контактной формы письмо содержит ссылку в защищённую админку, текст сообщения включается отдельной настройкой; для сервисных форм письмо содержит контакты, все сохранённые ответы с подписями из снимка, источник и ссылку независимо от этой настройки. Сроки хранения задаёт владелец сайта (исходно 180 дней для обычных заявок и 30 для спама). Инструменты WordPress ищут по email; обращения только с телефоном или Telegram требуют подтверждённого ручного поиска администратором. Удаление не удаляет ранее отправленные письма и резервные копии хостинга. В заявку не записываются IP и полный User-Agent. Адаптируйте этот текст к фактическим настройкам сайта.</p>');
});
function netchat_leads_privacy_rows($email,$offset=0){
    global $wpdb;if(!is_string($email)||!is_email($email))return array();
    $previous_errors=$wpdb->suppress_errors(true);
    try{$rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.netchat_leads_table().' WHERE email=%s ORDER BY id ASC LIMIT 50 OFFSET %d',$email,max(0,(int)$offset)),ARRAY_A);}
    finally{$wpdb->suppress_errors($previous_errors);}
    return $wpdb->last_error?new WP_Error('netchat_privacy_storage','Request storage is unavailable.'):$rows;
}
function netchat_leads_privacy_export($email,$page=1){
    $rows=netchat_leads_privacy_rows($email,(max(1,(int)$page)-1)*50);if(is_wp_error($rows))return $rows;
    $items=array();$labels=array('name'=>'Name','email'=>'Email','phone'=>'Phone','company'=>'Company','message'=>'Message','context'=>'Context','page_url'=>'Page','page_title'=>'Page title','referrer'=>'Referrer origin','utm'=>'UTM','status'=>'Status','created_at'=>'Created UTC','updated_at'=>'Updated UTC','consent_presented'=>'Consent presented','consent_accepted'=>'Consent accepted','consent_version'=>'Consent version','consent_at'=>'Consent UTC');
    foreach($rows as$row){$data=array();foreach($labels as$key=>$label)if(isset($row[$key])&&$row[$key]!=='')$data[]=array('name'=>$label,'value'=>(string)$row[$key]);
        if(!empty($row['submission_json']))$data[]=array('name'=>'Service form answers, contact, consent and source snapshot','value'=>$row['submission_json']);
        $items[]=array('group_id'=>'netchat-leads','group_label'=>'NetChat contact requests','item_id'=>'netchat-lead-'.$row['id'],'data'=>$data);}
    return array('data'=>$items,'done'=>count($rows)<50);
}
function netchat_leads_privacy_erase($email,$page=1){
    // Select the first remaining batch, avoiding skipped rows after deletion.
    $rows=netchat_leads_privacy_rows($email);$removed=false;$retained=false;
    if(is_wp_error($rows))return array('items_removed'=>false,'items_retained'=>true,'messages'=>array('NetChat storage is unavailable; ask the administrator to retry.'),'done'=>true);
    foreach($rows as$row){$result=netchat_leads_delete((int)$row['id']);if(is_wp_error($result)||!$result)$retained=true;else$removed=true;}
    return array('items_removed'=>$removed,'items_retained'=>$retained,'messages'=>$retained?array('Some NetChat requests could not be removed. Ask the administrator to retry.'):array(),'done'=>$retained||count($rows)<50);
}
function netchat_leads_retention_cleanup(){
    global $wpdb;$c=netchat_leads_config();$normal=gmdate('Y-m-d H:i:s',time()-max(1,min(3650,(int)$c['retention_days']))*DAY_IN_SECONDS);$spam=gmdate('Y-m-d H:i:s',time()-max(1,min(3650,(int)$c['spam_retention_days']))*DAY_IN_SECONDS);
    $ids=$wpdb->get_col($wpdb->prepare('SELECT id FROM '.netchat_leads_table()." WHERE (status='spam' AND created_at<%s) OR (status<>'spam' AND created_at<%s) ORDER BY id ASC LIMIT 100",$spam,$normal));
    $failed=false;foreach($ids as$id){$result=netchat_leads_delete((int)$id);if(is_wp_error($result)||!$result)$failed=true;}
    $wpdb->query($wpdb->prepare('DELETE FROM '.netchat_leads_keys_table().' WHERE expires_at<%s LIMIT 100',netchat_leads_now()));
    $wpdb->query($wpdb->prepare('DELETE FROM '.netchat_leads_limits_table().' WHERE window_start<%d LIMIT 1000',time()-DAY_IN_SECONDS));
    if(count($ids)===100&&!$failed)wp_schedule_single_event(time()+60,'netchat_leads_retention_cleanup');
}
