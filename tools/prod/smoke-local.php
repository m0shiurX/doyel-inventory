<?php
// Disposable LOCAL fixture only; never a production mutation tool.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../db/cleanup/paths.php';
require __DIR__ . '/../scenarios/run.php';
try {
    if ($argc !== 3 || !in_array($argv[1], ['http://127.0.0.1:8147','http://127.0.0.1:8148'], true) || getenv('DOYEL_DB_NAME') !== 'doyel_s45') throw new RuntimeException('Usage: DOYEL_DB_NAME=doyel_s45 php tools/prod/smoke-local.php http://127.0.0.1:8147 <NEW-private-directory>');
    private_path($argv[2]); umask(0077);
    if (file_exists($argv[2]) || !mkdir($argv[2], 0700)) throw new RuntimeException('Use new private directory');
    $s = new Scenario($argv[1], 'modern', $argv[2], 'cutover');
    $existingBill = $s->value('SELECT MIN(s.SalesID) FROM canteen_sales s WHERE s.Post=1 AND EXISTS (SELECT 1 FROM canteen_sales_detail d WHERE d.SalesID=s.SalesID)');
    $s->check($existingBill !== null, 'Existing posted fixture sale missing');
    foreach (['admin','user'] as $role) {
        $s->login($role);
        foreach (['/index.php','/Sales.php?Bill=' . $existingBill,'/Payment.php','/report/salesOfaPeriod-Bill.php','/report/closingBalance.php'] as $path) $s->check($s->http($path)['status'] === 200, 'Key page failed');
        $sale = $s->http('/Sales.php?Bill=' . $existingBill);
        $s->check(!str_contains($sale['body'], 'Bill No. Not Found') && str_contains($sale['body'], (string)$existingBill), 'Existing posted bill content missing');
        if ($role === 'user') {
            $r = characterization_http($s->base, ['method'=>'GET','path'=>'/backup.php','body'=>''], $s->cookie);
            $s->check($r['status'] === 302 && !str_contains($r['body'], '-- Doyel database backup'), 'User backup must redirect without SQL');
        }
        echo "PASS: $role login and five pages\n";
    }
    $s->login(); $item = $s->items(1)[0]; $bill = $s->newBill('sale');
    $s->addSale($bill, $item, 1);
    $total = $s->value('SELECT SUM(Amt) FROM canteen_sales_detail WHERE SalesID=?', [$bill]);
    $s->http('/Sales.php', ['Bill'=>$bill,'hPost'=>'YES','amount2'=>$total,'discount'=>0,'total'=>$total,'Nickname'=>$s->customer()['Nickname'],'Note'=>'local cutover rehearsal']);
    $s->check($s->value('SELECT Post FROM canteen_sales WHERE SalesID=?', [$bill]) === '1', 'Sale not posted');
    $s->http('/Unpost.php?d=' . $bill);
    $s->check($s->value('SELECT COUNT(*) FROM canteen_sales WHERE SalesID=?', [$bill]) === '0', 'Void header remains');
    $s->check($s->value('SELECT COUNT(*) FROM canteen_sales_detail WHERE SalesID=?', [$bill]) === '0', 'Void lines remain');
    $s->check($s->value('SELECT Qnty FROM canteen_item WHERE ItemID=?', [$item['ItemID']]) === $item['Qnty'], 'Void failed to restore stock');
    echo "PASS: posted test sale, HTML confirmation void, stock restored\n";
    $r = $s->http('/backup.php');
    $s->check(str_ends_with(rtrim($r['body']), '-- Doyel backup complete.'), 'Incomplete web backup');
    file_put_contents($argv[2] . '/backup.sql', $r['body']); chmod($argv[2] . '/backup.sql', 0600);
    echo "PASS: admin backup completion marker, non-admin denied, zero HTTP diagnostics\n";
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
