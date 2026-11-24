<?php
define('DB_NAME','ncncf_synthetic');define('DB_USER','root');define('DB_PASSWORD','synthetic-only-test');define('DB_HOST','127.0.0.1');
define('DB_CHARSET','utf8mb4');define('DB_COLLATE','');
define('AUTH_KEY','synthetic-auth');define('SECURE_AUTH_KEY','synthetic-secure');define('LOGGED_IN_KEY','synthetic-logged');define('NONCE_KEY','synthetic-nonce');
define('AUTH_SALT','synthetic-auth-salt');define('SECURE_AUTH_SALT','synthetic-secure-salt');define('LOGGED_IN_SALT','synthetic-logged-salt');define('NONCE_SALT','synthetic-nonce-salt');
define('DISABLE_WP_CRON',true);$table_prefix='nc_joint_';
if(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');require_once ABSPATH.'wp-settings.php';
