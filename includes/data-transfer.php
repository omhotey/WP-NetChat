<?php
/** Portable definitions only; uploads are JSON, never PHP-serialized data. */
if (!defined('ABSPATH')) exit;
function netchat_transfer_error($message,$status=400){return new WP_Error('netchat_transfer',$message,array('status'=>$status));}
function netchat_transfer_keys($value,$allowed,$required=array()){
    return is_array($value)&&!array_diff(array_keys($value),$allowed)&&!array_diff($required,array_keys($value));
}
function netchat_transfer_text($value,$max,$empty=false){
    return is_string($value)&&preg_match('//u',$value)&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value)&&netchat_leads_strlen($value)<=$max&&($empty||trim($value)!=='');
}
function netchat_transfer_condition($condition,$fields){
    if(!netchat_transfer_keys($condition,array('key','values'),array('key','values'))||!is_string($condition['key'])||!isset($fields[$condition['key']]['options']))return false;
    $values=$condition['values'];
    return is_array($values)&&count($values)>0&&array_keys($values)===range(0,count($values)-1)&&count(array_filter($values,'is_string'))===count($values)&&!array_diff($values,array_keys($fields[$condition['key']]['options']));
}
function netchat_transfer_schema($form){
    $required=array('id','service_code','schema_version','service_label','title','intro','submit_text','success_text','fields');
    if(!netchat_transfer_keys($form,array_merge($required,array('enabled','question_steps','notices')),$required))return netchat_transfer_error('Неизвестные или неполные данные формы.');
    if(!is_string($form['id'])||!preg_match('/^[a-z][a-z0-9_]{0,31}$/D',$form['id'])||!is_string($form['service_code'])||!preg_match('/^[A-Z][A-Z0-9_]{0,31}$/D',$form['service_code'])||!is_int($form['schema_version'])||$form['schema_version']<1||$form['schema_version']>1000000000)return netchat_transfer_error('Некорректный идентификатор или версия формы.');
    foreach(array('service_label'=>120,'title'=>120,'intro'=>2000,'submit_text'=>120,'success_text'=>2000)as$key=>$max)if(!netchat_transfer_text($form[$key],$max))return netchat_transfer_error('Проверьте тексты формы.');
    if(array_key_exists('enabled',$form)&&!in_array($form['enabled'],array(0,1),true))return netchat_transfer_error('Некорректное состояние формы.');
    if(!is_array($form['fields'])||count($form['fields'])<1||count($form['fields'])>20||array_keys($form['fields'])!==range(0,count($form['fields'])-1))return netchat_transfer_error('Нужно от 1 до 20 вопросов.');
    $inferred=1;foreach($form['fields']as$field){if(!is_array($field)||!array_key_exists('step',$field)||!is_int($field['step'])||!in_array($field['step'],array(1,2),true))return netchat_transfer_error('Некорректный шаг вопроса.');$inferred=max($inferred,$field['step']);}
    $steps=array_key_exists('question_steps',$form)?$form['question_steps']:$inferred;
    if(!in_array($steps,array(1,2),true))return netchat_transfer_error('Нужен один или два шага вопросов.');
    $fields=array();
    foreach($form['fields']as$field){
        if(!netchat_transfer_keys($field,array('key','label','type','required','step','placeholder','helper','options','condition'),array('key','label','type','required','step'))||!is_string($field['key'])||!preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$field['key'])||isset($fields[$field['key']])||!in_array($field['type'],array('short','text','url','radio','multi'),true)||!is_bool($field['required'])||!is_int($field['step'])||$field['step']<1||$field['step']>$steps)return netchat_transfer_error('Неизвестный тип, повтор или неверный шаг вопроса.');
        foreach(array('label'=>240,'placeholder'=>500,'helper'=>1000)as$key=>$max)if(($key==='label'||array_key_exists($key,$field))&&!netchat_transfer_text($field[$key],$max,$key!=='label'))return netchat_transfer_error('Некорректный текст вопроса.');
        if(in_array($field['type'],array('radio','multi'),true)){
            if(!isset($field['options'])||!is_array($field['options'])||count($field['options'])<2||count($field['options'])>12)return netchat_transfer_error('Нужно от 2 до 12 вариантов ответа.');
            foreach($field['options']as$key=>$label)if(!is_string($key)||!preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$key)||!netchat_transfer_text($label,240))return netchat_transfer_error('Некорректный вариант ответа.');
        }elseif(array_key_exists('options',$field))return netchat_transfer_error('Этот тип вопроса не поддерживает варианты.');
        $fields[$field['key']]=$field;
    }
    $resolved=array();$pending=$fields;
    while($pending){$progress=false;foreach($pending as$key=>$field){if(array_key_exists('condition',$field)&&!netchat_transfer_condition($field['condition'],$fields))return netchat_transfer_error('Неверная зависимость вопроса.');if(!array_key_exists('condition',$field)||isset($resolved[$field['condition']['key']])){$resolved[$key]=true;unset($pending[$key]);$progress=true;}}if(!$progress)return netchat_transfer_error('Циклическая зависимость вопросов.');}
    if(array_key_exists('notices',$form)){
        if(!is_array($form['notices'])||count($form['notices'])>20||(count($form['notices'])&&array_keys($form['notices'])!==range(0,count($form['notices'])-1)))return netchat_transfer_error('Некорректные сообщения формы.');
        foreach($form['notices']as$notice){
            if(!netchat_transfer_keys($notice,array('text','condition','any','before_contact','channel_link'),array('text'))||!netchat_transfer_text($notice['text'],2000))return netchat_transfer_error('Некорректное сообщение.');
            foreach(array('before_contact','channel_link')as$key)if(array_key_exists($key,$notice)&&!is_bool($notice[$key]))return netchat_transfer_error('Некорректный флаг сообщения.');
            if(array_key_exists('condition',$notice)&&!netchat_transfer_condition($notice['condition'],$fields))return netchat_transfer_error('Некорректное условие сообщения.');
            if(array_key_exists('any',$notice)){if(!is_array($notice['any'])||!count($notice['any'])||count($notice['any'])>20||array_keys($notice['any'])!==range(0,count($notice['any'])-1))return netchat_transfer_error('Некорректные условия сообщения.');foreach($notice['any']as$c)if(!netchat_transfer_condition($c,$fields))return netchat_transfer_error('Некорректное условие сообщения.');}
        }
    }
    $form['question_steps']=$steps;
    return $form;
}
function netchat_transfer_portable_forms($config,$ids){
    $forms=array();foreach($ids as$id){if(!is_string($id)||!isset($config['installed'][$id]))return netchat_transfer_error('Выберите существующие формы.');$form=netchat_transfer_schema($config['installed'][$id]);if(is_wp_error($form))return $form;$forms[$id]=$form;}
    return array('installed'=>$forms,'primary_form'=>isset($forms[$config['primary_form']])?$config['primary_form']:'','documents'=>$config['documents'],'enabled'=>(int)$config['enabled'],'retention_confirmed'=>(int)$config['retention_confirmed']);
}
function netchat_transfer_export($type,$ids=array()){
    if(!in_array($type,array('settings','forms','bundle'),true)||!is_array($ids)||count($ids)>100)return netchat_transfer_error('Выберите тип экспорта.');
    $result=array('product'=>'netchat','schema_version'=>1,'type'=>$type,'timestamp'=>gmdate('Y-m-d\TH:i:s\Z'));
    if($type!=='forms'){$settings=netchat_storage_read();if(!is_array($settings))return netchat_transfer_error('Настройки недоступны.',503);$result['settings']=netchat_normalize_config($settings['config'],false);}
    if($type!=='settings'){$config=netchat_service_forms_config();$result['forms']=netchat_transfer_portable_forms($config,$ids?:array_keys($config['installed']));if(is_wp_error($result['forms']))return $result['forms'];}
    $result=netchat_transfer_validate($result);if(is_wp_error($result))return $result;
    if(strlen(wp_json_encode($result))>1048576)return netchat_transfer_error('Экспорт больше 1 МиБ. Выберите меньше форм и сохраните их отдельными файлами.');
    return $result;
}
function netchat_transfer_validate($data){
    if(!netchat_transfer_keys($data,array('product','schema_version','type','timestamp','settings','forms'),array('product','schema_version','type','timestamp'))||$data['product']!=='netchat'||$data['schema_version']!==1||!in_array($data['type'],array('settings','forms','bundle'),true)||!is_string($data['timestamp'])||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$data['timestamp']))return netchat_transfer_error('Неизвестный формат или версия файла.');
    if(($data['type']!=='forms')!==array_key_exists('settings',$data)||($data['type']!=='settings')!==array_key_exists('forms',$data))return netchat_transfer_error('Содержимое не соответствует типу файла.');
    if(array_key_exists('settings',$data)){
        $config=netchat_normalize_config($data['settings'],true);if(is_wp_error($config))return $config;
        $supplied=$data['settings'];foreach(array('form_presentation'=>'modal','form_drawer_side'=>'left','hint_mobile'=>0,'button_delay'=>0)as$key=>$fallback)if(!array_key_exists($key,$supplied))$supplied[$key]=$fallback;
        foreach(array('button_scroll_percent','hint_scroll_percent')as$key){if(!array_key_exists($key,$supplied))$supplied[$key]=0;else $supplied[$key]=$config[$key];}
        // Preserve the existing canonical contract for every older setting.
        if($config!=$supplied)return netchat_transfer_error('Настройки должны быть полными и нормализованными.');
        foreach($supplied as$key=>$value)if($config[$key]!==$value)return netchat_transfer_error('Настройки должны быть полными и нормализованными.');
        $data['settings']=$config;
    }
    if(array_key_exists('forms',$data)){
        $c=$data['forms'];if(!netchat_transfer_keys($c,array('installed','primary_form','documents','enabled','retention_confirmed'),array('installed','primary_form','documents','enabled','retention_confirmed'))||!is_array($c['installed'])||count($c['installed'])>100||!in_array($c['enabled'],array(0,1),true)||!in_array($c['retention_confirmed'],array(0,1),true)||!is_string($c['primary_form'])||($c['primary_form']!==''&&!isset($c['installed'][$c['primary_form']])))return netchat_transfer_error('Некорректный набор форм.');
        $codes=array();foreach($c['installed']as$id=>$form){$check=netchat_transfer_schema($form);if(is_wp_error($check))return $check;if($id!==$form['id']||isset($codes[$form['service_code']]))return netchat_transfer_error('Повтор идентификатора формы.');$codes[$form['service_code']]=true;$data['forms']['installed'][$id]=$check;}
        $docs=$c['documents'];$keys=array('document_url','policy_url','text_version','document_version','policy_version');if(!netchat_transfer_keys($docs,$keys,$keys))return netchat_transfer_error('Некорректные настройки документов.');
        foreach($docs as$key=>$value){if(!netchat_transfer_text($value,in_array($key,array('document_url','policy_url'),true)?500:190,true))return netchat_transfer_error('Некорректный документ.');if(in_array($key,array('document_url','policy_url'),true)&&$value!==''&&(!netchat_service_url_valid($value)||strpos($value,'#')!==false))return netchat_transfer_error('Небезопасный адрес документа.');}
    }
    return $data;
}
function netchat_transfer_decode($json){
    if(!is_string($json)||strlen($json)>1048576||!preg_match('//u',$json))return netchat_transfer_error('Файл должен быть JSON до 1 МиБ.');
    $data=json_decode($json,true,40);if(json_last_error()!==JSON_ERROR_NONE)return netchat_transfer_error('Повреждённый JSON.');
    // Native json_decode overwrites duplicate object keys: reject them before planning.
    $stack=array();$length=strlen($json);
    for($i=0;$i<$length;$i++){
        $char=$json[$i];if($char==='{'||$char==='['){$stack[]=array('object'=>$char==='{','keys'=>array());continue;}
        if($char==='}'||$char===']'){array_pop($stack);continue;}
        if($char!=='"')continue;$start=$i;$i++;for(;$i<$length;$i++){if($json[$i]==='\\'){$i++;continue;}if($json[$i]==='"')break;}
        $next=$i+1;while($next<$length&&ctype_space($json[$next]))$next++;
        if($next<$length&&$json[$next]===':'&&$stack){$key=json_decode(substr($json,$start,$i-$start+1));$index=count($stack)-1;if(isset($stack[$index]['keys'][$key]))return netchat_transfer_error('Повтор ключа в JSON.');$stack[$index]['keys'][$key]=true;}
    }
    return netchat_transfer_validate($data);
}
function netchat_transfer_state(){
    global $wpdb;$settings=netchat_storage_read();if(!is_array($settings))return netchat_transfer_error('Настройки недоступны.',503);
    $raw=$wpdb->get_var("SELECT option_value FROM $wpdb->options WHERE option_name='netchat_service_forms'");if($wpdb->last_error||$raw===null)return netchat_transfer_error('Каталог форм недоступен.',503);
    $forms=maybe_unserialize($raw);if(!is_array($forms)||!isset($forms['installed'])||!is_array($forms['installed']))return netchat_transfer_error('Каталог повреждён.',503);
    return array('settings'=>$settings,'forms'=>$forms,'settings_revision'=>$settings['revision'],'forms_revision'=>netchat_service_admin_revision($forms));
}
function netchat_transfer_plan($data,$choices,$state){
    if(!netchat_transfer_keys($choices,array('selected','replace','apply_settings','apply_documents','set_primary'),array('selected','replace','apply_settings','apply_documents','set_primary'))||!is_array($choices['selected'])||!is_array($choices['replace'])||count($choices['selected'])>100||count(array_unique($choices['selected'],SORT_REGULAR))!==count($choices['selected']))return netchat_transfer_error('Некорректный выбор импорта.');
    if(count($choices['selected'])&&array_keys($choices['selected'])!==range(0,count($choices['selected'])-1))return netchat_transfer_error('Некорректный список форм.');
    foreach($choices['replace']as$source=>$target)if(!is_string($source)||!is_string($target))return netchat_transfer_error('Некорректная замена формы.');
    foreach(array('apply_settings','apply_documents','set_primary')as$key)if(!is_bool($choices[$key]))return netchat_transfer_error('Некорректный выбор импорта.');
    if($choices['apply_settings']&&!isset($data['settings']))return netchat_transfer_error('В файле нет настроек.');
    if(($choices['selected']||$choices['replace']||$choices['apply_documents']||$choices['set_primary'])&&!isset($data['forms']))return netchat_transfer_error('В файле нет форм.');
    $next=$state['forms'];$mapping=array();$rows=array();$taken=array_column($next['installed'],'service_code');
    if(array_diff(array_keys($choices['replace']),$choices['selected']))return netchat_transfer_error('Замена должна быть выбрана явно для импортируемой формы.');
    $targets=array();
    foreach($choices['selected']as$id){
        if(!is_string($id)||!isset($data['forms']['installed'][$id]))return netchat_transfer_error('Выбрана неизвестная форма.');
        $form=$data['forms']['installed'][$id];$target=$choices['replace'][$id]??null;
        if($target!==null){
            if(!is_string($target)||!isset($state['forms']['installed'][$target])||isset($targets[$target]))return netchat_transfer_error('Выберите уникальную существующую форму для замены.');$targets[$target]=true;
            $form['id']=$target;$form['service_code']=$state['forms']['installed'][$target]['service_code'];$form['schema_version']=$state['forms']['installed'][$target]['schema_version']+1;
        }else{
            $target=$id;
            if(isset($next['installed'][$target])||isset($next['deleted'][$target])||in_array($form['service_code'],$taken,true)){
                // Stable preview identity; changing choices or revisions requires a fresh preview.
                $suffix=substr(hash('sha256',wp_json_encode(array($data,$choices,$state['forms_revision'],$id))),0,24);$target='nc_'.$suffix;
                if(isset($next['installed'][$target])||isset($next['deleted'][$target]))return netchat_transfer_error('Конфликт копии: обновите preview.',409);
                $form['id']=$target;$form['service_code']='NC_'.strtoupper($suffix);
            }
            $form['schema_version']=1;
        }
        if(in_array($target,$mapping,true))return netchat_transfer_error('Две формы не могут получить один итоговый ID.');
        $taken[]=$form['service_code'];$next['installed'][$target]=$form;$mapping[$id]=$target;
        // New/copied forms never inherit a page binding. Explicit replacement keeps local binding.
        $rows[]=array('source'=>$id,'target'=>$target,'label'=>$form['service_label'],'action'=>isset($choices['replace'][$id])?'replace':($target===$id?'add':'copy'));
    }
    if(count($next['installed'])>100)return netchat_transfer_error('Можно хранить до 100 форм.');
    if($choices['apply_documents']){foreach(array('documents','enabled','retention_confirmed')as$key)$next[$key]=$data['forms'][$key];}
    if($choices['set_primary']){$primary=$data['forms']['primary_form'];if($primary===''||!isset($mapping[$primary]))return netchat_transfer_error('Выберите импорт основной формы.');$next['primary_form']=$mapping[$primary];}
    if(!$choices['selected']&&!$choices['apply_settings']&&!$choices['apply_documents']&&!$choices['set_primary'])return netchat_transfer_error('Выберите данные для переноса.');
    return array('settings'=>$choices['apply_settings']?$data['settings']:$state['settings']['config'],'forms'=>$next,'rows'=>$rows,'mapping'=>$mapping,'categories'=>$choices['apply_settings']?array('Каналы связи','Внешний вид виджета','Режимы открытия','Настройки обращений и уведомлений'):array(),'documents'=>$choices['apply_documents'],'binding_notice'=>'Привязки к страницам и реальные ID страниц не переносятся. Новые формы привяжите на этом сайте.');
}
function netchat_transfer_seal($json,$choices,$state){return hash_hmac('sha256',wp_json_encode(array(hash('sha256',$json),$choices,$state['settings_revision'],$state['forms_revision'],get_current_user_id())),wp_salt('nonce'));}
function netchat_transfer_preview($json,$choices){
    $data=netchat_transfer_decode($json);if(is_wp_error($data))return $data;$state=netchat_transfer_state();if(is_wp_error($state))return $state;$plan=netchat_transfer_plan($data,$choices,$state);if(is_wp_error($plan))return $plan;
    return array('digest'=>hash('sha256',$json),'ticket'=>netchat_transfer_seal($json,$choices,$state),'settings_revision'=>$state['settings_revision'],'forms_revision'=>$state['forms_revision'],'preview'=>array_intersect_key($plan,array_flip(array('rows','categories','documents','binding_notice','mapping'))));
}
function netchat_transfer_apply($json,$choices,$proof){
    global $wpdb;
    $data=netchat_transfer_decode($json);if(is_wp_error($data))return $data;$state=netchat_transfer_state();if(is_wp_error($state))return $state;
    if(!netchat_transfer_keys($proof,array('digest','ticket','settings_revision','forms_revision'),array('digest','ticket','settings_revision','forms_revision'))||!is_string($proof['digest'])||!is_string($proof['ticket'])||$proof['settings_revision']!==$state['settings_revision']||$proof['forms_revision']!==$state['forms_revision']||!hash_equals(hash('sha256',$json),$proof['digest'])||!hash_equals(netchat_transfer_seal($json,$choices,$state),$proof['ticket']))return netchat_transfer_error('Данные или настройки изменились. Загрузите файл и проверьте preview заново.',409);
    $plan=netchat_transfer_plan($data,$choices,$state);if(is_wp_error($plan))return $plan;
    $engine=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$wpdb->options),ARRAY_A);
    if(!$engine||strtolower($engine['Engine'])!=='innodb')return netchat_transfer_error('Импорт требует таблицу options InnoDB. Обратитесь к администратору базы данных.',503);
    if($wpdb->query('START TRANSACTION')===false)return netchat_transfer_error('Не удалось начать безопасный импорт.',503);
    $ok=false;
    try{
        // Lock both rows in one stable order; exact serialized CAS catches concurrent writers.
        $rows=$wpdb->get_results("SELECT option_name,option_value FROM $wpdb->options WHERE option_name IN ('netchat_service_forms','netchat_settings') ORDER BY option_name FOR UPDATE",ARRAY_A);
        $locked=array_column($rows?:array(),'option_value','option_name');
        if(count($locked)!==2||$locked['netchat_settings']!==maybe_serialize($state['settings'])||$locked['netchat_service_forms']!==maybe_serialize($state['forms']))return netchat_transfer_error('Настройки изменились. Проверьте preview заново.',409);
        $new_settings=$state['settings'];if($plan['settings']!==$new_settings['config']){$new_settings['config']=$plan['settings'];$new_settings['revision']++;$new_settings['migration_warnings']=array();}
        foreach(array('netchat_settings'=>array($state['settings'],$new_settings),'netchat_service_forms'=>array($state['forms'],$plan['forms']))as$name=>$values){
            if($values[0]===$values[1])continue;
            $count=$wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",maybe_serialize($values[1]),$name,maybe_serialize($values[0])));
            if($count!==1)return netchat_transfer_error('Импорт не сохранён. Все изменения отменены.',503);
        }
        if($wpdb->query('COMMIT')===false)return netchat_transfer_error('Импорт не подтверждён. Обновите страницу.',503);$ok=true;
    }finally{if(!$ok)$wpdb->query('ROLLBACK');netchat_storage_invalidate();wp_cache_delete('netchat_service_forms','options');}
    return array('saved'=>true,'mapping'=>$plan['mapping']);
}
add_action('admin_menu',function(){add_submenu_page('netchat','NetChat — Перенос данных','Перенос данных','manage_options','netchat-transfer','netchat_transfer_page');});
add_action('admin_enqueue_scripts',function($hook){if($hook!=='netchat_page_netchat-transfer')return;wp_enqueue_style('netchat-transfer',NETCHAT_URL.'assets/css/data-transfer.css',array(),NETCHAT_VER);wp_enqueue_script('netchat-transfer',NETCHAT_URL.'assets/js/data-transfer.js',array(),NETCHAT_VER,true);wp_localize_script('netchat-transfer','netchatTransfer',array('url'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('netchat_transfer')));});
add_action('wp_ajax_netchat_transfer','netchat_transfer_ajax');
add_action('wp_ajax_nopriv_netchat_transfer','netchat_transfer_ajax');
function netchat_transfer_ajax(){
    if(!current_user_can('manage_options')||!isset($_POST['_nonce'])||!is_string($_POST['_nonce'])||!wp_verify_nonce(wp_unslash($_POST['_nonce']),'netchat_transfer'))wp_send_json_error(array('message'=>'Нет доступа или срок подтверждения истёк.'),403);
    $raw=isset($_POST['payload'])?wp_unslash($_POST['payload']):'';if(!is_string($raw)||strlen($raw)>6291456)wp_send_json_error(array('message'=>'Слишком большой запрос.'),413);
    $request=json_decode($raw,true,40);if(!netchat_transfer_keys($request,array('operation','type','ids','json','choices','proof'),array('operation')))wp_send_json_error(array('message'=>'Некорректный запрос.'),400);
    if($request['operation']==='export')$result=netchat_transfer_export($request['type']??'', $request['ids']??array());
    elseif($request['operation']==='preview')$result=netchat_transfer_preview($request['json']??null,$request['choices']??null);
    elseif($request['operation']==='apply')$result=netchat_transfer_apply($request['json']??null,$request['choices']??null,$request['proof']??null);
    else $result=netchat_transfer_error('Неизвестное действие.');
    if(is_wp_error($result))wp_send_json_error(array('message'=>$result->get_error_message()),$result->get_error_data()['status']??400);wp_send_json_success($result);
}
function netchat_transfer_page(){
    if(!current_user_can('manage_options'))return;
    $forms=netchat_service_forms_config()['installed'];$selected=isset($_GET['form'])&&is_string($_GET['form'])?sanitize_text_field(wp_unslash($_GET['form'])):'';
    echo '<div class="wrap nc-transfer"><h1>Перенос данных NetChat</h1><p>Заявки, авторизация и привязки к страницам не входят в файл. Храните экспорт с контактами и настройками в приватном месте.</p><div class="nc-transfer-panels"><section aria-labelledby="nc-export-title"><h2 id="nc-export-title">Экспорт</h2><label for="nc-export-type">Что сохранить</label><select id="nc-export-type"><option value="bundle">Настройки и формы</option><option value="settings">Настройки</option><option value="forms"'.($selected?' selected':'').'>Выбранные формы</option></select><fieldset><legend>Формы для экспорта</legend>';
    foreach($forms as$id=>$form)echo '<label><input class="nc-export-form" type="checkbox" value="'.esc_attr($id).'"'.(!$selected||$selected===$id?' checked':'').'> '.esc_html($form['service_label']).'</label>';
    echo '</fieldset><button id="nc-export" class="button button-primary" type="button">Скачать JSON</button></section><section aria-labelledby="nc-import-title"><h2 id="nc-import-title">Импорт</h2><label for="nc-import-file">JSON до 1 МиБ</label><input id="nc-import-file" type="file" accept=".json,application/json"><div id="nc-import-options"></div><button id="nc-preview" class="button" type="button" disabled>Проверить изменения</button><div id="nc-transfer-preview" tabindex="-1"></div><button id="nc-apply" class="button button-primary" type="button" disabled>Применить проверенные изменения</button></section></div><p id="nc-transfer-status" role="status" aria-live="polite"></p></div>';
    echo '<script type="application/json" id="nc-local-forms">'.wp_json_encode(array_map(function($f){return array('id'=>$f['id'],'label'=>$f['service_label']);},array_values($forms)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
