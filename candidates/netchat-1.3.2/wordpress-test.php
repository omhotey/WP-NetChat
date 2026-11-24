<?php
if(getenv('NC_NCF_SYNTHETIC')!=='isolated-container-v1')exit(2);
$_SERVER['HTTP_HOST']='synthetic.test';$_SERVER['REQUEST_URI']='/';
require '/var/www/html/wp-load.php';
if(DB_NAME!=='ncncf_synthetic'||$wpdb->prefix!=='nc_e2e4_')throw new RuntimeException('Unexpected fixture');
require '/candidate/netchat.php';
function check_nc($value,$name){if(!$value)throw new RuntimeException($name);}
if(($argv[1]??'')==='read'){check_nc(netchat_get_config()['order']==='phone,telegram,whatsapp,messenger,max,vk,viber,email','order after new request');echo "PASS saved order after new WordPress request\n";exit;}
$old=netchat_defaults();unset($old['launcher_mode']);$old['enabled']['telegram']=1;$old['links']['telegram']='https://t.me/example';
add_option('netchat_settings_v3',array('schema_version'=>1,'revision'=>1,'settings'=>$old),'',false);
check_nc(netchat_get_config()['launcher_mode']==='channels','old options retain channels');
check_nc(netchat_get_contacts()['telegram']['url']==='https://t.me/example','native contact');
wp_set_current_user(1);$updated=netchat_get_config();$updated['launcher_mode']='form';$saved=netchat_save_settings($updated,1,wp_create_nonce('netchat_save_settings'));
check_nc(is_array($saved)&&$saved['settings']['launcher_mode']==='form','save new setting');
$reordered=$saved['settings'];$reordered['order']='phone,telegram,whatsapp,messenger,max,vk,viber,email';
$saved=netchat_save_settings($reordered,2,wp_create_nonce('netchat_save_settings'));check_nc(is_array($saved),'save order');
netchat_invalidate_settings();check_nc(netchat_get_config()['order']===$reordered['order'],'order after fresh read');
ob_start();netchat_render();$fallback=ob_get_clean();check_nc(strpos($fallback,'data-netchat-direct')===false,'missing addon fallback');
add_filter('netchat_channels',function($channels){$channels['forms']=array('enabled'=>true,'label'=>'Оставить заявку','icon'=>'','action'=>'netchat-forms-open');return $channels;});
ob_start();netchat_render();$render=ob_get_clean();
check_nc(strpos($render,'data-netchat-direct="netchat-forms-open"')!==false,'direct action');
check_nc(strpos($render,'aria-label="Оставить заявку"')!==false&&strpos($render,'title="Оставить заявку"')!==false,'direct label');
check_nc(preg_match('~id="nc-toggle"[^>]*>\s*<svg[^>]*>\s*<path[^>]*fill-rule="evenodd"~',$render)===1,'direct fallback form icon');
add_filter('netchat_channels',function($channels){$channels['forms']['icon']='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M2 3L4 5"/></svg>';return $channels;},20);
ob_start();netchat_render();$provided=ob_get_clean();check_nc(preg_match('~id="nc-toggle"[^>]*>\s*<svg[^>]*>\s*<path d="M2 3L4 5"~',$provided)===1,'provided action icon on launcher');
check_nc(!isset(netchat_get_contacts()['forms']),'contacts exclude addon action');
$state=get_option('netchat_settings_v3');$state['settings']['launcher_mode']='channels';update_option('netchat_settings_v3',$state,false);
ob_start();netchat_render();$multi=ob_get_clean();check_nc(strpos($multi,'data-netchat-direct')===false && strpos($multi,'data-netchat-action="netchat-forms-open"')!==false,'multiple channels retain menu');
$state['settings']['enabled']['telegram']=0;update_option('netchat_settings_v3',$state,false);
ob_start();netchat_render();$single=ob_get_clean();check_nc(strpos($single,'data-netchat-direct="netchat-forms-open"')!==false,'single form opens directly in channels mode');
$state['settings']['launcher_mode']='form';update_option('netchat_settings_v3',$state,false);ob_start();netchat_render();$singleExplicit=ob_get_clean();check_nc(strpos($singleExplicit,'data-netchat-direct="netchat-forms-open"')!==false,'single form opens directly in form mode');
echo "PASS single form and multiple channels modes\n";
echo "PASS legacy settings, contacts, save, fallback, direct action and label\n";
