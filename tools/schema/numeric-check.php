<?php
// Read-only local fixture checker. Private snapshots contain no public output data.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array($argv[1] ?? '', ['-h','--help'], true)) { echo "Usage: DOYEL_DB_NAME=doyel_* php tools/schema/numeric-check.php [--expect=converted] [--snapshot=/private/tmp/doyel-fixtures/new.json | --compare=private-baseline.json]\nCompare within one import/migration cycle; fresh resets can reassign snapshot IDs and reset credential hashes.\n"; exit; }
require_once __DIR__ . '/../../lib/html.php';
try {
    $options = getopt('', ['snapshot:', 'compare:', 'expect:']);
    $name = getenv('DOYEL_DB_NAME') ?: '';
    if ((getenv('DOYEL_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1'
        || (getenv('DOYEL_DB_PORT') ?: '33084') !== '33084'
        || !preg_match('/^doyel_[A-Za-z0-9_]+$/D', $name)) throw new RuntimeException('Only isolated local fixtures supported');
    $pdo = new PDO("mysql:host=127.0.0.1;port=33084;dbname=$name;charset=utf8mb4", getenv('DOYEL_DB_USER') ?: 'root', getenv('DOYEL_DB_PASS') ?: 'doyel-local', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>true]);
    $targets = json_decode('{"canteen_collection.amount":[14,2],"canteen_expenses.amount":[14,2],"canteen_item.PurPrice":[14,3],"canteen_item.SalesPrice":[14,3],"canteen_item.Qnty":[12,3],"canteen_purchase.Amount":[14,2],"canteen_purchase.Discount":[14,2],"canteen_purchase.Total":[14,2],"canteen_purchase_detail.Qnty":[12,3],"canteen_purchase_detail.QT":[12,3],"canteen_purchase_detail.Rate":[14,3],"canteen_purchase_detail.Amt":[14,2],"canteen_sales.Amount":[14,2],"canteen_sales.Discount":[14,2],"canteen_sales.Total":[14,2],"canteen_sales_detail.QT":[12,3],"canteen_sales_detail.Qnty":[12,3],"canteen_sales_detail.PurAmt":[14,2],"canteen_sales_detail.Rate":[14,3],"canteen_sales_detail.Amt":[14,2],"canteen_stock_detail.PurPrice":[14,3],"canteen_stock_detail.Qnty":[12,3]}', true, 512, JSON_THROW_ON_ERROR);
    $cols = $pdo->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll();
    $result = ['data'=>[], 'sums'=>[], 'dues'=>[], 'columns'=>$cols, 'indexes'=>$pdo->query('SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX')->fetchAll()];
    $tables = [];
    foreach ($cols as $c) {
        $key = $c['TABLE_NAME'].'.'.$c['COLUMN_NAME']; $t=$c['TABLE_NAME']; $n=$c['COLUMN_NAME'];
        $tables[$t][]=$n;
        if (!isset($targets[$key])) {
            if (in_array($c['DATA_TYPE'], ['float','double'])) throw new RuntimeException('Unprofiled approximate numeric column');
            continue;
        }
        [$precision,$scale]=$targets[$key];
        $v="CAST(CAST(`$n` AS CHAR) AS DECIMAL(40,12))";
        $bad=$pdo->query("SELECT COUNT(*) FROM `$t` WHERE ABS(`$n`) >= ".(10**($precision-$scale))." OR ABS($v-ROUND($v,$scale)) > 0.000001")->fetchColumn();
        $fraction=$pdo->query("SELECT COUNT(*) FROM `$t` WHERE $v <> TRUNCATE($v,0)")->fetchColumn();
        echo "$key fractional=$fraction unsupported=$bad\n";
        if ($bad) throw new RuntimeException('Range/precision preflight failed');
        if (($options['expect'] ?? '')==='converted' && $c['COLUMN_TYPE']!=="decimal($precision,$scale)") throw new RuntimeException('Incorrect numeric type');
        if ($scale===2 || in_array($n,['PurPrice','SalesPrice','Rate'])) {
            $sum=$pdo->query("SELECT CAST(COALESCE(SUM(`$n`),0) AS DECIMAL(40,8)) FROM `$t`")->fetchColumn();
            $cent=$pdo->query("SELECT CAST(ROUND(COALESCE(SUM(`$n`),0),2) AS DECIMAL(40,2)) FROM `$t`")->fetchColumn();
            $result['sums'][$key]=['raw'=>$sum,'cent'=>$cent];
        }
    }
    if (count($tables)!==13) throw new RuntimeException('Expected 13 tables');
    foreach ($tables as $t=>$fields) {
        $ctx=hash_init('sha256'); $count=0;
        foreach($pdo->query("SELECT * FROM `$t` ORDER BY ".implode(',',array_map(fn($f)=>"`$f`",$fields))) as $row) {
            foreach($row as $n=>&$v) if(isset($targets[$t.'.'.$n]) && $v!==null) $v=fmt_numeric($v);
            unset($v); hash_update($ctx,json_encode($row,JSON_THROW_ON_ERROR)."\n"); $count++;
        }
        $result['data'][$t]=['count'=>$count,'hash'=>hash_final($ctx)]; echo "$t rows=$count\n";
    }
    foreach ($pdo->query('SELECT c.MemberID, CAST(ROUND(COALESCE(s.due,0)-COALESCE(p.paid,0),2) AS DECIMAL(40,2)) AS due FROM canteen_customar c LEFT JOIN (SELECT CustomerName,SUM(Total) AS due FROM canteen_sales GROUP BY CustomerName) s ON s.CustomerName=c.Nickname LEFT JOIN (SELECT Description,SUM(amount) AS paid FROM canteen_collection GROUP BY Description) p ON p.Description=c.Nickname ORDER BY c.MemberID') as $r) $result['dues'][$r['MemberID']]=$r['due'];
    if(isset($options['snapshot'])) {
        $path=$options['snapshot'];
        if (!str_starts_with($path,'/private/tmp/doyel-fixtures/') || is_link($path) || file_exists($path)) throw new RuntimeException('Use a new private fixture snapshot path');
        $f=fopen($path,'x'); chmod($path,0600); fwrite($f,json_encode($result,JSON_THROW_ON_ERROR)); fclose($f); echo "Private baseline saved\n";
    }
    if(isset($options['compare'])) {
        $before=json_decode(file_get_contents($options['compare']),true,512,JSON_THROW_ON_ERROR);
        if($before['data']!==$result['data']) throw new RuntimeException('Normalized row bytes/counts changed');
        if($before['dues']!==$result['dues']) throw new RuntimeException('Per-customer dues changed to the cent');
        if($before['indexes']!==$result['indexes']) throw new RuntimeException('Indexes changed');
        foreach($result['sums'] as $key=>$sum) {
            $old=$before['sums'][$key]; $delta=(float)$sum['raw']-(float)$old['raw'];
            printf("%s sum_delta=%.8f cent_equal=%s\n",$key,$delta,$old['cent']===$sum['cent']?'yes':'NO');
            if($old['cent']!==$sum['cent']) throw new RuntimeException('SUM changed to the cent');
        }
        foreach($cols as $i=>$c) {
            $old=$before['columns'][$i]; $key=$c['TABLE_NAME'].'.'.$c['COLUMN_NAME'];
            $expected=$old;
            if(isset($targets[$key])) {[$p,$s]=$targets[$key];$expected['COLUMN_TYPE']="decimal($p,$s)";$expected['DATA_TYPE']='decimal';if($old['COLUMN_DEFAULT']!==null)$expected['COLUMN_DEFAULT']=number_format((float)$old['COLUMN_DEFAULT'],$s,'.','');}
            $optional=['conCode','Remarks','PR','SR','GenericName','ItemType','mobile','address']; $n=$c['COLUMN_NAME'];
            if($old['IS_NULLABLE']==='NO' && $old['COLUMN_DEFAULT']===null && !$old['EXTRA'] && (isset($targets[$key]) || (in_array($old['DATA_TYPE'],['int','tinyint']) && !(str_ends_with($n,'ID') || in_array($n,['BillNo','SlNo']))) || (in_array($n,$optional) && $old['DATA_TYPE']==='varchar'))) $expected['COLUMN_DEFAULT']=isset($targets[$key])?number_format(0,$targets[$key][1],'.',''):(in_array($old['DATA_TYPE'],['int','tinyint'])?'0':'');
            if($c!==$expected) throw new RuntimeException("Unexpected metadata/default change at $key");
        }
        echo 'PASS: exact normalized rows, all SUMs and '.count($result['dues'])." customer dues equal to cents; exact metadata/defaults/indexes\n";
    }
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
