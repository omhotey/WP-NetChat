<?php
if (!defined('ABSPATH')) exit;
function netchat_public_page(){return !is_admin()&&!in_array($GLOBALS['pagenow']??'',array('wp-login.php','wp-register.php','wp-activate.php'),true);}
function netchat_enqueue(){
 if(!netchat_public_page()||!netchat_get_channels())return;$c=netchat_get_config();
 wp_enqueue_style('netchat-front',NETCHAT_URL.'assets/css/style.css',array(),NETCHAT_VER);
 wp_enqueue_script('netchat-front',NETCHAT_URL.'assets/js/front.js',array(),NETCHAT_VER,true);
 wp_localize_script('netchat-front','netchat',array('speed'=>$c['animation'],'hintDelay'=>$c['hint_delay']*1000,'buttonDelay'=>$c['button_delay']*1000,'hintMobile'=>$c['hint_mobile'],'buttonScrollPercent'=>$c['button_scroll_percent'],'hintScrollPercent'=>$c['hint_scroll_percent']));
}
add_action('wp_enqueue_scripts','netchat_enqueue',20);
function netchat_render(){
 if(!netchat_public_page())return;$channels=netchat_get_channels();if(!$channels)return;$c=netchat_get_config();$p=$c['position'];$left=$p['left']!=='';$top=$p['top']!=='';
 $direct=false;$direct_label='';$direct_icon='';if($c['launcher_mode']==='form'||count($channels)===1)foreach($channels as $item)if(($item['action']??'')==='netchat-forms-open'){$direct=true;$direct_label=$item['label'];$direct_icon=$item['icon'];break;}
 $style=($top?'top:'.(int)$p['top']:'bottom:'.(int)($p['bottom']!==''?$p['bottom']:30)).'px;'.($left?'left:'.(int)$p['left']:'right:'.(int)($p['right']!==''?$p['right']:20)).'px';
 $style.=';--nc-size:'.$c['size'].'px;--nc-icon:'.round($c['size']*.5).'px;--nc-color:'.$c['color'].';--nc-duration:'.$c['animation'].'ms';
 $wait=$c['button_delay']>0||$c['button_scroll_percent']>0;
 $classes=($left?' nc-at-left':'').($top?' nc-at-top':'').($wait?' nc-scroll-wait':'');
 if(!$c['desktop']&&!$c['mobile'])$classes.=' nc-hide-all';elseif(!$c['desktop'])$classes.=' nc-hide-desktop';elseif(!$c['mobile'])$classes.=' nc-hide-mobile';
 echo '<div id="netchat" class="nc-wrap'.esc_attr($classes).'" style="'.esc_attr($style).'"><div class="nc-col"><div id="nc-bubble" class="nc-bubble" aria-hidden="true">';
 foreach($channels as $key=>$item){
  $class='nc-btn nc-item nc-'.esc_attr($key);$attrs=' class="'.$class.'" aria-label="'.esc_attr($item['label']).'" data-nc-tooltip="'.esc_attr($item['label']).'" title="'.esc_attr($item['label']).'" tabindex="-1"';
  $icon=$item['icon']!==''?$item['icon']:'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="7" fill="currentColor"/></svg>';
  if(isset($item['action']))echo '<button type="button" data-netchat-action="'.esc_attr($item['action']).'"'.$attrs.'>'.$icon.'</button>';
  else{
   $href=esc_url($item['url'],array('http','https','mailto','tel','viber'));if($href==='')continue;
   $target=preg_match('~^https?://~i',$item['url'])?' target="_blank" rel="noopener noreferrer"':'';
   echo '<a href="'.$href.'"'.$target.$attrs.'>'.$icon.'</a>';
  }
 }
 $main_icon=$direct?($direct_icon!==''?$direct_icon:'<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" fill-rule="evenodd" d="M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm2 5v2h8V7H8zm0 4v2h8v-2H8zm0 4v2h6v-2H8z"/></svg>'):'<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="18" r="2"/></svg>';
 echo '</div><div class="nc-main-wrap"><button type="button" id="nc-toggle" class="nc-btn nc-main'.($direct?' nc-main-form':'').'" aria-label="'.esc_attr($direct?$direct_label:netchat_text('open')).'"'.($direct?' data-netchat-direct="netchat-forms-open" title="'.esc_attr($direct_label).'" data-nc-tooltip="'.esc_attr($direct_label).'"':' aria-controls="nc-bubble" aria-expanded="false"').($wait?' hidden':'').'>'.$main_icon.'</button>';
 if($c['hint']&&$c['hint_text']!=='')echo '<div id="nc-hint" class="nc-hint'.(!$c['hint_mobile']?' nc-hint-no-mobile':'').'" hidden><div class="nc-hint-content">'.wp_kses_post($c['hint_text']).'</div><button type="button" class="nc-hint-close" aria-label="'.esc_attr(netchat_text('dismiss')).'">&times;</button></div>';
 echo '</div></div></div>';
}
add_action('wp_footer','netchat_render',99);
