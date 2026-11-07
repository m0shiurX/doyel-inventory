<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/restore_maintenance.php';
require_once __DIR__ . '/backup_package.php';
require_once __DIR__ . '/auth.php';

function restore_activation_identifier(string $name): string
{
    if (!preg_match('/^[a-zA-Z0-9_]{1,64}$/D', $name)) throw new RuntimeException('Unsafe database identity');
    return '`' . $name . '`';
}
/** Test-only owner fixture checkpoints; no request can select code or a command. */
function restore_activation_checkpoint(array $config,string $point): void
{
    if (($config['fixture'] ?? false)===true && is_callable($config['fixture_checkpoint'] ?? null)) ($config['fixture_checkpoint'])($point);
}
function restore_activation_pdo(array $owner, ?string $database = null, ?array $runtime = null): PDO
{
    $pdo = new PDO('mysql:host=' . $owner['host'] . ';port=' . $owner['port'] . ($database === null ? '' : ';dbname=' . trim(restore_activation_identifier($database), '`')) . ';charset=utf8mb4',
        $runtime['db_user'] ?? $owner['owner_user'], $runtime['db_pass'] ?? $owner['owner_password'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_STRINGIFY_FETCHES=>true, PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
    $pdo->exec('SET SESSION lock_wait_timeout = 15');
    $pdo->exec('SET SESSION innodb_lock_wait_timeout = 15');
    return $pdo;
}
function restore_activation_admin(PDO $pdo, string $username, string $password): void
{
    $s = $pdo->prepare('SELECT password, userAtho FROM l_user WHERE UserName = ? ORDER BY user_id'); $s->execute([$username]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) if ((string)$row['userAtho'] === '1' && auth_password_matches($password, $row['password'])) return;
    throw new RuntimeException('Administrator verification failed');
}
/** Returns the authenticated account, accepting exactly USAGE and active-schema DML. */
function restore_activation_runtime_account(PDO $runtime, string $database): array
{
    $identity = $runtime->query('SELECT CURRENT_USER()')->fetchColumn();
    if (!is_string($identity) || !preg_match('/^([a-zA-Z0-9_]{1,32})@([a-zA-Z0-9_.:%-]{1,255})$/D', $identity, $m) || in_array(strtolower($m[1]), ['root','mysql','mysql.sys','mysql.session'], true)) throw new RuntimeException('Dedicated runtime account required');
    $grants = $runtime->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN); $found = false;
    foreach ($grants as $grant) {
        if (preg_match('/^GRANT USAGE ON \*\.\* TO /D', $grant)) continue;
        if (!preg_match('/^GRANT ([A-Z, ]+) ON `([^`]+)`\.\* TO [^ ]+$/D', $grant, $g) || $g[2] !== str_replace('_', '\\_', $database)) throw new RuntimeException('Runtime grants exceed the active database');
        $privileges = explode(', ', $g[1]); sort($privileges); $expected = ['DELETE','INSERT','SELECT','UPDATE'];
        if ($privileges !== $expected || $found) throw new RuntimeException('Dedicated runtime DML grant required'); $found = true;
    }
    if (!$found) throw new RuntimeException('Dedicated runtime DML grant required');
    return ['user'=>$m[1], 'host'=>$m[2]];
}
function restore_activation_account_sql(PDO $owner, array $account): string { return $owner->quote($account['user']) . '@' . $owner->quote($account['host']); }
function restore_activation_kill_runtime(PDO $owner, array $account): void
{
    // Account-lock precedes this enumeration: no replacement connection can enter.
    $q = $owner->prepare('SELECT ID FROM information_schema.PROCESSLIST WHERE USER = ? AND ID <> CONNECTION_ID()'); $q->execute([$account['user']]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $id) { if (!ctype_digit((string)$id)) throw new RuntimeException('Invalid connection identity'); $owner->exec('KILL CONNECTION ' . $id); }
}
function restore_activation_private_config(array $owner): array
{
    foreach (['DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME','RESTORE_GENERATION','RESTORE_DIR'] as $key) if (getenv('DOYEL_' . $key) !== false) throw new RuntimeException('Environment overrides prevent authoritative activation');
    $path = $owner['runtime_config'] ?? '';
    if (!is_string($path) || $path === '' || !is_file($path) || is_link($path) || restore_jobs_path_key((string)realpath($path)) !== restore_jobs_path_key($path)) throw new RuntimeException('Authoritative private runtime config required');
    $parent = dirname($path); restore_jobs_path($parent);
    $roots = array_merge($owner['web_roots'] ?? [], [$owner['restore_dir']]);
    foreach ($roots as $root) {
        $key = restore_jobs_path_key($path); $base = restore_jobs_path_key((string)realpath($root));
        if ($base === '' || $key === $base || str_starts_with($key, $base . '/')) throw new RuntimeException('Runtime config must be outside web and restore storage');
    }
    $config = require $path;
    if (!is_array($config)) throw new RuntimeException('Invalid private runtime configuration');
    foreach (['db_host','db_port','db_user','db_pass','db_name','restore_dir','restore_generation'] as $field) if (!array_key_exists($field, $config)) throw new RuntimeException('Explicit runtime configuration required');
    if ($config['db_host'] !== $owner['host'] || (int)$config['db_port'] !== $owner['port'] || $config['db_name'] !== $owner['active_database'] || !is_string($config['restore_dir']) || restore_jobs_path_key($config['restore_dir']) !== restore_jobs_path_key($owner['restore_dir']) || !is_int($config['restore_generation']) || $config['restore_generation'] < 0) throw new RuntimeException('Runtime configuration target mismatch');
    return $config;
}
function restore_activation_config_write(string $path, array $config, ?callable $prepareAcl = null): void
{
    if (is_link($path) || !is_file($path)) throw new RuntimeException('Unsafe runtime config');
    if (PHP_OS_FAMILY === 'Windows' && $prepareAcl === null) throw new RuntimeException('Verified native config ACL adapter required');
    $mode = fileperms($path) & 0777;
    if (PHP_OS_FAMILY !== 'Windows' && ($mode & 0007) !== 0) throw new RuntimeException('Runtime config is not private');
    $temporary = dirname($path) . '/.restore-' . bin2hex(random_bytes(16));
    $h = fopen($temporary, 'xb'); if ($h === false) throw new RuntimeException('Cannot stage runtime config');
    try {
        if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporary, $mode)) throw new RuntimeException('Cannot preserve runtime config permissions');
        $bytes = "<?php\nreturn " . var_export($config, true) . ";\n";
        doyel_backup_package_write_bytes($h, $bytes);
        if (!fflush($h) || (function_exists('fsync') && !fsync($h))) throw new RuntimeException('Cannot sync runtime config');
        fclose($h); $h = null;
        if ($prepareAcl !== null) $prepareAcl($temporary, $path);
        if (!rename($temporary, $path)) throw new RuntimeException('Cannot replace runtime config');
        if (function_exists('opcache_invalidate')) opcache_invalidate($path, true);
    } finally { if (is_resource($h)) fclose($h); if (is_file($temporary)) unlink($temporary); }
}

