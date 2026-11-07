<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2) . '/lib/restore_worker.php';
require_once dirname(__DIR__,2) . '/lib/restore_activation.php';
// Optional isolated MySQL integration: owner credentials enter through STDIN only.
$checks=0;
function activation_assert(bool $condition): void { global $checks; if (!$condition) throw new RuntimeException('Activation assertion failed'); $checks++; }
function activation_refused(callable $fn): void { try { $fn(); } catch (Throwable $e) { activation_assert(true); return; } throw new RuntimeException('Expected refusal'); }
session_start();
$_SESSION=['Username'=>'old','restore_generation'=>2]; restore_maintenance_session_generation(['restore_generation'=>3]); activation_assert($_SESSION===[]);
$_SESSION=['Username'=>'old']; restore_maintenance_session_generation(['restore_generation'=>0]); activation_assert($_SESSION===[]);
session_write_close();
$base=['restore_generation'=>3];
activation_refused(fn()=>restore_maintenance_check($base,['generation'=>2,'mode'=>'normal'],'GET','index.php'));
activation_refused(fn()=>restore_maintenance_check($base,['generation'=>3,'mode'=>'frozen'],'GET','index.php'));
activation_refused(fn()=>restore_maintenance_check($base,['generation'=>3,'mode'=>'acceptance'],'POST','add_bill.php'));
foreach (['index.php','login.php','Restore.php','Report.php'] as $page) { restore_maintenance_check($base,['generation'=>3,'mode'=>'acceptance'],$page==='index.php'?'GET':'POST',$page); activation_assert(true); }
if (!in_array('--mysql', $argv, true)) { echo "PASS $checks maintenance checks; --mysql enables isolated DB fixtures with owner credentials on STDIN\n"; exit; }
$input=json_decode(stream_get_contents(STDIN,4096),true,8,JSON_THROW_ON_ERROR);
$config=['host'=>'127.0.0.1','port'=>33084,'fixture'=>true,'owner_user'=>$input['owner_user'],'owner_password'=>$input['owner_password'],'importer_host'=>'%','web_roots'=>[dirname(__DIR__,2)]];
$owner=restore_activation_pdo($config); $databases=[]; $accounts=[];
$private=realpath(sys_get_temp_dir()).'/doyel-r6-'.bin2hex(random_bytes(8)); mkdir($private,0700);
try {
foreach ([1,2] as $round) {
    $dir=$private.'/'.$round; mkdir($dir,0700); $root=$dir.'/restore'; mkdir($root,0700); restore_jobs_initialize($root); restore_maintenance_provision($root);
    $shared=restore_maintenance_gate($root); $exclusive=fopen($root.'/published/gate.lock','rb'); activation_assert(!flock($exclusive,LOCK_EX|LOCK_NB)); fclose($exclusive); restore_jobs_release($shared);
    $database='r6_fixture_'.bin2hex(random_bytes(8)); $databases[]=$database;
    $user='r6_'.bin2hex(random_bytes(8)); $accounts[]=['user'=>$user,'host'=>'%']; $password=bin2hex(random_bytes(16));
    $owner->exec('CREATE DATABASE '.restore_activation_identifier($database).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $active=restore_activation_pdo($config,$database); foreach(doyel_schema_tables() as $def) $active->exec($def['create']);
    $s=$active->prepare('INSERT INTO l_user (user_id,UserName,password,FullName,m_date,userAtho) VALUES (1,?,?,\'Fixture\',\'2026-11-07\',1)'); $s->execute(['fixture-admin',md5('fixture-password')]);
    $account=restore_activation_account_sql($owner,['user'=>$user,'host'=>'%']);
    $owner->exec('CREATE USER '.$account.' IDENTIFIED BY '.$owner->quote($password));
    $grant=str_replace('_','\\_',$database); $owner->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$grant.'`.* TO '.$account);
    if ($round===1) {
        $owner->exec('REVOKE SELECT,INSERT,UPDATE,DELETE ON `'.$grant.'`.* FROM '.$account);
        $owner->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$database.'`.* TO '.$account);
        $wildcard=restore_worker_pdo($config,$database,$user,$password);
        activation_refused(fn()=>restore_activation_runtime_account($wildcard,$database));
        $wildcard=null;
        $owner->exec('REVOKE SELECT,INSERT,UPDATE,DELETE ON `'.$database.'`.* FROM '.$account);
        $owner->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.$grant.'`.* TO '.$account);
    }
    $runtime=['db_host'=>'127.0.0.1','db_port'=>33084,'db_user'=>$user,'db_pass'=>$password,'db_name'=>$database,'restore_dir'=>$root,'restore_generation'=>0,'env'=>'development','backup_dir'=>$dir,'log_dir'=>$dir];
    $path=$dir.'/runtime.php'; file_put_contents($path,"<?php\nreturn ".var_export($runtime,true).";\n"); chmod($path,0600);
    $cfg=array_replace($config,['restore_dir'=>$root,'runtime_config'=>$path,'active_database'=>$database]);
    $package=$dir.'/source.doyel.gz'; doyel_backup_package_write($active,$package);
    $request=restore_job_create_local($root,$package,1,bin2hex(random_bytes(16))); $job=restore_worker_once($cfg); activation_assert($job['state']==='ready');
    $id=$job['id']; $sealed=restore_jobs_job_directory($root,'owner',$id); $candidate=restore_jobs_read_json($sealed.'/candidate.json'); $databases[]=$candidate['candidate_database'];
    restore_job_request($root,$id,'activation_request',1,bin2hex(random_bytes(16)),true);
    $credentials=['current_username'=>'fixture-admin','current_password'=>'fixture-password','candidate_username'=>'fixture-admin','candidate_password'=>'fixture-password'];
    foreach (['current_password','candidate_password'] as $field) { $bad=$credentials; $bad[$field]='wrong'; activation_refused(fn()=>restore_activation_run($cfg,$id,$bad)); activation_assert(restore_jobs_control_read($root)['mode']==='normal'); }
    // Real all-table READ lock blocks another connection's write.
    $lock=restore_activation_pdo($cfg); $lock->exec('LOCK TABLES '.restore_activation_identifier($database).'.l_user READ');
    $writer=restore_activation_pdo($cfg,$database); $writer->exec('SET SESSION lock_wait_timeout=1');
    activation_refused(fn()=>$writer->exec("UPDATE l_user SET UserName='blocked' WHERE user_id=1")); $lock->exec('UNLOCK TABLES'); $lock=null; $writer=null;
    $result=restore_activation_run($cfg,$id,$credentials); activation_assert($result['state']==='awaiting_acceptance');
    $recovery=restore_jobs_read_json($sealed.'/recovery.json'); $accounts[]=$recovery['new_account'];
    $next=require $path; activation_assert($next['restore_generation']===1 && $next['db_name']===$candidate['candidate_database']);
    activation_assert(restore_jobs_control_read($root)===['mode'=>'acceptance','generation'=>1,'active_job'=>$id]);
    activation_refused(fn()=>restore_maintenance_check($next,['generation'=>0,'mode'=>'frozen'],'GET','index.php'));
    activation_refused(fn()=>restore_activation_pdo($cfg,$database,$runtime));
    $readonly=restore_activation_pdo($cfg,$next['db_name'],$next); activation_assert($readonly->query('SELECT COUNT(*) FROM l_user')->fetchColumn()==='1');
    activation_refused(fn()=>$readonly->exec("UPDATE l_user SET UserName='blocked' WHERE user_id=1"));
    $receipt=doyel_backup_package_read($sealed.'/pre-switch.doyel.gz'); $source=doyel_backup_package_read($package);
    activation_assert($receipt['footer']['tables']===$source['footer']['tables'] && count($receipt['footer']['tables'])===13);
    activation_assert($receipt['footer']['inventory']===$source['footer']['inventory']);
    activation_assert($active->query('SELECT COUNT(*) FROM l_user')->fetchColumn()==='1');
    activation_assert($recovery['phase']==='awaiting_acceptance' && hash_file('sha256',$sealed.'/pre-switch.doyel.gz')===$recovery['backup_sha256']);
    $active=null; $readonly=null;
}
echo "PASS $checks checks including two isolated real-MySQL activation fixtures\n";
} finally {
    foreach($accounts as $account) $owner->exec('DROP USER IF EXISTS '.restore_activation_account_sql($owner,$account));
    foreach($databases as $database) $owner->exec('DROP DATABASE IF EXISTS '.restore_activation_identifier($database));
    $remove=function(string $path) use (&$remove):void { foreach(scandir($path) as $name) if($name!=='.'&&$name!=='..') { $item=$path.'/'.$name; is_dir($item)?$remove($item):unlink($item); } rmdir($path); }; $remove($private);
}
