<?php
// Q3 maintenance only: stop writers and sessions until cleanup/004 are complete.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/paths.php';
function users_private_write(string $path, array $data): void {
    private_path($path); umask(0077); $f = fopen($path, 'x');
    if (!$f) throw new RuntimeException('Use a new private output path');
    try {
        $bytes = json_encode($data, JSON_THROW_ON_ERROR); $offset = 0;
        while ($offset < strlen($bytes)) { $n = fwrite($f, substr($bytes, $offset)); if (!$n) throw new RuntimeException('Backup write failed'); $offset += $n; }
        if (!fflush($f) || !fsync($f)) throw new RuntimeException('Backup flush failed');
    } finally { fclose($f); }
}
function users_private_read(string $path): array {
    private_path($path);
    return json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}
try {
    $opt = getopt('h', ['help','report:','apply:','backup:','restore:','allow-production','fail-after-delete']);
    if (isset($opt['help']) || isset($opt['h'])) {
        echo "Usage: php -d memory_limit=512M db/cleanup/users.php --report=/private/new-preview.json\nApply: --apply=/private/preview.json --backup=/private/new-backup.json\nRestore after 004 down: --restore=/private/backup.json\nStop writers for all steps. Only Q3 IDs 26/28 are removed; 27/29 stay byte-exact.\nDefault is preview; production requires explicit --allow-production.\n"; exit;
    }
    if (isset($opt['restore']) && (isset($opt['apply']) || isset($opt['report']) || isset($opt['backup']))) throw new RuntimeException('Restore cannot be combined with preview/apply');
    $name = getenv('DOYEL_DB_NAME') ?: ''; $host = getenv('DOYEL_DB_HOST') ?: '127.0.0.1'; $port = getenv('DOYEL_DB_PORT') ?: '33084';
    $local = $host === '127.0.0.1' && $port === '33084' && preg_match('/^doyel_[A-Za-z0-9_]+$/D', $name);
    if (!$local && (isset($opt['fail-after-delete']) || !isset($opt['allow-production']))) throw new RuntimeException('Only isolated local fixtures allowed without --allow-production; fault injection is local only');
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $name) || !ctype_digit($port) || !preg_match('/^[A-Za-z0-9.:-]+$/D', $host)) throw new RuntimeException('Invalid DB configuration');
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", getenv('DOYEL_DB_USER') ?: 'root', getenv('DOYEL_DB_PASS') ?: 'doyel-local', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false]);
    $tables = ['canteen_ac_head'=>'headID','canteen_collection'=>'PaymentID','canteen_customar'=>'MemberID','canteen_expenses'=>'PaymentID','canteen_item'=>'ItemID','canteen_purchase'=>'BillNo','canteen_purchase_detail'=>'ID','canteen_sales'=>'SalesID','canteen_sales_detail'=>'ID','canteen_stock'=>'SlNo','canteen_stock_detail'=>'ID','canteen_supplier'=>'SupplierId','l_user'=>'user_id'];
    $engines = $pdo->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (array_keys($engines) !== array_keys($tables) || array_unique(array_values($engines)) !== ['InnoDB']) throw new RuntimeException('Expected 13 InnoDB tables; review changed schema');
    // Fail closed on new user-like columns or foreign keys: do not guess ID reassignment.
    $columns = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll();
    $userColumns = array_values(array_filter($columns, fn($c)=>preg_match('/user/i',$c['COLUMN_NAME'])));
    $expected = ['canteen_collection.UserName','canteen_expenses.UserName','canteen_purchase.UserName','canteen_sales.UserName','l_user.user_id','l_user.UserName','l_user.userAtho'];
    $actual = array_map(fn($c)=>$c['TABLE_NAME'].'.'.$c['COLUMN_NAME'], $userColumns); sort($expected); sort($actual);
    if ($actual !== $expected) throw new RuntimeException('Unexpected possible user ID/name reference; inventory and review before dedup');
    $idRefs = $pdo->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='l_user'")->fetchAll();
    if ($idRefs) throw new RuntimeException('Actual user ID foreign keys require reviewed reassignment; refusing deletion');
    foreach ($userColumns as $c) if ($c['COLUMN_NAME']==='UserName' && $c['COLLATION_NAME']!=='utf8mb4_unicode_ci') throw new RuntimeException('Expected case-insensitive username collation');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $pdo->beginTransaction();
    // Maintenance snapshot: lock all PK records and gaps, not only the removed rows.
    $data = [];
    foreach ($tables as $t=>$pk) $data[$t]=$pdo->query("SELECT * FROM `$t` ORDER BY `$pk` FOR UPDATE")->fetchAll();
    $digest = fn($rows)=>hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
    $state = []; foreach ($data as $t=>$rows) $state[$t]=['count'=>count($rows),'digest'=>$digest($rows)];
    $identity = ['database'=>$name,'host'=>$host,'port'=>$port];
    if (isset($opt['restore'])) {
        $backup=users_private_read($opt['restore']);
        if (($backup['version']??null)!==1 || ($backup['identity']??null)!==$identity || ($backup['after']??null)!==$state) throw new RuntimeException('Backup database/state mismatch; restore refused');
        if ($pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='l_user' AND INDEX_NAME='uq_l_user_username'")->fetchColumn() != 0) throw new RuntimeException('Run 004 down before restoring duplicates');
        if (array_column($backup['removed']??[],'user_id')!==['26','28']) throw new RuntimeException('Unexpected backup rows');
        $expectedKeys=array_column(array_values(array_filter($columns,fn($c)=>$c['TABLE_NAME']==='l_user')),'COLUMN_NAME');
        foreach ($backup['removed'] as $row) if (array_keys($row)!==$expectedKeys) throw new RuntimeException('Backup row columns mismatch; restore refused before writes');
        foreach ($backup['removed'] as $row) {
            $keys=array_keys($row); $sql='INSERT INTO l_user (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')';
            $pdo->prepare($sql)->execute(array_values($row));
        }
        foreach ($tables as $t=>$pk) { $rows=$pdo->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetchAll(); if (['count'=>count($rows),'digest'=>$digest($rows)]!==$backup['before'][$t]) throw new RuntimeException('Restore reconciliation failed'); }
        $pdo->commit(); echo "RESTORED IDs=[26,28]; all 13 tables exactly match private pre-delete state\n"; exit;
    }
    $groups=$pdo->query('SELECT GROUP_CONCAT(user_id ORDER BY user_id) AS ids FROM l_user GROUP BY UserName HAVING COUNT(*)>1 ORDER BY MIN(user_id)')->fetchAll(PDO::FETCH_COLUMN);
    if ($groups!==['26,27','28,29'] && $groups!==[]) throw new RuntimeException('Unsupported duplicate group; Q3 authorizes only IDs 26/27 and 28/29');
    $removed=[]; $kept=[]; foreach ($data['l_user'] as $row) { if (in_array($row['user_id'],['26','28'],true)) $removed[]=$row; if (in_array($row['user_id'],['27','29'],true)) $kept[]=$row; }
    if (count($kept)!==2 || ($groups===[] && $removed!==[]) || ($groups!==[] && count($removed)!==2)) throw new RuntimeException('Approved pair IDs do not match duplicate identities');
    $dangling=[];
    foreach ([['canteen_sales_detail','SalesID','canteen_sales','SalesID'],['canteen_purchase_detail','BillNo','canteen_purchase','BillNo'],['canteen_sales_detail','ItemID','canteen_item','ItemID'],['canteen_purchase_detail','ItemID','canteen_item','ItemID']] as [$t,$c,$p,$pk]) {
        $ids=$pdo->query("SELECT d.ID FROM `$t` d LEFT JOIN `$p` p ON p.`$pk`=d.`$c` WHERE p.`$pk` IS NULL ORDER BY d.ID")->fetchAll(PDO::FETCH_COLUMN);
        $dangling["$t.$c"]=$ids;
        if ($ids) throw new RuntimeException('Dangling detail references; apply reviewed S3.2 cleanup before dedup');
    }
    // Private report carries historical primary keys, never name values. SQL equality
    // is the existing collation; BINARY only measures old spelling variants.
    $history=[];
    foreach (['27','29'] as $id) foreach (['canteen_sales','canteen_purchase','canteen_collection','canteen_expenses'] as $t) {
        $pk=$tables[$t];
        $q=$pdo->prepare("SELECT h.`$pk` AS id, BINARY h.UserName=BINARY u.UserName AS exact_spelling FROM `$t` h JOIN l_user u ON h.UserName=u.UserName WHERE u.user_id=? ORDER BY h.`$pk`"); $q->execute([$id]);
        $history[$id][$t]=$q->fetchAll();
    }
    $report=['version'=>1,'identity'=>$identity,'remove'=>array_column($removed,'user_id'),'keep'=>['27','29'],'duplicate_groups'=>$groups,'user_columns'=>$userColumns,'user_id_foreign_keys'=>$idRefs,'dangling'=>$dangling,'history'=>$history,'private_state'=>$state,'private_kept_digest'=>$digest($kept)];
    echo 'remove IDs='.json_encode($report['remove']).' keep IDs=[27,29]; actual ID foreign keys=0; dangling detail references=0'."\n";
    foreach ($history as $id=>$kinds) foreach ($kinds as $t=>$rows) echo "keep ID=$id $t attribution_count=".count($rows).' case_variant_count='.count(array_filter($rows,fn($r)=>$r['exact_spelling']==='0'))."\n";
    if (isset($opt['report'])) users_private_write($opt['report'],$report);
    if (!isset($opt['apply'])) { $pdo->rollBack(); echo "PREVIEW ONLY; no changes\n"; exit; }
    if (users_private_read($opt['apply'])!==$report) throw new RuntimeException('Stale/different preview; create and review a fresh report');
    if (!$removed) { $pdo->rollBack(); echo "ALREADY CLEAN; no deletion\n"; exit; }
    if (!isset($opt['backup'])) throw new RuntimeException('Apply requires a new private backup');
    $afterData=$data; $afterData['l_user']=array_values(array_filter($data['l_user'],fn($r)=>!in_array($r['user_id'],['26','28'],true)));
    $after=[]; foreach ($afterData as $t=>$rows) $after[$t]=['count'=>count($rows),'digest'=>$digest($rows)];
    users_private_write($opt['backup'],['version'=>1,'identity'=>$identity,'before'=>$state,'after'=>$after,'removed'=>$removed,'report'=>$report]);
    if ($pdo->exec('DELETE FROM l_user WHERE user_id IN (26,28)')!==2) throw new RuntimeException('Deletion count mismatch');
    if (isset($opt['fail-after-delete'])) throw new RuntimeException('Injected rollback verification failure');
    foreach ($tables as $t=>$pk) if ($pdo->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetchAll()!==$afterData[$t]) throw new RuntimeException('Surviving rows/fields changed');
    $pdo->commit(); echo "APPLIED atomically IDs=[26,28]; every surviving field and all history unchanged across 13 tables\n";
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e instanceof PDOException ? "User cleanup database operation failed; transaction rolled back\n" : $e->getMessage()."\n"); exit(1);
}