/** Independent owner authorization: invoke only from CLI with credentials read from STDIN. */
function restore_activation_run(array $config, string $id, array $credentials): array
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Owner CLI required');
    restore_jobs_id($id);
    foreach (['current_username','current_password','candidate_username','candidate_password'] as $key) if (!is_string($credentials[$key] ?? null) || $credentials[$key] === '') throw new RuntimeException('Both administrator credentials are required on STDIN');
    if (PHP_OS_FAMILY === 'Windows' && !is_callable($config['prepare_config_acl'] ?? null)) throw new RuntimeException('Verified native config ACL adapter required');
    $root = $config['restore_dir']; $dir = restore_jobs_job_directory($root, 'owner', $id);
    $lease = restore_jobs_lock(restore_jobs_directory($root, 'owner') . '/worker.lock', true);
    if ($lease === null) throw new RuntimeException('Owner worker is busy');
    $gate = null; $locked = false; $switched = false; $frozen = false; $account = null; $owner = null; $state = 'ready';
    try {
        $job = restore_jobs_read_json($dir . '/journal.json');
        if (($job['state'] ?? null) !== 'ready') throw new RuntimeException('Candidate is not ready');
        restore_jobs_verify_sealed($dir . '/backup.doyel.gz', $job);
        $fresh = false;
        foreach (restore_jobs_actions($root, $id) as $action) if ($action['operation'] === 'activation_request' && strtotime($action['requested_at']) >= time()-900 && strtotime($action['requested_at']) <= time()+30) $fresh = true;
        if (!$fresh) throw new RuntimeException('Fresh activation request required');
        $old = restore_activation_private_config($config); $originalHash = hash_file('sha256', $config['runtime_config']);
        $control = restore_jobs_control_read($root);
        if (restore_jobs_read_json(restore_jobs_directory($root,'owner').'/control.json') !== $control) throw new RuntimeException('Control publication requires recovery');
        if ($control !== ['mode'=>'normal','generation'=>$old['restore_generation'],'active_job'=>null]) throw new RuntimeException('Existing maintenance requires recovery');
        $candidate = restore_jobs_read_json($dir . '/candidate.json');
        restore_worker_candidate_identity($config, $candidate);
        if ($candidate['job_id'] !== $id || $candidate['source_package_sha256'] !== $job['sha256']) throw new RuntimeException('Candidate receipt identity mismatch');
        if (($candidate['activated'] ?? true) || !($candidate['importer_revoked'] ?? false) || !is_array($candidate['validation_receipt'] ?? null)) throw new RuntimeException('Candidate validation is incomplete');
        $newDb = $candidate['candidate_database'];
        $active = restore_activation_pdo($config, $old['db_name']); $new = restore_activation_pdo($config, $newDb);
        restore_activation_admin($active, $credentials['current_username'], $credentials['current_password']);
        restore_activation_admin($new, $credentials['candidate_username'], $credentials['candidate_password']);
        $runtime = restore_activation_pdo($config, $old['db_name'], $old);
        $account = restore_activation_runtime_account($runtime, $old['db_name']); $runtime = null;
        $owner = restore_activation_pdo($config);
        if ((string)$owner->query('SELECT @@GLOBAL.partial_revokes')->fetchColumn() !== '0') throw new RuntimeException('Database grant wildcard policy is incompatible');
        $q = $owner->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ?'); $q->execute([$account['user']]);
        if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Runtime username is shared across accounts');
        doyel_schema_assert($active); doyel_schema_assert($new);
        $newAccount = ['user'=>'dya_' . bin2hex(random_bytes(12)), 'host'=>$account['host']]; $newPassword = bin2hex(random_bytes(32));
        $newAccountSql = restore_activation_account_sql($owner, $newAccount);
        $next = array_replace($old, ['db_name'=>$newDb,'db_user'=>$newAccount['user'],'db_pass'=>$newPassword,'restore_generation'=>$old['restore_generation']+1]);
        $recovery = ['version'=>1,'job_id'=>$id,'phase'=>'prepared','runtime_config'=>$config['runtime_config'],'original_sha256'=>$originalHash,'old_config'=>$old,'new_config'=>$next,'old_account'=>$account,'new_account'=>$newAccount,'backup'=>'pre-switch.doyel.gz','backup_sha256'=>null,'candidate_database'=>$newDb,'retained_database'=>$old['db_name'],'created_at'=>gmdate(DATE_ATOM)];
        $oldBytes = file_get_contents($config['runtime_config']);
        if ($oldBytes === false || hash('sha256',$oldBytes) !== $originalHash) throw new RuntimeException('Cannot preserve authoritative runtime config');
        $saved=fopen($dir . '/runtime-before.php', 'xb'); if ($saved === false) throw new RuntimeException('Cannot preserve old config');
        try { chmod($dir . '/runtime-before.php',0600); doyel_backup_package_write_bytes($saved,$oldBytes); if (!fflush($saved) || (function_exists('fsync') && !fsync($saved))) throw new RuntimeException('Cannot sync old config'); } finally { fclose($saved); }
        restore_jobs_write_json($dir . '/recovery.json', $recovery);
        restore_activation_checkpoint($config,'activation_prepared');
        restore_maintenance_provision($root);
        restore_job_transition($root, $id, 'ready', 'activation_requested'); $state = 'activation_requested';
        restore_jobs_control_write($root, ['mode'=>'frozen','generation'=>$old['restore_generation'],'active_job'=>$id]); $frozen = true;
        $gate = restore_maintenance_gate($root, true);
        restore_job_transition($root, $id, $state, 'frozen'); $state = 'frozen';
        $accountSql = restore_activation_account_sql($owner, $account);
        $owner->exec('ALTER USER ' . $accountSql . ' ACCOUNT LOCK'); $locked = true;
        $recovery['phase']='old_account_locked'; restore_jobs_write_json($dir . '/recovery.json',$recovery);
        restore_activation_kill_runtime($owner, $account);
        restore_activation_checkpoint($config,'activation_old_locked');
        $owner->exec('CREATE USER ' . $newAccountSql . ' IDENTIFIED BY ' . $owner->quote($newPassword));
        $owner->exec('GRANT SELECT ON ' . '`' . str_replace('_', '\\_', $newDb) . '`.* TO ' . $newAccountSql);

        $locks = [];
        foreach ([$old['db_name'], $newDb] as $database) foreach (doyel_schema_tables() as $table=>$unused) $locks[] = restore_activation_identifier($database) . '.' . restore_activation_identifier($table) . ' READ';
        $owner->exec('LOCK TABLES ' . implode(', ', $locks));
        // A separate connection owns the snapshot: START TRANSACTION must not release the READ locks.
        $candidateProof = $dir . '/activation-candidate.doyel.gz';
        doyel_backup_package_write($new, $candidateProof); $proof = doyel_backup_package_read($candidateProof);
        $expected = $candidate['validation_receipt'];
        if ($proof['header']['tables'] !== $expected['header']['tables'] || ($proof['footer']['tables'] !== $expected['footer']['tables'] || $proof['footer']['inventory'] !== $expected['footer']['inventory'])) throw new RuntimeException('Candidate changed after validation');
        $backup = $dir . '/pre-switch.doyel.gz';
        $written = doyel_backup_package_write($active, $backup); $verified = doyel_backup_package_read($backup);
        if ($written['header'] !== $verified['header'] || $written['footer'] !== $verified['footer']) throw new RuntimeException('Pre-switch backup verification failed');
        restore_jobs_write_json($dir . '/pre-switch-receipt.json', $verified);
        $recovery['phase']='backed_up'; $recovery['backup_sha256']=hash_file('sha256',$backup); restore_jobs_write_json($dir . '/recovery.json',$recovery);
        restore_job_transition($root, $id, $state, 'backed_up', ['backup_ref'=>'pre_switch','retained_ref'=>'retained_database']); $state = 'backed_up';
        restore_activation_checkpoint($config,'activation_backed_up');
        if (hash_file('sha256', $config['runtime_config']) !== $originalHash) throw new RuntimeException('Runtime configuration changed concurrently');
        restore_job_transition($root, $id, $state, 'switching'); $state = 'switching';
        // From here every error requires owner reconciliation; never reopen automatically.
        $switched = true;
        restore_activation_config_write($config['runtime_config'], $next, $config['prepare_config_acl'] ?? null);
        restore_activation_checkpoint($config,'activation_config_switched');
        $candidate['activated']=true; restore_jobs_write_json($dir . '/candidate.json',$candidate);
        $recovery['phase'] = 'config_switched'; $recovery['new_sha256'] = hash_file('sha256', $config['runtime_config']);
        restore_jobs_write_json($dir . '/recovery.json', $recovery);
        restore_jobs_control_write($root, ['mode'=>'acceptance','generation'=>$next['restore_generation'],'active_job'=>$id]);
        restore_job_transition($root, $id, $state, 'awaiting_acceptance'); $state = 'awaiting_acceptance';
        $recovery['phase'] = 'awaiting_acceptance'; restore_jobs_write_json($dir . '/recovery.json', $recovery);
        return ['id'=>$id,'state'=>$state,'generation'=>$next['restore_generation']];
    } catch (Throwable $error) {
        if ($frozen && !$switched && isset($old, $originalHash) && hash_file('sha256', $config['runtime_config']) === $originalHash) {
            // Config never moved. Remove DB locks before reopening only this proven old identity.
            if ($owner !== null) $owner->exec('UNLOCK TABLES');
            if ($locked) $owner->exec('ALTER USER ' . restore_activation_account_sql($owner, $account) . ' ACCOUNT UNLOCK');
            if (in_array($state, ['frozen','backed_up'], true)) restore_job_transition($root,$id,$state,'rolled_back');
            $recovery['phase']='aborted_before_switch'; restore_jobs_write_json($dir . '/recovery.json',$recovery);
            restore_jobs_control_write($root,['mode'=>'normal','generation'=>$old['restore_generation'],'active_job'=>null]);
        }
        // Never persist an exception: DB errors can contain sensitive values or SQL.
        throw new RuntimeException($switched ? 'Activation interrupted; owner recovery required; writes remain closed' : 'Activation refused or safely aborted', 0);
    } finally {
        if ($owner !== null) { try { $owner->exec('UNLOCK TABLES'); } catch (Throwable $ignored) {} }
        restore_jobs_release($gate); restore_jobs_release($lease);
    }
}
