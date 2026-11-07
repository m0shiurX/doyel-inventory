<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2) . '/lib/restore_worker.php';
require_once dirname(__DIR__,2) . '/lib/restore_activation.php';
try {
    $options = getopt('', ['config:', 'job:']);
    if (!is_string($options['config'] ?? null) || !is_string($options['job'] ?? null)) throw new RuntimeException('Required: --config=private-owner.json --job=32hex; credentials on STDIN');
    $config = restore_worker_load_config($options['config']);
    $bytes = stream_get_contents(STDIN, 16385);
    if ($bytes === false || strlen($bytes)>16384) throw new RuntimeException('Invalid credential input');
    $credentials = json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($credentials) || array_keys($credentials) !== ['current_username','current_password','candidate_username','candidate_password']) throw new RuntimeException('Invalid credential input');
    echo json_encode(restore_activation_run($config,$options['job'],$credentials),JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) { fwrite(STDERR,"Activation failed; inspect private owner state and maintenance control.\n"); exit(1); }
