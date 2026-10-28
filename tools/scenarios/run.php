<?php
// Fixture and profiling tools must never run through the public document root.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/** Real HTTP write flows. All SQL here reads/asserts disposable fixture state. */
require __DIR__ . '/../characterize/common.php';

final class Scenario
{
    public PDO $db;
    public array $lines = [];
    public string $cookie;
    private int $step = 0;
    private array $numeric = [];
    public function __construct(public string $base, public string $target, public string $out, public string $name)
    {
        $port = $target === 'legacy' ? 33057 : 33084;
        $dbname = getenv('DOYEL_DB_NAME') ?: 'software';
        if (!preg_match('/^(software|doyel_[A-Za-z0-9_]+)$/D', $dbname)) throw new RuntimeException('Invalid fixture database name');
        $this->db = new PDO("mysql:host=127.0.0.1;port=$port;dbname=$dbname;charset=utf8mb4", 'root', 'doyel-local', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_STRINGIFY_FETCHES => true]);
        $this->cookie = $out . '/' . $name . '.cookies.private';
    }
    public function rows(string $sql, array $params = []): array
    { $q = $this->db->prepare($sql); $q->execute($params); return $q->fetchAll(PDO::FETCH_ASSOC); }
    public function value(string $sql, array $params = []): mixed
    { $q = $this->db->prepare($sql); $q->execute($params); return $q->fetchColumn(); }
    public function check(bool $condition, string $message): void
    { if (!$condition) throw new RuntimeException($this->name . ': ' . $message); }
    public function http(string $path, array $post = [], string $method = ''): array
    {
        if ($this->target === 'modern') {
            $route = parse_url($path, PHP_URL_PATH);
            parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $query);
            $billAction = in_array($route, ['/Sales.php','/purchase.php'], true) && (isset($query['NBill']) || isset($query['hPost']) || isset($query['id']));
            $adminAction = $route === '/Unpost.php' && (bool)array_intersect(array_keys($query), ['p','s','d','dl']);
            $delete = in_array($route, ['/CashCollection.php','/Expenses.php','/remove_payment.php'], true) && isset($query['d']);
            if ($billAction || $adminAction || $delete) { $post = $query + $post; $path = $route; $method = 'POST'; }
            if (in_array($route, ['/SavePrestntStock.php','/logout.php'], true)) $method = 'POST';
        }
        $method = $method ?: ($post ? 'POST' : 'GET');
        if ($this->target === 'modern' && $method === 'POST') {
            $request = characterization_prepare_post($this->base, ['label'=>'form', 'method'=>'POST', 'path'=>$path, 'body'=>http_build_query($post)], $this->cookie, $route === '/AddSupplierName.php' ? '/Supplier.php' : ($route === '/remove_payment.php' ? '/Payment.php' : $path));
            parse_str($request['body'], $post);
        }
        $r = characterization_http($this->base, ['label' => $this->name . '-step-' . ++$this->step, 'method' => $method, 'path' => $path, 'body' => $post ? http_build_query($post) : ''], $this->cookie);
        if ($this->target === 'modern' && str_contains($r['body'], '<title>Confirm deletion</title>')) {
            // Submit the server-rendered confirmation, including its actual fields.
            $doc = new DOMDocument(); @$doc->loadHTML($r['body']); $confirmed = [];
            foreach ($doc->getElementsByTagName('input') as $input) $confirmed[$input->getAttribute('name')] = $input->getAttribute('value');
            $confirmed['confirm_delete'] = 'yes';
            $r = characterization_http($this->base, ['label'=>'confirm-delete', 'method'=>'POST', 'path'=>parse_url($path, PHP_URL_PATH), 'body'=>http_build_query($confirmed)], $this->cookie);
        }
        file_put_contents($this->out . '/' . $this->name . '-' . $this->step . '.html.private', $r['body']);
        $this->check($r['status'] >= 200 && $r['status'] < 400, 'unexpected HTTP status at step ' . $this->step);
        $this->check(!preg_match('/(?:<b>)?(?:Fatal error|Parse error)(?:<\/b>)?:/i', $r['body']), 'fatal PHP error at step ' . $this->step);
        if ($this->target === 'modern') $this->check(!characterization_errors($r['body']), 'modern PHP diagnostic at step ' . $this->step);
        return $r;
    }
    public function login(string $role = 'admin', ?string $password = null, ?string $user = null): void
    {
        $this->http('/logout.php');
        $r = $this->http('/login.php', ['username' => $user ?: ($role === 'admin' ? 'rayhan' : 'jems'), 'password' => $password ?: ($role === 'admin' ? 'admin-test' : 'user-test')]);
        $this->check($r['status'] === 302, 'fixture login did not redirect');
        // A protected dashboard confirms this is an authenticated session rather
        // than merely a failed-login redirect.
        $this->check($this->http('/index.php')['status'] === 200, 'fixture login did not authenticate');
    }
    public function emit(string $label, mixed $data): void
    { $this->lines[] = $label . "\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
    public function numericRows(array $rows, array $columns): array
    {
        foreach ($rows as &$row) foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if (is_string($value) && preg_match('/^-?[0-9]+\.[0-9]+$/D', $value)) $row[$column] = rtrim(rtrim($value, '0'), '.');
        }
        unset($row); return $rows;
    }
    public function dump(string $table, array $columns, string $where, array $params = [], string $order = '', bool $maskPassword = false, string $label = ''): void
    {
        $order = $order ?: $columns[0];
        $rows = $this->rows('SELECT ' . implode(',', $columns) . " FROM $table WHERE $where ORDER BY $order", $params);
        if (!isset($this->numeric[$table])) {
            $this->numeric[$table] = [];
             foreach ($this->rows("SHOW COLUMNS FROM $table") as $column) {
                if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real)\b/i', $column['Type'])) $this->numeric[$table][] = $column['Field'];
            }
        }
        $rows = $this->numericRows($rows, $this->numeric[$table]);
        foreach ($rows as &$row) foreach ($row as $key => &$value) {
            if ($value !== null && in_array($key, ['Date', 'm_date'], true)) $value = '<ts>';
            if ($maskPassword && $key === 'password') $value = '<password-verified>';
        }
        unset($row, $value);
        $this->emit($label ?: $table, $rows);
    }
    public function items(int $count = 2): array
    {
        $rows = $this->rows("SELECT ItemID,ItemName,Qnty,PurPrice,SalesPrice FROM canteen_item WHERE D=0 AND Qnty>=5 AND SalesPrice>0 ORDER BY ItemID LIMIT $count");
        $this->check(count($rows) === $count, 'fixture stock items missing'); return $rows;
    }
    public function customer(): array
    { $rows = $this->rows('SELECT MemberID,Nickname FROM canteen_customar ORDER BY MemberID LIMIT 1'); $this->check((bool)$rows, 'fixture customer missing'); return $rows[0]; }
    public function newBill(string $kind): int
    {
        $table = $kind === 'sale' ? 'canteen_sales' : 'canteen_purchase'; $key = $kind === 'sale' ? 'SalesID' : 'BillNo'; $page = $kind === 'sale' ? 'Sales.php' : 'purchase.php';
        $before = (int)$this->value("SELECT MAX($key) FROM $table");
        $this->http('/' . $page . '?NBill=add_new');
        $id = (int)$this->value("SELECT MAX($key) FROM $table");
        $this->check($id > $before, 'new bill was not inserted'); return $id;
    }
    public function addSale(int $bill, array $item, float $quantity): void
    {
        $this->http('/Sales.php', ['bill_no' => $bill, 'Item' => $item['ItemName'], 'ItemID' => $item['ItemID'], 'qnty' => $quantity, 'rate' => $item['SalesPrice'], 'PurAmt' => $item['PurPrice'], 'amount' => $quantity * (float)$item['SalesPrice'], 'Member' => 'Select Member']);
    }
    public function billDump(string $kind, int $id, string $label = ''): void
    {
        if ($kind === 'sale') {
            $this->dump('canteen_sales', ['SalesID','UserName','Amount','Discount','Total','RegNo','SR','Date','Post','Note','Credit','CustomerName'], 'SalesID=?', [$id], '', false, $label . 'sales');
            $this->dump('canteen_sales_detail', ['ID','SalesID','Date','ItemID','QT','Qnty','PurAmt','Rate','Amt'], 'SalesID=?', [$id], '', false, $label . 'sale-lines');
        } else {
            $this->dump('canteen_purchase', ['BillNo','PR','UserName','Supplier','Amount','Discount','Total','Date','Post'], 'BillNo=?', [$id], '', false, $label . 'purchase');
            $this->dump('canteen_purchase_detail', ['ID','BillNo','Date','ItemID','Qnty','QT','Rate','Amt'], 'BillNo=?', [$id], '', false, $label . 'purchase-lines');
        }
    }
    public function stock(array $ids, string $label = 'stock'): void
    { $this->dump('canteen_item', ['ItemID','Qnty','PurPrice','SalesPrice'], 'ItemID IN (' . implode(',', array_fill(0,count($ids),'?')) . ')', $ids, '', false, $label); }
    public function collections(int $after, string $label = ''): void
    { $this->dump('canteen_collection', ['PaymentID','UserName','Date','Description','HeadOfAccount','amount','amt','Remarks'], 'PaymentID>?', [$after], '', false, $label ?: 'collections'); }
    public function finish(): void
    {
        file_put_contents($this->out . '/' . $this->name . '.txt', implode("\n", $this->lines) . "\n");
        if (file_exists($this->cookie)) unlink($this->cookie);
        printf("%s completed (%d HTTP steps)\n", $this->name, $this->step);
    }
}
function run_scenario(Scenario $s, int $number): void
{
    $s->login();
    switch ($number) {
    case 1:
        [$a,$b] = $s->items(); $bill = $s->newBill('sale');
        $suggestions = $s->http('/dictionary/SalesItem.php');
        $s->check(str_contains($suggestions['body'], $a['ItemName']), 'autocomplete did not contain selected item');
        $lookup = $s->http('/dictionary/select.php?q=' . rawurlencode($a['ItemName']));
        $s->check(str_contains($lookup['body'], $a['ItemID'] . '/'), 'item lookup protocol missing');
        $s->addSale($bill,$a,2); $s->addSale($bill,$b,1);
        $detail = $s->rows('SELECT ID,ItemID,Qnty FROM canteen_sales_detail WHERE SalesID=? ORDER BY ID DESC LIMIT 1',[$bill])[0];
        $s->http('/Sales.php?' . http_build_query(['Bill'=>$bill,'id'=>$detail['ID'],'ItemID'=>$detail['ItemID'],'qnty'=>$detail['Qnty']]));
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill])===1,'line removal failed');
        $s->addSale($bill,$b,1);
        $total = (float)$s->value('SELECT SUM(Amt) FROM canteen_sales_detail WHERE SalesID=?',[$bill]);
        $s->http('/Sales.php?' . http_build_query(['Bill'=>$bill,'hPost'=>'YES','amount2'=>$total,'discount'=>1,'total'=>$total-1,'Nickname'=>$s->customer()['Nickname'],'Note'=>'scenario sale']));
        $s->check((int)$s->value('SELECT Post FROM canteen_sales WHERE SalesID=?',[$bill])===1,'sale post failed');
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill])===2,'sale final line count incorrect');
        $s->check((float)$s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?',[$a['ItemID']])===(float)$a['Qnty']-2,'sale stock decrement incorrect');
        $s->check((float)$s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?',[$b['ItemID']])===(float)$b['Qnty']-1,'removed/readded stock incorrect');
        $s->billDump('sale',$bill); $s->stock([$a['ItemID'],$b['ItemID']]); break;
    case 2:
        $a=$s->items(1)[0]; $bill=$s->newBill('sale'); $s->addSale($bill,$a,(float)$a['Qnty']+1);
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?',[$bill])===0,'oversell was not rejected');
        $s->check((float)$s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?',[$a['ItemID']])===(float)$a['Qnty'],'oversell changed stock');
        $s->billDump('sale',$bill); $s->stock([$a['ItemID']]); break;
    case 3:
        $a=$s->items(1)[0]; $bill=$s->newBill('purchase'); $rate=(float)$a['PurPrice']+2;
        $supplier=$s->value('SELECT SupplierName FROM canteen_supplier WHERE D=0 ORDER BY SupplierId LIMIT 1');
        $s->http('/purchase.php',['bill_no'=>$bill,'Item'=>$a['ItemName'],'ItemID'=>$a['ItemID'],'qnty'=>3,'rate'=>$rate,'amount'=>3*$rate,'supplier'=>$supplier]);
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_purchase_detail WHERE BillNo=?',[$bill])===1,'purchase line not added');
        $s->http('/purchase.php?' . http_build_query(['Bill'=>$bill,'hPost'=>'YES','supplier'=>$supplier,'amount2'=>3*$rate,'discount'=>1,'total'=>3*$rate-1]));
        $s->check((int)$s->value('SELECT Post FROM canteen_purchase WHERE BillNo=?',[$bill])===1,'purchase post failed');
        $s->check((float)$s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?',[$a['ItemID']])===(float)$a['Qnty']+3,'purchase stock increment incorrect');
        $s->check((float)$s->value('SELECT PurPrice FROM canteen_item WHERE ItemID=?',[$a['ItemID']])===$rate,'purchase price not updated');
        $s->billDump('purchase',$bill); $s->stock([$a['ItemID']]); break;
    case 4:
        $before=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_collection'); $customer=$s->customer();
        $path='/Payment.php?' . http_build_query(['ID'=>$customer['MemberID'],'Name'=>$customer['Nickname']]);
        $s->http($path,['amount'=>-11,'Remarks'=>'scenario admin collection']); $admin=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_collection');
        $s->check($admin>$before,'admin collection not inserted');
        $s->login('user'); $s->http($path,['amount'=>-7,'Remarks'=>'scenario user collection']); $user=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_collection');
        $s->check($user>$admin,'nonadmin collection not inserted');
        $s->check((float)$s->value('SELECT amount FROM canteen_collection WHERE PaymentID=?',[$admin])===-11.0,'admin negative amount not retained');
        $s->check((float)$s->value('SELECT amount FROM canteen_collection WHERE PaymentID=?',[$user])===7.0,'nonadmin negative amount not made absolute');
        $s->collections($before,'before-removals');
        $s->http('/remove_payment.php?d=' . $admin); $s->collections($before,'after-nonadmin-removal');
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_collection WHERE PaymentID=?',[$admin])===1,'nonadmin removed payment');
        $s->login(); $s->http('/remove_payment.php?d=' . $admin); $s->collections($before,'after-admin-removal');
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_collection WHERE PaymentID=?',[$admin])===0,'admin removal failed'); break;
    case 5:
        $headBefore=(int)$s->value('SELECT MAX(headID) FROM canteen_ac_head'); $colBefore=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_collection'); $expBefore=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_expenses');
        $s->http('/HeadOfAccount.php',['HeadOfAccount'=>'Scenario Income','conCode'=>'Income']);
        $head=(int)$s->value('SELECT MAX(headID) FROM canteen_ac_head'); $s->check($head>$headBefore,'account head not added');
        $s->http('/HeadOfAccount.php?headID='.$head,['HeadOfAccount'=>'Scenario Income Edited','conCode'=>'Income']);
        $s->http('/CashCollection.php',['Description'=>'Scenario cash receipt','HeadOfAccount'=>'Scenario Income Edited','amount'=>13,'Remarks'=>'scenario cash']);
        $collection=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_collection'); $s->check($collection>$colBefore,'cash collection not inserted');
        $expenseHead=$s->value("SELECT HeadOfAccount FROM canteen_ac_head WHERE conCode='Expense' ORDER BY headID LIMIT 1");
        $s->http('/Expenses.php',['Description'=>'Scenario cash expense','HeadOfAccount'=>$expenseHead,'amount'=>9,'Remarks'=>'scenario expense']);
        $expense=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_expenses'); $s->check($expense>$expBefore,'expense not inserted');
        $s->dump('canteen_ac_head',['headID','HeadOfAccount','conCode','D'],'headID>?',[$headBefore]);
        $s->collections($colBefore,'before-cash-delete');
        $s->dump('canteen_expenses',['PaymentID','Date','UserName','Description','HeadOfAccount','amt','amount','Remarks'],'PaymentID>?',[$expBefore], '', false, 'before-expense-delete');
        $s->http('/CashCollection.php?d='.$collection); $s->http('/Expenses.php?d='.$expense);
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_collection WHERE PaymentID=?',[$collection])===0,'cash delete failed');
        $s->check((int)$s->value('SELECT COUNT(*) FROM canteen_expenses WHERE PaymentID=?',[$expense])===0,'expense delete failed');
        $s->collections($colBefore,'after-cash-delete');
        $s->dump('canteen_expenses',['PaymentID','Date','UserName','Description','HeadOfAccount','amt','amount','Remarks'],'PaymentID>?',[$expBefore], '', false, 'after-expense-delete'); break;
    case 6:
        $itemBefore=(int)$s->value('SELECT MAX(ItemID) FROM canteen_item'); $customerBefore=(int)$s->value('SELECT MAX(MemberID) FROM canteen_customar'); $supplierBefore=(int)$s->value('SELECT MAX(SupplierId) FROM canteen_supplier'); $userBefore=(int)$s->value('SELECT MAX(user_id) FROM l_user'); $expenseBefore=(int)$s->value('SELECT MAX(PaymentID) FROM canteen_expenses');
        $fields=['Item'=>'Scenario Item','supplier'=>'Scenario Supplier','GenericName'=>'Sales Item','ItemType'=>'O','PurPrice'=>10,'SalesPrice'=>15,'MinimumQuantity'=>1,'MaximumQuantity'=>50];
        // Admin's stock field is disabled in the actual add/edit form; omit it.
        $s->http('/add_item.php',$fields); $item=(int)$s->value('SELECT MAX(ItemID) FROM canteen_item'); $s->check($item>$itemBefore,'item not added');
        $s->http('/add_item.php?ID='.$item,array_replace($fields,['Item'=>'Scenario Item Edited','SalesPrice'=>16]));
        $s->check($s->value('SELECT ItemName FROM canteen_item WHERE ItemID=?',[$item])==='Scenario Item Edited','item edit failed');
        $member=['name'=>'Scenario Customer','Nickname'=>'Scenario Customer Key','mobile'=>'00000000000','mobile2'=>'','address'=>'Scenario Address'];
        $s->http('/add_member.php',$member); $memberId=(int)$s->value('SELECT MAX(MemberID) FROM canteen_customar'); $s->check($memberId>$customerBefore,'customer not added');
        $s->http('/add_member.php?ID='.$memberId,array_replace($member,['name'=>'Scenario Customer Edited','address'=>'Scenario Address Edited']));
        $s->check($s->value('SELECT MemberName FROM canteen_customar WHERE MemberID=?',[$memberId])==='Scenario Customer Edited','customer edit failed');
        $s->http('/AddSupplierName.php',['SupplierName'=>'Scenario Supplier']); $supplierId=(int)$s->value('SELECT MAX(SupplierId) FROM canteen_supplier'); $s->check($supplierId>$supplierBefore,'supplier not added');
        $s->http('/Supplier.php?' . http_build_query(['ID'=>$supplierId,'Name'=>'Scenario Supplier']),['amount'=>17,'Remarks'=>'scenario supplier payment']);
        $s->check((int)$s->value('SELECT MAX(PaymentID) FROM canteen_expenses')>$expenseBefore,'supplier payment not added');
        $s->http('/list_of_user.php',['username'=>'scenario_user','password'=>'scenario-user-test','name'=>'Scenario User','email'=>'scenario@example.invalid','mobile'=>'00000000000','addess'=>'Scenario Address']);
        $s->check((int)$s->value('SELECT MAX(user_id) FROM l_user')>$userBefore,'user not added');
        $s->login('user','scenario-user-test','scenario_user');
        $s->dump('canteen_item',['ItemID','ItemName','PurPrice','SalesPrice','SupplierName','GenericName','MinimumQuantity','MaximumQuantity','Qnty','D','ItemType'],'ItemID>?',[$itemBefore]);
        $s->dump('canteen_customar',['MemberID','Nickname','MemberName','mobile','mobile2','address','m_date','mDrop'],'MemberID>?',[$customerBefore]);
        $s->dump('canteen_supplier',['SupplierId','SupplierName','D'],'SupplierId>?',[$supplierBefore]);
        $s->dump('canteen_expenses',['PaymentID','Date','UserName','Description','HeadOfAccount','amt','amount','Remarks'],'PaymentID>?',[$expenseBefore]);
        $s->dump('l_user',['user_id','UserName','password','FullName','email','address','mobile','m_date','userAtho'],'user_id>?',[$userBefore], '', true); break;
    case 7:
        foreach (['admin','user'] as $role) {
            $s->login($role);
             foreach (['sale','purchase'] as $kind) {
                $sale=$kind==='sale'; $table=$sale?'canteen_sales':'canteen_purchase'; $key=$sale?'SalesID':'BillNo'; $detail=$sale?'canteen_sales_detail':'canteen_purchase_detail';
                $id=(int)$s->value("SELECT b.$key FROM $table b WHERE Post=1 AND EXISTS (SELECT 1 FROM $detail d WHERE d.$key=b.$key) ORDER BY b.$key DESC LIMIT 1");
                $s->check($id>0,'posted bill fixture missing');
                $ids=array_column($s->rows("SELECT DISTINCT ItemID FROM $detail WHERE $key=? ORDER BY ItemID",[$id]),'ItemID');
                $s->billDump($kind,$id,"$role-before-"); $s->stock($ids,"$role-$kind-stock-before");
                $stockBefore = array_column($s->rows('SELECT ItemID,Qnty FROM canteen_item WHERE ItemID IN (' . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY ItemID', $ids), 'Qnty', 'ItemID');
                $quantities = array_column($s->rows("SELECT ItemID,SUM(Qnty) AS quantity FROM $detail WHERE $key=? GROUP BY ItemID ORDER BY ItemID",[$id]), 'quantity', 'ItemID');
                $lineCount = (int)$s->value("SELECT COUNT(*) FROM $detail WHERE $key=?",[$id]);
                $s->http('/Unpost.php?' . ($sale?'s':'p') . '=' . $id);
                $s->emit("$role-$kind-after-unpost",$s->rows("SELECT $key,Post FROM $table WHERE $key=?",[$id]));
                $s->http('/Unpost.php?' . ($sale?'d':'dl') . '=' . $id);
                if ($s->target==='legacy') {
                    $s->check((int)$s->value("SELECT COUNT(*) FROM $table WHERE $key=?",[$id])===0,'legacy bill delete did not execute');
                    $s->check((int)$s->value("SELECT COUNT(*) FROM $detail WHERE $key=?",[$id])>0,'legacy orphan baseline changed');
                } else {
                    $admin = $role === 'admin';
                    $s->check((int)$s->value("SELECT COUNT(*) FROM $table WHERE $key=?",[$id]) === ($admin ? 0 : 1),'modern header authorization/delete failed');
                    $s->check((int)$s->value("SELECT COUNT(*) FROM $detail WHERE $key=?",[$id]) === ($admin ? 0 : $lineCount),'modern detail authorization/delete failed');
                    if (!$admin) $s->check((int)$s->value("SELECT Post FROM $table WHERE $key=?",[$id]) === 1,'non-admin unpost changed the bill');
                    $stockAfter = array_column($s->rows('SELECT ItemID,Qnty FROM canteen_item WHERE ItemID IN (' . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY ItemID', $ids), 'Qnty', 'ItemID');
                     foreach ($stockBefore as $item => $quantity) {
                        $expected = (float)$quantity + ($admin ? ($sale ? 1 : -1) * (float)$quantities[$item] : 0);
                        $s->check(abs((float)$stockAfter[$item] - $expected) < 0.000001,'modern bill stock reversal incorrect');
                    }
                    // Repeated requests must neither recreate lines nor reverse stock twice.
                    $s->http('/Unpost.php?' . ($sale?'d':'dl') . '=' . $id);
                    $s->check($stockAfter === array_column($s->rows('SELECT ItemID,Qnty FROM canteen_item WHERE ItemID IN (' . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY ItemID', $ids), 'Qnty', 'ItemID'),'repeated bill delete changed stock');
                }
                $s->billDump($kind,$id,"$role-after-"); $s->stock($ids,"$role-$kind-stock-after");
            }
        }
        break;
    case 8:
        $before=(int)$s->value('SELECT MAX(SlNo) FROM canteen_stock');
        $s->http('/SavePrestntStock.php'); $id=(int)$s->value('SELECT MAX(SlNo) FROM canteen_stock'); $s->check($id>$before,'stock snapshot not created');
        $s->dump('canteen_stock',['SlNo','Date'],'SlNo>?',[$before]);
        $s->emit('snapshot-detail-summary',$s->numericRows($s->rows('SELECT SlNo,COUNT(*) AS rows_count,SUM(Qnty) AS quantity,SUM(PurPrice*Qnty) AS value FROM canteen_stock_detail WHERE SlNo=? GROUP BY SlNo ORDER BY SlNo',[$id]), ['SlNo','rows_count','quantity','value'])); break;
    case 9:
        $before=(string)$s->value('SELECT password FROM l_user WHERE user_id=17');
        $s->http('/password.php',['oPass'=>'admin-test','nPass'=>'scenario-new-password','cPass'=>'scenario-new-password']);
        $after=(string)$s->value('SELECT password FROM l_user WHERE user_id=17'); $s->check($before!==$after,'password did not change');
        $s->login('admin','scenario-new-password');
        $s->dump('l_user',['user_id','UserName','password','userAtho'],'user_id=17',[], '', true); break;
    default: throw new RuntimeException('Unknown scenario');
    }
    $s->finish();
}
function scenario_diagnostics(string $out): int
{
    $errors = [];
    $files = glob($out . '/*.html.private'); natsort($files);
    foreach ($files as $path) {
        foreach (characterization_errors(file_get_contents($path)) as $error) $errors[] = basename($path, '.html.private') . ' ' . $error;
    }
    $oldMask = umask(0077);
    file_put_contents($out . '/_errors.txt', $errors ? implode("\n", $errors) . "\n" : '');
    chmod($out . '/_errors.txt', 0600); umask($oldMask);
    return count($errors);
}
// Allow private diagnostic postprocessing without executing/resetting scenarios.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) return;
try {
    umask(0077);
    if (in_array($argv[1]??'', ['-h','--help'], true)) { echo "Usage: php tools/scenarios/run.php <1..9|all> <base-url> <legacy|modern> <out-dir>\n"; exit; }
    if ($argc!==5) throw new RuntimeException('Expected scenario, local URL, target and output directory');
    [$script,$number,$base,$target,$out]=$argv;
    if (!preg_match('~^http://(?:127\.0\.0\.1|localhost):[0-9]+$~D',$base) || !in_array($target,['legacy','modern'],true) || !preg_match('/^(all|[1-9])$/D',$number)) throw new RuntimeException('Invalid local scenario arguments');
    $urlPort=(int)parse_url($base, PHP_URL_PORT);
    if (($target==='legacy' && $urlPort!==8056) || ($target==='modern' && ($urlPort<8100 || $urlPort>8199))) throw new RuntimeException('Use legacy8056 or isolated modern81NN test servers');
    $fixtures=realpath(getenv('DOYEL_FIXTURES')?:getenv('HOME').'/.doyel-fixtures'); if (!$fixtures) throw new RuntimeException('Missing fixture directory');
    $repo=realpath(__DIR__.'/../..');
    if (str_starts_with($fixtures.'/', $repo.'/')) throw new RuntimeException('Fixtures must stay outside the repository');
    if (file_exists($out) && (!is_dir($out)||count(array_diff(scandir($out),['.','..'])))) throw new RuntimeException('Output directory must be new or empty');
    if (!is_dir($out) && !mkdir($out,0700,true)) throw new RuntimeException('Cannot create output'); $out=realpath($out);
    if (!str_starts_with($out.'/',$fixtures.'/')) throw new RuntimeException('Outputs must be within external DOYEL_FIXTURES');
    $names=[1=>'01-sale',2=>'02-oversell',3=>'03-purchase',4=>'04-customer-collection',5=>'05-cash',6=>'06-master-data',7=>'07-admin-bills',8=>'08-stock-snapshot',9=>'09-password'];
    foreach ($number==='all'?range(1,9):[(int)$number] as $n) {
        $dbname=getenv('DOYEL_DB_NAME')?:'software';
        if (!preg_match('/^(software|doyel_[A-Za-z0-9_]+)$/D',$dbname)) throw new RuntimeException('Invalid fixture database name');
        $reset=[__DIR__.'/../db-reset.sh',$target,$dbname]; $pipes=[];
        $process=proc_open($reset,[0=>['file','/dev/null','r'],1=>['file',$out.'/'.$names[$n].'-reset.private','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start local reset'); $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('Local reset failed; inspect private reset output: '.$error);
        run_scenario(new Scenario(rtrim($base,'/'),$target,$out,$names[$n]),$n);
    }
    printf("Recorded %d PHP diagnostics in private _errors.txt\n", scenario_diagnostics($out));
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
