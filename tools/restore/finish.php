<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_finish.php';
try {
    $options=getopt('',['config:','job:','operation:','recover']);
    if (!is_string($options['config'] ?? null) || !is_string($options['job'] ?? null) || !is_string($options['operation'] ?? null)) throw new RuntimeException('Required --config --job --operation');
    $config=restore_worker_load_config($options['config']);
    $bytes=stream_get_contents(STDIN,16385); if ($bytes===false || strlen($bytes)>16384) throw new RuntimeException('Invalid credential input');
    $credentials=json_decode($bytes,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($credentials) || array_diff(array_keys($credentials),['current_username','current_password','candidate_username','candidate_password','ordinary_username','ordinary_password'])) throw new RuntimeException('Invalid credential input');
    echo json_encode(restore_finish_run($config,$options['job'],$options['operation'],$credentials,array_key_exists('recover',$options)),JSON_THROW_ON_ERROR)."\n";
} catch(Throwable $error) { fwrite(STDERR,"Finish operation refused or interrupted; inspect private owner recovery state.\n"); exit(1); }
