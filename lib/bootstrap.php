<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Dhaka');
error_reporting(E_ALL);
ini_set('display_errors', app_config()['env'] === 'development' ? '1' : '0');
ini_set('log_errors', '1');
if (PHP_SAPI !== 'cli' && ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/numeric.php';
require_once __DIR__ . '/auth.php';
