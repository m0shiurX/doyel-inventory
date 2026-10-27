<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
$selectedUser = is_string($_GET['user'] ?? null) ? $_GET['user'] : '';
$users = db_all('SELECT UserName FROM canteen_purchase WHERE Post = ? AND Date BETWEEN ? AND ? GROUP BY UserName ORDER BY UserName', [1, $FormDate, $ToDate]);
$params = [1, $FormDate, $ToDate];
$where = 'h.Post = ? AND h.Date BETWEEN ? AND ?';
if ($selectedUser !== '') { $where .= ' AND h.UserName = ?'; $params[] = $selectedUser; }
$headers = db_all("SELECT h.BillNo, h.Date, h.Amount, h.Discount, h.Total FROM canteen_purchase h WHERE $where ORDER BY h.BillNo", $params);
$linesByBill = [];
foreach (db_all("SELECT d.BillNo, d.ID, i.ItemName, d.Qnty, d.Rate, d.Amt FROM canteen_purchase_detail d INNER JOIN canteen_purchase h ON h.BillNo = d.BillNo INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE $where ORDER BY d.ID", $params) as $line) $linesByBill[$line->BillNo][] = $line;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Add to Stock : Group by Bill</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Purchase of a period</h1>
<form name="form1" method="GET" action="">User Name: <select name="user" id="user" onChange="form1.submit();">
<?php if ($selectedUser !== '') echo '<option>' . e($selectedUser) . '</option>';
foreach ($users as $user) echo '<option>' . e($user->UserName) . '</option>'; ?>
</select></form>
<p align="left"><b>From:</b> <?= e(date_format(date_create($FormDate), 'd-M-Y')) ?> &nbsp;
<b>To:</b> <?= e(date_format(date_create($ToDate), 'd-M-Y')) ?></p>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<tr><th width="12%">SL</th><th width="48%">Item Name</th><th width="14%">Qunty</th><th width="13%">Amount</th><th width="13%">Total</th></tr>
<?php
$FTotal = 0;
foreach ($headers as $bill) {
    echo '<tr align="left" bgcolor="#fff"><td colspan="5"><b>Bill No : ' . e($bill->BillNo) . '&nbsp;&nbsp;&nbsp; Date : &nbsp;&nbsp;&nbsp;' . e(date_format(date_create($bill->Date), 'd-M-Y, h:i:s A')) . "</td>\n</tr>\n";
    $sl = 0;
    foreach ($linesByBill[$bill->BillNo] ?? [] as $line) {
        echo '<tr><td>' . ++$sl . "</td>
<td>" . e($line->ItemName) . "</td>
<td>" . e($line->Qnty) . "</td>
<td>" . e($line->Rate) . "</td>
<td>" . e($line->Amt) . "</td>
</tr>
";
    }
    echo '<tr><th>&nbsp;</th><th>&nbsp;</th><th>' . e($bill->Amount) . '</th><th>' . e($bill->Discount) . '</th><th>' . e($bill->Total) . "</th></tr>
";
    $FTotal += (float)$bill->Total;
}
?>
<tr><th>&nbsp;</th><th align="left"><b>Total &nbsp;&nbsp;</b></th><th align="left"><b>&nbsp;</b></th><th align="left"><b></b></th><th align="left"><b><?= e($FTotal) ?></b></th></tr>
</table>
<p>&nbsp;</p>
</div></body></html>
