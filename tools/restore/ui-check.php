<?php
/** CLI-only disposable HTTP integration. Owner credentials enter via STDIN JSON.
 * Usage: php tools/restore/ui-check.php < /absolute/private-owner-input.json
 * Input: {"owner_user":"...","owner_password":"..."}; fixed localhost:33084.
 * Creates only random r5_ui_* canonical DB/account fixtures; no source data read.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__,2).'/lib/restore_worker.php';
require_once dirname(__DIR__,2).'/lib/restore_maintenance.php';
$checks=0; $server=null; $owner=null; $database=''; $account=''; $private='';
function r5_assert(bool $pass,string $label): void { global $checks; if (!$pass) throw new RuntimeException($label); $checks++; echo 'PASS '.$label."\n"; }
function r5_http(string $path,string &$cookie,string $method='GET',string $body='',string $type='application/x-www-form-urlencoded'): array {
    $headers=['Connection: close']; if ($cookie!=='') $headers[]='Cookie: '.$cookie;
    if ($method==='POST') $headers[]='Content-Type: '.$type;
    $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>10]]);
    $content=@file_get_contents('http://127.0.0.1:8166/'.$path,false,$ctx); $response=$http_response_header ?? [];
    preg_match('~HTTP/\S+ (\d+)~',$response[0] ?? '',$status); $location='';
    foreach($response as $header) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i',$header,$m)) $cookie=$m[1];
        if (stripos($header,'Location: ')===0) $location=substr($header,10);
    }
    return ['status'=>(int)($status[1] ?? 0),'body'=>(string)$content,'location'=>$location];
}
function r5_field(string $html,string $name): string {
    if (!preg_match('/name="'.preg_quote($name,'/').'" value="([^"]*)"/',$html,$m)) throw new RuntimeException('Missing form field');
    return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
}
function r5_login(string $username,string $password,string &$cookie): array {
    $page=r5_http('login.php',$cookie);
    return r5_http('login.php',$cookie,'POST',http_build_query(['csrf_token'=>r5_field($page['body'],'csrf_token'),'username'=>$username,'password'=>$password]));
}
function r5_upload(string $name,array $fields,string &$cookie): array {
    $boundary='r5'.bin2hex(random_bytes(12)); $body='';
    foreach($fields as $key=>$value) $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
    $body.='--'.$boundary."\r\nContent-Disposition: form-data; name=\"backup\"; filename=\"$name\"\r\nContent-Type: application/gzip\r\n\r\nfixture quarantine bytes\r\n--".$boundary."--\r\n";
    return r5_http('Restore.php',$cookie,'POST',$body,'multipart/form-data; boundary='.$boundary);
}
function r5_action_fields(string $operation,string $id,string $password,string &$cookie): array {
    $page=r5_http('Restore.php?job='.$id,$cookie);
    if (!preg_match('/name="operation" value="'.preg_quote($operation,'/').'"><input type="hidden" name="nonce" value="([^"]+)"/',$page['body'],$m)) throw new RuntimeException('Missing action form');
    return ['operation'=>$operation,'nonce'=>$m[1],'csrf_token'=>r5_field($page['body'],'csrf_token'),'confirm'=>'yes','work_gap'=>'yes','password'=>$password];
}
try {
    $input=json_decode(stream_get_contents(STDIN,4096),true,8,JSON_THROW_ON_ERROR);
    $owner=new PDO('mysql:host=127.0.0.1;port=33084', $input['owner_user'],$input['owner_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); unset($input);
    $private=realpath(sys_get_temp_dir()).'/doyel-r5-ui-'.bin2hex(random_bytes(8)); mkdir($private,0700);
    $database='r5_ui_'.bin2hex(random_bytes(8)); $account='r5_ui_'.bin2hex(random_bytes(8)); $dbPassword=bin2hex(random_bytes(24));
    $owner->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"); $owner->exec("USE `$database`");
    foreach(doyel_schema_tables() as $definition) $owner->exec($definition['create']);
    $insert=$owner->prepare('INSERT INTO l_user (user_id,UserName,password,FullName,m_date,userAtho) VALUES (?,?,?,?,?,?)');
    $insert->execute([1,'fixture-admin',md5('r5-admin-password'),'Fixture','2026-11-07',1]);
    $insert->execute([2,'fixture-staff',md5('r5-staff-password'),'Fixture','2026-11-07',0]);
    $owner->exec("CREATE USER '$account'@'%' IDENTIFIED BY ".$owner->quote($dbPassword));
    $scope=str_replace('_','\\_',$database); $owner->exec("GRANT SELECT,INSERT,UPDATE,DELETE ON `$scope`.* TO '$account'@'%'");
    $root=$private.'/jobs';mkdir($root,0700);restore_jobs_initialize($root);restore_maintenance_provision($root);
    $runtime=['db_host'=>'127.0.0.1','db_port'=>33084,'db_name'=>$database,'db_user'=>$account,'db_pass'=>$dbPassword,'restore_dir'=>$root,'restore_generation'=>0,'log_dir'=>$private.'/logs','backup_dir'=>$private];
    $runtimePath=$private.'/runtime.php';file_put_contents($runtimePath,'<?php return '.var_export($runtime,true).';');chmod($runtimePath,0600);
    $pipes=[];$build=proc_open([PHP_BINARY,dirname(__DIR__).'/prod/build-release.php',$private.'/www',$runtimePath],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);stream_get_contents($pipes[1]);fclose($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[2]);r5_assert(proc_close($build)===0,'allowlisted runtime includes UI assets');
    mkdir($private.'/sessions',0700);
    $pipes=[];$server=proc_open([PHP_BINARY,'-d','session.save_path='.$private.'/sessions','-d','post_max_size=66M','-d','upload_max_filesize=64M','-S','127.0.0.1:8166','-t',$private.'/www'],[0=>['pipe','r'],1=>['file',$private.'/http.log','a'],2=>['file',$private.'/http.log','a']],$pipes);fclose($pipes[0]);
    $guest='';for($i=0;$i<30;$i++){if(r5_http('Restore.php',$guest)['status']!==0)break;usleep(100000);}
    r5_assert(r5_http('Restore.php',$guest)['status']===302,'guest redirect');
    $admin='';$staff='';r5_assert(r5_login('fixture-admin','r5-admin-password',$admin)['status']===302,'fresh admin login');r5_assert(r5_login('fixture-staff','r5-staff-password',$staff)['status']===302,'fresh staff login');
    r5_assert(r5_http('Restore.php',$staff)['status']===302,'ordinary role redirect');
    $page=r5_http('Restore.php',$admin);r5_assert($page['status']===200 && str_contains($page['body'],'schema 1: compatible'),'admin canonical schema overview');
    r5_assert(r5_http('Restore.php?job=bad&view=status',$admin)['status']===400,'strict job ID');
    r5_assert(r5_http('Restore.php',$admin,'POST')['status']===413,'missing POST bounded error');
    r5_assert(r5_http('Restore.php',$admin,'POST','operation=upload&csrf_token=bad')['status']===403,'CSRF refusal');
    $fields=['operation'=>'upload','csrf_token'=>r5_field($page['body'],'csrf_token'),'nonce'=>r5_field($page['body'],'nonce')];
    r5_assert(r5_upload('old.sql',$fields,$admin)['status']===400,'SQL conversion refusal');
    $upload=r5_upload('fixture.doyel.gz',$fields,$admin);r5_assert($upload['status']===302,'actual multipart quarantine');
    preg_match('/job=([a-f0-9]{32})/',$upload['location'],$m);$id=$m[1];
    r5_assert(r5_upload('fixture.doyel.gz',$fields,$admin)['location']===$upload['location'],'upload nonce replay');
    r5_assert(r5_http('Restore.php?job='.$id.'&view=status',$guest)['status']===302 && r5_http('Restore.php?job='.$id.'&view=status',$staff)['status']===302,'status role restrictions');
    $status=r5_http('Restore.php?job='.$id.'&view=status',$admin);$json=json_decode($status['body'],true,8,JSON_THROW_ON_ERROR);r5_assert($status['status']===200 && $json['state']==='submitted' && !isset($json['admin_id'],$json['nonce_hash']),'sanitized status');
    $validation=['backup_asof'=>'2026-11-01T00:00:00Z','app'=>['version'=>'<script>bad</script>','build'=>'test'],'schema'=>['version'=>1,'compatible'=>true],'row_counts'=>['l_user'=>2],'active_row_counts'=>['l_user'=>2],'active_counts_asof'=>gmdate(DATE_ATOM),'candidate_database'=>'<img src=x onerror=bad>','expected_active_database'=>$database,'comparisons'=>['row_digests_match'=>true,'inventory_match'=>true,'foreign_keys_valid'=>true],'customer_balances'=>['count'=>0,'sha256'=>str_repeat('a',64)],'password'=>'never-render-private'];
    restore_jobs_write_json($root.'/published/'.$id.'.validation.json',$validation);
    $record=['id'=>$id,'state'=>'ready','bytes'=>24,'sha256'=>str_repeat('a',64)];restore_jobs_write_json($root.'/published/'.$id.'.json',$record);
    $page=r5_http('Restore.php?job='.$id,$admin)['body'];r5_assert(str_contains($page,'&lt;img') && str_contains($page,'&lt;script&gt;') && !str_contains($page,'never-render-private') && !str_contains($page,'<img src=x'),'escaped selected validation evidence');
    r5_assert(str_contains($page,'Backup age now') && str_contains($page,'Current database counts as of') && str_contains($page,'Source row counts and digests'),'age, snapshot and verified comparisons');
    $hashes=fn()=>hash('sha256',json_encode($owner->query('SELECT user_id,password,userAtho FROM l_user ORDER BY user_id')->fetchAll(PDO::FETCH_ASSOC)));$before=$hashes();
    $action=r5_action_fields('activation_request',$id,'r5-admin-password',$admin);unset($action['work_gap']);r5_assert(r5_http('Restore.php?job='.$id,$admin,'POST',http_build_query($action))['status']===400,'work gap acknowledgement required');
    $action=r5_action_fields('activation_request',$id,'wrong-r5-password',$admin);r5_assert(r5_http('Restore.php?job='.$id,$admin,'POST',http_build_query($action))['status']===403,'wrong administrator password refused');
    r5_assert(count(glob($root.'/inbox/'.$id.'/action-*.json'))===0,'failed checks create no action');
    $action=r5_action_fields('activation_request',$id,'r5-admin-password',$admin);r5_assert(r5_http('Restore.php?job='.$id,$admin,'POST',http_build_query($action))['status']===302,'fresh administrator reauthentication');
    r5_assert(r5_http('Restore.php?job='.$id,$admin,'POST',http_build_query($action))['status']===302 && count(glob($root.'/inbox/'.$id.'/action-*.json'))===1,'action nonce replay');
    r5_assert($hashes()===$before,'normal reauthentication preserves exact password hashes');
    // Owner changes only this synthetic fixture to a legacy hash before acceptance.
    // Fresh read-only login must verify it without the usual rehash UPDATE.
    $legacy=$owner->prepare('UPDATE l_user SET password=? WHERE user_id=1');$legacy->execute([md5('r5-admin-password')]);$before=$hashes();
    $allRows=static function() use($owner): string {$rows=[];foreach(doyel_schema_tables() as $table=>$definition)$rows[$table]=$owner->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);return hash('sha256',json_encode($rows));};$acceptanceBefore=$allRows();
    $record['state']='awaiting_acceptance';restore_jobs_write_json($root.'/published/'.$id.'.json',$record);restore_jobs_control_write($root,['mode'=>'acceptance','generation'=>0,'active_job'=>$id]);
    r5_assert(str_contains(r5_http('Restore.php?job='.$id,$admin)['body'],'Read-only acceptance is in progress'),'acceptance hides uploads');
    r5_assert(r5_upload('fixture.doyel.gz',$fields,$admin)['status']===400,'acceptance refuses upload mutations');
    foreach(['accept','rollback'] as $operation){$action=r5_action_fields($operation,$id,'r5-admin-password',$admin);r5_assert(r5_http('Restore.php?job='.$id,$admin,'POST',http_build_query($action))['status']===302,'acceptance '.$operation.' fresh password');}
    $newLogin='';r5_assert(r5_login('fixture-admin','r5-admin-password',$newLogin)['status']===302,'acceptance fresh login');
    r5_assert($hashes()===$before,'legacy password hash stays byte exact through acceptance login and requests');
    r5_assert($allRows()===$acceptanceBefore,'all 13 table contents unchanged in acceptance');
    r5_assert((int)$owner->query('SELECT COUNT(*) FROM l_user')->fetchColumn()===2,'no account creation or removal');
    $found=false;foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile() && str_ends_with($file->getFilename(),'.json')){ $bytes=file_get_contents($file->getPathname()); if(str_contains($bytes,'r5-admin-password') || str_contains($bytes,'wrong-r5-password'))$found=true;}
    if(str_contains(file_get_contents($private.'/http.log'),'r5-admin-password') || str_contains(file_get_contents($private.'/http.log'),'wrong-r5-password'))$found=true;
    foreach(glob($private.'/sessions/*') as $file)if(str_contains(file_get_contents($file),'r5-admin-password'))$found=true;r5_assert(!$found,'no plaintext passwords in jobs, sessions or HTTP log');
    restore_jobs_control_write($root,['mode'=>'frozen','generation'=>0,'active_job'=>$id]);r5_assert(r5_http('Restore.php?job='.$id.'&view=status',$admin)['status']===503,'offline maintenance status is 503');
    echo "PASS $checks isolated HTTP checks; no existing database read or modified\n";
} catch(Throwable $error) { fwrite(STDERR,'UI check failed: '.(str_starts_with($error->getMessage(),'SQLSTATE')?'database fixture operation':$error->getMessage())."\n"); $failed=true; }
finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($owner!==null){if($database!=='')$owner->exec("DROP DATABASE IF EXISTS `$database`");if($account!=='')$owner->exec("DROP USER IF EXISTS '$account'@'%'");}
    if($private!==''){ $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($private,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($private); }
}
if(isset($failed))exit(1);
