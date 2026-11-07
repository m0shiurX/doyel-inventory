<?php
/** Disposable integration acceptance. Requires an owner config with fixture=true.
 * Creates and drops only random doyel_r4_* sources and positively owned candidates.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_worker.php';
function r4_assert(bool $pass,string $label): void { if (!$pass) throw new RuntimeException($label); echo 'PASS '.$label."\n"; }
function r4_resign(string $input,string $output,callable $modify): void {
    $records=array_map(static fn($line)=>json_decode($line,true,32,JSON_THROW_ON_ERROR),explode("\n",rtrim(gzdecode(file_get_contents($input)),"\n")));
    $modify($records); $hash=hash_init('sha256'); $tables=[];
    foreach (doyel_schema_tables() as $table=>$definition) $tables[$table]=['count'=>0,'context'=>hash_init('sha256')];
    $lines=[];
    foreach ($records as $i=>$record) {
        if ($record['kind']==='footer') {
            foreach ($tables as $table=>$part) $record['tables'][$table]=['count'=>$part['count'],'sha256'=>hash_final($part['context'])];
            $record['payload_sha256']=hash_final($hash);
        }
        $line=doyel_backup_data_encode($record)."\n"; $lines[]=$line;
        if ($record['kind']!=='footer') hash_update($hash,$line);
        if ($record['kind']==='row') { $tables[$record['table']]['count']++; hash_update($tables[$record['table']]['context'],$line); }
    }
    file_put_contents($output,gzencode(implode('',$lines))); chmod($output,0600);
}
if ($argc!==2) { fwrite(STDERR,"Usage: php import-check.php /absolute/private-fixture-config.php\n"); exit(2); }
$owned=[]; $accounts=[]; $owner=null;
try {
    $config=restore_worker_load_config($argv[1]);
    if (($config['fixture'] ?? false)!==true || $config['port']!==33084) throw new RuntimeException('Disposable local fixture only');
    $owner=restore_worker_pdo($config);
    $source='doyel_r4_'.bin2hex(random_bytes(8)); $owned[]=$source;
    $owner->exec("CREATE DATABASE `$source` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $config['active_database']=$source; unset($config['runtime_config']);
    $pdo=restore_worker_pdo($config,$source);
    $pdo->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach (doyel_schema_tables() as $definition) $pdo->exec($definition['create']);
    $metadata=doyel_schema_expected_inventory()['columns'];
    foreach (doyel_schema_tables() as $table=>$definition) {
        $values=[];
        foreach ($metadata as $column) if ($column['TABLE_NAME']===$table) {
            $type=$column['COLUMN_TYPE']; $name=$column['COLUMN_NAME'];
            $value=str_contains($type,'int')?'0':(str_starts_with($type,'decimal')?'0':($type==='date'?'2024-01-01':($type==='datetime'?'2024-01-01 00:00:00':'x')));
            if ($name==='userAtho') $value='1';
            if ($name==='Note') $value="বাংলা '; DROP DATABASE other; -- \\ NULL";
            if ($name==='RegNo' || $name==='QT' || $name==='mobile2') $value=null;
            if ($name==='Qnty') $value='1.125';
            $values[]=$value;
        }
        $s=$pdo->prepare("INSERT INTO `$table` VALUES (".implode(',',array_fill(0,count($values),'?')).')'); $s->execute($values);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    $base=realpath(sys_get_temp_dir()).'/doyel-r4-'.bin2hex(random_bytes(8)); mkdir($base,0700); mkdir($base.'/jobs',0700);
    $config['restore_dir']=$base.'/jobs'; restore_jobs_initialize($config['restore_dir']);
    $package=$base.'/valid.doyel.gz'; $before=doyel_backup_package_write($pdo,$package);
    $queue=static function(string $path) use ($config): array { $request=restore_job_create_local($config['restore_dir'],$path,1,bin2hex(random_bytes(16))); $job=restore_worker_once($config); r4_assert($job['id']===$request['id'],'fixed job claimed'); return $job; };
    $job=$queue($package); r4_assert($job['state']==='ready','valid candidate ready');
    $state=restore_jobs_read_json($config['restore_dir'].'/owner/'.$job['id'].'/candidate.json'); $owned[]=$state['candidate_database'];
    $candidate=restore_worker_pdo($config,$state['candidate_database']);
    $actual=restore_worker_receipt($candidate);
    r4_assert($actual['tables']===$before['footer']['tables'] && $actual['inventory']===$before['footer']['inventory'],'all 13 tables exact digests and business inventory');
    $after=doyel_backup_package_write($pdo,$base.'/after.doyel.gz');
    r4_assert($after['footer']['tables']===$before['footer']['tables'] && $after['footer']['inventory']===$before['footer']['inventory'] && $after['header']['tables']===$before['header']['tables'],'source active database unchanged');
    r4_assert($candidate->query('SELECT Note FROM canteen_sales')->fetchColumn()==="বাংলা '; DROP DATABASE other; -- \\ NULL",'SQL-looking Unicode text retained literally');
    $q=$owner->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?'); $q->execute([$state['importer_user']]); r4_assert($q->fetchColumn()==='0' && $state['importer_revoked'],'importer removed before ready');
    r4_assert(restore_worker_once($config)['state']==='ready','ready restart does not reimport');
    restore_job_transition($config['restore_dir'],$job['id'],'ready','cancelled');
    // The exact provisioning helper used by the worker must deny every external scope.
    $scope=$state; $scope['importer_user']='dyr_'.bin2hex(random_bytes(12)); $accounts[]=$scope; $secret=bin2hex(random_bytes(32)); restore_worker_provision_importer($owner,$scope,$secret);
    $limited=restore_worker_pdo($config,$scope['candidate_database'],$scope['importer_user'],$secret); unset($secret);
    $grants=$limited->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    r4_assert(count($grants)===2 && !preg_match('/FILE|TRIGGER|EXECUTE|ROUTINE|GRANT OPTION/',implode(' ',$grants)),'importer has no global FILE trigger routine or grant rights');
    $denied=false; try { $limited->query("SELECT * FROM `$source`.l_user"); } catch (PDOException $e) { $denied=true; } r4_assert($denied,'importer cannot select active source');
    $neighbor=str_replace('_','a',$scope['candidate_database']); $owned[]=$neighbor; $owner->exec("CREATE DATABASE `$neighbor`");
    $denied=false; try { $limited->exec("CREATE TABLE `$neighbor`.escape_test (id INT)"); } catch (PDOException $e) { $denied=true; } r4_assert($denied,'escaped grant underscores cannot match another database');
    $limited=null; $owner->exec('DROP USER '.$owner->quote($scope['importer_user']).'@'.$owner->quote($scope['importer_host'])); $accounts=[];
    foreach (['crc','schema','footer_digest','footer','orphan','insert'] as $case) {
        $path=$base.'/'.$case.'.doyel.gz';
        if ($case==='footer_digest') { $records=explode("\n",rtrim(gzdecode(file_get_contents($package)),"\n")); $last=count($records)-1; $footer=json_decode($records[$last],true); $footer['payload_sha256']=str_repeat('0',64); $records[$last]=doyel_backup_data_encode($footer); file_put_contents($path,gzencode(implode("\n",$records)."\n")); }
        elseif ($case==='crc') { $bytes=file_get_contents($package); $bytes[strlen($bytes)-8]=chr(ord($bytes[strlen($bytes)-8])^1); file_put_contents($path,$bytes); }
        else r4_resign($package,$path,static function(array &$records) use ($case): void {
            if ($case==='schema') $records[0]['schema_fingerprint']=str_repeat('0',64);
            if ($case==='footer') { $records[count($records)-1]['inventory']['financial']['canteen_sales.Total']='1.00'; return; }
            foreach ($records as &$record) {
                if (($record['kind']??'')!=='row') continue;
                if ($case==='orphan' && $record['table']==='canteen_sales_detail') $record['values'][1]='123456';
                if ($case==='insert' && $record['table']==='canteen_ac_head') $record['values'][0]='not-an-integer';
            }
        });
        $failed=$queue($path); r4_assert($failed['state']==='failed',$case.' input refused');
        $stateFile=$config['restore_dir'].'/owner/'.$failed['id'].'/candidate.json';
        if (is_file($stateFile)) { $failedState=restore_jobs_read_json($stateFile); $owned[]=$failedState['candidate_database']; }
        if (in_array($case,['crc','schema','footer_digest'],true)) r4_assert(!file_exists($stateFile),$case.' refused before database creation');
        if ($case==='insert' || $case==='orphan') {
            $failedDb=restore_worker_pdo($config,$failedState['candidate_database']);
            r4_assert($failedDb->query('SELECT COUNT(*) FROM canteen_ac_head')->fetchColumn()==='0',$case.' rolls back all imported rows');
        }
    }
    $request=restore_job_create_local($config['restore_dir'],$package,1,bin2hex(random_bytes(16))); $claim=restore_jobs_claim($config['restore_dir']);
    restore_job_transition($config['restore_dir'],$request['id'],'submitted','preflight'); restore_job_transition($config['restore_dir'],$request['id'],'preflight','creating'); restore_job_transition($config['restore_dir'],$request['id'],'creating','importing'); restore_jobs_release($claim['lease']);
    $count=$owner->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'doyel_restore_%'")->fetchColumn();
    r4_assert(restore_worker_once($config)['state']==='failed','interrupted import fails closed');
    r4_assert($owner->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE 'doyel_restore_%'")->fetchColumn()===$count,'interrupted import creates no replacement and reuses no rows');
    echo 'Private fixture evidence: '.$base."\n";
} catch (Throwable $error) { fwrite(STDERR,'FAIL '.get_class($error).' '.$error->getMessage()."\n"); exit(1); }
finally {
    if ($owner instanceof PDO) {
        foreach ($accounts as $account) $owner->exec('DROP USER IF EXISTS '.$owner->quote($account['importer_user']).'@'.$owner->quote($account['importer_host']));
        foreach (array_unique($owned) as $db) {
            if (!preg_match('/^(?:doyel_r4_[a-f0-9]{16}|doyel_restore_[a-f0-9]{32}|doyelarestorea[a-f0-9]{32})$/D',$db)) continue;
            $owner->exec("DROP DATABASE `$db`");
        }
    }
}
