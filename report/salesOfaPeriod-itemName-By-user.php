<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$users = db_all('SELECT UserName, SUM(Amount) AS Amount FROM canteen_sales WHERE Date BETWEEN ? AND ? GROUP BY UserName ORDER BY UserName', [$FormDate, $ToDate]);
$byUser = [];
// ItemName is unique; catalogue columns are constant. UserName groups use the DB collation.
foreach (db_all('SELECT h.UserName, i.ItemID, i.ItemName, i.SalesPrice, SUM(d.Qnty) AS sQnty FROM canteen_sales h INNER JOIN canteen_sales_detail d ON h.SalesID = d.SalesID INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE h.Post = ? AND i.GenericName <> ? AND h.Date BETWEEN ? AND ? GROUP BY h.UserName, i.ItemID, i.ItemName, i.SalesPrice ORDER BY h.UserName, i.ItemName', [1, 'Row Material', $FormDate, $ToDate]) as $item) $byUser[mb_strtolower(rtrim($item->UserName), 'UTF-8')][] = $item;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Sales of a period Item wise</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Sales of a period</h1>
<p align="left"><b>From:</b> <?= e(date_format(date_create($FormDate), 'd-M-Y')) ?> &nbsp;
<b>To:</b> <?= e(date_format(date_create($ToDate), 'd-M-Y')) ?></p>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<tr><th width="12%">SL</th><th width="48%">Item Name</th><th width="14%">Amount</th><th width="13%">Qunty</th><th width="13%">Total</th></tr>
<?php
$FTotal = 0; $fQnty = 0;
foreach ($users as $user) {
    if ((float)$user->Amount <= 0) continue;
    echo '<tr align="left" bgcolor="#fff"><td colspan="5"><b>' . e($user->UserName) . "</b></td></tr>
";
    $items = $byUser[mb_strtolower(rtrim($user->UserName), 'UTF-8')] ?? [];
    $sl = 0; $subTotal = 0; $totalAmt = 0;
    foreach ($items as $item) {
        $amount = (float)$item->sQnty * (float)$item->SalesPrice;
        echo '<tr><td>' . ++$sl . "</td>
<td>" . e($item->ItemName) . "</td>
<td>" . e($item->SalesPrice) . "</td>
<td>" . e($item->sQnty) . "</td>
<td>" . e($amount) . "</td>
</tr>
";
        $subTotal += (float)$item->sQnty; $totalAmt += $amount;
    }
    if ($items) echo '<tr><th><b>&nbsp;</b></th><th><b>Sub Total</b></th><th align="left"><b>&nbsp;</b></th><th align="left"><b>' . e($subTotal) . '</b></th><th align="left"><b>' . e($totalAmt) . '</b></th></tr>';
    $fQnty += $subTotal; $FTotal += $totalAmt;
}
?>
<tr><th>&nbsp;</th><th align="left"><b>Total &nbsp;&nbsp;</b></th><th align="left"><b>&nbsp;</b></th><th align="left"><b><?= e($fQnty) ?></b></th><th align="left"><b><?= e($FTotal) ?></b></th></tr>
</table>
<p>&nbsp;</p>
</div></body></html>
