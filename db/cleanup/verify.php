<?php
// Synthetic-only boundary and failure checks. No HTTP surface.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/paths.php';
if (in_array('-h',$argv,true) || in_array('--help',$argv,true)) { echo "Usage: DOYEL_DB_NAME=doyel_fixture php db/cleanup/verify.php /private/new-directory\nCreates a synthetic doyel_s32_guard database on local port 33084.\n"; exit; }
try {
    foreach (['/private/report.json','C:\\private\\report.json','C:/private/report.json','\\\\server\\share\\report.json'] as $path) if (!cleanup_absolute_path($path)) throw new RuntimeException('Absolute path classification failed');
    foreach (['relative.json','C:relative.json'] as $path) if (cleanup_absolute_path($path)) throw new RuntimeException('Relative path classification failed');
    if (cleanup_path_key('C:\\REPO\\Private',true)!==cleanup_path_key('c:/repo/private/',true)) throw new RuntimeException('Windows path normalization failed');
    $source=getenv('DOYEL_DB_NAME')?:'';
    if (!preg_match('/^doyel_[A-Za-z0-9_]+$/D',$source) || $source==='doyel_s32_guard') throw new RuntimeException('Expected isolated source fixture');
    $dir=$argv[1]??''; $parent=realpath(dirname($dir)); $repo=realpath(__DIR__.'/../..');
    if (!$parent || !str_starts_with($dir,'/') || str_starts_with($parent.'/',$repo.'/') || file_exists($dir)) throw new RuntimeException('Expected new private external directory');
    umask(0077); mkdir($dir,0700);
    $pdo=new PDO('mysql:host=127.0.0.1;port=33084;charset=utf8mb4',getenv('DOYEL_DB_USER')?:'root',getenv('DOYEL_DB_PASS')?:'doyel-local',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('DROP DATABASE IF EXISTS doyel_s32_guard'); $pdo->exec('CREATE DATABASE doyel_s32_guard');
    foreach ($pdo->query("SHOW TABLES FROM `$source`")->fetchAll(PDO::FETCH_COLUMN) as $t) { if (!preg_match('/^[a-z_]+$/D',$t)) throw new RuntimeException('Unexpected table'); $pdo->exec("CREATE TABLE doyel_s32_guard.`$t` LIKE `$source`.`$t`"); }
    $pdo->exec('USE doyel_s32_guard');
    $insert=function($table,$overrides)use($pdo) { $row=[]; foreach ($pdo->query("DESCRIBE `$table`") as $c) $row[$c['Field']]=str_contains($c['Type'],'datetime')?'2026-10-28 00:00:00':(preg_match('/int|float|decimal/',$c['Type'])?'0':'x'); $row=array_replace($row,$overrides); $q=$pdo->prepare("INSERT INTO `$table` (`".implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')'); $q->execute(array_values($row)); };
    foreach ([100=>'2024-10-27 23:59:59',101=>'2024-10-28 00:00:00',102=>'2024-10-28 00:00:01'] as $id=>$date) { $insert('canteen_stock',['SlNo'=>$id,'Date'=>$date]); $insert('canteen_stock_detail',['ID'=>$id,'SlNo'=>$id]); }
    $pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'"); $insert('canteen_stock',['SlNo'=>103,'Date'=>'0000-00-00 00:00:00']); $pdo->exec('SET SESSION sql_mode=DEFAULT');
    $insert('canteen_stock_detail',['ID'=>103,'SlNo'=>999,'Date'=>'2000-01-01 00:00:00']);
    foreach ([100=>'2026-09-27 23:59:59',101=>'2026-09-28 00:00:00',102=>'2026-09-28 00:00:01'] as $id=>$date) $insert('canteen_sales',['SalesID'=>$id,'Date'=>$date]);
    $insert('canteen_sales',['SalesID'=>103,'Post'=>1,'Date'=>'2000-01-01 00:00:00']);
    $insert('canteen_purchase',['BillNo'=>100]); $insert('canteen_purchase',['BillNo'=>101]); $insert('canteen_purchase_detail',['ID'=>100,'BillNo'=>101]);
    $insert('canteen_sales_detail',['ID'=>100,'SalesID'=>999]); $insert('canteen_purchase_detail',['ID'=>101,'BillNo'=>999]);
    putenv('DOYEL_DB_NAME=doyel_s32_guard'); putenv('DOYEL_DB_HOST=127.0.0.1'); putenv('DOYEL_DB_PORT=33084');
    $run=function($args,$expected=0)use($dir) { $command=array_merge([PHP_BINARY,__DIR__.'/cleanup.php','--as-of=2026-10-28'],$args); $pipes=[]; $p=proc_open($command,[0=>['pipe','r'],1=>['file',$dir.'/commands.log','a'],2=>['file',$dir.'/commands.log','a']],$pipes); fclose($pipes[0]); if (proc_close($p)!==$expected) throw new RuntimeException('Unexpected cleanup exit'); };
    $run(['--report='.$dir.'/preview.json']); $r=json_decode(file_get_contents($dir.'/preview.json'),true);
    $expected=['empty_purchase'=>['100'],'old_empty_sales'=>['100'],'orphan_sales_detail'=>['100'],'orphan_purchase_detail'=>['101'],'old_stock'=>['100'],'old_stock_detail'=>['100'],'invalid_stock_dates'=>['103'],'orphan_stock_detail'=>['103']];
    if ($r['sets']!==$expected) throw new RuntimeException('Boundary selection mismatch');
    $run(['--apply='.$dir.'/preview.json','--backup='.$dir.'/rollback.json','--fail-after-delete=4'],1);
    $run(['--report='.$dir.'/rollback-preview.json']);
    if (file_get_contents($dir.'/preview.json')!==file_get_contents($dir.'/rollback-preview.json')) throw new RuntimeException('Rollback changed data');
    $pdo->exec("UPDATE canteen_sales SET Note='synthetic changed' WHERE SalesID=101");
    $run(['--apply='.$dir.'/preview.json','--backup='.$dir.'/stale.json'],1);
    if (file_exists($dir.'/stale.json')) throw new RuntimeException('Stale report reached backup/deletion');
    $run(['--report='.$dir.'/fresh.json']); $run(['--apply='.$dir.'/fresh.json','--backup='.$dir.'/backup.json']); $run(['--report='.$dir.'/after.json']);
    $after=json_decode(file_get_contents($dir.'/after.json'),true)['sets'];
    foreach (array_keys($expected) as $key) if ($after[$key]!==($key==='invalid_stock_dates'||$key==='orphan_stock_detail'?['103']:[])) throw new RuntimeException('Idempotent selection mismatch');
    echo "PASS: exact/one-second cutoff boundaries; header date controls children; malformed/orphan snapshots preserved; posted/nonempty bills preserved; stale report refused; actual mid-delete rollback; idempotent rerun\n";
} catch (Throwable $e) { fwrite(STDERR,$e instanceof PDOException?'Synthetic fixture verification failed: '.$e->getCode()."\n":$e->getMessage()."\n"); exit(1); }
