<?php
if(PHP_SAPI!=='cli')exit(1);
require_once __DIR__.'/maintenance-lib.php';require_once dirname(__DIR__).'/restore/convert.php';
$checks=0;
function r8_assert(bool $pass,string $label):void{global $checks;if(!$pass)throw new RuntimeException($label);$checks++;echo 'PASS '.$label."\n";}
function r8_refuse(callable $fn,string $label):void{try{$fn();}catch(Throwable $error){r8_assert(true,$label);return;}throw new RuntimeException('Expected refusal '.$label);}
$clixml='#< CLIXML'."\r\n".'<Objs Version="1.1.0.1"><Obj S="progress"><AV>Preparing modules for first use.</AV></Obj><S S="Error">Untrusted code writer_x000D__x000A_</S><S S="Error">At line:13 char:62_x000D__x000A_</S><S S="Error">+ ... throw_x000D__x000A_</S></Objs>';
r8_assert(doyel_windows_native_detail($clixml)==='Untrusted code writer','CLIXML native errors reduce to their message');
r8_assert(doyel_windows_native_detail("Untrusted owner S-1-5-1 on C:\\x\r\nAt line:1 char:1\r\n+ throw\r\n")==='Untrusted owner S-1-5-1 on C:\\x','plain native errors reduce to their message');
$app='C:/Program Files/Doyel';$state='C:/ProgramData/Doyel';
$service=['path'=>'"C:\\Program Files\\Doyel\\apache\\bin\\httpd.exe" -k runservice','account'=>'NT AUTHORITY\\LocalService','sid_type'=>1,'config_args'=>['-f','C:\\ProgramData\\Doyel\\config\\httpd.conf'],'database_path'=>'"C:\\Program Files\\Doyel\\mysql\\bin\\mysqld.exe" --defaults-file="C:\\ProgramData\\Doyel\\config\\my.ini" DoyelDB'];
doyel_windows_service_proof($service,$app,$state);r8_assert(true,'exact owned service paths accepted');
foreach(['path'=>'"C:/Program Files/Doyel-other/apache/bin/httpd.exe" -k runservice','account'=>'LocalSystem','sid_type'=>0,'config_args'=>['-f',$state.'/other.conf'],'database_path'=>'"C:/other/mysqld.exe" --defaults-file="C:/ProgramData/Doyel/config/my.ini" DoyelDB'] as $key=>$value){$bad=$service;$bad[$key]=$value;r8_refuse(fn()=>doyel_windows_service_proof($bad,$app,$state),'altered service '.$key.' refused');}
$conf=doyel_windows_apache_conf($app,$state);$stub=doyel_windows_config_stub();doyel_windows_web_proof($conf,$stub,$app,$state);r8_assert(true,'trusted document root and private config stub accepted');
doyel_windows_web_proof(str_replace("\n","\r\n",$conf),str_replace("\n","\r\n",$stub),$app,$state);r8_assert(true,'stock CRLF templates accepted without directive relaxation');
r8_refuse(fn()=>doyel_windows_web_proof($conf."Alias /private C:/ProgramData/Doyel\n",$stub,$app,$state),'additional Alias refused');
r8_refuse(fn()=>doyel_windows_web_proof(str_replace('/www','/maintenance',$conf),$stub,$app,$state),'altered document root refused');
r8_refuse(fn()=>doyel_windows_web_proof($conf,str_replace('runtime.php','other.php',$stub),$app,$state),'different private connection stub refused');
if(!in_array('--mysql',$argv,true)){echo "PASS $checks pure native-policy checks; --mysql enables isolated converter fixture\n";exit;}
$owner=null;$db='';$private='';
try{
    $input=json_decode(stream_get_contents(STDIN,4096),true,8,JSON_THROW_ON_ERROR);$config=['host'=>'127.0.0.1','port'=>33084,'fixture'=>true,'owner_user'=>$input['owner_user'],'owner_password'=>$input['owner_password'],'web_roots'=>[dirname(__DIR__,2)]];unset($input);
    $owner=restore_worker_pdo($config);$db='r8_convert_'.bin2hex(random_bytes(8));$owner->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo=restore_worker_pdo($config,$db);foreach(doyel_schema_tables() as $definition)$pdo->exec($definition['create']);
    $s=$pdo->prepare('INSERT INTO l_user (user_id,UserName,password,FullName,m_date,userAtho) VALUES (1,?,?,?,\'2026-11-07\',1)');$s->execute(['synthetic',md5('synthetic-convert'),'বাংলা synthetic']);
    $before=restore_worker_receipt($pdo);$private=realpath(sys_get_temp_dir()).'/doyel-r8-'.bin2hex(random_bytes(8));mkdir($private,0700);$output=$private.'/converted.doyel.gz';
    $receipt=restore_convert_database($config,$db,$output);$read=doyel_backup_package_read($output);
    r8_assert($receipt['tables']===13 && $read['footer']['tables']===$before['tables'] && $read['footer']['inventory']===$before['inventory'],'converter exact thirteen-table evidence');
    r8_assert(restore_worker_receipt($pdo)===$before,'source records and credentials unchanged');$hash=hash_file('sha256',$output);
    r8_refuse(fn()=>restore_convert_database($config,$db,$output),'existing output refused');r8_assert(hash_file('sha256',$output)===$hash,'existing output preserved');
    $pdo->exec('CREATE TABLE unexpected(id INT)');$bad=$private.'/bad.doyel.gz';r8_refuse(fn()=>restore_convert_database($config,$db,$bad),'unknown schema refused');r8_assert(!file_exists($bad),'failed own partial output removed');
    echo "PASS $checks checks including disposable MySQL converter fixture\n";
}finally{if($owner!==null&&$db!=='')$owner->exec("DROP DATABASE IF EXISTS `$db`");if($private!==''){foreach(glob($private.'/*') as $file)unlink($file);rmdir($private);}}
