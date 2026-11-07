<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Strict data manifest, not an authenticity signature. Operator trusts the release. */
function doyel_update_path(string $path): string {
    if(strlen($path)>200 || !preg_match('~^[A-Za-z0-9_ ./-]+$~D',$path) || str_contains($path,'\\'))throw new RuntimeException('Invalid update path');
    foreach(explode('/',$path) as $part)if($part===''||$part==='.'||$part==='..'||rtrim($part," .")!==$part||preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/iD',$part))throw new RuntimeException('Invalid update path');
    return $path;
}
function doyel_update_tree(string $directory): array {
    if(!is_dir($directory)||is_link($directory))throw new RuntimeException('Missing code tree');$out=[];
    $walk=function(string $path,string $prefix='')use(&$walk,&$out):void{
        foreach(new DirectoryIterator($path) as $entry){if($entry->isDot())continue;if($entry->isLink())throw new RuntimeException('Linked update content');$name=doyel_update_path($prefix.$entry->getFilename());
            if($entry->isDir())$walk($entry->getPathname(),$name.'/');elseif($entry->isFile()){$out[$name]=hash_file('sha256',$entry->getPathname());}else throw new RuntimeException('Unsupported update content');}
    };$walk($directory);ksort($out);return $out;
}
function doyel_update_package(string $directory,string $policyHash): array {
    $real=realpath($directory);if($real===false||is_link($directory))throw new RuntimeException('Extracted update required');$directory=$real;
    foreach(['package-policy.json','SHA256SUMS.json'] as $file)if(!is_file($directory.'/'.$file)||is_link($directory.'/'.$file)||filesize($directory.'/'.$file)>131072)throw new RuntimeException('Invalid update manifest');
    if(!preg_match('/^[a-f0-9]{64}$/D',$policyHash)||!hash_equals($policyHash,hash_file('sha256',$directory.'/package-policy.json')))throw new RuntimeException('Update policy changed');
    $policy=json_decode(file_get_contents($directory.'/package-policy.json'),true,16,JSON_THROW_ON_ERROR);$manifest=json_decode(file_get_contents($directory.'/SHA256SUMS.json'),true,16,JSON_THROW_ON_ERROR);
    if(array_keys($policy)!==['format','app','schema','files']||$policy['format']!==1||array_keys($policy['app'])!==['version','build']||$policy['app']['version']!==doyel_app_version()['version']||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,95}$/D',$policy['app']['build'])||$policy['schema']!==['version'=>1,'fingerprint'=>doyel_schema_expected_fingerprint()]||!is_array($policy['files'])||!array_is_list($policy['files'])||count($policy['files'])>512)throw new RuntimeException('Unsupported update policy');
    $expected=[];$folded=[];foreach($policy['files'] as $file){doyel_update_path($file);if(!preg_match('~^(?:www|maintenance)/.+\.(?:php|js|css|png|gif|jpg|bat)$~D',$file)||preg_match('~(?:^|/)(?:config\.(?:sample|local)|restore-owner)\.php$~i',$file)&&$file!=='www/config.local.php')throw new RuntimeException('Update contains private or runtime content');if(isset($folded[strtolower($file)]))throw new RuntimeException('Colliding update paths');$folded[strtolower($file)]=true;$expected[$file]=true;}
    foreach(['Update.php','Update-Doyel.bat','Update-Preflight.ps1','README.txt','package-policy.json'] as $file)$expected[$file]=true;
    if(!is_array($manifest)||array_keys($manifest)!==array_keys($expected))throw new RuntimeException('Update file list changed');
    $actual=doyel_update_tree($directory);$actualFiles=array_keys($actual);$expectedFiles=[...array_keys($expected),'SHA256SUMS.json'];sort($actualFiles);sort($expectedFiles);if($actualFiles!==$expectedFiles)throw new RuntimeException('Unexpected update files');
    foreach($manifest as $file=>$hash)if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||filesize($directory.'/'.$file)>16777216||!hash_equals($hash,$actual[$file]))throw new RuntimeException('Update checksum mismatch');
    if(str_replace("\r\n","\n",file_get_contents($directory.'/www/config.local.php'))!==str_replace("\r\n","\n",doyel_windows_config_stub()))throw new RuntimeException('Update connection stub changed');
    return ['directory'=>$directory,'policy'=>$policy,'files'=>$manifest,'manifest_sha256'=>hash_file('sha256',$directory.'/SHA256SUMS.json'),'policy_sha256'=>$policyHash];
}
function doyel_update_copy(string $from,string $to): void {
    if(file_exists($to)||is_link($to))throw new RuntimeException('New code destination required');if(!mkdir($to,0700))throw new RuntimeException('Cannot stage code');
    foreach(doyel_update_tree($from) as $file=>$hash){$target=$to.'/'.$file;if(!is_dir(dirname($target))&&!mkdir(dirname($target),0700,true))throw new RuntimeException('Cannot stage code directory');if(!copy($from.'/'.$file,$target)||hash_file('sha256',$target)!==$hash)throw new RuntimeException('Cannot verify staged code');}
}
