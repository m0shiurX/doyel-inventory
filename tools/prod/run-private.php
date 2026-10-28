<?php
// Owner CLI launcher: keep maintenance credentials in a private PHP config file.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../db/cleanup/paths.php';
try {
    if ($argc < 3) throw new RuntimeException('Usage: php tools/prod/run-private.php <private-maintenance.php> <inspect|cleanup|users> [tool options]');
    private_path($argv[1]);
    $scripts = ['inspect'=>__DIR__ . '/inspect.php', 'cleanup'=>__DIR__ . '/../../db/cleanup/cleanup.php', 'users'=>__DIR__ . '/../../db/cleanup/users.php'];
    if (!isset($scripts[$argv[2]])) throw new RuntimeException('Unknown maintenance tool');
    $config = require $argv[1];
    if (!is_array($config)) throw new RuntimeException('Private config must return an array');
    $env = getenv();
    foreach (['db_host','db_port','db_name','db_user','db_pass'] as $key) {
        if (!array_key_exists($key, $config) || !is_scalar($config[$key])) throw new RuntimeException('Private config missing scalar ' . $key);
        $env['DOYEL_' . strtoupper($key)] = (string)$config[$key];
    }
    $command = array_merge([PHP_BINARY, '-d', 'memory_limit=512M', $scripts[$argv[2]]], array_slice($argv, 3));
    $process = proc_open($command, [0=>STDIN, 1=>STDOUT, 2=>STDERR], $pipes, null, $env, ['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Cannot start maintenance CLI');
    exit(proc_close($process));
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
