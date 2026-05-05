<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mijauth.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/update.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/plugins.php';

cms_session_start();
cms_db();
cms_ensure_plugins_directory();
cms_load_enabled_plugins();
