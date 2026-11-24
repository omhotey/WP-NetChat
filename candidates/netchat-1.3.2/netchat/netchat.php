<?php
/**
 * Plugin Name: NetChat
 * Plugin URI: https://github.com/omhotey/WP-NetChat
 * Description: Contact channel launcher with an optional addon action API. / Кнопка каналов связи с API дополнительных действий.
 * Version: 1.3.2
 * Author: omhotey
 * License: GPLv2 or later
 * Text Domain: netchat
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */
if (!defined('ABSPATH')) exit;
define('NETCHAT_VER','1.3.2');
define('NETCHAT_FILE',__FILE__);
define('NETCHAT_DIR',plugin_dir_path(__FILE__));
define('NETCHAT_URL',plugin_dir_url(__FILE__));
define('NETCHAT_CHANNELS_API_VERSION',1);
foreach(array('settings','icons','channels','frontend','admin') as $module)require_once NETCHAT_DIR.'includes/'.$module.'.php';
