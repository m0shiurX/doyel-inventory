<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$Supplier = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';
$params = [1, $FormDate, $ToDate];
$where = 'Post = ? AND Date BETWEEN ? AND ?';
if ($Supplier !== '') { $where .= ' AND Supplier = ?'; $params[] = $Supplier; }
$bills = $Supplier !== '' ? db_all("SELECT BillNo, Date, Amount, Discount, Total FROM canteen_purchase WHERE $where ORDER BY BillNo", $params) : [];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Add to Stock</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div align="center"><div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<?php if ($Supplier !== ''): ?><h1>Purchase of a period  <?= e($Supplier) ?></h1>
<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
<tr><td width="10%"><b>Bill No</b></td><td width="20%"><b>Date</b></td><td width="15%"><b>Amount</b></td><td width="15%"><b>Discount</b></td><td width="15%"><b>Total</b></td><td width="15%"><b>Action</b></td></tr>
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
echo '<tr><td></td><td>Total</td><td>' . e($Amount) . '</td><td>' . e($Discount) . '</td><td>' . e($Total) . '</td><td>&nbsp;</td></tr>';
?></table><?php endif; ?>
<p>&nbsp;</p>
</div></div></body></html>
