<?php
// Owner CLI only. Uploaded packages contain data, never executable DDL or SQL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/restore_jobs.php';
require_once __DIR__ . '/backup_package.php';

function restore_worker_identifier(string $name): string
{
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D', $name)) throw new RuntimeException('Unsafe database identifier');
    return $name;
}

function restore_worker_private_file(string $path): string
{
    if (!preg_match('~^(?:/|[A-Za-z]:[\\\\/]|[\\\\]{2})~', $path) || str_contains($path, "\0")) throw new RuntimeException('Absolute private file required');
    $probe = $path;
    while ($probe !== dirname($probe)) {
        if (is_link($probe) || basename($probe) === '..' || basename($probe) === '.') throw new RuntimeException('Unsafe private file path');
        $probe = dirname($probe);
    }
    $resolved = realpath($path);
    if (!is_file($path) || $resolved === false || restore_jobs_path_key($resolved) !== restore_jobs_path_key($path)) throw new RuntimeException('Private owner configuration required');
    return $resolved;
}

function restore_worker_load_config(string $path): array
{
    $path = restore_worker_private_file($path);
    if (PHP_OS_FAMILY !== 'Windows' && (fileperms($path) & 0077) !== 0) throw new RuntimeException('Owner configuration permissions must be private');
    $config = require $path;
    if (!is_array($config)) throw new RuntimeException('Invalid owner configuration');
    foreach (['restore_dir','host','owner_user','owner_password','active_database'] as $key) if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') throw new RuntimeException('Incomplete owner configuration');
    if (($config['host'] ?? '') !== '127.0.0.1' || !is_int($config['port'] ?? null) || !in_array($config['port'], ($config['fixture'] ?? false) === true ? [13306,33084] : [13306], true)) throw new RuntimeException('Unapproved local database endpoint');
    restore_worker_identifier($config['active_database']);
    $config['restore_dir'] = restore_jobs_path($config['restore_dir']);
    $config['importer_host'] ??= ($config['fixture'] ?? false) === true ? '%' : '127.0.0.1';
    if (!in_array($config['importer_host'], ['127.0.0.1','localhost','%'], true) || ($config['importer_host'] === '%' && ($config['fixture'] ?? false) !== true)) throw new RuntimeException('Unsafe importer account host');
    if (!isset($config['web_roots']) || !is_array($config['web_roots']) || $config['web_roots'] === []) throw new RuntimeException('Explicit web roots required');
    foreach (array_merge($config['web_roots'], [$config['restore_dir']]) as $root) {
        if (!is_string($root) || realpath($root) === false) throw new RuntimeException('Invalid private boundary');
        $key = restore_jobs_path_key((string)realpath($root));
        $file = restore_jobs_path_key($path);
        if ($file === $key || str_starts_with($file, $key . '/')) throw new RuntimeException('Owner configuration crosses public/job boundary');
    }
    foreach ($config['web_roots'] as $root) {
        $key = restore_jobs_path_key((string)realpath($root));
        $private = restore_jobs_path_key($config['restore_dir']);
        if ($private === $key || str_starts_with($private, $key . '/')) throw new RuntimeException('Restore storage crosses web boundary');
    }
    if (isset($config['runtime_config'])) {
        if (!is_string($config['runtime_config'])) throw new RuntimeException('Invalid runtime configuration path');
        $runtimePath = restore_worker_private_file($config['runtime_config']);
        foreach (array_merge($config['web_roots'], [$config['restore_dir']]) as $root) {
            $base = restore_jobs_path_key((string)realpath($root)); $key = restore_jobs_path_key($runtimePath);
            if ($key === $base || str_starts_with($key, $base . '/')) throw new RuntimeException('Runtime config crosses public/job boundary');
        }
        if (PHP_OS_FAMILY !== 'Windows' && (fileperms($runtimePath) & 0007) !== 0) throw new RuntimeException('Runtime configuration is not private');
        $runtime = require $runtimePath;
        if (!is_array($runtime) || ($runtime['db_host'] ?? null) !== $config['host'] || (int)($runtime['db_port'] ?? 0) !== $config['port'] || ($runtime['restore_dir'] ?? null) !== $config['restore_dir'] || !is_string($runtime['db_name'] ?? null)) throw new RuntimeException('Runtime connection identity mismatch');
        $config['active_database'] = restore_worker_identifier($runtime['db_name']);
        $config['runtime_config'] = $runtimePath;
    }
    return $config;
}

