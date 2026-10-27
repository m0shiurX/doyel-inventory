<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
// Repair the missing sales-header join. The legacy predicate explicitly used header Date.
$items = db_all('SELECT i.ItemID, i.ItemName, i.SalesPrice, SUM(d.Qnty) AS sQnty FROM canteen_sales_detail d INNER JOIN canteen_sales h ON h.SalesID = d.SalesID INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE i.GenericName = ? AND h.Date BETWEEN ? AND ? GROUP BY i.ItemID, i.ItemName, i.SalesPrice ORDER BY i.ItemName', ['Row Material', $FormDate, $ToDate]);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Send To Factory of a period</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Send To Factory of a period</h1>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<tr><th width="12%">SL</th><th width="48%">Item Name</th><th width="14%">Amount</th><th width="13%">Qunty</th><th width="13%">Total</th></tr>
<?php
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
if ($items) echo '<tr><th>&nbsp;</th><th>Sub Total</th><th>&nbsp;</th><th>' . e($subTotal) . '</th><th>' . e($totalAmt) . '</th></tr>';
?></table>
<p>&nbsp;</p>
</div></body></html>
