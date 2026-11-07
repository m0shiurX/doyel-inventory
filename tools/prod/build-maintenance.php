<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    if($argc!==2)throw new RuntimeException('Usage: php build-maintenance.php NEW-external-directory');
    $root=realpath(dirname(__DIR__,2));$target=$argv[1];$parent=realpath(dirname($target));
    if(!$parent || file_exists($target)||is_link($target)||!preg_match('~^(?:/|[A-Za-z]:[\\\\/])~',$target))throw new RuntimeException('New absolute output required');
    $target=$parent.'/'.basename($target);$key=static fn($p)=>strtolower(str_replace('\\','/',$p));
    if(str_starts_with($key($target).'/',rtrim($key($root),'/').'/'))throw new RuntimeException('Keep owner payload outside checkout');
    $files=file(__DIR__.'/maintenance-files.txt',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    foreach($files as $file)if(!preg_match('~^[A-Za-z0-9_/-]+\.(php|bat)$~D',$file)||str_contains($file,'..')||is_link($root.'/'.$file)||!is_file($root.'/'.$file)||$key(realpath($root.'/'.$file))!==$key($root.'/'.$file))throw new RuntimeException('Invalid maintenance source');
    umask(0077);if(!mkdir($target,0700))throw new RuntimeException('Cannot create owner payload');
    foreach($files as $file){$to=$target.'/'.$file;if(!is_dir(dirname($to))&&!mkdir(dirname($to),0700,true))throw new RuntimeException('Cannot create owner directory');if(!copy($root.'/'.$file,$to))throw new RuntimeException('Cannot copy owner source');}
    echo 'PASS '.count($files)." owner maintenance files; no credentials or client data copied\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n");exit(1);}
