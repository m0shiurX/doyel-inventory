<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$categories = db_all('SELECT ItemType FROM canteen_item GROUP BY ItemType ORDER BY ItemType');
$byCategory = [];
foreach (db_all('SELECT i.ItemType, i.ItemID, i.ItemName, i.SalesPrice, SUM(d.Qnty) AS sQnty FROM canteen_sales h INNER JOIN canteen_sales_detail d ON d.SalesID = h.SalesID INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE h.Post = ? AND d.Date BETWEEN ? AND ? GROUP BY i.ItemType, i.ItemID, i.ItemName, i.SalesPrice ORDER BY i.ItemType, i.ItemName', [1, $FormDate, $ToDate]) as $item) $byCategory[mb_strtolower(rtrim($item->ItemType), 'UTF-8')][] = $item;
$categoryNames = ['P'=>'PISTON','L'=>'LINER','AP'=>'Alay Puli','DP'=>'Dhalay Puli','BC'=>'Bearing Case','H'=>'Haps','DT'=>'Dhalay Taoa','AK'=>'Alay Koray','BD'=>'Break Drum','AF'=>'Alay Fan'];
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
<h2></h2><table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<?php foreach ($categories as $category): ?>
<tr align="left" bgcolor="#fff"><td colspan="5"><b>Category : <?= e($categoryNames[$category->ItemType] ?? 'Others') ?></b></td></tr>
<tr><th width="17%"><b>ID</b></th><th width="42%"><b>Name of Item </b></th><th width="21%"><b>Quantity</b></th><th width="20%"><b>Amount</b></th></tr>
<?php
$items = $byCategory[mb_strtolower(rtrim($category->ItemType), 'UTF-8')] ?? [];
$sl = 0; $subTotal = 0; $totalAmt = 0;
foreach ($items as $item) {
    $amount = (float)$item->sQnty * (float)$item->SalesPrice;
    echo '<tr><td>' . ++$sl . "</td>
<td>" . e($item->ItemName) . "</td>
<td>" . e(fmt_numeric($item->sQnty)) . "</td>
<td>" . e($amount) . "</td>
</tr>
";
    $subTotal += (float)$item->sQnty; $totalAmt += $amount;
}
if ($items) echo '<tr><th><b>&nbsp;</b></th><th><b>Sub Total</b></th><th align="left"><b>' . e($subTotal) . '</b></th><th align="left"><b>' . e($totalAmt) . '</b></th></tr>';
endforeach;
?></table>
<p>&nbsp;</p>
</div></body></html>
