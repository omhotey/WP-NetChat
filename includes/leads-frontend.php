<?php
/** Public contact form and independent launch buttons. */
if (!defined('ABSPATH')) exit;
add_action('wp_enqueue_scripts', function() {
    if(netchat_ncf_workspace_active())return;
    $c=netchat_leads_config(); if(is_admin() || !$c['enabled']) return;
    wp_enqueue_style('netchat-leads', NETCHAT_URL.'assets/css/leads.css', array(), NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/leads.css'));
    wp_enqueue_style('netchat-contact-alternatives',NETCHAT_URL.'assets/css/contact-alternatives.css',array('netchat-leads'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/contact-alternatives.css'));
    wp_enqueue_script('netchat-leads', NETCHAT_URL.'assets/js/leads.js', array(), NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/js/leads.js'), true);
    wp_localize_script('netchat-leads','netchatLeads',array('endpoint'=>rest_url('netchat/v1/leads'),'successText'=>$c['success_text'],
        'errorText'=>nc_t('Could not send. Your entered data has been kept. Please retry.','Не удалось отправить. Введённые данные сохранены. Повторите попытку.'),
        'contactText'=>nc_t('Enter an email address or phone number.','Введите email или телефон.'),'busyText'=>nc_t('Sending…','Отправляем…')));
},25);
add_action('wp_enqueue_scripts',function(){if(netchat_ncf_workspace_active())return;if(!is_admin()&&!empty(netchat_leads_config()['enabled']))wp_enqueue_style('netchat-popup',NETCHAT_URL.'assets/css/popup.css',array('netchat-leads','netchat-contact-alternatives'),NETCHAT_VER.'.'.filemtime(NETCHAT_DIR.'assets/css/popup.css'));},40);
function netchat_leads_shortcode($atts,$content=null) {
    if(netchat_ncf_workspace_active())return '';
    $c=netchat_leads_config(); if(!$c['enabled']) return '';
    $a=shortcode_atts(array('text'=>$c['title'],'context'=>'','source'=>'','service'=>'','cta'=>'other'),$atts);
    if ($a['service'] !== '' && !netchat_service_ready()) return '';
    if ($a['service'] !== '' && !in_array($a['service'],array_column(netchat_service_active_forms(),'service_code'),true)) return '';
    $text=$content!==null && trim($content)!==''?wp_strip_all_tags($content):$a['text'];
    $service = $a['service'] !== '' ? ' data-netchat-service="'.esc_attr($a['service']).'" data-netchat-cta="'.esc_attr($a['cta']).'"' : '';
    return '<button type="button" class="nc-contact-cta" data-netchat-open="form"'.$service.' data-netchat-context="'.esc_attr($a['context']?:$a['source']).'">'.esc_html($text).'</button>';
}
add_shortcode('netchat_button','netchat_leads_shortcode');
add_shortcode('netchat_form','netchat_leads_shortcode');
add_action('wp_footer','netchat_leads_render_form',100);
function netchat_leads_render_form() {
    if(netchat_ncf_workspace_active())return;
    $c=netchat_leads_config(); if(is_admin() || !$c['enabled']) return;
    $types=array('name'=>'text','email'=>'email','phone'=>'tel','company'=>'text','message'=>'textarea');
    $limits=array('name'=>190,'email'=>254,'phone'=>64,'company'=>190,'message'=>10000);
    $autocomplete=array('name'=>'name','email'=>'email','phone'=>'tel','company'=>'organization');
    ?>
    <div id="nc-leads-modal" class="nc-leads-overlay" data-form-presentation="<?php echo esc_attr(netchat_get_config()['form_presentation']); ?>" data-form-drawer-side="<?php echo esc_attr(netchat_get_config()['form_drawer_side']); ?>" hidden>
      <section class="nc-leads-dialog" role="dialog" aria-modal="true" aria-labelledby="nc-leads-title" tabindex="-1">
        <div class="nc-leads-closebar"><button type="button" class="nc-leads-close" data-netchat-close aria-label="<?php echo esc_attr(nc_t('Close contact form','Закрыть форму')); ?>">&times;</button></div>
        <h2 id="nc-leads-title"><?php echo esc_html($c['title']); ?></h2>
        <form id="nc-leads-form">
        <?php foreach($types as $key=>$type): if(!$c['fields'][$key]['enabled'])continue; $f=$c['fields'][$key]; $id='nc-lead-'.$key; ?>
          <div class="nc-leads-field"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($f['label']); if($f['required'])echo ' <span aria-hidden="true">*</span>'; ?></label>
          <?php if($type==='textarea'): ?>
            <textarea id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>" rows="4" maxlength="<?php echo esc_attr($limits[$key]); ?>" placeholder="<?php echo esc_attr($f['placeholder']); ?>" <?php if($f['required'])echo 'required'; ?>></textarea>
          <?php else: ?>
            <input id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($key); ?>" type="<?php echo esc_attr($type); ?>" maxlength="<?php echo esc_attr($limits[$key]); ?>" autocomplete="<?php echo esc_attr($autocomplete[$key]); ?>" placeholder="<?php echo esc_attr($f['placeholder']); ?>" <?php if($f['required'])echo 'required'; ?>>
          <?php endif; ?></div>
        <?php endforeach; ?>
          <div class="nc-leads-trap" aria-hidden="true"><label for="nc-lead-website">Website</label><input id="nc-lead-website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
        <?php if($c['consent']['enabled']): ?>
          <label class="nc-leads-consent"><input name="consent" type="checkbox" value="1" <?php if($c['consent']['required'])echo 'required'; ?>><span><?php echo esc_html($c['consent']['text']); if($c['consent']['url']): ?> <a href="<?php echo esc_url($c['consent']['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(nc_t('Privacy policy','Политика обработки данных')); ?></a><?php endif; ?></span></label>
        <?php endif; ?>
          <button type="submit" class="nc-leads-submit"><?php echo esc_html($c['button_text']); ?></button>
          <p id="nc-leads-status" class="nc-leads-status" role="status" aria-live="polite" aria-atomic="true"></p>
        </form>
        <div id="nc-leads-success" class="nc-leads-success" hidden tabindex="-1" role="status" aria-live="polite">
          <div class="nc-leads-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <h3><?php echo esc_html(nc_t('Request received','Заявка отправлена')); ?></h3>
          <p><?php echo esc_html($c['success_text']); ?></p>
          <button type="button" class="nc-leads-done" data-netchat-close><?php echo esc_html(nc_t('Done','Готово')); ?></button>
        </div>
        <?php echo netchat_contact_alternatives('nc-contact-alternatives'); ?>
      </section>
    </div>
    <?php
}
/** Only configured, enabled and validated public contact links. */
function netchat_contact_alternatives($id=''){
    $c=netchat_get_config();$messengers=netchat_messengers();$links='';
    foreach(array_unique(array_filter(array_map('trim',explode(',',$c['order']))))as$key){
        if(!isset($messengers[$key])||empty($c['enabled'][$key])||empty($c['links'][$key]))continue;
        $url=netchat_validate_url($c['links'][$key],$key);if($url==='')continue;
        $protocols=wp_allowed_protocols();if($key==='viber')$protocols[]='viber';$href=esc_url($url,$protocols);if($href==='')continue;
        $m=$messengers[$key];$target=in_array($key,array('email','phone'),true)?'':' target="_blank" rel="noopener noreferrer"';
        $links.='<a href="'.$href.'"'.$target.' data-contact-channel="'.esc_attr($key).'"><span class="nc-alternative-icon" aria-hidden="true" style="background:'.esc_attr($m['color']).'">'.netchat_svg_icon($key).'</span><span>'.esc_html($m['name']).'</span></a>';
    }
    if($links==='')return '';
    return '<aside class="nc-contact-alternatives" data-contact-alternatives'.($id!==''?' id="'.esc_attr($id).'"':'').'><p class="nc-alternatives-title">'.esc_html(nc_t('Or contact directly','Или связаться напрямую')).'</p><nav aria-label="'.esc_attr(nc_t('Alternative contact channels','Другие способы связи')).'">'.$links.'</nav></aside>';
}
