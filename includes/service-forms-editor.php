<?php
/** Dedicated service form catalog, editor and global settings. */
if (!defined('ABSPATH')) exit;

function netchat_service_admin_binding($raw,$config,$code) {
    if(!is_array($raw))return new WP_Error('binding','Некорректная привязка.');
    $url=netchat_service_admin_text($raw['url']??'',500);
    $id=$raw['page_id']??'';
    if($url===null||!is_string($id)||($id!==''&&(!ctype_digit($id)||strlen($id)>10)))return new WP_Error('binding','Проверьте адрес и ID страницы.');
    if($url==='')return array();
    $parts=wp_parse_url($url);
    if(!netchat_service_url_valid($url)||!netchat_service_same_origin($url)||isset($parts['query'])||isset($parts['fragment']))return new WP_Error('binding','Нужен адрес страницы текущего сайта без параметров и якоря.');
    $url=netchat_leads_source_url($url);
    foreach($config['bindings']as$other=>$binding)if($other!==$code&&($binding['url']??'')===$url)return new WP_Error('binding','Эта страница уже привязана к другой форме.');
    return array('url'=>$url,'page_id'=>(int)$id);
}

function netchat_service_admin_edit_schema($schema,$post) {
    $next=$schema;
    if(!is_array($post['texts']??null)||!is_array($post['fields']??null)||!is_array($post['order']??null))return new WP_Error('schema','Неполные данные формы. Обновите редактор.');
    if(isset($post['form_name'])){
        $name=netchat_service_admin_text($post['form_name'],120);
        if($name===null||$name==='')return new WP_Error('name','Заполните название формы: до 120 символов.');
        $next['service_label']=$name;
    }
    foreach(array('title'=>120,'intro'=>2000,'submit_text'=>120,'success_text'=>2000)as$key=>$max){
        $text=netchat_service_admin_text($post['texts'][$key]??null,$max);
        if($text===null||$text==='')return new WP_Error('text','Заполните все тексты формы в пределах указанной длины.');
        $next[$key]=$text;
    }
    $old=array_column($schema['fields'],null,'key');$deleted=$post['deleted']??array();
    if(!is_array($deleted)||count(array_filter($deleted,'is_string'))!==count($deleted)||array_diff($deleted,array_keys($old)))return new WP_Error('delete','Некорректные удаляемые вопросы.');
    if(array_diff(array_keys($old),array_keys($post['fields']),$deleted)||array_intersect($deleted,array_keys($post['fields'])))return new WP_Error('field','Не хватает данных вопроса.');
    if(count($post['fields'])<1||count($post['fields'])>20)return new WP_Error('limit','В форме должно быть от 1 до 20 вопросов.');
    $groups=array();$by_key=array();
    foreach($post['fields']as$key=>$raw){
        if(!is_string($key)||!is_array($raw))return new WP_Error('field','Некорректный вопрос.');
        if(isset($old[$key]))$field=$old[$key];
        else {
            if(!preg_match('/^q_[a-f0-9]{16}$/D',$key)||!in_array($raw['type']??null,array('short','text','url','radio','multi'),true)||!in_array($raw['step']??null,array('1','2'),true)||(int)$raw['step']>netchat_service_question_steps($schema))return new WP_Error('field','Некорректный тип или шаг нового вопроса.');
            $field=array('key'=>$key,'type'=>$raw['type'],'step'=>(int)$raw['step'],'required'=>false);
            if(in_array($field['type'],array('radio','multi'),true))$field['options']=array();
        }
        foreach(array('label'=>240,'placeholder'=>500,'helper'=>1000)as$name=>$max){
            $value=netchat_service_admin_text($raw[$name]??'',$max);
            if($value===null||($name==='label'&&$value===''))return new WP_Error('field','Проверьте название и подсказки вопроса.');
            $field[$name]=$value;
        }
        if(isset($raw['required'])&&$raw['required']!=='1')return new WP_Error('field','Некорректная обязательность вопроса.');
        $field['required']=isset($raw['required']);
        if(isset($field['options'])){
            $options=$raw['options']??null;$removed=$raw['deleted_options']??array();
            if(!is_array($options)||count($options)<2||count($options)>12||!is_array($removed)||count(array_filter($removed,'is_string'))!==count($removed)||array_diff($removed,array_keys($field['options'])))return new WP_Error('option','Нужно от 2 до 12 вариантов ответа.');
            if(array_diff(array_keys($field['options']),array_keys($options),$removed)||array_intersect($removed,array_keys($options)))return new WP_Error('option','Не хватает вариантов ответа.');
            $values=array();
            foreach($options as$value=>$label){
                if(!is_string($value)||(!array_key_exists($value,$field['options'])&&!preg_match('/^o_[a-f0-9]{12}$/D',$value)))return new WP_Error('option','Некорректный вариант ответа.');
                $edited=netchat_service_admin_text($label,240);
                if($edited===null||$edited==='')return new WP_Error('option','Заполните подписи всех вариантов ответа.');
                $values[$value]=$edited;
            }
            $field['options']=$values;
        }
        $by_key[$key]=$field;$groups[(string)$field['step']][]=$key;
    }
    if(array_diff(array_keys($post['order']),array_keys($groups)))return new WP_Error('field','Неизвестные шаги.');
    $next['fields']=array();
    ksort($groups,SORT_NUMERIC);
    foreach($groups as$step=>$keys){
        $order=$post['order'][$step]??null;
        if(!is_array($order)||count(array_filter($order,'is_string'))!==count($order)||count($order)!==count($keys)||count(array_unique($order,SORT_REGULAR))!==count($keys)||array_diff($order,$keys)||array_diff($keys,$order))return new WP_Error('order','Некорректный порядок вопросов.');
        foreach($order as$key)$next['fields'][]=$by_key[$key];
    }
    $dependencies=netchat_service_admin_dependencies($schema,$next['fields']);
    if(is_wp_error($dependencies))return $dependencies;
    $before=$schema;foreach($before['fields']as&$field){$field['helper']=$field['helper']??'';$field['placeholder']=$field['placeholder']??'';}unset($field);
    if($next!=$before)$next['schema_version']=(int)$schema['schema_version']+1;
    return $next;
}

