<?php
/** Pure lifecycle transformations. The admin controller handles authorization and CAS. */
if(!defined('ABSPATH'))exit;
function netchat_service_admin_lifecycle($config,$operation,$post){
    $id=$post['preset_id']??'';
    if($operation==='install'){
        foreach(netchat_service_builtin()as$key=>$form)if(!isset($config['installed'][$key])){
            $form['schema_version']=max($form['schema_version'],(int)($config['deleted'][$key]['schema_version']??0)+1);
            $config['installed'][$key]=$form;unset($config['deleted'][$key]);
        }
        return array('config'=>$config,'id'=>'');
    }
    if(in_array($operation,array('duplicate','toggle','delete'),true)&&(!is_string($id)||!isset($config['installed'][$id])))return new WP_Error('form','Форма не найдена.',array('status'=>404));
    if($operation==='create'||$operation==='duplicate'){
        if(count($config['installed'])>=100)return new WP_Error('limit','Можно хранить до 100 форм.');
        $source=null;
        if($operation==='duplicate'){$source=$config['installed'][$id];$name=wp_html_excerpt($source['service_label'],110,'').' (копия)';}
        else {
            $name=netchat_service_admin_text($post['form_name']??null,120);
            if($name===null||$name==='')return new WP_Error('name','Укажите название формы: до 120 символов.');
            $template=$post['template_id']??'';
            if(!is_string($template)||($template!==''&&!isset($config['installed'][$template])))return new WP_Error('template','Выберите существующую основу формы.');
            if($template!=='')$source=$config['installed'][$template];
        }
        // Keep the identity within the existing lead table's 32-character form_key.
        do{$suffix=bin2hex(random_bytes(12));$id='nc_'.$suffix;}while(isset($config['installed'][$id])||isset($config['deleted'][$id]));
        $form=$source?:array('title'=>$name,'intro'=>'Расскажите о задаче. После этого оставьте удобный контакт.','submit_text'=>'Отправить обращение','success_text'=>'Спасибо! Я свяжусь с вами, чтобы обсудить задачу.','fields'=>array(array('key'=>'q_'.bin2hex(random_bytes(8)),'label'=>'Расскажите о вашей задаче','type'=>'text','required'=>true,'step'=>1,'placeholder'=>'Что хотите обсудить или сделать','helper'=>'')));
        $steps=$post['question_steps']??'1';
        if(!$source&&!in_array($steps,array('1','2'),true))return new WP_Error('steps','Выберите количество шагов вопросов.');
        $form['question_steps']=$source?netchat_service_question_steps($source):(int)$steps;
        $form['id']=$id;$form['service_code']='NC_'.strtoupper($suffix);$form['service_label']=$name;$form['schema_version']=1;$form['enabled']=0;
        $config['installed'][$id]=$form;
        return array('config'=>$config,'id'=>$id);
    }
    if($operation==='toggle'){
        $enabled=$post['target_enabled']??null;
        if(!in_array($enabled,array('0','1'),true))return new WP_Error('enabled','Некорректное состояние формы.');
        $form=&$config['installed'][$id];
        if(netchat_service_form_enabled($form)!==($enabled==='1')){$form['enabled']=(int)$enabled;$form['schema_version']++;}
        unset($form);return array('config'=>$config,'id'=>'');
    }
    if($operation==='delete'){
        if(($post['confirm_delete']??null)!=='1')return new WP_Error('confirm','Подтвердите удаление этой формы.');
        $form=$config['installed'][$id];
        $config['deleted'][$id]=array('schema_version'=>$form['schema_version'],'service_label'=>$form['service_label']);
        unset($config['installed'][$id],$config['bindings'][$form['service_code']]);
        if(($config['primary_form']??'')===$id)$config['primary_form']='';
        return array('config'=>$config,'id'=>'');
    }
    return new WP_Error('operation','Неизвестное действие.');
}

/** A source question cannot disappear while a dependent question or notice survives. */
function netchat_service_admin_dependencies($schema,$fields){
    $by_key=array_column($fields,null,'key');
    $check=function($condition)use($by_key){
        if(!is_array($condition)||!isset($by_key[$condition['key']??'']))return false;
        $source=$by_key[$condition['key']];
        return isset($source['options'])&&is_array($condition['values']??null)&&!array_diff($condition['values'],array_keys($source['options']));
    };
    foreach($fields as$field)if(isset($field['condition'])&&!$check($field['condition']))return new WP_Error('dependency','Сначала удалите зависимые вопросы. Этот вопрос управляет их показом.');
    foreach($schema['notices']??array()as$notice){
        if(isset($notice['condition'])&&!$check($notice['condition']))return new WP_Error('dependency','Этот вопрос управляет сообщением формы; его нельзя удалить.');
        foreach($notice['any']??array()as$condition)if(!$check($condition))return new WP_Error('dependency','Этот вопрос управляет сообщением формы; его нельзя удалить.');
    }
    return true;
}
