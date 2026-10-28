<?php
// Synthetic local verification only. Never touch production or expose this over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array($argv[1] ?? '', ['-h','--help'], true)) { echo "Usage: DOYEL_DB_NAME=doyel_* php tools/schema/numeric-forms.php <new-private-output-directory>\nRequires a fresh 001,002,cleanup,003 local fixture served on 8133.\n"; exit; }
require_once __DIR__ . '/../scenarios/run.php';
try {
    umask(0077);
    $name = getenv('DOYEL_DB_NAME') ?: '';
    if (!preg_match('/^doyel_[A-Za-z0-9_]+$/D', $name)
        || (getenv('DOYEL_DB_HOST') ?: '127.0.0.1') !== '127.0.0.1'
        || (getenv('DOYEL_DB_PORT') ?: '33084') !== '33084') throw new RuntimeException('Only isolated local fixtures supported');
    $out=$argv[1] ?? '';
    if (!str_starts_with($out,'/private/tmp/doyel-fixtures/') || file_exists($out) || !mkdir($out,0700)) throw new RuntimeException('Use a new private output directory');
    $s=new Scenario('http://127.0.0.1:8133','modern',$out,'numeric');
    if ($s->value("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='canteen_item' AND COLUMN_NAME='Qnty'")!=='decimal(12,3)') throw new RuntimeException('003 required');
    $s->login();
    $s->db->exec("INSERT INTO canteen_item (ItemName,SupplierName,Qnty,PurPrice,SalesPrice) VALUES ('__S33_NUMERIC__','__S33_SUPPLIER__',10,0.333,1)");
    $id=(int)$s->db->lastInsertId();
    $stock=fn()=> $s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?',[$id]);
    $sale=fn($bill,$qty)=>$s->http('/Sales.php',['bill_no'=>$bill,'Item'=>'__S33_NUMERIC__','ItemID'=>$id,'qnty'=>$qty,'rate'=>'999','PurAmt'=>'999','amount'=>'999']);
    $purchase=fn($bill,$qty,$rate)=>$s->http('/purchase.php',['bill_no'=>$bill,'Item'=>'__S33_NUMERIC__','ItemID'=>$id,'qnty'=>$qty,'rate'=>$rate,'amount'=>'999']);
    $bill=$s->newBill('sale'); $sale($bill,'1.2345');
    $d=$s->rows('SELECT ID,Qnty,Rate,PurAmt,Amt FROM canteen_sales_detail WHERE SalesID=?',[$bill])[0];
    $s->check(array_values(array_diff_key($d,['ID'=>1]))===['1.235','1.000','0.41','1.24'] && $stock()==='8.765','fractional sale or tamper protection');
    $s->http('/Sales.php?'.http_build_query(['Bill'=>$bill,'id'=>$d['ID'],'qnty'=>'999','ItemID'=>0]));
    $s->check($stock()==='10.000','sale line reversal');
    $sale($bill,'1.0055');
    $r=characterization_http($s->base,characterization_prepare_post($s->base,['label'=>'header-overflow','method'=>'POST','path'=>'/Sales.php','body'=>http_build_query(['Bill'=>$bill,'hPost'=>'1','discount'=>'-999999999999.99','Nickname'=>'Select Member'])],$s->cookie),$s->cookie);
    $s->check($r['status']===400 && !characterization_errors($r['body']) && $s->value('SELECT Post FROM canteen_sales WHERE SalesID=?',[$bill])==='0','header total overflow rejects before posting');
    $s->http('/Sales.php?'.http_build_query(['Bill'=>$bill,'hPost'=>'1','discount'=>'0.005','Nickname'=>'Select Member','amount'=>'999','total'=>'999']));
    $s->check($s->rows('SELECT Amount,Discount,Total FROM canteen_sales WHERE SalesID=?',[$bill])[0]===['Amount'=>'1.01','Discount'=>'0.01','Total'=>'1.00'],'sale discount before total and server-owned amount');
    $s->http('/Unpost.php?d='.$bill); $s->http('/Unpost.php?d='.$bill);
    $s->check($stock()==='10.000' && !$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill]),'whole sale reversal exactly once');
    $bill=$s->newBill('sale'); $sale($bill,'-1.2345');
    $s->check($stock()==='11.235' && $s->value('SELECT Amt FROM canteen_sales_detail WHERE SalesID=?',[$bill])==='-1.24','legacy negative sale/return');
    $s->http('/Unpost.php?d='.$bill); $s->check($stock()==='10.000','negative sale reversal');
    $bill=$s->newBill('purchase'); $purchase($bill,'-1.2345','0.3335');
    $d=$s->rows('SELECT ID,Qnty,Rate,Amt FROM canteen_purchase_detail WHERE BillNo=?',[$bill])[0];
    $s->check($d['Qnty']==='1.235' && $d['Rate']==='0.334' && $d['Amt']==='0.41' && $stock()==='11.235','purchase input normalization');
    $s->check($s->value('SELECT PurPrice FROM canteen_item WHERE ItemID=?',[$id])==='0.334','purchase catalogue precision');
    $s->http('/SavePrestntStock.php');
    $snapshot=$s->value('SELECT MAX(SlNo) FROM canteen_stock');
    $s->check($s->rows('SELECT PurPrice,Qnty FROM canteen_stock_detail WHERE SlNo=? AND ItemID=?',[$snapshot,$id])[0]===['PurPrice'=>'0.334','Qnty'=>'11.235'],'snapshot precision');
    $s->http('/purchase.php?'.http_build_query(['Bill'=>$bill,'id'=>$d['ID'],'qnty'=>'999','ItemID'=>0]));
    $s->check($stock()==='10.000','purchase line reversal');
    $purchase($bill,'3','0.3334');
    $s->http('/purchase.php?'.http_build_query(['Bill'=>$bill,'hPost'=>'1','discount'=>'0.005','supplier'=>'__S33_SUPPLIER__','amount'=>'999','total'=>'999']));
    $s->check($s->rows('SELECT Amount,Discount,Total FROM canteen_purchase WHERE BillNo=?',[$bill])[0]===['Amount'=>'1.00','Discount'=>'0.01','Total'=>'0.99'],'purchase discount boundary');
    $s->http('/Unpost.php?dl='.$bill);$s->http('/Unpost.php?dl='.$bill);
    $s->check($stock()==='10.000','whole purchase reversal exactly once');
    $s->http('/add_item.php?ID='.$id,['Item'=>'__S33_NUMERIC__','supplier'=>'__S33_SUPPLIER__','GenericName'=>'Synthetic','ItemType'=>'X','PurPrice'=>'0.3335','SalesPrice'=>'1.2345','totalQnty'=>'10.0005','MaximumQuantity'=>'50','MinimumQuantity'=>'1']);
    $s->check($s->rows('SELECT Qnty,PurPrice,SalesPrice FROM canteen_item WHERE ItemID=?',[$id])[0]===['Qnty'=>'10.001','PurPrice'=>'0.334','SalesPrice'=>'1.235'],'catalogue form precision');
    foreach(['CashCollection.php'=>'canteen_collection','Expenses.php'=>'canteen_expenses'] as $page=>$table) {
        $before=(int)$s->value("SELECT MAX(PaymentID) FROM $table");
        $s->http('/'.$page,['amount'=>'-0.005','Description'=>'__S33_NUMERIC__','HeadOfAccount'=>'Synthetic','Remarks'=>'boundary']);
        $s->check($s->value("SELECT amount FROM $table WHERE PaymentID>?",[$before])==='-0.01','cash input exact negative cent');
    }
    $bill=$s->newBill('sale');
    foreach(['1000000000','999999999.9995'] as $qty) {
        $r=characterization_http($s->base,characterization_prepare_post($s->base,['label'=>'overflow','method'=>'POST','path'=>'/Sales.php','body'=>http_build_query(['bill_no'=>$bill,'Item'=>'__S33_NUMERIC__','ItemID'=>$id,'qnty'=>$qty])],$s->cookie),$s->cookie);
        $s->check($r['status']===400 && !characterization_errors($r['body']),'input overflow clean rejection');
    }
    $s->db->exec("UPDATE canteen_item SET SalesPrice=99999999999.999 WHERE ItemID=$id");
    $r=characterization_http($s->base,characterization_prepare_post($s->base,['label'=>'product-overflow','method'=>'POST','path'=>'/Sales.php','body'=>http_build_query(['bill_no'=>$bill,'Item'=>'__S33_NUMERIC__','ItemID'=>$id,'qnty'=>'20'])],$s->cookie),$s->cookie);
    $s->check($r['status']===400 && !characterization_errors($r['body']) && $stock()==='10.001' && !$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill]),'extension overflow preserves entire bill and stock');
    // Force strict DECIMAL overflow after the stock UPDATE: the real transaction must roll back.
    $s->db->exec("UPDATE canteen_item SET SalesPrice=1 WHERE ItemID=$id");
    $s->db->exec("CREATE TRIGGER s33_overflow BEFORE INSERT ON canteen_sales_detail FOR EACH ROW SET NEW.Amt=1000000000000");
    try {
        $r=characterization_http($s->base,characterization_prepare_post($s->base,['label'=>'forced-rollback','method'=>'POST','path'=>'/Sales.php','body'=>http_build_query(['bill_no'=>$bill,'Item'=>'__S33_NUMERIC__','ItemID'=>$id,'qnty'=>'1.2345'])],$s->cookie),$s->cookie);
        file_put_contents($out.'/forced-rollback.html.private',$r['body']);
        $s->check(str_contains($r['body'],'Out of range value') && $stock()==='10.001' && !$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill]),'strict overflow mid-transaction rollback');
    } finally { $s->db->exec('DROP TRIGGER s33_overflow'); }
    unlink($s->cookie);
    echo "PASS: fractional sale/purchase add/remove; tamper resistance; whole-bill reversal exactly once; negative returns; unit-price/catalogue/snapshot scale; half-cent discounts and cash; clean input/product overflow; actual post-stock strict-overflow rollback\n";
    echo "Forced server overflow retains the pre-existing development fatal response (S4.3 hand-off); normal and rejected-input requests have zero diagnostics.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
