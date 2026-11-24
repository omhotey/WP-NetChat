<?php
if(getenv('NC_NCF_SYNTHETIC')!=='isolated-container-v1')exit(2);
$_SERVER['HTTP_HOST']='synthetic.test';$_SERVER['REQUEST_URI']='/';require '/var/www/html/wp-load.php';
if(DB_NAME!=='ncncf_synthetic'||$wpdb->prefix!=='nc_e2e4_')throw new RuntimeException('Unexpected fixture');
function upgrade_assert($ok,$message){if(!$ok)throw new RuntimeException($message);}
$oldForms=get_option('netchat_service_forms');$oldSettings=get_option('netchat_settings');$oldRows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}netchat_leads ORDER BY id",ARRAY_A);
upgrade_assert(is_array($oldForms)&&!empty($oldForms['installed'])&&is_array($oldSettings)&&count($oldRows)===1,'legacy fixture');
delete_option('nc_ncf_protocol_marker');require '/candidate/netchat.php';require '/ncf/netchat-ysm.php';ncf_intake_activate();
upgrade_assert(get_option('netchat_service_forms')===$oldForms && get_option('netchat_settings')===$oldSettings,'legacy options remain');
upgrade_assert($wpdb->get_results("SELECT * FROM {$wpdb->prefix}netchat_leads ORDER BY id",ARRAY_A)===$oldRows,'legacy rows remain');
upgrade_assert(netchat_read_settings()['revision']===0,'old NC settings not imported');
upgrade_assert(ncf_intake_service_forms_config()['installed']===array(),'old forms not imported');
upgrade_assert((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ncf_intake_leads")===0,'old leads not imported');
echo "PASS legacy data remain in storage; widget settings, forms and leads are not migrated\n";
