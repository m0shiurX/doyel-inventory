<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/update-core.php';
function doyel_update_native_owner(array $context,bool $bootstrap=false):array{
    $runtime=doyel_windows_runtime($context);$client=parse_ini_file($context['state'].'/maintenance/root.cnf',true,INI_SCANNER_RAW);
    if(($client['client']['user']??'')!=='root'||($client['client']['host']??'')!=='127.0.0.1'||($client['client']['port']??'')!=='13306'||!preg_match('/^[a-f0-9]{64}$/D',$client['client']['password']??''))throw new RuntimeException('Installed owner credential format changed');
    $owner=['host'=>'127.0.0.1','port'=>13306,'owner_user'=>'root','owner_password'=>$client['client']['password'],'active_database'=>$runtime['db_name'],'runtime_config'=>$context['state'].'/config/runtime.php','restore_dir'=>$context['state'].'/restore','web_roots'=>[$context['app'].'/www']];
    $path=$context['state'].'/maintenance/restore-owner.php';if(is_file($path)){$existing=require restore_worker_private_file($path);foreach(['host','port','owner_user','owner_password','runtime_config','restore_dir','web_roots'] as $key)if(($existing[$key]??null)!==$owner[$key])throw new RuntimeException('Installed owner connection changed');}elseif(!$bootstrap)throw new RuntimeException('Owner provisioning missing');
    $owner['prepare_config_acl']=doyel_windows_config_acl($context);return $owner;
}
function doyel_update_cli(string $bundle,string $policyHash):void{
    register_shutdown_function(static function():void{if(PHP_OS_FAMILY==='Windows'){fwrite(STDOUT,"\nPress Enter to close. ");fgets(STDIN);}});
    try{
        $package=doyel_update_package($bundle,$policyHash);$base=getenv('ProgramFiles');if(!$base)throw new RuntimeException('Installed app required');$app=str_replace('\\','/',$base).'/Doyel';$context=doyel_windows_context($app,false,true);$runtime=doyel_windows_runtime($context);
        echo 'Doyel application-only update '.$package['policy']['app']['version'].' build '.$package['policy']['app']['build']."\nNo PHP/MySQL binaries or client database are replaced.\n";
        fwrite(STDOUT,'Operation (apply or recover): ');$operation=trim(fgets(STDIN,32)?:'');if(!in_array($operation,['apply','recover'],true))throw new RuntimeException('Unknown operation');
        fwrite(STDOUT,'Close data entry. Type '.$operation.' again to approve: ');if(trim(fgets(STDIN,32)?:'')!==$operation)throw new RuntimeException('Owner cancelled');
        $owner=doyel_update_native_owner($context,true);
        $stageAcl=static function(string $www,string $maintenance)use($context):void{doyel_windows_powershell($context, <<<'PS'
$v=[Console]::In.ReadToEnd()|ConvertFrom-Json;$web=([Security.Principal.NTAccount]::new('NT SERVICE\DoyelWeb')).Translate([Security.Principal.SecurityIdentifier]);foreach($tree in @($v.www,$v.maintenance)){foreach($item in @((Get-Item -LiteralPath $tree))+(Get-ChildItem -LiteralPath $tree -Force -Recurse)){if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Reparse stage'};$acl=if($item.PSIsContainer){[Security.AccessControl.DirectorySecurity]::new()}else{[Security.AccessControl.FileSecurity]::new()};$acl.SetAccessRuleProtection($true,$false);$inherit=if($item.PSIsContainer){[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'}else{[Security.AccessControl.InheritanceFlags]::None};foreach($sid in @('S-1-5-18','S-1-5-32-544')){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new($sid),'FullControl',$inherit,'None','Allow'))};if($tree -eq $v.www){$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($web,'ReadAndExecute',$inherit,'None','Allow'))}else{$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new([Security.Principal.SecurityIdentifier]::new('S-1-5-32-545'),'ReadAndExecute',$inherit,'None','Allow'))};Set-Acl -LiteralPath $item.FullName -AclObject $acl}}
PS,['www'=>$www,'maintenance'=>$maintenance]);};
        $platform=['stage_acl'=>$stageAcl,'service'=>static fn(string $operation)=>doyel_windows_service($context,$operation),'acl'=>doyel_windows_config_acl($context),'provision'=>static fn()=>doyel_windows_provision($context),'owner'=>static fn()=>doyel_update_native_owner($context),'health'=>static function(string $id,string $build,array $runtime):void{
            $url='http://127.0.0.1:18080/update-health-'.$id.'.php';$ctx=stream_context_create(['http'=>['timeout'=>2,'ignore_errors'=>true,'follow_location'=>0]]);$ok=false;
            for($i=0;$i<15;$i++){$bytes=@file_get_contents($url,false,$ctx);$value=$bytes===false?null:json_decode($bytes,true);if(is_array($value)&&($value['token']??null)===$id&&($value['app']['build']??null)===$build&&($value['app']['version']??null)===doyel_app_version()['version']&&($value['schema']??null)===doyel_schema_expected_fingerprint()){$ok=true;break;}usleep(200000);}
            if(!$ok)throw new RuntimeException('Read-only HTTP health check failed');
        }];
        $recover=null;if($operation==='recover')$recover=restore_jobs_id(restore_jobs_read_json($context['state'].'/maintenance/update-active.json')['id']);
        $result=doyel_update_run($owner,$context['app'],$context['state'],$package,$platform,$recover);echo json_encode($result,JSON_THROW_ON_ERROR)."\nCheck About Doyel, representative pages and printing before resuming data entry.\n";
    }catch(Throwable $error){$cause=$error->getPrevious()??$error;fwrite(STDERR,"Update refused or interrupted. Keep the bundle, database and private update evidence. Run recover from this same bundle; unknown states need owner review.\nReason: ".substr(preg_replace('/\s+/',' ',$cause->getMessage()),0,500)."\n");exit(1);}
}
