<?php
// Privileged owner workflow. Never publish this file in the document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/restore_worker.php';
require_once __DIR__ . '/restore_activation.php';

function restore_finish_bytes(array $config): string { return "<?php\nreturn " . var_export($config,true) . ";\n"; }
function restore_finish_checkpoint(array $config,string $point): void
{
    if (($config['fixture'] ?? false)===true && is_callable($config['fixture_checkpoint'] ?? null)) ($config['fixture_checkpoint'])($point);
}
function restore_finish_staff(PDO $pdo,string $username,string $password): void
{
    $q=$pdo->prepare('SELECT password,userAtho FROM l_user WHERE UserName=? ORDER BY user_id'); $q->execute([$username]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) if ((string)$row['userAtho']!=='1' && auth_password_matches($password,(string)$row['password'])) return;
    throw new RuntimeException('Ordinary-user verification failed');
}
/** Inspect locked accounts through owner connection; never grant/revoke unknown scopes. */
function restore_finish_account(PDO $owner,array $account,string $database,bool $missing=false): ?array
{
    if (!preg_match('/^[A-Za-z0-9_]{1,32}$/D',$account['user'] ?? '') || in_array(strtolower($account['user']),['root','mysql','mysql.sys','mysql.session'],true)
        || !preg_match('/^[A-Za-z0-9_.:%-]{1,255}$/D',$account['host'] ?? '')) throw new RuntimeException('Invalid dedicated account');
    $q=$owner->prepare('SELECT Host,account_locked FROM mysql.user WHERE User=?'); $q->execute([$account['user']]); $rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if ($rows===[] && $missing) return null;
    if (count($rows)!==1 || $rows[0]['Host']!==$account['host']) throw new RuntimeException('Dedicated account identity changed');
    $grants=$owner->query('SHOW GRANTS FOR '.restore_activation_account_sql($owner,$account))->fetchAll(PDO::FETCH_COLUMN); $privileges=null;
    foreach($grants as $grant) {
        if (preg_match('/^GRANT USAGE ON \*\.\* TO /D',$grant)) continue;
        if (!preg_match('/^GRANT ([A-Z, ]+) ON `([^`]+)`\.\* TO [^ ]+$/D',$grant,$m) || $m[2]!==str_replace('_','\\_',$database) || $privileges!==null) throw new RuntimeException('Account privileges changed');
        $privileges=explode(', ',$m[1]); sort($privileges);
    }
    if (!in_array($privileges,[['SELECT'],['DELETE','INSERT','SELECT','UPDATE']],true)) throw new RuntimeException('Unexpected runtime privileges');
    return ['locked'=>$rows[0]['account_locked']==='Y','privileges'=>$privileges];
}
function restore_finish_fresh(string $root,string $id,string $operation): void
{
    foreach(restore_jobs_actions($root,$id) as $action) if ($action['operation']===$operation && strtotime($action['requested_at'])>=time()-900 && strtotime($action['requested_at'])<=time()+30) return;
    throw new RuntimeException('Fresh administrator request required');
}
/** Only called under global owner lease after concrete DB/config proof. */
function restore_finish_state(string $root,string $id,string $next): void
{
    $dir=restore_jobs_job_directory($root,'owner',$id); $lease=restore_jobs_lock($dir.'/journal.lock');
    try {
        $job=restore_jobs_read_json($dir.'/journal.json');
        if ($job['id']!==$id || !in_array($next,['awaiting_acceptance','active','rolled_back'],true)
            || ($job['state']==='active' && $next!=='active') || ($job['state']==='rolled_back' && $next!=='rolled_back')) throw new RuntimeException('Recovery transition refused');
        if ($job['state']!==$next) { $job['state']=$next; $job['sequence']++; $job['updated_at']=gmdate(DATE_ATOM); $job['history'][]=['state'=>$next,'at'=>$job['updated_at']]; restore_jobs_write_json($dir.'/journal.json',$job); }
        restore_jobs_publish($root,$job);
    } finally { restore_jobs_release($lease); }
}
function restore_finish_proof(PDO $pdo,array $receipt): void
{
    $actual=restore_worker_receipt($pdo);
    if ($actual['tables']!==$receipt['footer']['tables'] || $actual['inventory']!==$receipt['footer']['inventory']) throw new RuntimeException('Database differs from verified evidence');
    foreach(doyel_backup_package_table_counters($pdo) as $table=>$next) if ($next!==$receipt['header']['tables'][$table]['next_id']) throw new RuntimeException('Database counters differ');
}
/** Counters are part of identity preservation, not just row totals. */
function doyel_backup_package_table_counters(PDO $pdo): array
{
    $out=[]; foreach($pdo->query('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC) as $row) $out[$row['Name']]=$row['Auto_increment']===null?null:(string)$row['Auto_increment']; return $out;
}
function restore_finish_export(PDO $pdo,string $dir,string $prefix): array
{
    $name=$prefix.'-'.bin2hex(random_bytes(8)).'.doyel.gz'; $written=doyel_backup_package_write($pdo,$dir.'/'.$name); $read=doyel_backup_package_read($dir.'/'.$name);
    if ($written['header']!==$read['header'] || $written['footer']!==$read['footer']) throw new RuntimeException('Recovery export verification failed');
    return ['name'=>$name,'sha256'=>$read['sha256'],'receipt'=>$read];
}

/** Credentials only enter owner memory; UI hints never authorize privileges. */
function restore_finish_run(array $config,string $id,string $operation,array $credentials,bool $recovery=false): array
{
    if (PHP_SAPI!=='cli' || !in_array($operation,['accept','rollback','recover'],true) || ($operation==='recover' && !$recovery)) throw new RuntimeException('Owner operation required');
    restore_jobs_id($id);
    foreach(['current_username','current_password','candidate_username','candidate_password'] as $key) if (!is_string($credentials[$key] ?? null) || $credentials[$key]==='' || strlen($credentials[$key])>4096) throw new RuntimeException('Both administrator credentials required');
    if (PHP_OS_FAMILY==='Windows' && !is_callable($config['prepare_config_acl'] ?? null)) throw new RuntimeException('Native ACL adapter required');
    $root=$config['restore_dir']; $dir=restore_jobs_job_directory($root,'owner',$id); $lease=restore_jobs_lock(restore_jobs_directory($root,'owner').'/worker.lock',true);
    if ($lease===null) throw new RuntimeException('Owner worker is busy');
    $gate=null; $owner=null; $frozen=false;
    try {
        $job=restore_jobs_read_json($dir.'/journal.json'); $record=restore_jobs_read_json($dir.'/recovery.json');
        if (($record['version'] ?? null)!==1 || ($record['job_id'] ?? null)!==$id || !is_string($record['runtime_config'] ?? null) || restore_jobs_path_key($record['runtime_config'])!==restore_jobs_path_key($config['runtime_config'])) throw new RuntimeException('Recovery identity mismatch');
        $old=$record['old_config']; $new=$record['new_config'];
        $probe=$config; $probe['active_database']=$record['retained_database'];
        $candidate=restore_jobs_read_json($dir.'/candidate.json'); restore_worker_candidate_identity($probe,$candidate);
        if ($candidate['job_id']!==$id || $candidate['source_package_sha256']!==$job['sha256'] || $candidate['candidate_database']!==$record['candidate_database'] || !$candidate['importer_revoked'] || !is_array($candidate['validation_receipt'])) throw new RuntimeException('Candidate evidence identity mismatch');
        foreach([$old,$new] as $runtime) if (($runtime['db_host'] ?? null)!==$config['host'] || ($runtime['db_port'] ?? null)!==$config['port'] || !is_string($runtime['restore_dir'] ?? null) || restore_jobs_path_key($runtime['restore_dir'])!==restore_jobs_path_key($root) || !is_int($runtime['restore_generation'] ?? null)) throw new RuntimeException('Recovery endpoint mismatch');
        if ($old['db_name']!==$record['retained_database'] || $new['db_name']!==$candidate['candidate_database'] || $new['restore_generation']!==$old['restore_generation']+1
            || $record['old_account']!==['user'=>$old['db_user'],'host'=>$record['old_account']['host']] || $record['new_account']!==['user'=>$new['db_user'],'host'=>$record['old_account']['host']]
            || !preg_match('/^dya_[a-f0-9]{24}$/D',$new['db_user'])) throw new RuntimeException('Recovery connection identity mismatch');
        if (hash_file('sha256',$dir.'/runtime-before.php')!==$record['original_sha256'] || (require $dir.'/runtime-before.php')!==$old) throw new RuntimeException('Original config evidence changed');
        restore_jobs_verify_sealed($dir.'/backup.doyel.gz',$job);
        $current=restore_activation_private_config($config); $currentHash=hash_file('sha256',$config['runtime_config']);
        $finish=is_file($dir.'/finish.json')?restore_jobs_read_json($dir.'/finish.json'):null;
        if ($finish!==null && (($finish['version'] ?? null)!==1 || ($finish['job_id'] ?? null)!==$id || !in_array($finish['phase'] ?? '',['accept_preparing','accept_committed','active','rollback_prepared','rollback_committed','rolled_back'],true))) throw new RuntimeException('Invalid finish evidence');
        if ($finish!==null && (!preg_match('/^(?:acceptance|rollback)-candidate-[a-f0-9]{16}\.doyel\.gz$/D',$finish['candidate_export'] ?? '') || !preg_match('/^[a-f0-9]{64}$/D',$finish['candidate_export_sha256'] ?? ''))) throw new RuntimeException('Invalid candidate export evidence');
        $which=$currentHash===$record['original_sha256'] && $current===$old?'old':($currentHash===hash('sha256',restore_finish_bytes($new)) && $current===$new?'new':null);
        if ($finish!==null && isset($finish['rollback_config']) && $current===$finish['rollback_config'] && $currentHash===hash('sha256',restore_finish_bytes($finish['rollback_config']))) $which='rollback';
        if ($which===null) throw new RuntimeException('Ambiguous runtime configuration');
        $owner=restore_activation_pdo($config);
        if ((string)$owner->query('SELECT @@GLOBAL.partial_revokes')->fetchColumn()!=='0') throw new RuntimeException('Unsupported grant policy');
        $oldAccount=restore_finish_account($owner,$record['old_account'],$old['db_name']);
        $newAccount=restore_finish_account($owner,$record['new_account'],$new['db_name'],true);
        if ($oldAccount['privileges']!==['DELETE','INSERT','SELECT','UPDATE']) throw new RuntimeException('Retained runtime DML privileges changed');
        $oldPdo=restore_activation_pdo($config,$old['db_name']); $newPdo=restore_activation_pdo($config,$new['db_name']);
        restore_activation_admin($oldPdo,$credentials['current_username'],$credentials['current_password']);
        restore_activation_admin($newPdo,$credentials['candidate_username'],$credentials['candidate_password']);
        $committed=in_array($finish['phase'] ?? '',['accept_committed','active'],true);
        if ($job['state']==='active' && !$committed) throw new RuntimeException('Acceptance commit evidence missing');
        if (!$committed && $newAccount!==null && $newAccount['privileges']===['DELETE','INSERT','SELECT','UPDATE'] && (!$newAccount['locked'] || ($finish['phase'] ?? '')!=='accept_preparing')) throw new RuntimeException('Uncommitted runtime writer is ambiguous');
        if ($committed && ($newAccount===null || $newAccount['privileges']!==['DELETE','INSERT','SELECT','UPDATE'])) throw new RuntimeException('Accepted runtime privileges changed');
        if ($operation==='rollback' && $committed) throw new RuntimeException('Writes committed; blind rollback refused');
        if ($operation==='recover') $operation=$committed || ($finish['phase'] ?? '')==='accept_preparing'?'accept':(in_array($finish['phase'] ?? '',['rollback_prepared','rollback_committed','rolled_back'],true)||$which==='old'?'rollback':'reconcile');
        if ($operation==='accept') {
            if (in_array($finish['phase'] ?? '',['rollback_prepared','rollback_committed','rolled_back'],true)) throw new RuntimeException('Rollback intent cannot be accepted');
            foreach(['ordinary_username','ordinary_password'] as $key) if (!is_string($credentials[$key] ?? null) || $credentials[$key]==='' || strlen($credentials[$key])>4096) throw new RuntimeException('Ordinary-user credentials required');
            restore_finish_staff($newPdo,$credentials['ordinary_username'],$credentials['ordinary_password']);
            if ($which!=='new' || $newAccount===null || !$oldAccount['locked']) throw new RuntimeException('Candidate activation identity mismatch');
            if (!$recovery && !$committed) { if ($job['state']!=='awaiting_acceptance') throw new RuntimeException('Acceptance not ready'); restore_finish_fresh($root,$id,'accept'); }
        } elseif ($operation==='rollback' && !$recovery && ($finish['phase'] ?? '')!=='rolled_back') {
            if ($job['state']!=='awaiting_acceptance' || $which!=='new') throw new RuntimeException('Rollback not ready'); restore_finish_fresh($root,$id,'rollback');
        }
        if ($job['state']==='rolled_back' && $operation!=='rollback') throw new RuntimeException('Job already rolled back');
        // A different pending restore cannot be displaced by this recovery command.
        foreach([restore_jobs_control_read($root),restore_jobs_read_json(restore_jobs_directory($root,'owner').'/control.json')] as $control) {
            restore_jobs_validate_control($control); if ($control['active_job']!==null && $control['active_job']!==$id) throw new RuntimeException('Another maintenance job is active');
        }
        $terminalControl=['mode'=>'normal','generation'=>$current['restore_generation'],'active_job'=>null];
        $terminalPublished=restore_jobs_control_read($root)===$terminalControl && restore_jobs_read_json(restore_jobs_directory($root,'owner').'/control.json')===$terminalControl;
        if (($finish['phase'] ?? '')==='active' && $operation==='accept' && $job['state']==='active' && $oldAccount['locked'] && !$newAccount['locked'] && $newAccount['privileges']===['DELETE','INSERT','SELECT','UPDATE'] && $terminalPublished) return ['id'=>$id,'state'=>'active','generation'=>$current['restore_generation']];
        if (($finish['phase'] ?? '')==='rolled_back' && $operation==='rollback' && $which==='rollback' && $job['state']==='rolled_back' && !$oldAccount['locked'] && ($newAccount===null || $newAccount['locked']) && $terminalPublished) return ['id'=>$id,'state'=>'rolled_back','generation'=>$current['restore_generation']];
        restore_jobs_control_write($root,['mode'=>'frozen','generation'=>$current['restore_generation'],'active_job'=>$id]); $frozen=true;
        $gate=restore_maintenance_gate($root,true);
        if (hash_file('sha256',$config['runtime_config'])!==$currentHash) throw new RuntimeException('Runtime configuration changed concurrently');
        // Dedicated identities are locked before inspecting or changing DB grants.
        foreach([$record['old_account'],$newAccount===null?null:$record['new_account']] as $account) if ($account!==null) { $owner->exec('ALTER USER '.restore_activation_account_sql($owner,$account).' ACCOUNT LOCK'); restore_activation_kill_runtime($owner,$account); }
        $locks=[]; foreach([$old['db_name'],$new['db_name']] as $db) foreach(doyel_schema_tables() as $table=>$unused) $locks[]=restore_activation_identifier($db).'.'.restore_activation_identifier($table).' READ';
        $owner->exec('LOCK TABLES '.implode(', ',$locks));
        if (!$committed) restore_finish_proof($newPdo,$candidate['validation_receipt']);
        $rollbackCommitted=in_array($finish['phase'] ?? '',['rollback_committed','rolled_back'],true);
        if ($record['backup_sha256']!==null) {
            if ($record['backup']!=='pre-switch.doyel.gz' || hash_file('sha256',$dir.'/pre-switch.doyel.gz')!==$record['backup_sha256']) throw new RuntimeException('Retained backup changed');
            $oldProof=doyel_backup_package_read($dir.'/pre-switch.doyel.gz'); if (!$rollbackCommitted) restore_finish_proof($oldPdo,$oldProof);
        } elseif ($record['backup_sha256']===null && !$rollbackCommitted) {
            if ($which!=='old') throw new RuntimeException('Switched database has no final backup');
            $saved=restore_finish_export($oldPdo,$dir,'recovery-old');
            $record['recovery_backup']=$saved['name']; $record['recovery_backup_sha256']=$saved['sha256']; restore_jobs_write_json($dir.'/recovery.json',$record);
        }
        if ($operation==='reconcile') {
            if ($newAccount===null || $newAccount['privileges']!==['SELECT']) throw new RuntimeException('Read-only account state ambiguous');
            $candidate['activated']=true; restore_jobs_write_json($dir.'/candidate.json',$candidate);
            $owner->exec('UNLOCK TABLES'); $owner->exec('ALTER USER '.restore_activation_account_sql($owner,$record['new_account']).' ACCOUNT UNLOCK');
            restore_finish_state($root,$id,'awaiting_acceptance');
            restore_jobs_control_write($root,['mode'=>'acceptance','generation'=>$new['restore_generation'],'active_job'=>$id]);
            return ['id'=>$id,'state'=>'awaiting_acceptance','generation'=>$new['restore_generation']];
        }
        if ($operation==='accept') {
            if (!$committed) {
                if ($newAccount['privileges']!==['SELECT'] && ($finish['phase'] ?? '')!=='accept_preparing') throw new RuntimeException('Candidate was opened outside acceptance');
                $saved=restore_finish_export($newPdo,$dir,'acceptance-candidate');
                $finish=['version'=>1,'job_id'=>$id,'phase'=>'accept_preparing','accepted_at'=>gmdate(DATE_ATOM),'candidate_export'=>$saved['name'],'candidate_export_sha256'=>$saved['sha256'],'admin_verified'=>true,'ordinary_verified'=>true];
                restore_jobs_write_json($dir.'/finish.json',$finish);
                $owner->exec('UNLOCK TABLES');
                $owner->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$new['db_name']).'`.* TO '.restore_activation_account_sql($owner,$record['new_account']));
                restore_finish_checkpoint($config,'accept_before_commit');
                $finish['phase']='accept_committed'; restore_jobs_write_json($dir.'/finish.json',$finish);
                restore_finish_checkpoint($config,'accept_committed');
            } else $owner->exec('UNLOCK TABLES');
            // Once committed, only forward completion is safe, even after a crash.
            $owner->exec('ALTER USER '.restore_activation_account_sql($owner,$record['new_account']).' ACCOUNT UNLOCK');
            restore_finish_checkpoint($config,'accept_unlocked');
            restore_finish_state($root,$id,'active');
            restore_jobs_control_write($root,['mode'=>'normal','generation'=>$new['restore_generation'],'active_job'=>null]);
            $finish['phase']='active'; restore_jobs_write_json($dir.'/finish.json',$finish);
            return ['id'=>$id,'state'=>'active','generation'=>$new['restore_generation']];
        }
        // A durable rollback intent survives config replacement and preserves evidence.
        if (!in_array($finish['phase'] ?? '',['rollback_prepared','rollback_committed','rolled_back'],true)) {
            $saved=restore_finish_export($newPdo,$dir,'rollback-candidate');
            if ($newAccount!==null && $newAccount['privileges']===['DELETE','INSERT','SELECT','UPDATE']) {
                // Interrupted acceptance granted DML only while this account was locked.
                $owner->exec('UNLOCK TABLES');
                $owner->exec('REVOKE INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$new['db_name']).'`.* FROM '.restore_activation_account_sql($owner,$record['new_account']));
            }
            $generation=max($old['restore_generation'],$new['restore_generation'],$current['restore_generation'])+1;
            $rollback=array_replace($old,['restore_generation'=>$generation]);
            $finish=['version'=>1,'job_id'=>$id,'phase'=>'rollback_prepared','rollback_at'=>gmdate(DATE_ATOM),'rollback_config'=>$rollback,'candidate_export'=>$saved['name'],'candidate_export_sha256'=>$saved['sha256']];
            restore_jobs_write_json($dir.'/finish.json',$finish);
        } else $rollback=$finish['rollback_config'];
        if ($rollback['db_name']!==$old['db_name'] || $rollback!==array_replace($old,['restore_generation'=>$rollback['restore_generation']]) || $rollback['restore_generation']<=$new['restore_generation']) throw new RuntimeException('Rollback configuration evidence invalid');
        if (hash_file('sha256',$dir.'/'.$finish['candidate_export'])!==$finish['candidate_export_sha256']) throw new RuntimeException('Candidate export evidence changed');
        $owner->exec('UNLOCK TABLES');
        restore_activation_config_write($config['runtime_config'],$rollback,$config['prepare_config_acl'] ?? null);
        restore_finish_checkpoint($config,'rollback_config_switched');
        $finish['phase']='rollback_committed'; restore_jobs_write_json($dir.'/finish.json',$finish);
        restore_finish_checkpoint($config,'rollback_committed');
        $owner->exec('ALTER USER '.restore_activation_account_sql($owner,$record['old_account']).' ACCOUNT UNLOCK');
        restore_finish_state($root,$id,'rolled_back');
        restore_jobs_control_write($root,['mode'=>'normal','generation'=>$rollback['restore_generation'],'active_job'=>null]);
        $finish['phase']='rolled_back'; restore_jobs_write_json($dir.'/finish.json',$finish);
        return ['id'=>$id,'state'=>'rolled_back','generation'=>$rollback['restore_generation']];
    } catch (Throwable $error) {
        // Never put SQL, raw error text, account names or passwords into public evidence.
        throw new RuntimeException($frozen?'Owner recovery required; writes remain closed':'Owner finish operation refused',0);
    } finally {
        if ($owner!==null) try { $owner->exec('UNLOCK TABLES'); } catch(Throwable $ignored) {}
        restore_jobs_release($gate); restore_jobs_release($lease);
    }
}
