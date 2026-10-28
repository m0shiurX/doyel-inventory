<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$id = filter_var($_GET['SlN'] ?? null, FILTER_VALIDATE_INT);
$SlNo = $id !== false && $id > 0 ? $id : 0;
$snapshot = db_one('SELECT Date FROM canteen_stock WHERE SlNo = ?', [$SlNo]);
$Date = $snapshot !== null ? date('d-M-Y h:i:s A', strtotime($snapshot->Date)) : '';
// Read stored snapshot names/prices, never the current item catalogue.
$rows = db_all('SELECT d.ItemID, d.ItemName, d.PurPrice, d.Qnty, g.SupplierName AS GroupName, g.GroupID
    FROM (SELECT SupplierName, MIN(ID) AS GroupID FROM canteen_stock_detail WHERE SlNo = ? GROUP BY SupplierName) g
    JOIN canteen_stock_detail d ON d.SupplierName = g.SupplierName AND d.SlNo = ?
    ORDER BY g.SupplierName, d.ItemName, d.ID', [$SlNo, $SlNo]);
$groups = [];
foreach ($rows as $row) {
    $groups[$row->GroupID]['name'] = $row->GroupName;
    $groups[$row->GroupID]['items'][] = $row;
}
$summary = db_one('SELECT COUNT(*) AS total, SUM(Qnty) AS tQnty, SUM(PurPrice) AS tPurPrice FROM canteen_stock_detail WHERE SlNo = ?', [$SlNo]);
$FTotal = 0;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Closing Balance</title>
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include __DIR__ . '/menu.php'; ?>
<div align="center"><h1>Closing Balance Date - <?= e($Date) ?></h1></div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" id="tableGd">
<tr><th width="10%">ID</th><th width="51%">Name of Item</th><th width="11%">Pur Price</th><th width="14%">Quantity</th><th width="14%">Total</th></tr>
<?php foreach ($groups as $group): $PurPrice = 0; $Qnty = 0; $Total = 0; ?>
<tr><td colspan="5"><b><?= e($group['name']) ?></b></td></tr>
<?php foreach ($group['items'] as $row): $Price = (float)$row->PurPrice * (float)$row->Qnty; ?>
<tr>
<td><?= e($row->ItemID) ?></td>
<td><?= e($row->ItemName) ?></td>
<td><?= e(fmt_numeric($row->PurPrice)) ?></td>
<td><?= e(fmt_numeric($row->Qnty)) ?></td>
<td><?= e($Price) ?></td>
</tr>
<?php $PurPrice += (float)$row->PurPrice; $Qnty += (float)$row->Qnty; $Total += $Price; endforeach; $FTotal += $Total; ?>
<tr><th><b>&nbsp;</b></th><th><b>Sub Total - <?= e(count($group['items'])) ?></b></th><th align="left"><b><?= e(round($PurPrice, 2)) ?></b></th><th align="left"><b><?= e($Qnty) ?></b></th><th align="left"><b><?= e($Total) ?></b></th></tr>
<?php endforeach; ?>
<tr><th><b>&nbsp;</b></th><th><b>Total: <?= e($summary->total) ?></b></th><th align="left"><b><?= e(round((float)($summary->tPurPrice ?? 0), 2)) ?></b></th><th align="left"><b><?= e(fmt_numeric($summary->tQnty)) ?></b></th><th align="left"><b><?= e($groups ? $FTotal : '') ?></b></th></tr>
</table>
</table><br>
</div>
</body>
</html>
