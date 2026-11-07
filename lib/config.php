<?php
// Environment values override the optional local file, including an empty password.
function app_config(): array
{
    static $config;
    if ($config !== null) return $config;
    $config = ['db_host' => 'localhost', 'db_port' => 3306, 'db_user' => 'root', 'db_pass' => '', 'db_name' => 'software', 'env' => 'production', 'log_dir' => sys_get_temp_dir() . '/doyel-private-' . substr(hash('sha256', dirname(__DIR__)), 0, 12) . '/logs', 'backup_dir' => '', 'restore_dir' => '', 'restore_generation' => 0];
    $local = dirname(__DIR__) . '/config.local.php';
    if (is_file($local)) {
        $overrides = require $local;
        if (!is_array($overrides)) throw new RuntimeException('config.local.php must return an array');
        $config = array_replace($config, $overrides);
    }
    foreach (['db_host', 'db_port', 'db_user', 'db_pass', 'db_name', 'env', 'log_dir', 'backup_dir', 'restore_dir', 'restore_generation'] as $key) {
        $value = getenv('DOYEL_' . strtoupper($key));
        if ($value !== false) $config[$key] = $value;
    }
    if (filter_var($config['restore_generation'], FILTER_VALIDATE_INT) === false || (int)$config['restore_generation'] < 0) throw new RuntimeException('Invalid restore generation');
    $config['restore_generation'] = (int)$config['restore_generation'];
    $config['db_port'] = (int)$config['db_port'];
    if ($config['db_port'] < 1 || $config['db_port'] > 65535) throw new RuntimeException('Invalid database port');
    return $config;
}
