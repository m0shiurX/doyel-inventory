<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/maintenance-lib.php';require_once __DIR__.'/update-package.php';
function doyel_update_private_config(array $owner,string $app):array{
    foreach(['DB_HOST','DB_PORT','DB_USER','DB_PASS','DB_NAME','RESTORE_GENERATION','RESTORE_DIR'] as $key)if(getenv('DOYEL_'.$key)!==false)throw new RuntimeException('Environment overrides prevent authoritative update');
    $path=restore_worker_private_file($owner['runtime_config']);foreach([$app,$owner['restore_dir']] as $root){$base=doyel_windows_key($root);$file=doyel_windows_key($path);if($file===$base||str_starts_with($file,$base.'/'))throw new RuntimeException('Private connection crosses code/job boundary');}
    $c=require $path;if(!is_array($c)||($c['db_host']??null)!==$owner['host']||($c['db_port']??null)!==$owner['port']||($c['db_name']??null)!==$owner['active_database']||doyel_windows_key($c['restore_dir']??'')!==doyel_windows_key($owner['restore_dir'])||!is_int($c['restore_generation']??null)||$c['restore_generation']<0)throw new RuntimeException('Private connection identity changed');return $c;
}
function doyel_update_checkpoint(array $platform,string $at):void{if(($platform['fixture']??false)===true&&is_callable($platform['checkpoint']??null))($platform['checkpoint'])($at);}
function doyel_update_inventory(string $path):?array{return file_exists($path)?doyel_update_tree($path):null;}
function doyel_update_record(string $path):array{if(is_link($path)||!is_file($path)||filesize($path)>262144)throw new RuntimeException('Invalid update evidence');$r=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);if(!is_array($r)||array_is_list($r))throw new RuntimeException('Invalid update evidence');return $r;}
function doyel_update_write(string $work,array $record):void{$bytes=json_encode($record,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";if(strlen($bytes)>262144)throw new RuntimeException('Update evidence too large');$temp=$work.'/.journal-'.bin2hex(random_bytes(16));$h=fopen($temp,'xb');if(!$h)throw new RuntimeException('Cannot stage update evidence');try{chmod($temp,0600);doyel_backup_package_write_bytes($h,$bytes);if(!fflush($h)||(function_exists('fsync')&&!fsync($h)))throw new RuntimeException('Cannot sync update evidence');fclose($h);$h=null;if(!rename($temp,$work.'/journal.json'))throw new RuntimeException('Cannot replace update evidence');}finally{if(is_resource($h))fclose($h);if(is_file($temp))unlink($temp);}}
function doyel_update_probe(string $id):string{return '<?php require __DIR__."/lib/config.php"; require __DIR__."/lib/db.php"; require __DIR__."/lib/backup_schema.php"; require __DIR__."/lib/app_version.php"; header("Content-Type: application/json"); doyel_schema_assert(db()); echo json_encode(["token"=>'.var_export($id,true).',"app"=>doyel_app_version(),"schema"=>doyel_schema_fingerprint(db())]);';}
function doyel_update_cleanup_probe(string $app,string $id):void{
    $path=$app.'/www/update-health-'.$id.'.php';if(!file_exists($path))return;
    if(is_link($path)||!is_file($path)||hash_file('sha256',$path)!==hash('sha256',doyel_update_probe($id)))throw new RuntimeException('Unknown health probe');if(!unlink($path))throw new RuntimeException('Cannot remove health probe');
}
function doyel_update_swaps(array $r,bool $forward,array $platform=[]):void{
    $app=$r['app'];$id=$r['id'];
    foreach(['www','maintenance'] as $tree){$live=$app.'/'.$tree;$previous=$app.'/.doyel-previous-'.$id.'-'.$tree;$staged=$app.'/.doyel-update-'.$id.'/'.$tree;$rejected=$app.'/.doyel-rejected-'.$id.'-'.$tree;$old=$r['old_'.$tree];$new=$r['new_'.$tree];
        $actual=doyel_update_inventory($live);$retained=doyel_update_inventory($previous);
        if($retained!==null&&$retained!==$old)throw new RuntimeException('Retained code identity changed');
        if($forward){
            if($actual===$old&&$old!==null){if($retained!==null||!rename($live,$previous))throw new RuntimeException('Cannot retain old code');$actual=null;doyel_update_checkpoint($platform,$tree.'_retained');}
            if($actual===null){if(doyel_update_inventory($staged)!==$new||!rename($staged,$live))throw new RuntimeException('Cannot activate staged code');}
            elseif($actual!==$new)throw new RuntimeException('Unknown live code');
        }else{
            if($actual===$new){if(file_exists($rejected)||!rename($live,$rejected))throw new RuntimeException('Cannot preserve rejected code');$actual=null;}
            if($old===null){if($actual!==null)throw new RuntimeException('Unknown original maintenance slot');}
            elseif($actual===null){if($retained!==$old||!rename($previous,$live))throw new RuntimeException('Cannot recover retained code');}
            elseif($actual!==$old||$retained!==null)throw new RuntimeException('Ambiguous old code identity');
        }
    }
}
/** All database names/accounts come from the checked private installed connection. */
function doyel_update_run(array $owner,string $app,string $state,array $package,array $platform,?string $recoverId=null):array{
    if(PHP_SAPI!=='cli'||(($platform['fixture']??false)!==true&&PHP_OS_FAMILY!=='Windows'))throw new RuntimeException('Owner platform required');
    foreach(['service','acl','health','provision','owner','stage_acl'] as $callback)if(!is_callable($platform[$callback]??null))throw new RuntimeException('Owner platform adapter required');
    $updateLease=restore_jobs_lock($state.'/maintenance/update.lock',true);if($updateLease===null)throw new RuntimeException('Another updater is running');
    $lease=null;$gate=null;$db=null;$r=null;$work=null;
    try{
        $updates=$state.'/maintenance/updates';if(is_link($updates))throw new RuntimeException('Linked update storage');if(!is_dir($updates)&&!mkdir($updates,0700))throw new RuntimeException('Cannot create owner update storage');
        if($recoverId===null){
            $index=$state.'/maintenance/update-active.json';if(file_exists($index)){$last=restore_jobs_read_json($index);$oldJob=doyel_update_record($updates.'/'.restore_jobs_id($last['id']).'/journal.json');if(!in_array($oldJob['phase']??'',['active','rolled_back'],true))throw new RuntimeException('Recover the pending update first');}
            $preflight=require $owner['runtime_config'];$preflightPdo=restore_activation_pdo($owner,$preflight['db_name']);doyel_schema_assert($preflightPdo);$preflightPdo=null;
            $id=bin2hex(random_bytes(16));$work=$updates.'/'.$id;if(!mkdir($work,0700))throw new RuntimeException('Cannot create update journal');
            $path=$owner['runtime_config'];restore_worker_private_file($path);$boot=require $path;$original=file_get_contents($path);
            if(!is_array($boot)||!is_string($original))throw new RuntimeException('Invalid runtime configuration');
            $newWww=doyel_update_tree($package['directory'].'/www');$newWww['config.local.php']=hash_file('sha256',$app.'/www/config.local.php');ksort($newWww);
            $r=['version'=>1,'id'=>$id,'phase'=>'bootstrap','app'=>$app,'runtime_config'=>$path,'bootstrap_config'=>$boot,'bootstrap_sha256'=>hash('sha256',$original),'old_config'=>null,'old_sha256'=>null,'new_config'=>null,'rollback_config'=>null,'old_www'=>doyel_update_tree($app.'/www'),'old_maintenance'=>doyel_update_inventory($app.'/maintenance'),'new_www'=>$newWww,'new_maintenance'=>doyel_update_tree($package['directory'].'/maintenance'),'account'=>null,'backup_file'=>null,'backup_sha256'=>null,'build'=>$package['policy']['app']['build'],'manifest_sha256'=>$package['manifest_sha256'],'created_at'=>gmdate(DATE_ATOM)];
            if(file_put_contents($work.'/runtime-bootstrap.php',$original)!==strlen($original))throw new RuntimeException('Cannot retain initial runtime config');chmod($work.'/runtime-bootstrap.php',0600);doyel_update_write($work,$r);restore_jobs_write_json($index,['id'=>$id]);
        }else{$id=restore_jobs_id($recoverId);$work=$updates.'/'.$id;$r=doyel_update_record($work.'/journal.json');if(($r['version']??null)!==1||($r['id']??null)!==$id||($r['app']??null)!==$app||($r['runtime_config']??null)!==$owner['runtime_config']||$r['manifest_sha256']!==$package['manifest_sha256'])throw new RuntimeException('Update recovery identity mismatch');}
        if($r['phase']==='bootstrap'){
            $expected=$r['bootstrap_config'];foreach(['backup_dir'=>$state.'/backups','restore_dir'=>$state.'/restore','restore_generation'=>0] as $key=>$value)if(!array_key_exists($key,$expected))$expected[$key]=$value;
            $now=require $owner['runtime_config'];$nowHash=hash_file('sha256',$owner['runtime_config']);if(!(($now===$r['bootstrap_config']&&$nowHash===$r['bootstrap_sha256'])||($now===$expected&&$nowHash===hash('sha256',restore_finish_bytes($expected)))))throw new RuntimeException('Bootstrap config changed');
            ($platform['provision'])();$owner=($platform['owner'])();$old=doyel_update_private_config($owner,$app);if($old!==$expected)throw new RuntimeException('Provision changed the installed connection');
            $r['old_config']=$old;$r['old_sha256']=hash_file('sha256',$owner['runtime_config']);$r['new_config']=array_replace($old,['restore_generation'=>$old['restore_generation']+1]);$r['rollback_config']=array_replace($old,['restore_generation'=>$old['restore_generation']+2]);$r['phase']='prepared';doyel_update_write($work,$r);
        }else{$owner=($platform['owner'])();}
        $root=$owner['restore_dir'];$lease=restore_jobs_lock(restore_jobs_directory($root,'owner').'/worker.lock',true);if($lease===null)throw new RuntimeException('Database maintenance is busy');
        $runtime=doyel_update_private_config($owner,$app);$currentHash=hash_file('sha256',$owner['runtime_config']);$which=$runtime===$r['old_config']&&$currentHash===$r['old_sha256']?'old':($runtime===$r['new_config']&&$currentHash===hash('sha256',restore_finish_bytes($r['new_config']))?'new':($runtime===$r['rollback_config']&&$currentHash===hash('sha256',restore_finish_bytes($r['rollback_config']))?'rollback':null));if($which===null)throw new RuntimeException('Unknown private runtime identity');
        $committed=in_array($r['phase'],['committed','active'],true);$rolled=in_array($r['phase'],['rollback_committed','rolled_back'],true);
        $control=restore_jobs_control_read($root);$privateControl=restore_jobs_read_json(restore_jobs_directory($root,'owner').'/control.json');
        foreach([$control,$privateControl] as $c){restore_jobs_validate_control($c);if(($c['active_job']!==null&&$c['active_job']!==$id)||!in_array($c['generation'],[$r['old_config']['restore_generation'],$r['new_config']['restore_generation'],$r['rollback_config']['restore_generation']],true)||!in_array($c['mode'],['normal','frozen'],true))throw new RuntimeException('Other database maintenance');}
        if($recoverId===null&&($control!==$privateControl||$control!==['mode'=>'normal','generation'=>$runtime['restore_generation'],'active_job'=>null]))throw new RuntimeException('Update requires normal application state');
        $db=restore_activation_pdo($owner);if((string)$db->query('SELECT @@GLOBAL.partial_revokes')->fetchColumn()!=='0')throw new RuntimeException('Unsupported grant policy');
        $source=restore_activation_pdo($owner,$runtime['db_name']);doyel_schema_assert($source);
        if($r['account']===null){if($which!=='old'||$control['mode']!=='normal'||$control['active_job']!==null)throw new RuntimeException('Update requires normal application state');$connection=restore_activation_pdo($owner,$runtime['db_name'],$runtime);$r['account']=restore_activation_runtime_account($connection,$runtime['db_name']);$connection=null;doyel_update_write($work,$r);}
        $account=restore_finish_account($db,$r['account'],$runtime['db_name']);
        if($control===$privateControl&&$r['phase']==='active'&&$which==='new'&&$control===['mode'=>'normal','generation'=>$runtime['restore_generation'],'active_job'=>null]&&!$account['locked']&&$account['privileges']===['DELETE','INSERT','SELECT','UPDATE']&&doyel_update_tree($app.'/www')===$r['new_www']&&doyel_update_tree($app.'/maintenance')===$r['new_maintenance'])return ['id'=>$id,'state'=>'active','build'=>$r['build']];
        if($control===$privateControl&&$r['phase']==='rolled_back'&&$which==='rollback'&&$control===['mode'=>'normal','generation'=>$runtime['restore_generation'],'active_job'=>null]&&!$account['locked']&&$account['privileges']===['DELETE','INSERT','SELECT','UPDATE']&&doyel_update_tree($app.'/www')===$r['old_www']&&doyel_update_inventory($app.'/maintenance')===$r['old_maintenance'])return ['id'=>$id,'state'=>'rolled_back'];
        if($recoverId===null){
            $stage=$app.'/.doyel-update-'.$id;if(file_exists($stage)||!mkdir($stage,0700))throw new RuntimeException('New staging slot required');doyel_update_copy($package['directory'].'/www',$stage.'/www');doyel_update_copy($package['directory'].'/maintenance',$stage.'/maintenance');
            ($platform['stage_acl'])($stage.'/www',$stage.'/maintenance');
            if(!copy($app.'/www/config.local.php',$stage.'/www/config.local.php')||doyel_update_tree($stage.'/www')!==$r['new_www']||doyel_update_tree($stage.'/maintenance')!==$r['new_maintenance'])throw new RuntimeException('Staged code identity differs');
            if(doyel_update_tree($app.'/www')!==$r['old_www']||doyel_update_inventory($app.'/maintenance')!==$r['old_maintenance'])throw new RuntimeException('Installed code changed concurrently');
        }
        // A normal untouched preparation may still have DML. Later unlocked DML
        // without a durable commit cannot be treated as a safe rollback state.
        if(!$committed&&!$rolled&&!$account['locked']&&$account['privileges']===['DELETE','INSERT','SELECT','UPDATE']&&($which!=='old'||!in_array($r['phase'],['prepared','freezing'],true)))throw new RuntimeException('Uncommitted writer is ambiguous');
        $r['phase']=$committed?'committed':($rolled?'rollback_committed':$r['phase']);doyel_update_write($work,$r);
        restore_jobs_control_write($root,['mode'=>'frozen','generation'=>$runtime['restore_generation'],'active_job'=>$id]);$gate=restore_maintenance_gate($root,true);
        if(hash_file('sha256',$owner['runtime_config'])!==$currentHash)throw new RuntimeException('Runtime changed while draining');
        ($platform['service'])('stop');$db->exec('ALTER USER '.restore_activation_account_sql($db,$r['account']).' ACCOUNT LOCK');restore_activation_kill_runtime($db,$r['account']);doyel_update_checkpoint($platform,'account_locked');
        doyel_update_cleanup_probe($app,$id);
        $locks=[];foreach(doyel_schema_tables() as $table=>$unused)$locks[]=restore_activation_identifier($runtime['db_name']).'.'.restore_activation_identifier($table).' READ';$db->exec('LOCK TABLES '.implode(', ',$locks));
        if(!$committed&&!$rolled){
            if($r['backup_sha256']===null){$backupName='before-'.bin2hex(random_bytes(8)).'.doyel.gz';$backup=$work.'/'.$backupName;$written=doyel_backup_package_write($source,$backup);$read=doyel_backup_package_read($backup);if($written['header']!==$read['header']||$written['footer']!==$read['footer'])throw new RuntimeException('Update backup verification failed');$r['backup_file']=$backupName;$r['backup_sha256']=$read['sha256'];$r['phase']='backed_up';doyel_update_write($work,$r);}
            else{if(!preg_match('/^before-[a-f0-9]{16}\.doyel\.gz$/D',$r['backup_file'])||hash_file('sha256',$work.'/'.$r['backup_file'])!==$r['backup_sha256'])throw new RuntimeException('Update backup identity changed');restore_finish_proof($source,doyel_backup_package_read($work.'/'.$r['backup_file']));}
        }
        if($recoverId!==null&&!$committed){
            doyel_update_swaps($r,false);$db->exec('UNLOCK TABLES');restore_activation_config_write($owner['runtime_config'],$r['rollback_config'],$platform['acl']);
            $db->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$runtime['db_name']).'`.* TO '.restore_activation_account_sql($db,$r['account']));$r['phase']='rollback_committed';doyel_update_write($work,$r);doyel_update_checkpoint($platform,'rollback_committed');
            $db->exec('ALTER USER '.restore_activation_account_sql($db,$r['account']).' ACCOUNT UNLOCK');restore_jobs_control_write($root,['mode'=>'normal','generation'=>$r['rollback_config']['restore_generation'],'active_job'=>null]);($platform['service'])('start');$r['phase']='rolled_back';doyel_update_write($work,$r);return ['id'=>$id,'state'=>'rolled_back'];
        }
        if(!$committed){
            $r['phase']='swapping';doyel_update_write($work,$r);doyel_update_swaps($r,true,$platform);doyel_update_checkpoint($platform,'code_switched');
            restore_activation_config_write($owner['runtime_config'],$r['new_config'],$platform['acl']);$r['phase']='config_switched';doyel_update_write($work,$r);doyel_update_checkpoint($platform,'config_switched');
            $probe=$app.'/www/update-health-'.$id.'.php';$probeHandle=fopen($probe,'xb');if(!$probeHandle)throw new RuntimeException('Cannot create read-only health probe');doyel_backup_package_write_bytes($probeHandle,doyel_update_probe($id));fclose($probeHandle);
            $r['phase']='health_readonly';doyel_update_write($work,$r);$db->exec('UNLOCK TABLES');$db->exec('REVOKE INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$runtime['db_name']).'`.* FROM '.restore_activation_account_sql($db,$r['account']));$db->exec('ALTER USER '.restore_activation_account_sql($db,$r['account']).' ACCOUNT UNLOCK');
            try{($platform['service'])('start');($platform['health'])($id,$r['build'],$r['new_config']);}finally{($platform['service'])('stop');$db->exec('ALTER USER '.restore_activation_account_sql($db,$r['account']).' ACCOUNT LOCK');restore_activation_kill_runtime($db,$r['account']);doyel_update_cleanup_probe($app,$id);}
            $db->exec('GRANT SELECT,INSERT,UPDATE,DELETE ON `'.str_replace('_','\\_',$runtime['db_name']).'`.* TO '.restore_activation_account_sql($db,$r['account']));doyel_update_checkpoint($platform,'before_commit');$r['phase']='committed';doyel_update_write($work,$r);doyel_update_checkpoint($platform,'committed');
        }else{$db->exec('UNLOCK TABLES');if($which!=='new'||doyel_update_tree($app.'/www')!==$r['new_www']||doyel_update_tree($app.'/maintenance')!==$r['new_maintenance']||$account['privileges']!==['DELETE','INSERT','SELECT','UPDATE'])throw new RuntimeException('Committed update identity changed');}
        $db->exec('ALTER USER '.restore_activation_account_sql($db,$r['account']).' ACCOUNT UNLOCK');doyel_update_checkpoint($platform,'unlocked');restore_jobs_control_write($root,['mode'=>'normal','generation'=>$r['new_config']['restore_generation'],'active_job'=>null]);($platform['service'])('start');$r['phase']='active';doyel_update_write($work,$r);return ['id'=>$id,'state'=>'active','build'=>$r['build']];
    }catch(Throwable $error){throw new RuntimeException('Update refused or interrupted; retain owner evidence and use recover',0,$error);}
    finally{if($db!==null)try{$db->exec('UNLOCK TABLES');}catch(Throwable $ignored){}restore_jobs_release($gate);restore_jobs_release($lease);restore_jobs_release($updateLease);}
}
