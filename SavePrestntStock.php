<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo '<!doctype html><html><head><meta charset="UTF-8"><title>Save stock</title></head><body>' . action_form('SavePrestntStock.php', [], 'Save Prestnt Stock') . '</body></html>';
    exit;
}
db_tx(static function (): void {
    $date = now_sql();
    db_exec('INSERT INTO canteen_stock (Date) VALUES (?)', [$date]);
    $snapshot = db_insert_id();
    db_exec('INSERT INTO canteen_stock_detail (SlNo, Date, SupplierName, ItemID, ItemName, PurPrice, Qnty) SELECT ?, ?, SupplierName, ItemID, ItemName, PurPrice, Qnty FROM canteen_item WHERE D = ? AND Qnty > ? ORDER BY ItemID', [$snapshot, $date, 0, 0]);
});
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Update Closing Balance</title>
<h2 align="center">Stock Save Complite</h2>
    </body>
</html>
