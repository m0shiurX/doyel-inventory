<?php
// Run with php.exe or php-cgi.exe -q -f; never publish this maintenance tool.
if (!in_array(PHP_SAPI, ['cli','cgi-fcgi'], true) || isset($_SERVER['REQUEST_METHOD'])) { http_response_code(404); exit; }
try {
    if (PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500) throw new RuntimeException('PHP 8.4 required');
    foreach (['pdo_mysql','mbstring','dom'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException('Missing ' . $extension);
    if (!defined('PASSWORD_ARGON2ID')) throw new RuntimeException('Argon2id unavailable');
    foreach (["nul\0password",str_repeat('x',73)] as $password) if (!password_verify($password, password_hash($password, PASSWORD_ARGON2ID))) throw new RuntimeException('Argon2id verify failed');
    echo 'PASS: PHP=' . PHP_VERSION . ' SAPI=' . PHP_SAPI . ' ini=' . (php_ini_loaded_file() ?: '(none)') . " pdo_mysql/mbstring/dom and Argon2id NUL/73-byte checks\n";
} catch (Throwable $e) { echo 'FAIL: ' . $e->getMessage() . "\n"; exit(1); }
