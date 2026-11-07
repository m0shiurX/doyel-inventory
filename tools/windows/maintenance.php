<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/maintenance-lib.php';
function maintenance_password(string $label): string
{
    fwrite(STDOUT,$label.': ');
    // Console.ReadKey reads the attached console; the secret travels only in an
    // anonymous pipe, never a command argument, file or PowerShell history.
    $script= <<<'PS'
[Console]::OutputEncoding=[Text.UTF8Encoding]::new();$s='';while($true){$k=[Console]::ReadKey($true);if($k.Key -eq 'Enter'){break};if($k.Key -eq 'Backspace'){if($s.Length -gt 0){$s=$s.Substring(0,$s.Length-1);[Console]::Error.Write("`b `b")};continue};if(-not [char]::IsControl($k.KeyChar) -and $s.Length -lt 4096){$s+=$k.KeyChar;[Console]::Error.Write('*')}};[Console]::Error.WriteLine();[Console]::Write([Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($s)))
PS;
    $encoded=base64_encode(mb_convert_encoding($script,'UTF-16LE','UTF-8'));
    $p=proc_open([getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe','-NoLogo','-NoProfile','-EncodedCommand',$encoded],[0=>STDIN,1=>['pipe','w'],2=>STDERR],$pipes,null,null,['bypass_shell'=>true]);
    if(!is_resource($p))throw new RuntimeException('Secure console unavailable');$bytes=stream_get_contents($pipes[1],32769);fclose($pipes[1]);$code=proc_close($p);$secret=base64_decode($bytes ?: '',true);
    if($code!==0 || $secret===false || $secret==='' || strlen($secret)>4096)throw new RuntimeException('Secure input unavailable');return $secret;
}
function maintenance_username(string $label): string {fwrite(STDOUT,$label.': ');$value=trim(fgets(STDIN,4097) ?: '');if($value==='' || strlen($value)>255)throw new RuntimeException('Username required');return $value;}
try{
    $options=getopt('',['app:','provision']);$app=$options['app'] ?? dirname(__DIR__,3);
    if(!array_key_exists('provision',$options))register_shutdown_function(static function():void{fwrite(STDOUT,"Press Enter to close maintenance.\n");fgets(STDIN);});
    if(!is_string($app))throw new RuntimeException('Installed app required');
    $context=doyel_windows_context($app,array_key_exists('provision',$options));
    if(array_key_exists('provision',$options)){doyel_windows_provision($context);echo "Private restore storage and owner helper provisioned.\n";exit;}
    $config=doyel_windows_owner_config($context);
    echo "Doyel database maintenance. Old databases and backups are retained.\n";
    echo "prepare = import next queued backup; activate = switch read-only; accept = open writes; rollback = before acceptance; recover = reconcile an interrupted operation.\n";
    fwrite(STDOUT,'Operation: ');$operation=trim(fgets(STDIN,32) ?: '');
    if(!in_array($operation,['prepare','activate','accept','rollback','recover'],true))throw new RuntimeException('Unknown operation');
    if($operation==='prepare'){
        $job=restore_worker_once($config);echo json_encode($job===null?['state'=>'idle']:['job'=>$job['id'],'state'=>$job['state']],JSON_THROW_ON_ERROR)."\n";exit;
    }
    fwrite(STDOUT,'Job reference from restore page: ');$id=restore_jobs_id(trim(fgets(STDIN,128) ?: ''));
    $credentials=[];
    foreach(['current'=>'Retained/current administrator','candidate'=>'Candidate administrator'] as $prefix=>$label){$credentials[$prefix.'_username']=maintenance_username($label.' username');$credentials[$prefix.'_password']=maintenance_password($label.' password');}
    if(in_array($operation,['accept','recover'],true)){$credentials['ordinary_username']=maintenance_username('Candidate ordinary-user username');$credentials['ordinary_password']=maintenance_password('Candidate ordinary-user password');}
    echo "The web service will close during this operation. The database service stays running.\n";
    fwrite(STDOUT,'Type the operation again to approve: ');if(trim(fgets(STDIN,32) ?: '')!==$operation)throw new RuntimeException('Owner cancelled');
    $status=$context['state'].'/maintenance/native-operation.json';
    restore_jobs_write_json($status,['operation'=>$operation,'job'=>$id,'phase'=>'stopping_web','at'=>gmdate(DATE_ATOM)]);
    doyel_windows_service($context,'stop');
    try{
        $result=$operation==='activate'?restore_activation_run($config,$id,$credentials):restore_finish_run($config,$id,$operation,$credentials,$operation==='recover');
        unset($credentials);
        $control=restore_jobs_control_read($config['restore_dir']);$runtime=doyel_windows_runtime($context);
        if(!in_array($control['mode'],['normal','acceptance'],true) || $control['generation']!==$runtime['restore_generation'])throw new RuntimeException('Writes remain closed; owner recovery required');
        doyel_windows_service($context,'start');
        restore_jobs_write_json($status,['operation'=>$operation,'job'=>$id,'phase'=>'completed','at'=>gmdate(DATE_ATOM)]);
        echo json_encode($result,JSON_THROW_ON_ERROR)."\nReopen the saved restore page and sign in again.\n";
    }catch(Throwable $error){
        unset($credentials);
        // Refusal before freeze may safely return the existing web service.
        $control=restore_jobs_control_read($config['restore_dir']);$runtime=doyel_windows_runtime($context);
        if(in_array($control['mode'],['normal','acceptance'],true) && $control['generation']===$runtime['restore_generation'])doyel_windows_service($context,'start');
        restore_jobs_write_json($status,['operation'=>$operation,'job'=>$id,'phase'=>'owner_review_required','at'=>gmdate(DATE_ATOM)]);throw new RuntimeException('Owner recovery required');
    }
}catch(Throwable $error){fwrite(STDERR,"Maintenance refused or interrupted. Keep both databases and private recovery files. Inspect the owner-only operation/job state; use recover for a saved interrupted job.\n");exit(1);}
