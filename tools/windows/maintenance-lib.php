<?php
// Owner/elevated native adapter; never include in the public runtime allowlist.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_finish.php';
require_once __DIR__.'/server-config.php';

function doyel_windows_key(string $path): string { return strtolower(rtrim(str_replace('\\','/',$path),'/')); }
function doyel_windows_web_proof(string $apache,string $stub,string $app,string $state): void
{
    $lines=static fn(string $value):string=>str_replace("\r\n","\n",$value);
    if($lines($apache)!==$lines(doyel_windows_apache_conf($app,$state)) || $lines($stub)!==$lines(doyel_windows_config_stub()))throw new RuntimeException('Customized web configuration requires owner review');
}
function doyel_windows_service_proof(array $service,string $app,string $state): void
{
    $expected=doyel_windows_key($app.'/apache/bin/httpd.exe');
    if (!preg_match('/^"([^"]+)"\s+-k\s+runservice(?:\s+-n\s+"?DoyelWeb"?)?$/Di',$service['path'] ?? '',$m) || doyel_windows_key($m[1])!==$expected
        || strcasecmp($service['account'] ?? '','NT AUTHORITY\\LocalService')!==0
        || ($service['sid_type'] ?? null)!==1) throw new RuntimeException('Web service ownership mismatch');
    $args=$service['config_args'] ?? null;
    if (!is_array($args) || count($args)!==2 || $args[0]!=='-f' || doyel_windows_key($args[1])!==doyel_windows_key($state.'/config/httpd.conf')) throw new RuntimeException('Web service configuration mismatch');
    if (!preg_match('/^"([^"]+)"\s+--defaults-file=(?:"([^"]+)"|([^\s]+))\s+DoyelDB$/Di',$service['database_path'] ?? '',$db)
        || doyel_windows_key($db[1])!==doyel_windows_key($app.'/mysql/bin/mysqld.exe')
        || doyel_windows_key($db[2]!==''?$db[2]:($db[3] ?? ''))!==doyel_windows_key($state.'/config/my.ini')) throw new RuntimeException('Database service ownership mismatch');
}
function doyel_windows_powershell(array $context,string $script,array $input=[],bool $keepDiagnostic=true): string
{
    if (PHP_OS_FAMILY!=='Windows') throw new RuntimeException('Native Windows required');
    $exe=getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe';
    if (!is_file($exe)) throw new RuntimeException('Windows PowerShell unavailable');
    $encoded=base64_encode(mb_convert_encoding("$"."ErrorActionPreference='Stop'; [Console]::OutputEncoding=[Text.UTF8Encoding]::new(); ".$script,'UTF-16LE','UTF-8'));
    $out=tempnam($context['state'].'/maintenance','native-');$err=tempnam($context['state'].'/maintenance','native-');
    try {
        $process=proc_open([$exe,'-NoLogo','-NoProfile','-NonInteractive','-EncodedCommand',$encoded],[0=>['pipe','r'],1=>['file',$out,'w'],2=>['file',$err,'w']],$pipes,null,null,['bypass_shell'=>true]);
        if(!is_resource($process)) throw new RuntimeException('Native process unavailable');
        fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$code=proc_close($process);
        if($code!==0) {
            if($keepDiagnostic){
                try{
                    // Protect the individual diagnostic even if the failing
                    // operation was the initial directory-ACL audit.
                    doyel_windows_acl($context,$err,false,null,false);
                    $reference='native-error-'.bin2hex(random_bytes(12)).'.txt';
                    if(!rename($err,$context['state'].'/maintenance/'.$reference))throw new RuntimeException('Cannot retain diagnostic');
                    fwrite(STDERR,'Private native diagnostic: '.$reference."\n");
                }catch(Throwable $diagnosticError){fwrite(STDERR,"Native command failed; private diagnostic storage could not be secured.\n");}
            }
            throw new RuntimeException('Native maintenance command refused');
        }
        $result=file_get_contents($out);if($result===false || strlen($result)>65536)throw new RuntimeException('Invalid native result');return ltrim($result,"\xEF\xBB\xBF");
    } finally {if(is_file($out))unlink($out);if(is_file($err))unlink($err);}
}
function doyel_windows_context(string $app,bool $installing=false,bool $updater=false): array
{
    if(PHP_OS_FAMILY!=='Windows')throw new RuntimeException('Windows owner operation required');
    $resolved=realpath($app);$base=getenv('ProgramData');
    if($resolved===false || is_link($app) || !$base || !preg_match('~^[A-Za-z]:[\\\\/]~',$base))throw new RuntimeException('Invalid installed paths');
    $app=str_replace('\\','/',$resolved);$state=str_replace('\\','/',$base).'/Doyel';
    foreach([$app,$state,$state.'/config',$state.'/maintenance'] as $path) {
        $probe=$path;while($probe!==dirname($probe)){if(is_link($probe))throw new RuntimeException('Installed path is linked');$probe=dirname($probe);}
        if(realpath($path)===false || doyel_windows_key((string)realpath($path))!==doyel_windows_key($path))throw new RuntimeException('Installed path mismatch');
    }
    $context=['app'=>$app,'state'=>$state,'updater'=>$updater];doyel_windows_audit($context);
    if(!is_dir($app.'/www')&&$updater){if(str_replace("\r\n","\n",(string)file_get_contents($state.'/config/httpd.conf'))!==str_replace("\r\n","\n",doyel_windows_apache_conf($app,$state)))throw new RuntimeException('Customized web configuration');}else doyel_windows_web_proof((string)file_get_contents($state.'/config/httpd.conf'),(string)file_get_contents($app.'/www/config.local.php'),$app,$state);
    if(filesize($state.'/installation.json')>16384)throw new RuntimeException('Invalid installation record');
    $saved=json_decode(file_get_contents($state.'/installation.json'),true,16,JSON_THROW_ON_ERROR);$services=$saved['services'] ?? [];sort($services);
    if(($saved['format'] ?? null)!==1 || doyel_windows_key($saved['app'] ?? '')!==doyel_windows_key($app) || $services!==['DoyelDB','DoyelWeb'] || !in_array($saved['status'] ?? '',$installing?['installing','installed']:['installed'],true))throw new RuntimeException('Installation ownership mismatch');
    $script= <<<'PS'
$identity=[Security.Principal.WindowsIdentity]::GetCurrent(); $principal=[Security.Principal.WindowsPrincipal]::new($identity); if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){throw 'Elevation required'}
$web=Get-CimInstance Win32_Service -Filter "Name='DoyelWeb'"; $db=Get-CimInstance Win32_Service -Filter "Name='DoyelDB'";
$key=Get-ItemProperty -LiteralPath 'HKLM:\SYSTEM\CurrentControlSet\Services\DoyelWeb'; $parameters=Get-ItemProperty -LiteralPath 'HKLM:\SYSTEM\CurrentControlSet\Services\DoyelWeb\Parameters';
@{path=$web.PathName;account=$web.StartName;sid_type=[int]$key.ServiceSidType;config_args=@($parameters.ConfigArgs);database_path=$db.PathName;web_state=$web.State;database_state=$db.State}|ConvertTo-Json -Compress
PS;
    $service=json_decode(doyel_windows_powershell($context,$script),true,16,JSON_THROW_ON_ERROR);doyel_windows_service_proof($service,$app,$state);
    if(($service['database_state'] ?? '')!=='Running')throw new RuntimeException('Database service must remain running');
    $context['service']=$service;return $context;
}
/** Validate the privilege boundary before requiring any private PHP file. */
function doyel_windows_audit(array $context): void
{
    $script= <<<'PS'
$v=[Console]::In.ReadToEnd()|ConvertFrom-Json
$principal=[Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent());if(-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)){throw 'Elevation required'}
$trusted=@('S-1-5-18','S-1-5-32-544',[Security.Principal.WindowsIdentity]::GetCurrent().User.Value);$trusted+=@(Get-LocalGroupMember -SID 'S-1-5-32-544'|ForEach-Object {$_.SID.Value})
$web=([Security.Principal.NTAccount]::new('NT SERVICE\DoyelWeb')).Translate([Security.Principal.SecurityIdentifier]).Value
$writes=[Security.AccessControl.FileSystemRights]'Write,Delete,DeleteSubdirectoriesAndFiles,ChangePermissions,TakeOwnership'
$reads=[Security.AccessControl.FileSystemRights]'ReadAndExecute,Synchronize'
function Check-Path($path,$kind){
 $item=Get-Item -LiteralPath $path -Force;if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Reparse point'}
 $acl=Get-Acl -LiteralPath $path;$owner=$acl.GetOwner([Security.Principal.SecurityIdentifier]).Value;if($trusted -notcontains $owner){throw 'Untrusted owner'}
 foreach($r in $acl.GetAccessRules($true,$true,[Security.Principal.SecurityIdentifier])){
  if($r.AccessControlType -ne 'Allow' -or ($r.PropagationFlags -band [Security.AccessControl.PropagationFlags]::InheritOnly)){continue}
  $sid=$r.IdentityReference.Value;if($trusted -contains $sid){continue}
  if($kind -eq 'code'){if($r.FileSystemRights -band $writes){throw 'Untrusted code writer'};continue}
  if($kind -ne 'webread' -or $sid -ne $web -or (($r.FileSystemRights -band $reads) -ne $r.FileSystemRights)){throw 'Untrusted private access'}
 }
}
foreach($p in @($v.state,(Join-Path $v.state 'config'))){Check-Path $p 'webread'}
foreach($f in @('runtime.php','httpd.conf','php.ini')){Check-Path (Join-Path $v.state ('config\'+$f)) 'webread'}
Check-Path (Join-Path $v.state 'installation.json') 'secret';Check-Path (Join-Path $v.state 'maintenance') 'secret';Check-Path (Join-Path $v.state 'maintenance\root.cnf') 'secret'
$owner=Join-Path $v.state 'maintenance\restore-owner.php';if(Test-Path -LiteralPath $owner){Check-Path $owner 'secret'}
$restore=Join-Path $v.state 'restore';if(Test-Path -LiteralPath $restore){
 Check-Path $restore 'webread'
 foreach($area in @('owner','published')){$path=Join-Path $restore $area;if(Test-Path -LiteralPath $path){$kind=if($area -eq 'owner'){'secret'}else{'webread'};Check-Path $path $kind;Get-ChildItem -LiteralPath $path -Force -Recurse|ForEach-Object{Check-Path $_.FullName $kind}}}
}
foreach($f in @('update-active.json','update.lock')){$p=Join-Path $v.state ('maintenance\'+$f);if(Test-Path -LiteralPath $p){Check-Path $p 'secret'}}
$updates=Join-Path $v.state 'maintenance\updates';if(Test-Path -LiteralPath $updates){Check-Path $updates 'secret';Get-ChildItem -LiteralPath $updates -Force -Recurse|ForEach-Object{Check-Path $_.FullName 'secret'}}
Check-Path $v.app 'code';foreach($folder in @('maintenance','www','installer','php','apache','mysql')){$path=Join-Path $v.app $folder;if($v.updater -and $folder -in @('www','maintenance') -and -not(Test-Path -LiteralPath $path)){continue};Check-Path $path 'code';Get-ChildItem -LiteralPath $path -Force -Recurse|ForEach-Object{Check-Path $_.FullName 'code'}}
PS;
    doyel_windows_powershell($context,$script,['app'=>$context['app'],'state'=>$context['state'],'updater'=>$context['updater']??false]);
}
function doyel_windows_acl(array $context,string $path,bool $directory,?string $webRights=null,bool $keepDiagnostic=true): void
{
    // Build a fresh protected DACL, rather than retaining unknown explicit entries.
    $script= <<<'PS'
$v=[Console]::In.ReadToEnd()|ConvertFrom-Json; $item=Get-Item -LiteralPath $v.path -Force; if($item.Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Reparse path'}
if($v.directory){$acl=[Security.AccessControl.DirectorySecurity]::new()}else{$acl=[Security.AccessControl.FileSecurity]::new()};$acl.SetAccessRuleProtection($true,$false)
$inherit=if($v.directory){[Security.AccessControl.InheritanceFlags]'ContainerInherit,ObjectInherit'}else{[Security.AccessControl.InheritanceFlags]::None}
foreach($sid in @('S-1-5-18','S-1-5-32-544')){$id=[Security.Principal.SecurityIdentifier]::new($sid);$rule=[Security.AccessControl.FileSystemAccessRule]::new($id,'FullControl',$inherit,'None','Allow');$acl.AddAccessRule($rule)}
if($v.web){$sid=([Security.Principal.NTAccount]::new('NT SERVICE\DoyelWeb')).Translate([Security.Principal.SecurityIdentifier]);$rule=[Security.AccessControl.FileSystemAccessRule]::new($sid,$v.web,$inherit,'None','Allow');$acl.AddAccessRule($rule)}
Set-Acl -LiteralPath $v.path -AclObject $acl
$actual=Get-Acl -LiteralPath $v.path;if(-not $actual.AreAccessRulesProtected){throw 'Unprotected ACL'};if(@($actual.Access).Count -ne $(if($v.web){3}else{2})){throw 'Unexpected ACL entries'}
PS;
    if(!in_array($webRights,[null,'Read','ReadAndExecute','Modify'],true))throw new RuntimeException('Invalid ACL policy');
    doyel_windows_powershell($context,$script,['path'=>$path,'directory'=>$directory,'web'=>$webRights],$keepDiagnostic);
}
function doyel_windows_config_acl(array $context): callable
{
    return static function(string $temporary,string $existing)use($context):void {
        if(doyel_windows_key(dirname($temporary))!==doyel_windows_key($context['state'].'/config') || doyel_windows_key($existing)!==doyel_windows_key($context['state'].'/config/runtime.php'))throw new RuntimeException('Config replacement boundary mismatch');
        doyel_windows_acl($context,$temporary,false,'Read');
    };
}
function doyel_windows_service(array $context,string $operation): void
{
    if(!in_array($operation,['stop','start'],true))throw new RuntimeException('Fixed service operation required');
    // Recheck native ownership immediately before control; never touch DoyelDB.
    doyel_windows_context($context['app'],false,$context['updater']??false);
    $script=$operation==='stop'?"$"."s=Get-Service -Name DoyelWeb; if($"."s.Status -ne 'Stopped'){Stop-Service -Name DoyelWeb; $"."s.WaitForStatus('Stopped',[TimeSpan]::FromSeconds(45))}":"$"."s=Get-Service -Name DoyelWeb; if($"."s.Status -ne 'Running'){Start-Service -Name DoyelWeb; $"."s.WaitForStatus('Running',[TimeSpan]::FromSeconds(45))}";
    doyel_windows_powershell($context,$script);
}
function doyel_windows_runtime(array $context): array
{
    $path=$context['state'].'/config/runtime.php';restore_worker_private_file($path);$config=require $path;
    if(!is_array($config) || ($config['db_host'] ?? '')!=='127.0.0.1' || ($config['db_port'] ?? null)!==13306 || !is_string($config['db_name'] ?? null))throw new RuntimeException('Private runtime target mismatch');
    restore_worker_identifier($config['db_name']);return $config;
}
function doyel_windows_owner_config(array $context): array
{
    $config=restore_worker_load_config($context['state'].'/maintenance/restore-owner.php');
    if(doyel_windows_key($config['restore_dir'])!==doyel_windows_key($context['state'].'/restore') || doyel_windows_key($config['runtime_config'] ?? '')!==doyel_windows_key($context['state'].'/config/runtime.php') || array_map('doyel_windows_key',$config['web_roots'])!==[doyel_windows_key($context['app'].'/www')] || ($config['fixture'] ?? false)!==false || $config['port']!==13306)throw new RuntimeException('Owner connection boundary mismatch');
    $config['prepare_config_acl']=doyel_windows_config_acl($context);return $config;
}
/** Adds only missing feature settings; never initializes/replaces database state. */
function doyel_windows_provision(array $context): void
{
    $state=$context['state'];$runtime=doyel_windows_runtime($context);
    foreach(['backups','restore'] as $name){$path=$state.'/'.$name;if(is_link($path))throw new RuntimeException('Linked private storage');if(!is_dir($path)&&!mkdir($path,0700))throw new RuntimeException('Cannot provision private storage');}
    $root=$state.'/restore';doyel_windows_acl($context,$root,true,null);
    restore_jobs_initialize($root);restore_maintenance_provision($root);
    doyel_windows_acl($context,$root.'/inbox',true,'Modify');doyel_windows_acl($context,$root.'/published',true,'ReadAndExecute');doyel_windows_acl($context,$root.'/owner',true,null);
    // Root traversal without inheritance; descendants get only their own ACLs.
    doyel_windows_powershell($context, <<<'PS'
$v=[Console]::In.ReadToEnd()|ConvertFrom-Json;$acl=Get-Acl -LiteralPath $v.path;$sid=([Security.Principal.NTAccount]::new('NT SERVICE\DoyelWeb')).Translate([Security.Principal.SecurityIdentifier]);$acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($sid,'ReadAndExecute','None','None','Allow'));Set-Acl -LiteralPath $v.path -AclObject $acl
PS,['path'=>$root]);
    // Existing published files must receive web-read permissions too.
    foreach(new DirectoryIterator($root.'/published') as $entry)if(!$entry->isDot()){if(!$entry->isFile()||$entry->isLink())throw new RuntimeException('Unexpected published storage');doyel_windows_acl($context,$entry->getPathname(),false,'Read');}
    doyel_windows_acl($context,$state.'/backups',true,'Modify');
    $next=$runtime;
    foreach(['backup_dir'=>$state.'/backups','restore_dir'=>$root,'restore_generation'=>0] as $key=>$value)if(!array_key_exists($key,$next))$next[$key]=$value;
    if(doyel_windows_key($next['backup_dir'])!==doyel_windows_key($state.'/backups') || doyel_windows_key($next['restore_dir'])!==doyel_windows_key($root) || !is_int($next['restore_generation']) || $next['restore_generation']<0)throw new RuntimeException('Existing maintenance settings require owner review');
    if($runtime!==$next)restore_activation_config_write($state.'/config/runtime.php',$next,doyel_windows_config_acl($context));
    $client=parse_ini_file($state.'/maintenance/root.cnf',true,INI_SCANNER_RAW);
    if(($client['client']['host'] ?? '')!=='127.0.0.1' || ($client['client']['port'] ?? '')!=='13306' || ($client['client']['user'] ?? '')!=='root' || !preg_match('/^[a-f0-9]{64}$/D',$client['client']['password'] ?? ''))throw new RuntimeException('Installed owner credential format mismatch');
    $owner=['restore_dir'=>$root,'web_roots'=>[$context['app'].'/www'],'host'=>'127.0.0.1','port'=>13306,'owner_user'=>'root','owner_password'=>$client['client']['password'],'active_database'=>$runtime['db_name'],'runtime_config'=>$state.'/config/runtime.php'];
    $path=$state.'/maintenance/restore-owner.php';
    if(file_exists($path)){
        $existing=restore_worker_load_config($path);
        if(doyel_windows_key($existing['restore_dir'])!==doyel_windows_key($root) || doyel_windows_key($existing['runtime_config'])!==doyel_windows_key($state.'/config/runtime.php') || array_map('doyel_windows_key',$existing['web_roots'])!==[doyel_windows_key($context['app'].'/www')] || $existing['owner_user']!=='root' || $existing['owner_password']!==$owner['owner_password'])throw new RuntimeException('Existing owner configuration requires review');return;
    }
    $h=fopen($path,'xb');if(!$h)throw new RuntimeException('Cannot provision owner configuration');try{doyel_backup_package_write_bytes($h,restore_finish_bytes($owner));if(!fflush($h)||(function_exists('fsync')&&!fsync($h)))throw new RuntimeException('Cannot sync owner configuration');}finally{fclose($h);}doyel_windows_acl($context,$path,false,null);
}