function restore_worker_pdo(array $config, ?string $database = null, ?string $user = null, ?string $password = null): PDO
{
    if ($database !== null) restore_worker_identifier($database);
    $dsn = 'mysql:host=' . $config['host'] . ';port=' . $config['port'] . ';charset=utf8mb4' . ($database === null ? '' : ';dbname=' . $database);
    $pdo = new PDO($dsn, $user ?? $config['owner_user'], $password ?? $config['owner_password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_STRINGIFY_FETCHES=>true, PDO::MYSQL_ATTR_LOCAL_INFILE=>false, PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
    $pdo->exec('SET SESSION lock_wait_timeout=15');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout=15');
    return $pdo;
}

/** Private ownership evidence is not an authorization to delete or activate. */
function restore_worker_candidate_identity(array $config, array $state): void
{
    $keys = ['version','job_id','candidate_database','candidate_nonce','expected_active_database','source_package_sha256','created_at','owned','activated','importer_user','importer_host','importer_revoked','validation_receipt'];
    if (array_diff(array_keys($state), $keys) || array_diff($keys, array_keys($state)) || $state['version'] !== 1 || $state['owned'] !== true || !is_bool($state['activated']) || !is_bool($state['importer_revoked'])) throw new RuntimeException('Invalid candidate ownership evidence');
    restore_jobs_id($state['job_id']); restore_jobs_id($state['candidate_nonce']);
    if ($state['candidate_database'] !== 'doyel_restore_' . $state['candidate_nonce'] || $state['candidate_database'] === $config['active_database'] || $state['expected_active_database'] !== $config['active_database'] || !preg_match('/^[a-f0-9]{64}$/D', $state['source_package_sha256']) || !preg_match('/^dyr_[a-f0-9]{24}$/D', $state['importer_user']) || $state['importer_host'] !== $config['importer_host'] || !is_string($state['created_at']) || !preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\+00:00$/D', $state['created_at']) || ($state['validation_receipt'] !== null && !is_array($state['validation_receipt']))) throw new RuntimeException('Candidate identity mismatch');
}

function restore_worker_provision_importer(PDO $owner, array $state, string $password): void
{
    $db = restore_worker_identifier($state['candidate_database']);
    $account = $owner->quote($state['importer_user']).'@'.$owner->quote($state['importer_host']);
    $owner->exec('CREATE USER '.$account.' IDENTIFIED BY '.$owner->quote($password));
    $grantDb = str_replace('_', '\\_', $db); // Database grants use wildcard matching.
    $owner->exec("GRANT SELECT,INSERT,CREATE,ALTER,INDEX,REFERENCES ON `$grantDb`.* TO $account");
}

function restore_worker_orphans(PDO $pdo): void
{
    foreach (doyel_schema_expected_inventory()['foreign_keys'] as $key) {
        $table=$key['TABLE_NAME']; $column=$key['COLUMN_NAME']; $parent=$key['REFERENCED_TABLE_NAME']; $target=$key['REFERENCED_COLUMN_NAME'];
        if ((string)$pdo->query("SELECT COUNT(*) FROM `$table` c LEFT JOIN `$parent` p ON c.`$column`=p.`$target` WHERE c.`$column` IS NOT NULL AND p.`$target` IS NULL")->fetchColumn() !== '0') throw new RuntimeException('Candidate relationships failed');
    }
}

/** Recompute exact canonical row digests without writing a second package. */
function restore_worker_receipt(PDO $pdo): array
{
    doyel_schema_assert($pdo); restore_worker_orphans($pdo);
    $tables=[]; $buffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try {
    foreach (doyel_schema_tables() as $table=>$definition) {
        $hash=hash_init('sha256'); $count=0; $key=$definition['primary_key'];
        $cursor=$pdo->query("SELECT * FROM `$table` ORDER BY `$key`");
        while (($values=$cursor->fetch(PDO::FETCH_NUM)) !== false) { hash_update($hash,doyel_backup_data_encode(['kind'=>'row','table'=>$table,'values'=>$values])."\n"); $count++; }
        $cursor->closeCursor(); $tables[$table]=['count'=>$count,'sha256'=>hash_final($hash)];
    }
    return ['tables'=>$tables,'inventory'=>doyel_backup_package_inventory($pdo)];
    } finally { $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered); }
}

function restore_worker_once(array $config): ?array
{
    $claim=restore_jobs_claim($config['restore_dir']);
    if ($claim === null) return null;
    $root=$config['restore_dir']; $job=$claim['job']; $dir=$claim['directory']; $state=null; $owner=null; $importer=null;
    try {
        if (in_array($job['state'], ['ready','activation_requested','frozen','backed_up','switching','awaiting_acceptance'], true)) return $job;
        if (!in_array($job['state'], ['submitted','preflight'], true)) throw new RuntimeException('Interrupted import requires a fresh job');
        if ($job['state'] === 'submitted') $job=restore_job_transition($root,$job['id'],'submitted','preflight');
        $receipt=doyel_backup_package_read($claim['package'],null,['compressed_bytes'=>RESTORE_JOB_MAX_UPLOAD]);
        // Workspace and DB can reside on distinct volumes: owner must reserve DB space too.
        $required=$receipt['uncompressed_bytes']*2+67108864;
        $free=disk_free_space($root);
        if ($free === false || $free < $required) throw new RuntimeException('Insufficient private workspace budget');
        $owner=restore_worker_pdo($config);
        $owner->query('SELECT VERSION()')->closeCursor();
        $nonce=bin2hex(random_bytes(16));
        $state=['version'=>1,'job_id'=>$job['id'],'candidate_database'=>'doyel_restore_'.$nonce,'candidate_nonce'=>$nonce,'expected_active_database'=>$config['active_database'],'source_package_sha256'=>$job['sha256'],'created_at'=>gmdate(DATE_ATOM),'owned'=>true,'activated'=>false,'importer_user'=>'dyr_'.bin2hex(random_bytes(12)),'importer_host'=>$config['importer_host'],'importer_revoked'=>false,'validation_receipt'=>null];
        restore_worker_candidate_identity($config,$state);
        restore_jobs_write_json($dir.'/candidate.json',$state);
        $job=restore_job_transition($root,$job['id'],'preflight','creating',['candidate_ref'=>$nonce]);
        $db=$state['candidate_database']; $account=$owner->quote($state['importer_user']).'@'.$owner->quote($state['importer_host']);
        $owner->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $password=bin2hex(random_bytes(32));
        restore_worker_provision_importer($owner,$state,$password);
        $importer=restore_worker_pdo($config,$db,$state['importer_user'],$password); unset($password);
        $importer->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION'");
        $importer->exec("SET SESSION time_zone='+00:00'");
        $importer->exec('SET SESSION FOREIGN_KEY_CHECKS=0');
        foreach (doyel_schema_tables() as $definition) $importer->exec($definition['create']);
        $job=restore_job_transition($root,$job['id'],'creating','importing');
        $statements=[];
        foreach (doyel_schema_tables() as $table=>$definition) {
            $columns=implode(',',array_map(static fn($c)=>'`'.$c.'`',$definition['columns']));
            $statements[$table]=$importer->prepare("INSERT INTO `$table` ($columns) VALUES (".implode(',',array_fill(0,count($definition['columns']),'?')).')');
        }
        $importer->beginTransaction();
        $second=doyel_backup_package_read($claim['package'],static function(string $table,array $values) use ($statements): void { $statement=$statements[$table]; foreach ($values as $i=>$value) $statement->bindValue($i+1,$value,$value===null?PDO::PARAM_NULL:PDO::PARAM_STR); $statement->execute(); },['compressed_bytes'=>RESTORE_JOB_MAX_UPLOAD]);
        if ($second !== $receipt) throw new RuntimeException('Sealed package changed');
        restore_worker_orphans($importer);
        $importer->commit();
        $importer->exec('SET SESSION FOREIGN_KEY_CHECKS=1');
        foreach (doyel_schema_tables() as $table=>$definition) {
            $next=$receipt['header']['tables'][$table]['next_id']; $key=$definition['primary_key'];
            $max=$importer->query("SELECT MAX(`$key`) FROM `$table`")->fetchColumn();
            if ($next === null || (int)$next < max(1,(int)$max+1)) throw new RuntimeException('Invalid source next identifier');
            $importer->exec("ALTER TABLE `$table` AUTO_INCREMENT=".(int)$next);
        }
        $job=restore_job_transition($root,$job['id'],'importing','validating');
        $actual=restore_worker_receipt($importer);
        if ($actual['tables'] !== $receipt['footer']['tables'] || $actual['inventory'] !== $receipt['footer']['inventory']) throw new RuntimeException('Candidate validation differs from source');
        $active=restore_worker_pdo($config,$config['active_database']);
        $active->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $active->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        doyel_schema_assert($active); $activeCounts=[]; $activeCountsAsOf=gmdate('Y-m-d\TH:i:s\Z');
        foreach (doyel_schema_tables() as $table=>$definition) $activeCounts[$table]=(string)$active->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        $active->commit();
        $statements=[]; $importer=null;
        $owner->exec('DROP USER '.$account); $state['importer_revoked']=true;
        $state['validation_receipt']=$receipt; restore_jobs_write_json($dir.'/candidate.json',$state);
        $validation=['backup_asof'=>$receipt['header']['snapshot_utc'],'app'=>$receipt['header']['app'],'schema'=>['version'=>1,'fingerprint'=>$receipt['header']['schema_fingerprint'],'compatible'=>true],'row_counts'=>array_map(static fn($t)=>$t['count'],$actual['tables']),'financial'=>$actual['inventory']['financial'],'date_coverage'=>$actual['inventory']['dates'],'roles'=>$actual['inventory']['roles'],'customer_balances'=>$actual['inventory']['customer_balances'],'comparisons'=>['row_digests_match'=>true,'inventory_match'=>true,'foreign_keys_valid'=>true],'candidate_ref'=>$nonce,'candidate_database'=>$db,'expected_active_database'=>$config['active_database'],'active_row_counts'=>$activeCounts,'active_counts_asof'=>$activeCountsAsOf];
        restore_jobs_write_json(restore_jobs_directory($root,'published').'/'.$job['id'].'.validation.json',$validation);
        return restore_job_transition($root,$job['id'],'validating','ready',['validation_ref'=>$job['id']]);
    } catch (Throwable $error) {
        if ($importer instanceof PDO && $importer->inTransaction()) $importer->rollBack();
        $importer=null;
        // A crash may leave an importer account, but never its secret in job state.
        try {
            if ($state === null && is_file($dir.'/candidate.json')) $state=restore_jobs_read_json($dir.'/candidate.json');
            if ($state !== null) {
                restore_worker_candidate_identity($config,$state);
                $owner ??= restore_worker_pdo($config);
                $owner->exec('DROP USER IF EXISTS '.$owner->quote($state['importer_user']).'@'.$owner->quote($state['importer_host']));
                $state['importer_revoked']=true; restore_jobs_write_json($dir.'/candidate.json',$state);
            }
        } catch (Throwable $cleanupError) { /* Preserve ownership evidence for explicit owner recovery. */ }
        $reference='restore_'.bin2hex(random_bytes(12));
        restore_jobs_write_json($dir.'/failure.json',['error_ref'=>$reference,'stage'=>$job['state'],'class'=>get_class($error),'at'=>gmdate(DATE_ATOM)]);
        return restore_job_transition($root,$job['id'],$job['state'],'failed',['error_ref'=>$reference]);
    } finally { restore_jobs_release($claim['lease']); }
}
