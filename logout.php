<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>Logout</title></head><body>' . action_form('logout.php', [], 'Logout') . '</body></html>';
    exit;
}
// Retain the legacy navigation body accompanying the redirect.
include __DIR__ . '/menu.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => $params['samesite'] ?? 'Lax']);
}
session_destroy();
redirect('login.php');
