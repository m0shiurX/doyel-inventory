<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$suppliers = db_all('SELECT SupplierName FROM canteen_item WHERE D = ? GROUP BY SupplierName ORDER BY SupplierName', [0]);
$bySupplier = [];
// No Post filter: the legacy supplier report includes all dated sale detail rows.
foreach (db_all('SELECT i.SupplierName, i.ItemID, i.ItemName, i.SalesPrice, SUM(d.Qnty) AS sQnty FROM canteen_sales_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE d.Date BETWEEN ? AND ? GROUP BY i.SupplierName, i.ItemID, i.ItemName, i.SalesPrice ORDER BY i.SupplierName, i.ItemName', [$FormDate, $ToDate]) as $item) $bySupplier[mb_strtolower(rtrim($item->SupplierName), 'UTF-8')][] = $item;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Sales of a period supplier name wise</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div align="center"><div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Sales of a period supplier name wise</h1>
<p align="left"><b>From:</b> <?= e(date_format(date_create($FormDate), 'd-M-Y')) ?> &nbsp;
<b>To:</b> <?= e(date_format(date_create($ToDate), 'd-M-Y')) ?></p>
<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
<tr bgcolor="#f1f1f1"><td width="12%"><b>SL</b></td><td width="48%"><b>Item Name</b></td><td width="14%"><b>Amount</b></td><td width="13%"><b>Qunty</b></td><td width="13%"><b>Total</b></td></tr>
<?php
$gTotal = 0; $gTotalQunt = 0; $printed = false;
foreach ($suppliers as $supplier) {
    $items = $bySupplier[mb_strtolower(rtrim($supplier->SupplierName), 'UTF-8')] ?? [];
    if (!$items) continue;
    echo '<tr align="left" bgcolor="#fff"><th colspan="5">' . e($supplier->SupplierName) . "</th></tr>
";
    $sl = 0; $subTotal = 0; $totalAmt = 0;
    foreach ($items as $item) {
        $amount = (float)$item->sQnty * (float)$item->SalesPrice;
        echo '<tr><td>' . ++$sl . "</td>
<td>" . e($item->ItemName) . "</td>
<td>" . e(fmt_numeric($item->SalesPrice)) . "</td>
<td>" . e(fmt_numeric($item->sQnty)) . "</td>
<td>" . e($amount) . "</td>
</tr>
";
        $subTotal += (float)$item->sQnty; $totalAmt += $amount;
    }
    echo '<tr><th><b>&nbsp;</b></th><th><b>Sub Total :- ' . $sl . '</b></th><th align="left"><b>&nbsp;</b></th><th align="left"><b>' . e($subTotal) . '</b></th><th align="left"><b>' . e($totalAmt) . '</b></th></tr>';
    $gTotal += $totalAmt; $gTotalQunt += $subTotal; $printed = true;
}
if ($suppliers) echo '<tr bgcolor="#f1f1f1"><th><b>&nbsp;</b></th><th><b>Gand Total</b></th><th align="left"><b>&nbsp;</b></th><th align="left"><b>' . e($printed ? $gTotalQunt : '') . '</b></th><th align="left"><b>' . e($printed ? $gTotal : '') . '</b></th></tr>';
?></table>
<p>&nbsp;</p>
</div></div></body></html>
