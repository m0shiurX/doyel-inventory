<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$Supplier = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';
$params = [1, $FormDate, $ToDate];
$where = 'Post = ? AND Date BETWEEN ? AND ?';
$bills = db_all("SELECT BillNo, Date, Amount, Discount, Total FROM canteen_purchase WHERE $where ORDER BY BillNo", $params);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Purchase of a period</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Purchase of a period</h1>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<tr><th width="10%">Bill No</th><th width="20%">Date</th><th width="15%">Amount</th><th width="15%">Discount</th><th width="15%">Total</th><th width="15%">Action</th></tr>
<?php
$Amount = 0; $Discount = 0; $Total = 0;
foreach ($bills as $bill) {
    echo '<tr><td>' . e($bill->BillNo) . "</td>
<td>" . e(date_format(date_create($bill->Date), 'd-M-Y')) . "</td>
<td>" . e(fmt_numeric($bill->Amount)) . "</td>
<td>" . e(fmt_numeric($bill->Discount)) . "</td>
<td>" . e(fmt_numeric($bill->Total)) . '</td>';
    echo '<td><a href="PurMemo.php?id=' . e(urlencode($bill->BillNo)) . '">View</a></td></tr>' . "
";
    $Amount += (float)$bill->Amount; $Discount += (float)$bill->Discount; $Total += (float)$bill->Total;
}
echo '<tr><th>&nbsp;</th><th>Total</th><th>' . e($Amount) . '</th><th>' . e($Discount) . '</th><th>' . e($Total) . '</th><th>&nbsp;</th></tr>';
?></table>
<p>&nbsp;</p>
</div></body></html>
