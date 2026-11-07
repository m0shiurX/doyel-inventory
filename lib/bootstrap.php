<?php
require_once __DIR__ . '/errors.php';
app_errors_register();
require_once __DIR__ . '/config.php';
date_default_timezone_set('Asia/Dhaka');
app_errors_configure(app_config());
require_once __DIR__ . '/restore_maintenance.php';
restore_maintenance_enter(app_config());
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
restore_maintenance_session_generation(app_config());
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/html.php';
require_once __DIR__ . '/numeric.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
