<?php
// Owner-only current-schema database conversion; uploaded SQL is never executed.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_worker.php';
function restore_convert_database(array $config,string $database,string $output): array
{
    restore_worker_identifier($database);
    $parent=realpath(dirname($output));
    if ($parent===false || !preg_match('~^(?:/|[A-Za-z]:[\\\\/])~',$output) || is_link($output) || file_exists($output) || !str_ends_with($output,'.doyel.gz')) throw new RuntimeException('New private .doyel.gz output required');
    foreach($config['web_roots'] as $web) {
        $base=restore_jobs_path_key((string)realpath($web)); $key=restore_jobs_path_key($parent);
        if ($key===$base || str_starts_with($key,$base.'/')) throw new RuntimeException('Output must be outside web roots');
    }
    $pdo=restore_worker_pdo($config,$database);$lock=restore_worker_pdo($config);$handle=null;$created=null;
    try {
        $handle=fopen($output,'xb');if($handle===false)throw new RuntimeException('Output already exists');chmod($output,0600);$created=fstat($handle);
        $tables=[];foreach(doyel_schema_tables() as $table=>$definition)$tables[]="`$database`.`$table` READ";
        $lock->exec('LOCK TABLES '.implode(', ',$tables));
        doyel_schema_assert($pdo);restore_worker_orphans($pdo);
        $written=doyel_backup_package_write($pdo,$handle);
        if(!fflush($handle)||(function_exists('fsync')&&!fsync($handle)))throw new RuntimeException('Cannot sync package');
        $read=doyel_backup_package_read($output);
        if ($written['header']!==$read['header'] || $written['footer']!==$read['footer']) throw new RuntimeException('Conversion verification failed');
        return ['bytes'=>$read['compressed_bytes'],'sha256'=>$read['sha256'],'tables'=>count($read['footer']['tables'])];
    } catch(Throwable $error) {
        if(is_resource($handle)){fclose($handle);$handle=null;}
        clearstatcache(true,$output);$now=is_file($output)&&!is_link($output)?stat($output):false;
        if($created!==null && $now!==false && $created['ino']>0 && $now['dev']===$created['dev'] && $now['ino']===$created['ino'])unlink($output);
        throw new RuntimeException('Conversion verification failed');
    } finally{if(is_resource($handle))fclose($handle);$lock->exec('UNLOCK TABLES');}
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    try {
        $options=getopt('',['config:','database:','output:']);
        foreach(['config','database','output'] as $key) if(!is_string($options[$key] ?? null)) throw new RuntimeException('Required --config --database --output');
        echo json_encode(restore_convert_database(restore_worker_load_config($options['config']),$options['database'],$options['output']),JSON_THROW_ON_ERROR)."\n";
    } catch(Throwable $error) { fwrite(STDERR,"Conversion refused. Use an approved current-schema database and a new private output file.\n");exit(1); }
}
