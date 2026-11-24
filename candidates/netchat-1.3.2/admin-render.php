<?php
if(getenv('NC_NCF_SYNTHETIC')!=='isolated-container-v1')exit(2);
$_SERVER['HTTP_HOST']='synthetic.test';$_SERVER['REQUEST_URI']='/';
require '/var/www/html/wp-load.php';if(DB_NAME!=='ncncf_synthetic'||$wpdb->prefix!=='nc_e2e4_')exit(3);
require '/candidate/netchat.php';wp_set_current_user(1);netchat_admin_assets('toplevel_page_netchat');
ob_start();netchat_admin_page();$html=ob_get_clean();global $wp_scripts;
$data=$wp_scripts->get_data('netchat-admin','data');$json=preg_replace('/^var netchatAdmin =\s*|;\s*$/','',trim($data));
echo wp_json_encode(array('html'=>$html,'config'=>json_decode($json,true)));
