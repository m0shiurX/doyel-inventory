<?php
// Local S3.4 fixture verification; all probes roll back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array($argv[1]??'', ['--help','-h'],true)) { echo "Usage: DOYEL_DB_NAME=doyel_* php -d memory_limit=512M tools/schema/constraints-check.php [--snapshot=/private/new.json | --compare=/private/before.json | --probe | --forms=/private/new-directory]\n"; exit; }
require __DIR__.'/../../db/cleanup/paths.php';
try {
    $opt=getopt('', ['snapshot:','compare:','probe','forms:']);
    $name=getenv('DOYEL_DB_NAME')?:'';
    if (!preg_match('/^doyel_[A-Za-z0-9_]+$/D',$name) || (getenv('DOYEL_DB_HOST')?:'127.0.0.1')!=='127.0.0.1' || (getenv('DOYEL_DB_PORT')?:'33084')!=='33084') throw new RuntimeException('Only isolated local fixture supported');
    $p=new PDO("mysql:host=127.0.0.1;port=33084;dbname=$name;charset=utf8mb4", 'root','doyel-local',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $tables=['canteen_ac_head'=>'headID','canteen_collection'=>'PaymentID','canteen_customar'=>'MemberID','canteen_expenses'=>'PaymentID','canteen_item'=>'ItemID','canteen_purchase'=>'BillNo','canteen_purchase_detail'=>'ID','canteen_sales'=>'SalesID','canteen_sales_detail'=>'ID','canteen_stock'=>'SlNo','canteen_stock_detail'=>'ID','canteen_supplier'=>'SupplierId','l_user'=>'user_id'];
    $assert=static function(bool $ok,string $why):void { if (!$ok) throw new RuntimeException($why); };
    $hash=fn($v)=>hash('sha256',json_encode($v,JSON_THROW_ON_ERROR));
    $state=['tables'=>[]];
    foreach ($tables as $t=>$pk) {
        $rows=$p->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetchAll();
        if ($t==='l_user') $rows=array_values(array_filter($rows,fn($r)=>!in_array($r['user_id'],['26','28'],true)));
        $indexes=$p->query("SHOW INDEX FROM `$t`")->fetchAll();
        foreach ($indexes as &$i) unset($i['Cardinality']); unset($i);
        $state['tables'][$t]=['count'=>count($rows),'digest'=>$hash($rows),'indexes'=>$indexes];
    }
    $state['columns']=$p->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION')->fetchAll();
    if (isset($opt['snapshot'])) {
        private_path($opt['snapshot']); umask(0077); $f=fopen($opt['snapshot'],'x'); if (!$f) throw new RuntimeException('Use new private output'); fwrite($f,json_encode($state,JSON_THROW_ON_ERROR)); fclose($f); echo "SNAPSHOT: all columns/indexes, every surviving row/field across 13 tables\n";
    }
    if (isset($opt['compare'])) {
        private_path($opt['compare']); $before=json_decode(file_get_contents($opt['compare']),true,512,JSON_THROW_ON_ERROR);
        $compare=$state;
        $compare['tables']['l_user']['indexes']=array_values(array_filter($compare['tables']['l_user']['indexes'],fn($i)=>$i['Key_name']!=='uq_l_user_username'));
        $assert($compare===$before,'Surviving rows/columns/indexes mismatch');
        $assert($p->query('SELECT COUNT(*) FROM l_user WHERE user_id IN (26,28)')->fetchColumn()==='0','Removed IDs remain');
        echo "PASS: all 13 tables/columns and every surviving field exact; IDs 26/28 absent; all 001 indexes reused\n";
    }
    if (isset($opt['forms'])) {
        require __DIR__.'/../scenarios/run.php';
        $out=$opt['forms']; private_path($out); umask(0077);
        if (file_exists($out) || !mkdir($out,0700)) throw new RuntimeException('Use a new private form output directory');
        $s=new Scenario('http://127.0.0.1:8134','modern',$out,'constraints'); $s->login();
        $user=$s->rows('SELECT UserName FROM l_user WHERE user_id=27')[0]['UserName'];
        $before=$hash($s->rows('SELECT * FROM l_user ORDER BY user_id'));
        foreach ([$user,strtoupper($user),$user.' '] as $v) {
            $r=$s->http('/list_of_user.php',['username'=>$v,'password'=>'__S34_SYNTHETIC_PASSWORD__','name'=>'__S34_SYNTHETIC_NAME__']);
            $assert(str_contains($r['body'],'That username already exists. Choose another username.'),'Missing clear duplicate username message');
            $assert($hash($s->rows('SELECT * FROM l_user ORDER BY user_id'))===$before,'Duplicate form request changed users');
        }
        unlink($s->cookie);
        echo "PASS: actual duplicate/case/trailing-space user forms show clear message, zero diagnostics and preserve all user fields\n";
    }
    if (isset($opt['probe'])) {
        $constraints=$p->query("SELECT CONSTRAINT_NAME,UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() ORDER BY CONSTRAINT_NAME")->fetchAll();
        $assert(array_column($constraints,'CONSTRAINT_NAME')===['fk_purchase_detail_bill','fk_purchase_detail_item','fk_sales_detail_item','fk_sales_detail_sale'],'Expected four foreign keys');
        foreach ($constraints as $c) $assert($c['UPDATE_RULE']==='RESTRICT' && $c['DELETE_RULE']==='RESTRICT','Rules must be RESTRICT');
        $reject=static function(string $sql,array $params,int $code) use($p,$assert):void {
            $p->beginTransaction(); $failed=false;
            try { $p->prepare($sql)->execute($params); } catch(PDOException $e) { $failed=($e->errorInfo[1]??null)===$code; }
            finally { $p->rollBack(); }
            $assert($failed,'Expected database rejection '.$code);
        };
        $user=$p->query('SELECT * FROM l_user WHERE user_id=27')->fetch();
        foreach ([$user['UserName'],strtoupper($user['UserName']),$user['UserName'].' '] as $v) $reject('UPDATE l_user SET UserName=? WHERE user_id=17',[$v],1062);
        $cols=array_values(array_filter(array_keys($user),fn($c)=>$c!=='user_id')); $insert='INSERT INTO l_user (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')';
        foreach ([$user['UserName'],strtoupper($user['UserName']),$user['UserName'].' '] as $v) { $copy=$user; $copy['UserName']=$v; $reject($insert,array_map(fn($c)=>$copy[$c],$cols),1062); }
        foreach ([['canteen_sales_detail','SalesID','canteen_sales','SalesID'],['canteen_purchase_detail','BillNo','canteen_purchase','BillNo'],['canteen_sales_detail','ItemID','canteen_item','ItemID'],['canteen_purchase_detail','ItemID','canteen_item','ItemID']] as [$t,$c,$parent,$pk]) {
            $row=$p->query("SELECT * FROM `$t` ORDER BY ID LIMIT 1")->fetch();
            $assert(!$p->query("SELECT COUNT(*) FROM `$parent` WHERE `$pk`=2147483647")->fetchColumn(),'Sentinel collision');
            $reject("UPDATE `$t` SET `$c`=2147483647 WHERE ID=?",[$row['ID']],1452);
            $row[$c]='2147483647'; $cols=array_values(array_filter(array_keys($row),fn($col)=>$col!=='ID'));
            $reject('INSERT INTO `'.$t.'` (`'.implode('`,`',$cols).'`) VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_map(fn($col)=>$row[$col],$cols),1452);
            $ref=$p->query("SELECT `$c` FROM `$t` ORDER BY ID LIMIT 1")->fetchColumn();
            $reject("DELETE FROM `$parent` WHERE `$pk`=?",[$ref],1451);
            $reject("UPDATE `$parent` SET `$pk`=2147483647 WHERE `$pk`=?",[$ref],1451);
        }
        echo "PASS: four RESTRICT FKs; each rejects invalid insert/update and referenced parent delete/update; unique rejects same/case/trailing-space usernames on insert/update; all probes rolled back\n";
    }
} catch(Throwable $e) { if(isset($p)&&$p->inTransaction())$p->rollBack(); fwrite(STDERR,$e instanceof PDOException?"Constraint check database operation failed\n":$e->getMessage()."\n"); exit(1); }