function netchat_service_admin_url($args=array()) {return add_query_arg($args,admin_url('admin.php?page=netchat-service-forms'));}
function netchat_service_admin_input($name,$label,$value,$max=500,$type='text') {
    echo '<label class="nc-editor-input">'.esc_html($label);
    $required=$name==='form_name'||strpos($name,'texts[')===0||preg_match('/^fields\[.+\]\[(label|options)\]/',$name)?' required':'';
    if($type==='textarea')echo '<textarea name="'.esc_attr($name).'" rows="3" maxlength="'.$max.'"'.$required.'>'.esc_textarea($value).'</textarea>';
    else echo '<input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr($value).'" maxlength="'.$max.'"'.$required.'>';
    echo '</label>';
}
function netchat_service_admin_condition($field,$schema) {
    if(empty($field['condition']))return 'Показывается всегда';
    $condition=$field['condition'];
    foreach($schema['fields']as$source)if($source['key']===$condition['key']){
        $labels=array();foreach($condition['values']as$value)$labels[]=$source['options'][$value]??$value;
        return 'Показывается, если «'.$source['label'].'»: '.implode(' / ',$labels);
    }
    return 'Условный вопрос';
}
function netchat_service_admin_header($title,$description) {
    echo '<header class="nc-editor-header"><div><h1>'.esc_html($title).'</h1><p>'.esc_html($description).'</p></div></header>';
}
function netchat_service_admin_page() {
    if(!current_user_can('manage_options'))wp_die('Insufficient permissions.');
    $config=netchat_service_forms_config();$raw=get_option('netchat_service_forms',null);
    $id=isset($_GET['form'])&&is_string($_GET['form'])?sanitize_key(wp_unslash($_GET['form'])):'';
    if(isset($_GET['form'])&&!isset($config['installed'][$id]))wp_die('Форма не найдена.','',array('response'=>404));
    echo '<div class="wrap nc-service-admin">';
    if(isset($_GET['saved']))echo '<div class="notice notice-success is-dismissible"><p>Изменения сохранены.</p></div>';
    if($id!==''&&($_GET['view']??'')==='delete')netchat_service_admin_delete_page($config['installed'][$id],$config,$raw);
    elseif($id!=='')netchat_service_admin_editor($config['installed'][$id],$config,$raw);
    elseif(($_GET['view']??'')==='create')netchat_service_admin_create_page($config,$raw);
    elseif(($_GET['view']??'')==='settings')netchat_service_admin_globals($config,$raw);
    else netchat_service_admin_catalog($config,$raw);
    echo '</div>';
}
function netchat_service_admin_catalog($config,$raw) {
    $total=count($config['installed']);$enabled=0;$unbound=0;
    foreach($config['installed']as$schema){if(netchat_service_form_enabled($schema))$enabled++;if(empty($config['bindings'][$schema['service_code']]['url']))$unbound++;}
    $ready=netchat_service_ready();
    $primary=$config['installed'][$config['primary_form']??'']??null;
    echo '<p class="nc-primary-summary">'.($primary?'Основная / общая: <a href="'.esc_url(netchat_service_admin_url(array('form'=>$primary['id']))).'">'.esc_html($primary['service_label']).'</a>':'Основная / общая форма не выбрана. Отметьте её в редакторе одной из форм.').'</p>';
    echo '<div class="nc-form-library"><header class="nc-library-header"><div><a class="nc-library-back" href="'.esc_url(admin_url('admin.php?page=netchat')).'">NetChat / Настройки виджета</a><h1>Формы услуг</h1><p>Сценарии обращения для разных задач посетителей</p></div><div class="nc-library-header-actions"><a class="button" href="'.esc_url(netchat_service_admin_url(array('view'=>'settings'))).'">Общие настройки</a><a class="button" href="'.esc_url(admin_url('admin.php?page=netchat-leads')).'">Заявки</a><a class="button button-primary" href="'.esc_url(netchat_service_admin_url(array('view'=>'create'))).'">Создать форму</a></div></header>';
    echo '<div class="nc-library-readiness'.($ready?' is-ready':'').'"><span class="nc-library-state-dot" aria-hidden="true"></span><div><strong>'.($ready?'Приём форм услуг включён':'Формы пока не принимают заявки').'</strong><span>'.($ready?'Включённые формы доступны посетителям сайта.':'Проверьте включение, документы и срок хранения в общих настройках.').'</span></div><a href="'.esc_url(netchat_service_admin_url(array('view'=>'settings'))).'">Настроить приём</a></div>';
    if(!$config['installed'])echo '<section class="nc-library-empty"><h2>Создайте первую форму</h2><p>Начните с пустой формы или установите семь готовых сценариев, которые можно настроить под свои услуги.</p><a class="button" href="'.esc_url(netchat_service_admin_url(array('view'=>'create'))).'">Создать свою форму</a></section>';
    else {
        echo '<div class="nc-library-tools"><nav class="nc-library-filters" aria-label="Фильтр форм">';
        foreach(array('all'=>array('Все',$total),'enabled'=>array('Включены',$enabled),'disabled'=>array('Выключены',$total-$enabled),'unbound'=>array('Без страницы',$unbound))as$key=>$filter)echo '<button type="button" data-form-filter="'.esc_attr($key).'" aria-pressed="'.($key==='all'?'true':'false').'">'.esc_html($filter[0]).'<span>'.esc_html($filter[1]).'</span></button>';
        echo '</nav><label class="nc-editor-search"><span>Поиск форм</span><input type="search" id="nc-form-search" placeholder="Название или заголовок"></label></div><section class="nc-editor-catalog" aria-label="Список форм"><div class="nc-editor-table-wrap"><table class="widefat nc-editor-table"><thead><tr><th scope="col">Форма</th><th scope="col">Вопросы</th><th scope="col">Размещение</th><th scope="col">Состояние</th><th scope="col"><span class="screen-reader-text">Действия</span></th></tr></thead><tbody>';
        $catalog=$primary?array($primary['id']=>$primary)+$config['installed']:$config['installed'];
        foreach($catalog as$id=>$schema){$url=$config['bindings'][$schema['service_code']]['url']??'';$editor=netchat_service_admin_url(array('form'=>$id));
            $active=netchat_service_form_enabled($schema);
            $steps=netchat_service_question_steps($schema)+1;
            echo '<tr data-form-row data-form-id="'.esc_attr($id).'" data-form-enabled="'.($active?'1':'0').'" data-form-bound="'.($url?'1':'0').'" data-form-search="'.esc_attr($schema['service_label'].' '.$schema['title']).'"><td class="nc-library-name"><a class="nc-editor-name" href="'.esc_url($editor).'">'.esc_html($schema['service_label']).'</a>'.(($config['primary_form']??'')===$id?'<span class="nc-primary-badge">Основная / общая</span>':'').'<p>'.esc_html($schema['title']).'</p></td><td class="nc-library-content" data-label="Вопросы"><strong>'.count($schema['fields']).'</strong><span>'.$steps.' '.($steps===2?'шага':'шага').'</span></td><td class="nc-library-placement" data-label="Страница">'.($url?'<a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">'.esc_html(rawurldecode(wp_parse_url($url,PHP_URL_PATH)?:'/')).'</a>':(($config['primary_form']??'')===$id?'<span class="nc-editor-muted">Главная и остальные страницы</span>':'<span class="nc-editor-muted">Без привязки</span>')).'</td><td class="nc-library-state"><span class="nc-editor-badge '.($active?'is-enabled':'is-disabled').'">'.($active?'Включена':'Выключена').'</span></td><td class="nc-library-actions"><div class="nc-library-action-row"><a class="button" href="'.esc_url($editor).'">Редактировать</a><details class="nc-form-actions"><summary aria-label="Действия с формой '.esc_attr($schema['service_label']).'" title="Действия"><span aria-hidden="true">⋯</span><span class="screen-reader-text">Действия</span></summary><div class="nc-library-action-menu">';
            echo '<a class="button" href="'.esc_url(admin_url('admin.php?page=netchat-transfer&form='.rawurlencode($id))).'">Экспорт формы</a>';
            netchat_service_admin_form_start('duplicate',$raw);echo '<input type="hidden" name="preset_id" value="'.esc_attr($id).'"><button class="button">Дублировать</button></form>';
            netchat_service_admin_form_start('toggle',$raw);echo '<input type="hidden" name="preset_id" value="'.esc_attr($id).'"><input type="hidden" name="target_enabled" value="'.($active?'0':'1').'"><button class="button">'.($active?'Выключить':'Включить').'</button></form><a class="nc-editor-danger" href="'.esc_url(netchat_service_admin_url(array('form'=>$id,'view'=>'delete'))).'">Удалить форму</a></div></details></div></td></tr>';
        }
        echo '</tbody></table></div><div id="nc-form-empty" class="nc-library-empty" hidden><h2>Формы не найдены</h2><p>Измените запрос или сбросьте фильтры.</p><button type="button" class="button" data-form-clear>Сбросить фильтры</button></div></section><p id="nc-form-count" class="nc-library-count" role="status">Показано '.$total.' из '.$total.'</p>';
    }
    $missing=array_diff_key(netchat_service_builtin(),$config['installed']);
    if($missing){echo '<div class="nc-library-presets"><div><strong>Готовые сценарии услуг</strong><p>Добавьте '.count($missing).' из исходного набора. Ваши формы сохранятся.</p></div>';netchat_service_admin_form_start('install',$raw);echo '<button class="button">'.($config['installed']?'Добавить недостающие формы':'Добавить встроенные формы').'</button></form></div>';}
    echo '</div>';
}
function netchat_service_admin_create_page($config,$raw){
    echo '<a class="nc-editor-back" href="'.esc_url(netchat_service_admin_url()).'">← Все формы</a>';
    netchat_service_admin_header('Создать форму','Начните с пустой формы или возьмите существующую за основу.');
    netchat_service_admin_form_start('create',$raw);
    echo '<div data-editor-form class="nc-form-create"><section>';
    netchat_service_admin_input('form_name','Название формы','',120);
    echo '<label class="nc-editor-input">Основа формы<select name="template_id"><option value="">Пустая форма</option>';
    foreach($config['installed']as$id=>$schema)echo '<option value="'.esc_attr($id).'">'.esc_html($schema['service_label']).'</option>';
    echo '</select></label><label class="nc-editor-input" data-blank-steps>Шаги пустой формы<select name="question_steps"><option value="1">Задача → контакт</option><option value="2">Задача → уточнения → контакт</option></select></label><p>У выбранного шаблона сохраняется его количество шагов. Новая форма создаётся выключенной. После настройки включите её в списке форм.</p></section><div class="nc-editor-savebar"><span data-dirty-status role="status">Укажите название формы</span><button class="button button-primary">Создать и открыть редактор</button></div></div></form>';
}
function netchat_service_admin_delete_page($schema,$config,$raw){
    echo '<a class="nc-editor-back" href="'.esc_url(netchat_service_admin_url()).'">← Все формы</a>';
    netchat_service_admin_header('Удалить форму','Проверьте выбранную форму перед удалением.');
    echo '<section class="nc-form-create"><h2>'.esc_html($schema['service_label']).'</h2><p>Форма исчезнет из списка и перестанет принимать новые обращения. Её привязка страницы будет удалена. Кнопки открытия этой формы на сайте больше не будут работать.</p><p><strong>Ранее полученные заявки и их содержимое сохранятся.</strong></p>';
    if(isset(netchat_service_builtin()[$schema['id']]))echo '<p>Встроенную форму можно вернуть кнопкой добавления недостающих форм.</p>';
    netchat_service_admin_form_start('delete',$raw);
    echo '<input type="hidden" name="preset_id" value="'.esc_attr($schema['id']).'"><label class="nc-service-check"><input type="checkbox" name="confirm_delete" value="1" required> Подтверждаю удаление формы «'.esc_html($schema['service_label']).'»</label><div class="nc-editor-toolbar"><button class="button nc-editor-danger">Удалить форму</button><a class="button" href="'.esc_url(netchat_service_admin_url(array('form'=>$schema['id']))).'">Отмена</a></div></form></section>';
}
function netchat_service_admin_globals($config,$raw) {
    echo '<a class="nc-editor-back" href="'.esc_url(netchat_service_admin_url()).'">← Все формы</a>';
    netchat_service_admin_header('Общие настройки','Настройки приёма и документы применяются ко всем формам.');
    netchat_service_admin_form_start('save_global',$raw);
    echo '<div data-editor-form><section><h2>Приём обращений</h2><label class="nc-service-check"><input type="checkbox" name="enabled" value="1" '.checked($config['enabled'],1,false).'> Включить формы услуг</label><p>Также включите приём заявок в <a href="'.esc_url(admin_url('admin.php?page=netchat#netchat-leads-channel')).'">настройках NetChat</a>.</p></section><section><h2>Документы и хранение</h2><div class="nc-service-grid">';
    foreach(array('document_url'=>'Согласие на обработку данных: URL','policy_url'=>'Политика конфиденциальности: URL','text_version'=>'Версия текста согласия','document_version'=>'Версия документа согласия','policy_version'=>'Версия политики')as$key=>$label)netchat_service_admin_input($key,$label,$config['documents'][$key],strpos($key,'url')!==false?500:190,strpos($key,'url')!==false?'url':'text');
    $lead=netchat_leads_config();echo '</div><label class="nc-service-check"><input type="checkbox" name="retention_confirmed" value="1" '.checked($config['retention_confirmed'],1,false).'> Подтверждаю срок хранения: '.esc_html($lead['retention_days']).' дней, спам — '.esc_html($lead['spam_retention_days']).' дней</label></section><div class="nc-editor-savebar"><span data-dirty-status role="status">Все изменения сохранены</span><button class="button button-primary">Сохранить настройки</button></div></div></form>';
}
function netchat_service_admin_editor($schema,$config,$raw) {
    $id=$schema['id'];$code=$schema['service_code'];$is_builtin=isset(netchat_service_builtin()[$id]);
    echo '<a class="nc-editor-back" href="'.esc_url(netchat_service_admin_url()).'">← Все формы</a>';
    netchat_service_admin_header($schema['service_label'],'Настройте форму и проверьте результат в предпросмотре.');
    echo '<div class="nc-editor-status"><span class="nc-editor-badge">'.(netchat_service_form_enabled($schema)?'Форма включена':'Форма выключена').'</span>';
    netchat_service_admin_form_start('toggle',$raw);
    echo '<input type="hidden" name="preset_id" value="'.esc_attr($id).'"><input type="hidden" name="return_to_editor" value="1"><input type="hidden" name="target_enabled" value="'.(netchat_service_form_enabled($schema)?'0':'1').'"><button class="button" data-editor-toggle>'.(netchat_service_form_enabled($schema)?'Выключить форму':'Включить форму').'</button></form><span data-state-hint>Сначала сохраните правки, затем измените состояние формы.</span></div>';
    echo '<button type="button" class="button nc-mobile-preview" data-show-preview>Предпросмотр формы</button>';
    netchat_service_admin_form_start('save_form',$raw);
    echo '<input type="hidden" name="preset_id" value="'.esc_attr($id).'"><div data-editor-form class="nc-editor-layout"><div class="nc-editor-main"><div class="nc-editor-savebar"><span data-dirty-status role="status">Все изменения сохранены</span><button class="button button-primary">Сохранить форму</button></div><nav class="nc-editor-tabs" aria-label="Разделы редактора">';
    foreach(array('texts'=>'Тексты','questions'=>'Вопросы','placement'=>'Размещение','reset'=>$is_builtin?'Сброс':'Управление')as$key=>$label)echo '<a href="#nc-panel-'.esc_attr($key).'" data-editor-tab="'.esc_attr($key).'">'.esc_html($label).'</a>';
    echo '</nav><section id="nc-panel-texts" data-editor-panel="texts"><h2>Тексты формы</h2>';
    netchat_service_admin_input('form_name','Название в списке форм',$schema['service_label'],120);
    echo '<label class="nc-service-check nc-primary-check"><input type="checkbox" name="is_primary" value="1" '.checked(($config['primary_form']??''),$id,false).'> Основная / общая форма</label><p>Открывается на главной и страницах без своей привязки. Можно выбрать только одну: отметка с предыдущей формы снимется при сохранении. Для приёма форма должна быть включена.</p>';
    foreach(array('title'=>'Заголовок','intro'=>'Вступление','submit_text'=>'Кнопка отправки','success_text'=>'Сообщение после отправки')as$key=>$label)netchat_service_admin_input('texts['.$key.']',$label,$schema[$key],in_array($key,array('title','submit_text'),true)?120:2000,in_array($key,array('intro','success_text'),true)?'textarea':'text');
    echo '</section><section id="nc-panel-questions" data-editor-panel="questions"><h2>Вопросы</h2><p>Раскройте вопрос для редактирования. Стрелки меняют порядок внутри шага.</p>';
    $steps=netchat_service_question_steps($schema);
    for($step=1;$step<=$steps;$step++){
        echo '<div class="nc-editor-step" data-step="'.$step.'"><h3>Шаг '.$step.' · '.($step===1?'Задача':'Уточнения').'</h3><div data-field-list>';
        foreach($schema['fields']as$field){if($field['step']!==$step)continue;$key=$field['key'];$prefix='fields['.$key.']';$types=array('text'=>'Развёрнутый ответ','short'=>'Короткий ответ','url'=>'Ссылка','radio'=>'Один вариант','multi'=>'Несколько вариантов');
            echo '<article class="nc-editor-question" data-field-key="'.esc_attr($key).'" data-field-type="'.esc_attr($field['type']).'" data-field-step="'.$step.'"><input type="hidden" name="order['.$step.'][]" value="'.esc_attr($key).'"><div class="nc-editor-question-head"><details><summary><span data-question-label>'.esc_html($field['label']).'</span><small>'.esc_html($types[$field['type']]??$field['type']).'</small></summary><div class="nc-editor-question-body">';
            netchat_service_admin_input($prefix.'[label]','Название вопроса',$field['label'],240);
            if(!in_array($field['type'],array('radio','multi'),true))netchat_service_admin_input($prefix.'[placeholder]','Пример ответа в поле',$field['placeholder']??'',500);
            else echo '<input type="hidden" name="'.esc_attr($prefix.'[placeholder]').'" value="'.esc_attr($field['placeholder']??'').'">';
            netchat_service_admin_input($prefix.'[helper]','Подсказка под вопросом',$field['helper']??'',1000);
            echo '<label class="nc-service-check"><input type="checkbox" name="'.esc_attr($prefix.'[required]').'" value="1" '.checked(!empty($field['required']),true,false).'> Обязательный вопрос</label>';
            if(isset($field['options'])){echo '<fieldset class="nc-editor-options"><legend>Варианты ответа</legend>';foreach($field['options']as$value=>$label){echo '<div data-option-key="'.esc_attr($value).'">';netchat_service_admin_input($prefix.'[options]['.$value.']','Вариант ответа',$label,240);echo '<button type="button" class="button" data-delete-option>Убрать вариант</button></div>';}echo '<button type="button" class="button" data-add-option>Добавить вариант</button></fieldset>';}
            echo '<p class="nc-editor-condition">'.esc_html(netchat_service_admin_condition($field,$schema)).'</p><button type="button" class="button nc-editor-danger" data-delete-field>Удалить вопрос</button><p data-field-dependency class="nc-editor-muted" hidden></p></div></details><div class="nc-editor-reorder"><button type="button" class="button" data-move="up" aria-label="Переместить вопрос вверх">↑</button><button type="button" class="button" data-move="down" aria-label="Переместить вопрос вниз">↓</button></div></div></article>';
        }
        echo '</div><div class="nc-add-question"><label>Тип нового вопроса<select data-new-type><option value="short">Короткий ответ</option><option value="text">Развёрнутый ответ</option><option value="url">Ссылка</option><option value="radio">Один вариант</option><option value="multi">Несколько вариантов</option></select></label><button type="button" class="button" data-add-field="'.$step.'">Добавить вопрос</button></div></div>';
    }
    echo '<div class="nc-editor-contact"><h3>Последний шаг · Контакт</h3><p>Имя, компания, канал связи, контакт, примечание и согласие. Контактный шаг общий для всех форм.</p></div></section><section id="nc-panel-placement" data-editor-panel="placement"><h2>Размещение на сайте</h2><p>Привязка выбирает эту услугу при открытии из виджета на указанной странице.</p>';
    $binding=$config['bindings'][$code]??array('url'=>'','page_id'=>0);
    netchat_service_admin_input('binding[url]','URL страницы текущего сайта',$binding['url'],500,'url');
    netchat_service_admin_input('binding[page_id]','ID страницы (необязательно)',$binding['page_id']?:'',10);
    echo '<h3>Кнопка открытия формы</h3><p>Вставьте шорткод в содержимое страницы. Он открывает эту форму независимо от привязки.</p><label class="nc-editor-input">Шорткод<textarea readonly data-shortcode rows="3">'.esc_textarea('[netchat_button service="'.$code.'" cta="hero" text="'.$schema['title'].'"]').'</textarea></label><button type="button" class="button" data-copy>Копировать шорткод</button><span data-copy-status role="status"></span><p>Для нижней кнопки замените <code>hero</code> на <code>bottom</code>.</p></section><section id="nc-panel-reset" data-editor-panel="reset"><h2>'.($is_builtin?'Восстановить исходную форму':'Управление формой').'</h2><p>'.($is_builtin?'Сброс вернёт тексты и вопросы этой формы к исходному набору. Привязка и сохранённые заявки сохранятся. Для сброса используйте отдельную кнопку под редактором.':'Чтобы временно остановить приём, выключите форму в списке. Для удаления используйте отдельную ссылку ниже. Старые заявки сохранятся.').'</p></section></div><aside class="nc-editor-preview" id="nc-preview-panel"><div class="nc-editor-preview-head"><button type="button" class="button nc-mobile-preview" data-hide-preview>← К редактированию</button><h2>Предпросмотр</h2><span>Текущие правки · без отправки</span><label>Шаг формы<select data-preview-step>';
    for($step=1;$step<=$steps+1;$step++)echo '<option value="'.$step.'">'.$step.' · '.($step===$steps+1?'Контакт':($step===1?'Задача':'Уточнения')).'</option>';
    echo '</select></label></div><div id="nc-service-root" data-preview></div><noscript><p>Для интерактивного предпросмотра включите JavaScript.</p></noscript></aside></div></form><section class="nc-editor-reset" data-reset-section><h2>'.($is_builtin?'Сброс этой формы':'Удаление формы').'</h2>';
    if(isset(netchat_service_builtin()[$id])){
        netchat_service_admin_form_start('reset',$raw);echo '<input type="hidden" name="preset_id" value="'.esc_attr($id).'"><label class="nc-service-check"><input type="checkbox" name="confirm_reset" value="1" required> Подтверждаю восстановление исходной формы</label><button class="button">Восстановить исходную форму</button></form>';
    }else echo '<p>Это ваша форма. Восстановление встроенного пресета к ней не применяется.</p>';
    echo '<p><a class="nc-editor-danger" href="'.esc_url(netchat_service_admin_url(array('form'=>$id,'view'=>'delete'))).'">Удалить эту форму</a></p>';
    echo '<template id="nc-preview-alternatives">'.netchat_contact_alternatives().'</template><script type="application/json" id="nc-editor-schema">'.wp_json_encode($schema,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
}
