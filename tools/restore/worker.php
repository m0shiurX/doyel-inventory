<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_worker.php';
if ($argc !== 3 || $argv[1] !== '--once') { fwrite(STDERR,"Usage: php worker.php --once /absolute/private-owner-config.php\n"); exit(2); }
try {
    $config=restore_worker_load_config($argv[2]);
    $result=restore_worker_once($config);
    echo json_encode($result === null ? ['state'=>'idle'] : ['id'=>$result['id'],'state'=>$result['state'],'evidence'=>$result['evidence']],JSON_THROW_ON_ERROR)."\n";
    exit(($result['state'] ?? '') === 'failed' ? 1 : 0);
} catch (Throwable $error) { fwrite(STDERR,"Restore helper refused operation; review private owner configuration and sealed job contracts.\n"); exit(1); }
