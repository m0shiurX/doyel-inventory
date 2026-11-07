<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/maintenance-lib.php';require_once dirname(__DIR__).'/prod/build-metadata.php';require_once __DIR__.'/update-package.php';
function doyel_update_bootstrap(string $policyHash,array $hashes):string{
    $template= <<<'PHP'
<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Check payload bytes before loading any bundled PHP module. The supported
// launcher also makes an ACL-protected verified copy before elevation uses PHP.
$expected=__PAYLOAD_HASHES__;
foreach($expected as $file=>$hash){$path=__DIR__.'/'.$file;$real=realpath($path);$key=static fn($p)=>PHP_OS_FAMILY==='Windows'?strtolower(str_replace('\\','/',$p)):str_replace('\\','/',$p);if($real===false||is_link($path)||$key($real)!==$key($path)||!hash_equals($hash,hash_file('sha256',$path))){fwrite(STDERR,"Update payload integrity check failed. Use the verified launcher.\n");exit(1);}}
require __DIR__.'/maintenance/tools/windows/update.php';
doyel_update_cli(__DIR__,__POLICY_HASH__);
PHP;
    return str_replace(['__PAYLOAD_HASHES__','__POLICY_HASH__'],[var_export($hashes,true),var_export($policyHash,true)],$template)."\n";
}
try{
    if($argc!==3||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,95}$/D',$argv[2]))throw new RuntimeException('Usage: php build-update.php NEW-external-directory reviewed-build-id');
    $parent=realpath(dirname($argv[1]));$repo=realpath(dirname(__DIR__,2));if(!$parent||file_exists($argv[1])||is_link($argv[1])||!preg_match('~^(?:/|[A-Za-z]:[\\\\/])~',$argv[1]))throw new RuntimeException('New external output required');$target=$parent.'/'.basename($argv[1]);if(str_starts_with(doyel_windows_key($target).'/',doyel_windows_key($repo).'/'))throw new RuntimeException('Output must be outside checkout');
    umask(0077);mkdir($target,0700);mkdir($target.'/private-build',0700);
    foreach([['build-release.php',[$target.'/www',$target.'/private-build/runtime.php']],['build-maintenance.php',[$target.'/maintenance']]] as [$tool,$args]){$p=proc_open([PHP_BINARY,$repo.'/tools/prod/'.$tool,...$args],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes);if(!is_resource($p)||proc_close($p)!==0)throw new RuntimeException('Allowlisted code build failed');}rmdir($target.'/private-build');
    file_put_contents($target.'/www/config.local.php',doyel_windows_config_stub());$metadata=doyel_build_metadata($argv[2]);foreach(['www','maintenance'] as $tree)file_put_contents($target.'/'.$tree.'/lib/build_metadata.php',$metadata);
    $files=[];foreach(['www','maintenance'] as $tree)foreach(doyel_update_tree($target.'/'.$tree) as $file=>$hash)$files[]=$tree.'/'.$file;
    $policy=['format'=>1,'app'=>['version'=>doyel_app_version()['version'],'build'=>$argv[2]],'schema'=>['version'=>1,'fingerprint'=>doyel_schema_expected_fingerprint()],'files'=>$files];
    file_put_contents($target.'/package-policy.json',json_encode($policy,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n");$policyHash=hash_file('sha256',$target.'/package-policy.json');
    $payloadHashes=[];foreach($files as $file)$payloadHashes[$file]=hash_file('sha256',$target.'/'.$file);file_put_contents($target.'/Update.php',doyel_update_bootstrap($policyHash,$payloadHashes));
    copy(__DIR__.'/Update-Preflight.ps1',$target.'/Update-Preflight.ps1');file_put_contents($target.'/Update-Doyel.bat',str_replace('__PREFLIGHT_SHA256__',hash_file('sha256',$target.'/Update-Preflight.ps1'),file_get_contents(__DIR__.'/Update-Doyel.bat')));
    file_put_contents($target.'/README.txt',"Doyel application-only update ".doyel_app_version()['version']." — build ".$argv[2]."\n\nWindows acceptance candidate: test on a copy first. Requires an existing Doyel Windows services installation and canonical migrated schema 1. Does not upgrade PHP Desktop 5.6 or migrate legacy SQL.\n\nExtract the complete folder. Close data entry. Double-click Update-Doyel.bat, approve Windows administrator elevation, and confirm apply. It retains the database, private configuration, previous code and a verified owner-only backup. PHP, Apache and MySQL binaries are not included or reinstalled.\n\nFor an interrupted update run the same launcher and choose recover. Keep this folder and all private recovery files. Unknown identities remain closed. After committed writes, automatic code rollback is refused. Check About Doyel and test stock/balances/reports/printing before entering real work.\n\nHashes detect corruption, not who published a release. Obtain this code from your trusted developer/release location. No client SQL or login credentials are included. No automatic downloader or publisher is supplied.\n");
    $hashes=[];foreach([...$files,'Update.php','Update-Doyel.bat','Update-Preflight.ps1','README.txt','package-policy.json'] as $file)$hashes[$file]=hash_file('sha256',$target.'/'.$file);
    file_put_contents($target.'/SHA256SUMS.json',json_encode($hashes,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");doyel_update_package($target,$policyHash);echo "PASS application-only update built: $target\nPolicy SHA256: $policyHash\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
