<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$sales = db_all('SELECT h.SalesID, h.Date, d.Amt FROM canteen_sales h LEFT JOIN (SELECT SalesID, SUM(Amt) AS Amt FROM canteen_sales_detail GROUP BY SalesID) d ON d.SalesID = h.SalesID WHERE h.Post = ? ORDER BY h.SalesID', [0]);
$purchases = db_all('SELECT h.BillNo, h.Date, d.Amt FROM canteen_purchase h LEFT JOIN (SELECT BillNo, SUM(Amt) AS Amt FROM canteen_purchase_detail GROUP BY BillNo) d ON d.BillNo = h.BillNo WHERE h.Post = ? ORDER BY h.BillNo', [0]);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Unposted Bill</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div align="center"><div id="main">
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Unposted Bill</h1>
<div align="left"><h1>Sales</h1></div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd"><tr><th width="10%">Bill No</th><th width="20%">Date</th><th width="20%">Amount</th></tr>
<?php foreach ($sales as $bill) echo '<tr><td>' . e($bill->SalesID) . "</td>
<td>" . e(date_format(date_create($bill->Date), 'd-M-Y')) . "</td>
<td>" . e(round((float)($bill->Amt ?? 0), 2)) . "</td>
</tr>
"; ?>
</table><h1 align="left">Purchase</h1>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd"><tr><th width="10%">Bill No</th><th width="20%">Date</th><th width="20%">Amount</th></tr>
<?php foreach ($purchases as $bill) echo '<tr><td>' . e($bill->BillNo) . "</td>
<td>" . e(date_format(date_create($bill->Date), 'd-M-Y')) . "</td>
<td>" . e(round((float)($bill->Amt ?? 0), 2)) . "</td>
</tr>
"; ?>
</table>
<p>&nbsp;</p>
</div></div></body></html>
