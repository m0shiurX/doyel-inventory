<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d') . ' 00:00:00';
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d') . ' 23:59:59';
// Display the first primary-key head spelling, matching the old lenient group representative.
$groups = db_all('SELECT p.HeadOfAccount, g.amt FROM (SELECT MIN(PaymentID) AS FirstID, SUM(amount) AS amt FROM canteen_collection WHERE Date BETWEEN ? AND ? GROUP BY HeadOfAccount) g INNER JOIN canteen_collection p ON p.PaymentID = g.FirstID ORDER BY p.HeadOfAccount', [$FormDate, $ToDate]);
$byHead = [];
foreach (db_all('SELECT PaymentID, HeadOfAccount, Date, UserName, Description, amount, Remarks FROM canteen_collection WHERE Date BETWEEN ? AND ? ORDER BY PaymentID', [$FormDate, $ToDate]) as $payment) $byHead[mb_strtolower(rtrim($payment->HeadOfAccount), 'UTF-8')][] = $payment;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>Cash Collection Of a Period</title><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<div class="printb"><a href="#" onClick="javascript:window.print()">Print</a></div>
<?php include __DIR__ . '/menu.php'; ?>
<h1 align="center">Cash Collection Of a Period</h1>
<p align="left"><b>From:</b> <?= e(date_format(date_create($FormDate), 'd-M-Y')) ?> &nbsp;
<b>To:</b> <?= e(date_format(date_create($ToDate), 'd-M-Y')) ?></p>
<table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
<tr><th width="17%">Date</th><th width="11%">Receved By</th><th width="34%">Description</th><th width="8%">Amount</th><th width="20%">Remarks</th></tr>
<?php
$TotalAmt = 0;
foreach ($groups as $group) {
    echo '<tr><td colspan="5"><b>' . e($group->HeadOfAccount) . '</b></td></tr>';
    foreach ($byHead[mb_strtolower(rtrim($group->HeadOfAccount), 'UTF-8')] ?? [] as $payment) {
        echo '<tr><td>' . e(date_format(date_create($payment->Date), 'd-M-y h:i A')) . "</td>\n<td>" . e($payment->UserName) . "</td>\n<td>" . e($payment->Description) . "</td>\n<td>" . e(fmt_numeric($payment->amount)) . "</td>\n<td>" . e($payment->Remarks) . "&nbsp;</td>\n</tr>\n";
    }
    echo '<tr><th>&nbsp;</th><th>&nbsp;</th><th>Sub Total</th><th>' . e(fmt_numeric($group->amt)) . '</th><th>&nbsp;</th></tr>';
    $TotalAmt += (float)$group->amt;
}
echo '<tr><th>&nbsp;</th><th>&nbsp;</th><th>Ground Total</th><th>' . e($TotalAmt) . '</th><th>&nbsp;</th></tr>';
?></table><br>
<p>&nbsp;</p>
</div></body></html>
