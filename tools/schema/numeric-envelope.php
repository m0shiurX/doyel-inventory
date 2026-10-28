<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array($argv[1] ?? '', ['-h','--help'], true)) { echo "Usage: DOYEL_DB_NAME=doyel_* php tools/schema/numeric-envelope.php <cleaned-before-pages> <converted-pages>\nFixture-specific independent S3.3 envelope for cleanup as-of 2026-10-28.\n"; exit; }
require __DIR__ . '/../../lib/html.php';
$name=getenv('DOYEL_DB_NAME') ?: '';
if (!preg_match('/^doyel_[A-Za-z0-9_]+$/D',$name) || (getenv('DOYEL_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1' || (getenv('DOYEL_DB_PORT') ?: '33084') !== '33084') throw new RuntimeException('Only isolated local fixtures supported');
$before=realpath($argv[1] ?? '');$after=realpath($argv[2] ?? '');
if (!$before || !$after || !str_starts_with($before,'/private/tmp/doyel-fixtures/') || !str_starts_with($after,'/private/tmp/doyel-fixtures/')) throw new RuntimeException('Use private fixture page directories');
$p=new PDO("mysql:host=127.0.0.1;port=33084;dbname=$name",'root','doyel-local',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_STRINGIFY_FETCHES=>true]);
$pairs=[];
$add=function($label,$old,$new)use(&$pairs){$old=(string)$old;$new=fmt_numeric($new);if($old!==$new)$pairs[$label][$old.'=>'.$new]=true;};
$sumfn=function($table,$old)use($p){return $p->query('SELECT SUM('.($old?'CAST(amount AS FLOAT)':'amount').") FROM $table")->fetchColumn();};
$add('accounts',(float)$sumfn('canteen_collection',true)-(float)$sumfn('canteen_expenses',true),(float)$sumfn('canteen_collection',false)-(float)$sumfn('canteen_expenses',false));
$oldTotal=0;$newTotal=0;
foreach($p->query("SELECT HeadOfAccount,SUM(CAST(amount AS FLOAT)) AS oldSum,SUM(amount) AS newSum FROM canteen_collection WHERE Date BETWEEN '2016-01-01 00:00:00' AND '2026-10-25 23:59:59' GROUP BY HeadOfAccount ORDER BY HeadOfAccount") as $r){$add('report-cash-collection.full',$r['oldSum'],$r['newSum']);$oldTotal+=(float)$r['oldSum'];$newTotal+=(float)$r['newSum'];}
$add('report-cash-collection.full',$oldTotal,$newTotal);
foreach(['report-sales-name.full'=>'', 'report-sales-category.full'=>'i.ItemType', 'report-sales-supplier.full'=>'i.SupplierName', 'report-sales-user.full'=>'h.UserName'] as $label=>$group){
 $supplier=str_contains($label,'supplier');$user=str_contains($label,'user');
 $join=$supplier?'':' INNER JOIN canteen_sales h ON h.SalesID=d.SalesID';
 $condition=$supplier?'':'h.Post=1 AND ';
 if($user)$condition.="i.GenericName<>'Row Material' AND ";
 $date=$user?'h.Date':'d.Date';
 $groupSql=$group ? "$group AS grp," : "'' AS grp,";
 $query="SELECT $groupSql i.ItemID,i.SalesPrice,SUM(CAST(d.Qnty AS FLOAT)) AS oldQty,SUM(d.Qnty) AS newQty FROM canteen_sales_detail d INNER JOIN canteen_item i ON i.ItemID=d.ItemID $join WHERE $condition $date BETWEEN '2016-01-01 00:00:00' AND '2026-10-25 23:59:59' GROUP BY ".($group?"$group,":'')."i.ItemID,i.ItemName,i.SalesPrice ORDER BY ".($group?"$group,":'')."i.ItemName";
 $groups=[];
 foreach($p->query($query) as $r){
  $key=mb_strtolower(rtrim($r['grp']),'UTF-8');
  $groups[$key]??=['oq'=>0,'nq'=>0,'oa'=>0,'na'=>0];
  $oa=(float)$r['oldQty']*(float)$r['SalesPrice'];$na=(float)$r['newQty']*(float)$r['SalesPrice'];
  $add($label,$r['oldQty'],$r['newQty']);$add($label,$oa,$na);
  $groups[$key]['oq']+=(float)$r['oldQty'];$groups[$key]['nq']+=(float)$r['newQty'];$groups[$key]['oa']+=$oa;$groups[$key]['na']+=$na;
 }
 $total=['oq'=>0,'nq'=>0,'oa'=>0,'na'=>0];
 foreach($groups as $g){$add($label,$g['oq'],$g['nq']);$add($label,$g['oa'],$g['na']);foreach($total as $k=>&$v)$v+=$g[$k];unset($v);}
 if($supplier||$user){$add($label,$total['oq'],$total['nq']);$add($label,$total['oa'],$total['na']);}
}
$expected=['accounts'=>[10=>1],'report-cash-collection.full'=>[5776=>1,6910=>1],'report-sales-category.full'=>[550=>2,553=>2],'report-sales-name.full'=>[609=>2,619=>2],'report-sales-supplier.full'=>[610=>2,620=>2,621=>2],'report-sales-user.full'=>[1125=>2,1130=>2,2544=>2]];
$seen=[];$lines=0;$cells=0;
foreach(glob($before.'/*.txt') as $file){$label=basename($file,'.txt');$a=explode("\n",file_get_contents($file));$b=explode("\n",file_get_contents($after.'/'.basename($file)));if(count($a)!==count($b))throw new Exception('Line count changed');
 foreach($a as $i=>$x){$y=$b[$i];if($x===$y)continue;if(!isset($expected[$label][$i+1]))throw new Exception('Unexpected changed label/line');
  $re='/-?\d+(?:\.\d+)?/';preg_match_all($re,$x,$aa);preg_match_all($re,$y,$bb);
  if(preg_replace($re,'#',$x)!==preg_replace($re,'#',$y) || count($aa[0])!==count($bb[0]))throw new Exception('Nonnumeric text changed');
  $n=0;foreach($aa[0] as $j=>$v){$w=$bb[0][$j];if($v===$w)continue;if(!isset($pairs[$label][$v.'=>'.$w]) || abs((float)$v-(float)$w)>=0.01)throw new Exception('Numeric change not independently derived from original FLOAT and current DECIMAL aggregation');$n++;}
  if($n!==$expected[$label][$i+1])throw new Exception('Unexpected changed cell count');$seen[$label][$i+1]=$n;$lines++;$cells+=$n;
 }
}
ksort($seen);ksort($expected);if($seen!==$expected)throw new Exception('Missing expected differences');
echo "PASS: six exact labels, $lines exact lines, $cells numeric cells independently derived from FLOAT/DECIMAL SQL sums and report arithmetic; every other byte/status unchanged; every delta <0.01; no masks\n";
