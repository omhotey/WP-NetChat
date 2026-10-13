<?php
/** Capability and nonce protected lead administration. */
if(!defined('ABSPATH'))exit;
add_action('admin_enqueue_scripts',function($hook){
    if(in_array($hook,array('netchat_page_netchat-lead-settings','netchat_page_netchat-leads'),true))wp_enqueue_style('netchat-leads-admin',NETCHAT_URL.'assets/css/leads-admin.css',array(),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/leads-admin.css'));
    if($hook==='netchat_page_netchat-lead-settings'){
        wp_enqueue_style('netchat-enquiry-editor',NETCHAT_URL.'assets/css/enquiry-editor.css',array('netchat-leads-admin'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/enquiry-editor.css'));
        wp_enqueue_script('netchat-enquiry-editor',NETCHAT_URL.'assets/js/enquiry-editor.js',array('netchat-admin'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/js/enquiry-editor.js'),true);
    }
});
add_action('admin_menu',function(){add_submenu_page('netchat','NetChat — Заявки','Заявки','manage_options','netchat-leads','netchat_leads_admin_page');});
add_action('admin_menu',function(){add_submenu_page('netchat','NetChat — Настройки обращений','Настройки обращений','manage_options','netchat-lead-settings','netchat_leads_settings_page');});
function netchat_leads_settings_page(){
    if(!current_user_can('manage_options'))wp_die('Insufficient permissions.');
    echo '<div class="netchat-standalone-page nc-enquiry-page" data-nc-leads-only><div id="netchat-notice-container"></div>';
    netchat_leads_render_settings(netchat_leads_config());
    echo '<p class="nc-enquiry-opening-link">Форма главной редактируется в <a href="'.esc_url(admin_url('admin.php?page=netchat-service-forms')).'">списке форм</a> и отмечается как «Основная / общая». Эти настройки управляют обычной контактной формой, уведомлениями и хранением заявок.</p>';
    echo '</div>';
}
add_action('admin_post_netchat_lead_action','netchat_leads_admin_action');
function netchat_leads_admin_action(){
    if(!current_user_can('manage_options'))wp_die('Insufficient permissions.','',array('response'=>403));
    $id=isset($_POST['lead_id'])?absint($_POST['lead_id']):0;
    check_admin_referer('netchat_lead_action_'.$id);
    $action=isset($_POST['lead_action'])?sanitize_key(wp_unslash($_POST['lead_action'])):'';
    $result=new WP_Error('invalid_action','Invalid action.');
    if($action==='status')$result=netchat_leads_update_status($id,isset($_POST['lead_status'])?sanitize_key(wp_unslash($_POST['lead_status'])):'');
    elseif($action==='delete')$result=netchat_leads_delete($id);
    elseif($action==='retry')$result=netchat_leads_retry_notification($id);
    $url=add_query_arg(array('page'=>'netchat-leads','notice'=>is_wp_error($result)?'error':'saved'),admin_url('admin.php'));
    if($action==='retry'&&!is_wp_error($result)) {
        $current=netchat_leads_get($id);
        if(is_array($current))$url=add_query_arg('notice','mail_'.$current['notification_status'],$url);
    }
    if($action!=='delete')$url=add_query_arg('lead',$id,$url);
    wp_safe_redirect($url);exit;
}
function netchat_leads_status_labels(){return array('new'=>'Новая','viewed'=>'Просмотрена','processed'=>'Обработана','spam'=>'Спам');}
function netchat_leads_mail_labels(){return array('pending'=>'Ожидает отправки','sent'=>'Передано почтовому механизму','failed'=>'Ошибка отправки','disabled'=>'Уведомления выключены');}
function netchat_leads_admin_date($utc){
    $timestamp=strtotime($utc.' UTC');if(!$timestamp)return $utc;
    $format=get_option('date_format').' '.get_option('time_format');
    return function_exists('wp_date')?wp_date($format,$timestamp):date_i18n($format,$timestamp+(float)get_option('gmt_offset')*HOUR_IN_SECONDS);
}
function netchat_leads_action_form($id,$action,$label,$status='',$primary=false){
    echo '<form class="nc-lead-action" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
    wp_nonce_field('netchat_lead_action_'.$id);
    echo '<input type="hidden" name="action" value="netchat_lead_action"><input type="hidden" name="lead_id" value="'.esc_attr($id).'"><input type="hidden" name="lead_action" value="'.esc_attr($action).'">';
    if($status!=='')echo '<input type="hidden" name="lead_status" value="'.esc_attr($status).'">';
    echo '<button type="submit" class="button'.($primary?' button-primary':'').($action==='delete'?' nc-danger-button':'').'">'.esc_html($label).'</button></form>';
}
function netchat_leads_admin_badge($value,$labels){return '<span class="nc-lead-badge nc-state-'.esc_attr(sanitize_key($value)).'">'.esc_html($labels[$value]??$value).'</span>';}
function netchat_service_submission($row){$data=!empty($row['submission_json'])?json_decode($row['submission_json'],true):null;return is_array($data)?$data:null;}
function netchat_service_admin_answers($data){$lines=array();foreach($data['answers']as$key=>$value){$field=$data['snapshot']['fields'][$key]??array('label'=>$key,'options'=>array());$values=is_array($value)?$value:array($value);$lines[]=$field['label']."\n".implode(', ',array_map(function($v)use($field){return $field['options'][$v]??$v;},$values));}return implode("\n\n",$lines);}
function netchat_leads_admin_panel($title,$fields,$lead){
    echo '<section class="nc-lead-card"><h2>'.esc_html($title).'</h2><table class="nc-detail-table"><tbody>';
    foreach($fields as $key=>$label){
        $value=isset($lead[$key])?(string)$lead[$key]:'';
        if($key==='status')$html=netchat_leads_admin_badge($value,netchat_leads_status_labels());
        elseif($key==='notification_status')$html=netchat_leads_admin_badge($value,netchat_leads_mail_labels());
        else {
            if(in_array($key,array('consent_presented','consent_accepted'),true))$value=$value==='1'?'Да':'Нет';
            if(in_array($key,array('created_at','updated_at','consent_at'),true)&&$value!=='')$value=netchat_leads_admin_date($value);
            if($key==='utm'&&$value==='[]')$value='';
            $html=esc_html($value!==''?$value:'—');
        }
        echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.$html.'</td></tr>';
    }
    echo '</tbody></table></section>';
}
function netchat_leads_admin_page(){
    if(!current_user_can('manage_options'))wp_die('Insufficient permissions.');
    global $wpdb;$labels=netchat_leads_status_labels();$mail_labels=netchat_leads_mail_labels();
    echo '<div class="wrap nc-leads-page"><header class="nc-leads-header"><div><span class="nc-leads-eyebrow">NETCHAT · ОБРАЩЕНИЯ</span><h1>Заявки</h1><p>Контакты и сообщения посетителей в одном месте.</p></div><a class="button" href="'.esc_url(add_query_arg('page','netchat',admin_url('admin.php'))).'">Настройки формы</a></header>';
    if(isset($_GET['notice'])) {
        $notices=array('saved'=>'Изменения сохранены.','error'=>'Действие не выполнено. Повторите попытку; проверьте настройки уведомлений.','mail_sent'=>'Письмо передано почтовому механизму сайта. На стенде проверьте Mailpit.','mail_pending'=>'Не удалось передать письмо. Заявка сохранена; повтор запланирован. Проверьте SMTP сайта.','mail_failed'=>'Попытка отправки не удалась. Проверьте SMTP и получателей.','mail_disabled'=>'Почтовые уведомления выключены.');
        $notice=is_string($_GET['notice'])?sanitize_key($_GET['notice']):'';
        if(isset($notices[$notice]))echo '<div class="notice '.(in_array($notice,array('error','mail_failed','mail_pending'),true)?'notice-error':'notice-success').'"><p>'.esc_html($notices[$notice]).'</p></div>';
    }
    $id=isset($_GET['lead'])?absint($_GET['lead']):0;
    if($id){
        $lead=netchat_leads_get($id);
        if(is_wp_error($lead)||!$lead){echo '<p>Заявка не найдена или хранилище недоступно.</p></div>';return;}
        $service=netchat_service_submission($lead);
        if($service){$lead['message']=netchat_service_admin_answers($service);$lead['context']=$service['snapshot']['title'];echo '<p class="nc-lead-context">'.esc_html($service['snapshot']['title']).' · '.esc_html($service['form_id']).' · Версия схемы '.esc_html($service['schema_version']).'</p>';}
        echo '<a class="nc-leads-back" href="'.esc_url(add_query_arg('page','netchat-leads',admin_url('admin.php'))).'">← Все заявки</a><div class="nc-lead-title"><h2>Заявка #'.esc_html($lead['public_code']).'</h2>'.netchat_leads_admin_badge($lead['status'],$labels).'<span class="nc-lead-created">'.esc_html(netchat_leads_admin_date($lead['created_at'])).'</span></div>';
        echo '<section class="nc-lead-card nc-lead-workspace"><div class="nc-lead-content"><div class="nc-lead-request"><h2>Обращение</h2><div class="nc-lead-message">'.esc_html($lead['message']!==''?$lead['message']:'Сообщение не указано.').'</div>';
        if(!empty($lead['context']))echo '<p class="nc-lead-context">'.esc_html($lead['context']).'</p>';
        echo '</div><aside class="nc-lead-person"><h2>Связаться с заявителем</h2><strong class="nc-lead-person-name">'.esc_html($lead['name']!==''?$lead['name']:'Имя не указано').'</strong>';
        if(!empty($lead['company']))echo '<p>'.esc_html($lead['company']).'</p>';
        foreach(array('email'=>'mailto:','phone'=>'tel:')as$key=>$scheme){if(empty($lead[$key]))continue;$target=$key==='phone'?preg_replace('/[^0-9+]/','',$lead[$key]):$lead[$key];echo '<a class="nc-lead-contact-link" href="'.esc_url($scheme.$target).'">'.esc_html($lead[$key]).'</a>';}
        if($service&&$service['contact']['channel']==='telegram')echo '<a class="nc-lead-contact-link" href="'.esc_url('https://t.me/'.ltrim($service['contact']['value'],'@')).'" target="_blank" rel="noopener noreferrer">'.esc_html($service['contact']['value']).'</a>';
        if($service&&!empty($service['contact']['note']))echo '<p>'.esc_html($service['contact']['note']).'</p>';
        echo '<p class="nc-lead-contact-note">Откроется ваша почта или приложение для звонка.</p></aside></div><footer class="nc-lead-workflow"><div class="nc-lead-next"><strong>'.esc_html($lead['status']==='processed'?'Обращение обработано':($lead['status']==='spam'?'Заявка отмечена как спам':'Прочитайте обращение и свяжитесь с заявителем')).'</strong><p>'.esc_html(in_array($lead['status'],array('processed','spam'),true)?'При необходимости измените статус заявки.':'После работы с обращением отметьте результат.').'</p></div><div class="nc-lead-actions">';
        if($lead['status']!=='processed'&&$lead['status']!=='spam')netchat_leads_action_form($id,'status','Отметить обработанной','processed',true);
        echo '<form class="nc-lead-status-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('netchat_lead_action_'.$id);
        echo '<input type="hidden" name="action" value="netchat_lead_action"><input type="hidden" name="lead_id" value="'.esc_attr($id).'"><input type="hidden" name="lead_action" value="status"><label class="screen-reader-text" for="nc-lead-status-select">Статус заявки</label><select id="nc-lead-status-select" name="lead_status">';
        foreach($labels as$status=>$label)echo '<option value="'.esc_attr($status).'" '.selected($lead['status'],$status,false).'>'.esc_html($label).'</option>';
        echo '</select><button class="button" type="submit">Сохранить статус</button></form></div></footer></section><div class="nc-lead-secondary">';
        echo '<details class="nc-lead-disclosure" id="nc-lead-origin"><summary>Источник обращения</summary>';
        netchat_leads_admin_panel('Откуда пришла заявка',array('context'=>'Контекст','page_title'=>'Название страницы','page_url'=>'Страница','referrer'=>'Источник','utm'=>'UTM'),$lead);echo '</details>';
        echo '<details class="nc-lead-disclosure" id="nc-lead-mail"'.($lead['notification_status']==='failed'?' open':'').'><summary>Уведомление владельцу '.netchat_leads_admin_badge($lead['notification_status'],$mail_labels).'</summary>';
        netchat_leads_admin_panel('Почтовое уведомление',array('notification_status'=>'Состояние','notification_attempts'=>'Попытки','notification_error'=>'Ошибка'),$lead);
        echo '<div class="nc-lead-disclosure-actions"><p>Это уведомление владельцу сайта, а не ответ заявителю. Передача почтовому механизму не подтверждает доставку.</p>';
        netchat_leads_action_form($id,'retry','Повторить уведомление');
        echo '</div></details><details class="nc-lead-disclosure" id="nc-lead-consent"><summary>Согласие и сведения о записи</summary>';
        netchat_leads_admin_panel('Согласие и сведения о записи',array('created_at'=>'Создана','updated_at'=>'Обновлена','consent_presented'=>'Согласие предъявлено','consent_accepted'=>'Согласие принято','consent_version'=>'Версия текста','consent_at'=>'Время согласия'),$lead);
        if($service)netchat_leads_admin_panel('Документы при отправке',array('document_url'=>'Согласие','document_version'=>'Версия согласия','policy_url'=>'Политика','policy_version'=>'Версия политики'),$service['consent']);
        echo '</details><details class="nc-lead-disclosure nc-lead-danger" id="nc-lead-delete"><summary>Удалить заявку</summary><div class="nc-lead-disclosure-actions"><p>Удаление необратимо. Письма и независимые резервные копии сохраняются.</p>';
        netchat_leads_action_form($id,'delete','Удалить заявку');echo '</div></details></div></div>';return;
    }
    $status=isset($_GET['status'])?sanitize_key(wp_unslash($_GET['status'])):'';if(!isset($labels[$status]))$status='';
    $search=isset($_GET['s'])&&is_string($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';
    $search=function_exists('mb_substr')?mb_substr($search,0,190):substr($search,0,190);
    $page=isset($_GET['paged'])?max(1,min(100000,absint($_GET['paged']))):1;
    $where='1=1';$args=array();if($status!==''){$where.=' AND status=%s';$args[]=$status;}
    $form_filter=isset($_GET['form'])&&is_string($_GET['form'])?sanitize_key($_GET['form']):'';
    $form_config=netchat_service_forms_config();$form_labels=array();
    foreach($form_config['installed']as$key=>$form)$form_labels[$key]=$form['service_label'].(netchat_service_form_enabled($form)?'':' (выключена)');
    foreach($form_config['deleted']as$key=>$form)if(!isset($form_labels[$key]))$form_labels[$key]=$form['service_label'].' (удалена)';
    if($form_filter!==''&&($form_filter==='contact'||isset($form_labels[$form_filter]))){$where.=' AND form_key=%s';$args[]=$form_filter;}else $form_filter='';
    if($search!==''){$like='%'.$wpdb->esc_like($search).'%';$where.=' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR company LIKE %s OR message LIKE %s OR context LIKE %s OR submission_json LIKE %s OR public_code LIKE %s)';for($i=0;$i<8;$i++)$args[]=$like;}
    $table=netchat_leads_table();$sql="SELECT COUNT(*) FROM {$table} WHERE {$where}";
    $count=(int)$wpdb->get_var($args?$wpdb->prepare($sql,$args):$sql);
    if($wpdb->last_error){echo '<p>Хранилище заявок недоступно.</p></div>';return;}
    $pages=max(1,(int)ceil($count/25));$page=min($page,$pages);
    $rows=$wpdb->get_results($wpdb->prepare("SELECT id,public_code,status,name,email,phone,context,created_at,notification_status,form_key,submission_json FROM {$table} WHERE {$where} ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d",array_merge($args,array(25,($page-1)*25))),ARRAY_A);
    foreach($rows as&$row){if(empty($row['public_code'])){$code=netchat_leads_public_code($row['id']);$row['public_code']=is_wp_error($code)?'—':$code;}$service=netchat_service_submission($row);if($service){$row['context']=$service['snapshot']['title'];if($service['contact']['channel']==='telegram')$row['email']=$service['contact']['value'];}}unset($row);
    echo '<form class="nc-lead-filters nc-lead-card" method="get"><input type="hidden" name="page" value="netchat-leads"><label>Статус <select name="status"><option value="">Все</option>';
    foreach($labels as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($status,$key,false).'>'.esc_html($label).'</option>';
    echo '</select></label><label>Форма <select name="form"><option value="">Все формы</option><option value="contact" '.selected($form_filter,'contact',false).'>Контактная форма</option>';
    foreach($form_labels as$key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($form_filter,$key,false).'>'.esc_html($label).'</option>';
    echo '</select></label> <label class="nc-lead-search">Поиск <input type="search" name="s" placeholder="Код, имя, email, телефон или сообщение" value="'.esc_attr($search).'" maxlength="190"></label> <button class="button button-primary">Найти</button></form><div class="nc-leads-summary"><strong>Всего: '.esc_html($count).'</strong><span>Поиск по контактам и содержанию заявки</span></div><div class="nc-leads-table-wrap"><table class="widefat nc-leads-table"><thead><tr><th>Заявка</th><th>Статус</th><th>Контакт</th><th>Контекст</th><th>Дата</th><th>Почта</th></tr></thead><tbody>';
    foreach($rows as $row){$url=add_query_arg(array('page'=>'netchat-leads','lead'=>$row['id']),admin_url('admin.php'));echo '<tr><td data-label="Заявка"><a class="nc-lead-name" href="'.esc_url($url).'">'.esc_html($row['name']!==''?$row['name']:'Без имени').'</a><span class="nc-lead-muted">#'.esc_html($row['public_code']).'</span></td><td data-label="Статус">'.netchat_leads_admin_badge($row['status'],$labels).'</td><td data-label="Контакт"><div>'.esc_html($row['email']).'</div><div class="nc-lead-muted">'.esc_html($row['phone']).'</div></td><td data-label="Контекст">'.esc_html($row['context']!==''?$row['context']:'—').'</td><td data-label="Дата">'.esc_html(netchat_leads_admin_date($row['created_at'])).'</td><td data-label="Почта">'.netchat_leads_admin_badge($row['notification_status'],$mail_labels).'</td></tr>';}
    if(!$rows)echo '<tr><td class="nc-leads-empty" colspan="6">Заявок нет.</td></tr>';echo '</tbody></table></div>';
    if($pages>1)echo '<p>'.paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>$pages)).'</p>';echo '</div>';
}
function netchat_leads_setting_control($path,$label,$value,$type='text'){
    echo '<div class="nc-lead-control'.($type==='checkbox'?' nc-lead-switch':'').'"><label><span>'.esc_html($label).'</span> ';
    if($type==='checkbox')echo '<input type="checkbox" data-nc-lead-setting="'.esc_attr($path).'" '.checked(!empty($value),true,false).'>';
    elseif($type==='textarea'||$type==='recipients')echo '<br><textarea rows="3" class="large-text" data-nc-lead-setting="'.esc_attr($path).'"'.($type==='recipients'?' data-nc-lead-type="recipients"':'').'>'.esc_textarea($type==='recipients'?implode(', ',$value):$value).'</textarea>';
    else echo '<input class="regular-text" type="'.esc_attr($type).'" data-nc-lead-setting="'.esc_attr($path).'" value="'.esc_attr($value).'"'.($type==='number'?' min="1" max="3650"':'').'>';
    echo '</label></div>';
}
function netchat_leads_render_settings($c){
    if(!current_user_can('manage_options'))return;
    echo '<section id="netchat-leads-settings" class="nc-enquiry-editor"><header class="nc-e-header"><div><a class="nc-e-back" href="'.esc_url(admin_url('admin.php?page=netchat')).'">NetChat / Настройки виджета</a><h1>Настройки обращений</h1><p>Контактная форма для посетителей сайта</p></div><div class="nc-e-header-actions"><a class="nc-e-secondary" href="'.esc_url(admin_url('admin.php?page=netchat-leads')).'">Открыть заявки</a><button type="button" id="nc-save-btn" class="nc-save-btn">Сохранить настройки обращений</button></div></header>';
    echo '<div class="nc-e-toolbar"><nav class="nc-e-tabs" aria-label="Разделы настроек">';
    foreach(array('fields'=>'Поля формы','texts'=>'Тексты','consent'=>'Согласие','delivery'=>'Уведомления','placement'=>'Размещение')as$key=>$label)echo '<button type="button" data-enquiry-tab="'.esc_attr($key).'" aria-controls="nc-e-'.esc_attr($key).'" aria-pressed="'.($key==='fields'?'true':'false').'">'.esc_html($label).'</button>';
    echo '</nav><span class="nc-e-status" data-enquiry-status role="status">Все изменения сохранены</span><button type="button" class="nc-e-mobile-preview nc-e-secondary" data-enquiry-preview-open>Предпросмотр</button></div><div class="nc-e-workspace"><main class="nc-e-settings"><section id="nc-e-fields" data-enquiry-panel="fields"><div class="nc-e-fields-layout"><nav class="nc-e-field-list" aria-label="Поля контактной формы">';
    $names=array('name'=>'Имя','email'=>'Email','phone'=>'Телефон','company'=>'Компания','message'=>'Сообщение');
    $icons=array('name'=>'Aa','email'=>'@','phone'=>'+7','company'=>'▤','message'=>'☰');
    foreach($names as$key=>$label)echo '<button type="button" data-enquiry-field="'.esc_attr($key).'" aria-controls="nc-e-field-'.esc_attr($key).'" aria-pressed="'.($key==='name'?'true':'false').'"><span class="nc-e-field-icon" aria-hidden="true">'.esc_html($icons[$key]).'</span><span><strong>'.esc_html($label).'</strong><small data-enquiry-field-state="'.esc_attr($key).'">'.esc_html(!$c['fields'][$key]['enabled']?'Скрыто':($c['fields'][$key]['required']?'Обязательное':'Необязательное')).'</small></span><span class="nc-e-field-dot'.(!$c['fields'][$key]['enabled']?' is-off':'').'" aria-hidden="true"></span></button>';
    echo '<p>Оставьте email или телефон для связи.</p></nav><label class="nc-e-field-picker">Поле формы<select data-enquiry-field-select>';
    foreach($names as$key=>$label)echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>';
    echo '</select></label><div class="nc-e-field-inspector">';
    foreach($names as$key=>$label){$field=$c['fields'][$key];echo '<section id="nc-e-field-'.esc_attr($key).'" data-enquiry-field-panel="'.esc_attr($key).'"'.($key==='name'?'':' hidden').'><h2>'.esc_html($label).'</h2><p class="nc-e-description">'.esc_html($key==='message'?'Посетитель описывает задачу в несколько строк.':'Настройте, как это поле выглядит в форме.').'</p><div class="nc-lead-controls">';netchat_leads_setting_control('fields.'.$key.'.label','Подпись поля',$field['label']);netchat_leads_setting_control('fields.'.$key.'.placeholder','Подсказка внутри поля',$field['placeholder']);echo '<div class="nc-e-field-options">';netchat_leads_setting_control('fields.'.$key.'.enabled','Показывать в форме',$field['enabled'],'checkbox');netchat_leads_setting_control('fields.'.$key.'.required','Обязательное поле',$field['required'],'checkbox');echo '</div></div><p class="nc-e-footnote">'.esc_html($key==='email'||$key==='phone'?'Посетитель должен указать хотя бы один контакт: email или телефон.':'Обязательное поле нужно заполнить перед отправкой.').'</p></section>';}
    echo '</div></div></section><section id="nc-e-texts" data-enquiry-panel="texts" hidden><div class="nc-e-section"><h2>Тексты формы</h2><p>Заголовок, кнопка и ответ после отправки.</p><div class="nc-lead-controls">';
    foreach(array('title'=>'Заголовок','button_text'=>'Текст кнопки','success_text'=>'Сообщение после отправки')as$key=>$label)netchat_leads_setting_control($key,$label,$c[$key],$key==='success_text'?'textarea':'text');
    echo '</div></div></section><section id="nc-e-consent" data-enquiry-panel="consent" hidden><div class="nc-e-section"><h2>Согласие и политика</h2><p>Условия обработки данных в контактной форме.</p><div class="nc-lead-controls">';
    foreach(array('enabled'=>'Показывать согласие','required'=>'Обязательное согласие','text'=>'Текст','url'=>'Ссылка на политику','version'=>'Версия текста')as$key=>$label)netchat_leads_setting_control('consent.'.$key,$label,$c['consent'][$key],in_array($key,array('enabled','required'),true)?'checkbox':($key==='text'?'textarea':($key==='url'?'url':'text')));
    echo '</div></div></section><section id="nc-e-delivery" data-enquiry-panel="delivery" hidden><div class="nc-e-section"><h2>Почтовые уведомления</h2><p>До пяти получателей через запятую.</p><div class="nc-lead-controls">';
    netchat_leads_setting_control('email.enabled','Уведомлять по email',$c['email']['enabled'],'checkbox');netchat_leads_setting_control('email.recipients','Получатели',$c['email']['recipients'],'recipients');netchat_leads_setting_control('email.include_message','Включать сообщение в письмо',$c['email']['include_message'],'checkbox');
    echo '</div><h2 class="nc-e-subheading">Хранение данных</h2><p>Заявки удаляются автоматически по истечении срока.</p><div class="nc-lead-controls">';
    netchat_leads_setting_control('retention_days','Обычные заявки, дней',$c['retention_days'],'number');netchat_leads_setting_control('spam_retention_days','Спам, дней',$c['spam_retention_days'],'number');
    echo '</div></div></section><section id="nc-e-placement" data-enquiry-panel="placement" hidden><div class="nc-e-section"><h2>Кнопка на странице</h2><p>Добавьте блок «Шорткод» в редакторе WordPress и вставьте:</p><code>[netchat_button text="Связаться" context="Страница услуги"]</code><details class="nc-e-advanced"><summary>Подключение к своей кнопке</summary><p>Добавьте атрибут <code>data-netchat-open="form"</code>. Шорткод <code>[netchat_form]</code> открывает ту же форму.</p></details></div></section></main><aside class="nc-e-preview"><div class="nc-e-preview-heading"><div><strong>Предпросмотр</strong><span>Так форму увидит посетитель</span></div><button type="button" class="nc-e-secondary nc-e-preview-back" data-enquiry-preview-close>К настройкам</button></div><div class="nc-e-preview-paper" data-enquiry-preview></div><p class="nc-e-preview-note">Предпросмотр не отправляет заявки.</p></aside></div><footer class="nc-e-footer"><span>Приём заявок включается в <a href="'.esc_url(admin_url('admin.php?page=netchat#netchat-leads-channel')).'">настройках виджета</a>.</span><button type="button" class="nc-e-reset" id="nc-reset-btn">Восстановить настройки формы</button></footer></section>';
}
