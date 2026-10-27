<?php
// Maintenance CLI: stop all application writers for preview, backup and apply.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/paths.php';
function private_write(string $path, array $data): void {
    private_path($path); umask(0077); $file = fopen($path, 'x');
    if (!$file) throw new RuntimeException('Refusing existing/unwritable private output');
    try { $bytes=json_encode($data, JSON_THROW_ON_ERROR); $offset=0; while ($offset<strlen($bytes)) { $written=fwrite($file,substr($bytes,$offset)); if (!$written) throw new RuntimeException('Private output failed'); $offset+=$written; } if (!fflush($file) || !fsync($file)) throw new RuntimeException('Private output failed'); } finally { fclose($file); }
}
function valid_date(string $date): bool {
    if (!preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $date)) return false;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date, new DateTimeZone('Asia/Dhaka'));
    return $parsed && $parsed->format('Y-m-d H:i:s') === $date;
}
try {
    $opt = getopt('h', ['help','as-of:','report:','apply:','backup:','allow-production','fail-after-delete:']);
    if (isset($opt['h']) || isset($opt['help'])) { echo "Usage: php db/cleanup/cleanup.php --as-of=YYYY-MM-DD [--report=/private/new.json]\nApply: --apply=/private/preview.json --backup=/private/new-backup.json\nDefaults to preview. Production requires --allow-production and a maintenance window.\n"; exit; }
    if (!isset($opt['as-of'])) throw new RuntimeException('Explicit --as-of=YYYY-MM-DD is required');
    $asOf = $opt['as-of'];
    if (!valid_date($asOf . ' 00:00:00')) throw new RuntimeException('Invalid as-of calendar date');
    $clock = new DateTimeImmutable($asOf . ' 00:00:00', new DateTimeZone('Asia/Dhaka'));
    $cutoff = $clock->modify('-2 years')->format('Y-m-d H:i:s'); $saleCutoff = $clock->modify('-30 days')->format('Y-m-d H:i:s');
    $name = getenv('DOYEL_DB_NAME') ?: ''; $host = getenv('DOYEL_DB_HOST') ?: '127.0.0.1'; $port = getenv('DOYEL_DB_PORT') ?: '33084';
    $local = $host === '127.0.0.1' && $port === '33084' && preg_match('/^doyel_[A-Za-z0-9_]+$/D', $name);
    if (!$local && isset($opt['fail-after-delete'])) throw new RuntimeException('Fault injection is restricted to isolated local fixtures');
    if (!$local && !isset($opt['allow-production'])) throw new RuntimeException('Only isolated local fixtures allowed without --allow-production');
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $name) || !ctype_digit($port) || !preg_match('/^[A-Za-z0-9.:-]+$/D', $host)) throw new RuntimeException('Invalid DB configuration');
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DOYEL_DB_USER') ?: 'root', getenv('DOYEL_DB_PASS') ?: 'doyel-local', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>true]);
    $pdo->exec("SET SESSION time_zone='+06:00'");
    $tables = ['canteen_ac_head'=>'headID','canteen_collection'=>'PaymentID','canteen_customar'=>'MemberID','canteen_expenses'=>'PaymentID','canteen_item'=>'ItemID','canteen_purchase'=>'BillNo','canteen_purchase_detail'=>'ID','canteen_sales'=>'SalesID','canteen_sales_detail'=>'ID','canteen_stock'=>'SlNo','canteen_stock_detail'=>'ID','canteen_supplier'=>'SupplierId','l_user'=>'user_id'];
    $engines=$pdo->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (array_keys($engines)!==array_keys($tables) || array_unique(array_values($engines))!==['InnoDB']) throw new RuntimeException('Expected exactly 13 transactional InnoDB tables');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction();
    // Parent locks precede children, as in the bill actions. Full PK scans also
    // lock gaps: no new bill/header/line can sneak into this maintenance snapshot.
    $data = [];
    foreach (['canteen_purchase','canteen_sales','canteen_stock','canteen_purchase_detail','canteen_sales_detail','canteen_stock_detail'] as $t) $data[$t] = $pdo->query("SELECT * FROM `$t` ORDER BY `{$tables[$t]}` FOR UPDATE")->fetchAll();
    foreach ($tables as $t=>$pk) if (!isset($data[$t])) $data[$t] = $pdo->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetchAll();
    $indexed = []; foreach ($data as $t=>$rows) foreach ($rows as $r) $indexed[$t][$r[$tables[$t]]] = true;
    $hasSale = array_fill_keys(array_column($data['canteen_sales_detail'],'SalesID'),true); $hasPurchase = array_fill_keys(array_column($data['canteen_purchase_detail'],'BillNo'),true);
    $sets = array_fill_keys(['empty_purchase','old_empty_sales','orphan_sales_detail','orphan_purchase_detail','old_stock','old_stock_detail','invalid_stock_dates','orphan_stock_detail'],[]);
    foreach ($data['canteen_purchase'] as $r) if ($r['Amount']==0 && $r['Post']==0 && !isset($hasPurchase[$r['BillNo']])) $sets['empty_purchase'][]=$r['BillNo'];
    foreach ($data['canteen_sales'] as $r) if ($r['Post']==0 && valid_date($r['Date']) && $r['Date']<$saleCutoff && !isset($hasSale[$r['SalesID']])) $sets['old_empty_sales'][]=$r['SalesID'];
    foreach (['sales'=>'SalesID','purchase'=>'BillNo'] as $kind=>$fk) foreach ($data["canteen_{$kind}_detail"] as $r) if (!isset($indexed["canteen_$kind"][$r[$fk]])) $sets["orphan_{$kind}_detail"][]=$r['ID'];
    foreach ($data['canteen_stock'] as $r) { if (!valid_date($r['Date'])) $sets['invalid_stock_dates'][]=$r['SlNo']; elseif ($r['Date']<$cutoff) $sets['old_stock'][]=$r['SlNo']; }
    $oldStock = array_fill_keys($sets['old_stock'],true);
    foreach ($data['canteen_stock_detail'] as $r) { if (isset($oldStock[$r['SlNo']])) $sets['old_stock_detail'][]=$r['ID']; if (!isset($indexed['canteen_stock'][$r['SlNo']])) $sets['orphan_stock_detail'][]=$r['ID']; }
    $profile = [];
    foreach (['canteen_sales_detail'=>['Qnty','QT','Rate'],'canteen_purchase_detail'=>['Qnty','QT','Rate'],'canteen_item'=>['Qnty','PurPrice','SalesPrice'],'canteen_stock_detail'=>['Qnty','PurPrice']] as $t=>$fields) foreach ($fields as $field) {
        $fraction=[]; $precision=[]; $precision3=[]; $range=[];
        foreach ($data[$t] as $r) { $v=(float)$r[$field]; $id=$r[$tables[$t]]; if ($v!=round($v)) $fraction[]=$id; if (abs($v-round($v,2))>0.000001) $precision[]=$id; if (abs($v-round($v,3))>0.000001) $precision3[]=$id; if (abs($v)>=(in_array($field,['Qnty','QT'],true)?1e9:1e11)) $range[]=$id; }
        $profile["$t.$field"]=['fractional'=>$fraction,'precision_over_2'=>$precision,'precision_over_3'=>$precision3,'outside_decimal_target'=>$range];
    }
    foreach (['canteen_sales'=>['Amount','Discount','Total'],'canteen_purchase'=>['Amount','Discount','Total'],'canteen_collection'=>['amount'],'canteen_expenses'=>['amount'],'canteen_sales_detail'=>['Amt','PurAmt'],'canteen_purchase_detail'=>['Amt']] as $t=>$fields) foreach ($fields as $field) {
        if (!array_key_exists($field,$data[$t][0]??[])) continue;
        $range=[]; foreach ($data[$t] as $r) if (abs((float)$r[$field])>=1e12) $range[]=$r[$tables[$t]];
        $profile["$t.$field"]=['outside_decimal_target'=>$range];
    }
    $digests=[]; foreach ($data as $t=>$rows) $digests[$t]=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)); ksort($digests);
    $report=['version'=>1,'database'=>$name,'host'=>$host,'port'=>$port,'as_of'=>$clock->format('Y-m-d H:i:s'),'timezone'=>'Asia/Dhaka','snapshot_cutoff'=>$cutoff,'sales_cutoff'=>$saleCutoff,'sets'=>$sets,'profile'=>$profile,'private_state'=>$digests];
    foreach ($sets as $label=>$ids) echo "$label count=".count($ids).($label==='old_stock_detail' ? ' IDs selected by old_stock SlNo (exact detail IDs in private report)' : ' IDs='.json_encode($ids))."\n";
    if (isset($opt['report'])) private_write($opt['report'],$report);
    if (!isset($opt['apply'])) { $pdo->rollBack(); echo "PREVIEW ONLY; no changes\npeak_memory_bytes=".memory_get_peak_usage(true)."\n"; exit; }
    private_path($opt['apply']);
    $approved=json_decode(file_get_contents($opt['apply']),true,512,JSON_THROW_ON_ERROR);
    if ($approved!==$report) throw new RuntimeException('Stale/different preview; create and review a fresh report');
    if (!isset($opt['backup'])) throw new RuntimeException('Apply requires a new private --backup path');
    private_write($opt['backup'],['report'=>$report,'tables'=>$data]);
    $map=['orphan_sales_detail'=>'canteen_sales_detail','orphan_purchase_detail'=>'canteen_purchase_detail','old_stock_detail'=>'canteen_stock_detail','old_stock'=>'canteen_stock','empty_purchase'=>'canteen_purchase','old_empty_sales'=>'canteen_sales'];
    $deletes=0;
    foreach ($map as $label=>$t) foreach (array_chunk($sets[$label],500) as $ids) {
        $s=$pdo->prepare("DELETE FROM `$t` WHERE `{$tables[$t]}` IN (".implode(',',array_fill(0,count($ids),'?')).')'); $s->execute($ids); if ($s->rowCount()!==count($ids)) throw new RuntimeException('Deletion count mismatch');
        $deletes++; if (isset($opt['fail-after-delete']) && $deletes===(int)$opt['fail-after-delete']) throw new RuntimeException('Injected rollback verification failure');
    }
    // Prove every surviving row/field unchanged, including stock and Bengali Note.
    foreach ($map as $label=>$t) { $removed=array_fill_keys($sets[$label],true); $data[$t]=array_values(array_filter($data[$t],fn($r)=>!isset($removed[$r[$tables[$t]]]))); }
    foreach ($tables as $t=>$pk) if ($pdo->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetchAll()!==$data[$t]) throw new RuntimeException('Survivor reconciliation failed');
    $pdo->commit(); echo "APPLIED atomically; all surviving rows/fields unchanged across 13 tables\npeak_memory_bytes=".memory_get_peak_usage(true)."\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e instanceof PDOException ? "Cleanup database operation failed; rolled back (inspect private local configuration)\n" : $e->getMessage()."\n"); exit(1);
}
