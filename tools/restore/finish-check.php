<?php
// Tiny canonical fixtures only. No existing/client databases are read or modified.
if (PHP_SAPI!=='cli') exit(1);
require_once dirname(__DIR__,2).'/lib/restore_finish.php';
$credentials=['current_username'=>'fixture-admin','current_password'=>'fixture-password','candidate_username'=>'fixture-admin','candidate_password'=>'fixture-password','ordinary_username'=>'fixture-staff','ordinary_password'=>'fixture-staff-password'];
if (($argv[1] ?? '')==='--child') {
    $config=restore_worker_load_config($argv[2]); $point=$argv[4];
    $config['fixture_checkpoint']=static function(string $at) use($point):void { if ($at===$point) exit(73); };
    $credentials=json_decode(stream_get_contents(STDIN,8192),true,8,JSON_THROW_ON_ERROR);
    if ($argv[3]==='activate') restore_activation_run($config,$argv[5],$credentials);
    else restore_finish_run($config,$argv[5],$argv[3],$credentials);
    exit(0);
}
$checks=0;$owner=null;$owned=[];$accounts=[];$private='';
function r7_assert(bool $ok,string $label): void { global $checks; if(!$ok) throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n"; }
function r7_refuse(callable $run,string $label): void { try{$run();}catch(Throwable $error){r7_assert(true,$label);return;}throw new RuntimeException('Expected refusal: '.$label); }
function r7_crash(string $path,string $operation,string $point,string $id,array $credentials): void {
    $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--child',$path,$operation,$point,$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fwrite($pipes[0],json_encode($credentials,JSON_THROW_ON_ERROR));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    r7_assert(proc_close($process)===73,'real subprocess interruption at '.$point);
}
try {
    $input=json_decode(stream_get_contents(STDIN,4096),true,8,JSON_THROW_ON_ERROR);
    $base=['host'=>'127.0.0.1','port'=>33084,'fixture'=>true,'owner_user'=>$input['owner_user'],'owner_password'=>$input['owner_password'],'importer_host'=>'%','web_roots'=>[dirname(__DIR__,2)]];unset($input);
    $owner=restore_activation_pdo($base);
    $private=realpath(sys_get_temp_dir()).'/doyel-r7-'.bin2hex(random_bytes(8));mkdir($private,0700);
    foreach(['accept','rollback','activation_old_locked','activation_config_switched','accept_before_commit','accept_committed','rollback_config_switched'] as $case) {
        $dir=$private.'/'.$case;mkdir($dir,0700);$root=$dir.'/jobs';mkdir($root,0700);restore_jobs_initialize($root);restore_maintenance_provision($root);
        $db='r7_fixture_'.bin2hex(random_bytes(8));$owned[]=$db;$user='r7_'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(16));$accounts[]=['user'=>$user,'host'=>'%'];
        $owner->exec('CREATE DATABASE '.restore_activation_identifier($db).' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo=restore_activation_pdo($base,$db);foreach(doyel_schema_tables() as $def)$pdo->exec($def['create']);
        $insert=$pdo->prepare('INSERT INTO l_user (user_id,UserName,password,FullName,m_date,userAtho) VALUES (?,?,?,?,?,?)');
        $insert->execute([1,'fixture-admin',md5('fixture-password'),'Synthetic','2026-11-07',1]);$insert->execute([2,'fixture-staff',md5('fixture-staff-password'),'Synthetic','2026-11-07',0]);
        $account=restore_activation_account_sql($owner,['user'=>$user,'host'=>'%']);$owner->exec('CREATE USER '.$account.' IDENTIFIED BY '.$owner->quote($password));$owner->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$db).'`.* TO '.$account);
        $runtime=['db_host'=>'127.0.0.1','db_port'=>33084,'db_name'=>$db,'db_user'=>$user,'db_pass'=>$password,'restore_generation'=>0,'restore_dir'=>$root,'env'=>'development','backup_dir'=>$dir,'log_dir'=>$dir];
        $path=$dir.'/runtime.php';file_put_contents($path,restore_finish_bytes($runtime));chmod($path,0600);
        $config=array_replace($base,['restore_dir'=>$root,'active_database'=>$db,'runtime_config'=>$path]);$configPath=$dir.'/owner.php';file_put_contents($configPath,'<?php return '.var_export($config,true).';');chmod($configPath,0600);
        $package=$dir.'/source.doyel.gz';$source=doyel_backup_package_write($pdo,$package);$request=restore_job_create_local($root,$package,1,bin2hex(random_bytes(16)));$job=restore_worker_once($config);r7_assert($job['state']==='ready',$case.' real R4 candidate ready');
        $id=$job['id'];$sealed=$root.'/owner/'.$id;$candidate=restore_jobs_read_json($sealed.'/candidate.json');$owned[]=$candidate['candidate_database'];
        restore_job_request($root,$id,'activation_request',1,bin2hex(random_bytes(16)),true);
        if (str_starts_with($case,'activation_')) {
            r7_crash($configPath,'activate',$case,$id,$credentials);
            $record=restore_jobs_read_json($sealed.'/recovery.json');$accounts[]=$record['new_account'];
            $config=restore_worker_load_config($configPath);
            $result=restore_finish_run($config,$id,'recover',$credentials,true);
            if($case==='activation_old_locked'){r7_assert($result['state']==='rolled_back' && $result['generation']===2,'pre-switch interrupted recovery returns old with fresh generation');continue;}
            r7_assert($result['state']==='awaiting_acceptance','post-config crash recovers read-only acceptance');
        } else {
            restore_activation_run($config,$id,$credentials);$record=restore_jobs_read_json($sealed.'/recovery.json');$accounts[]=$record['new_account'];$config=restore_worker_load_config($configPath);
        }
        $next=require $path;$candidatePdo=restore_activation_pdo($config,$next['db_name']);
        $before=restore_worker_receipt($candidatePdo);
        if($case==='accept') r7_refuse(fn()=>restore_finish_run($config,$id,'accept',$credentials),'missing fresh acceptance request refused');
        restore_job_request($root,$id,'accept',1,bin2hex(random_bytes(16)),true);restore_job_request($root,$id,'rollback',1,bin2hex(random_bytes(16)),true);
        if($case==='accept') {
            $bad=$credentials;$bad['ordinary_password']='wrong';r7_refuse(fn()=>restore_finish_run($config,$id,'accept',$bad),'wrong ordinary credential refused');
            r7_assert(restore_jobs_control_read($root)['mode']==='acceptance','wrong credential leaves read-only mode');
            $candidatePdo->exec("UPDATE l_user SET FullName='Unexpected change' WHERE user_id=2");
            r7_refuse(fn()=>restore_finish_run($config,$id,'accept',$credentials),'changed candidate evidence blocks acceptance');
            r7_assert(restore_jobs_control_read($root)['mode']==='frozen','unexplained candidate change leaves writes frozen');
            $candidatePdo->exec("UPDATE l_user SET FullName='Synthetic' WHERE user_id=2");
            $result=restore_finish_run($config,$id,'accept',$credentials);r7_assert($result['state']==='active','acceptance opens chosen candidate');
            $rw=restore_activation_pdo($config,$next['db_name'],$next);$rw->exec("UPDATE l_user SET FullName='New legitimate work' WHERE user_id=2");
            r7_refuse(fn()=>restore_finish_run($config,$id,'rollback',$credentials,true),'blind rollback after acceptance refused');
            r7_assert(restore_finish_run($config,$id,'accept',$credentials)['state']==='active' && restore_jobs_control_read($root)['mode']==='normal','active retry preserves new work without freeze');
            continue;
        }
        if(str_starts_with($case,'accept_')) {
            r7_crash($configPath,'accept',$case,$id,$credentials);$finish=restore_jobs_read_json($sealed.'/finish.json');
            r7_assert($finish['phase']===($case==='accept_committed'?'accept_committed':'accept_preparing'),'durable acceptance boundary matches interruption');
            if($case==='accept_committed')r7_refuse(fn()=>restore_finish_run($config,$id,'rollback',$credentials,true),'commit marker forbids rollback before public active state');
            $result=restore_finish_run($config,$id,'recover',$credentials,true);r7_assert($result['state']==='active','interrupted acceptance finishes forward');
            continue;
        }
        if($case==='rollback_config_switched') {
            r7_crash($configPath,'rollback',$case,$id,$credentials);$config=restore_worker_load_config($configPath);
            $result=restore_finish_run($config,$id,'recover',$credentials,true);
        } else $result=restore_finish_run($config,$id,'rollback',$credentials);
        r7_assert($result['state']==='rolled_back' && $result['generation']===2,'rollback preserves old database and increments generation');
        $restored=require $path;$rw=restore_activation_pdo($config,$db,$restored);$rw->exec("UPDATE l_user SET FullName='New source work' WHERE user_id=2");
        r7_assert(restore_finish_run(restore_worker_load_config($configPath),$id,'recover',$credentials,true)['state']==='rolled_back' && restore_jobs_control_read($root)['mode']==='normal','rollback retry preserves newer source work');
        r7_assert(restore_worker_receipt($candidatePdo)===$before,'rollback retains unchanged candidate contents');
        $finish=restore_jobs_read_json($sealed.'/finish.json');r7_assert(hash_file('sha256',$sealed.'/'.$finish['candidate_export'])===$finish['candidate_export_sha256'],'candidate export retained and verified');
    }
    echo "PASS $checks R7 checks across seven isolated canonical fixtures\n";
}catch(Throwable $error){fwrite(STDERR,'R7 check failed: '.$error->getMessage()."\n");$failed=true;}
finally {
    if($owner!==null){foreach($accounts as $account)$owner->exec('DROP USER IF EXISTS '.restore_activation_account_sql($owner,$account));foreach($owned as $db)$owner->exec('DROP DATABASE IF EXISTS '.restore_activation_identifier($db));}
    if($private!==''){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($private); }
}
if(isset($failed))exit(1);
