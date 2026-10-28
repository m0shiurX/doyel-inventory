<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
// Group amounts explicitly; the printed username is the first matching primary-key spelling.
$employees = db_all('SELECT h.UserName, g.Amount, g.Discount, g.Total FROM (SELECT MIN(SalesID) AS FirstID, SUM(Amount) AS Amount, SUM(Discount) AS Discount, SUM(Total) AS Total FROM canteen_sales WHERE Post = ? AND Date BETWEEN ? AND ? GROUP BY UserName) g INNER JOIN canteen_sales h ON h.SalesID = g.FirstID ORDER BY h.UserName', [1, $FormDate, $ToDate]);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Employee ledgr-Summairy</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div align="center"><div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Employee Summery</h1>
<p align="left"><b>From:</b> <?= e(date_format(date_create($FormDate), 'd-M-Y')) ?> &nbsp;
<b>To:</b> <?= e(date_format(date_create($ToDate), 'd-M-Y')) ?></p>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
<tr bgcolor="#f1f1f1"><th width="5%">&nbsp;</th><th width="25%">Salesman</th><th width="25%">Amount</th><th width="25%">Discount</th><th width="20%">Total</th></tr>
<?php
$Amount = 0; $Discount = 0; $Total = 0;
foreach ($employees as $employee) {
    $amount = round((float)$employee->Amount, 2); $total = round((float)$employee->Total, 2);
    echo '<tr><th width="5%">&nbsp;</th><td>' . e($employee->UserName) . "</td>
<td>" . e($amount) . "</td>
<td>" . e(fmt_numeric($employee->Discount)) . "</td>
<td>" . e($total) . "</td></tr>
";
    $Amount += $amount; $Discount += (float)$employee->Discount; $Total += $total;
}
echo '<tr><th width="5%">&nbsp;</th><th>Total</th><th>' . e($Amount) . '</th><th>' . e($Discount) . '</th><th>' . e($Total) . '</th></tr>';
?></table><br>
<p>&nbsp;</p>
</div></div></body></html>
