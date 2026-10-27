<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
// Category inclusion uses active items, while legacy detail/final totals include
// every positive-stock item. Preserve that deliberate filter asymmetry.
$rows = db_all('SELECT i.ItemID, i.ItemName, i.PurPrice, i.Qnty, g.GenericName AS GroupName, g.GroupID
    FROM (SELECT GenericName, MIN(ItemID) AS GroupID FROM canteen_item WHERE D = ? GROUP BY GenericName HAVING SUM(Qnty) > 0) g
    JOIN canteen_item i ON i.GenericName = g.GenericName AND i.Qnty > 0
    ORDER BY g.GenericName, i.ItemName, i.ItemID', [0]);
$groups = [];
foreach ($rows as $row) {
    $groups[$row->GroupID]['name'] = $row->GroupName;
    $groups[$row->GroupID]['items'][] = $row;
}
$summary = db_one('SELECT COUNT(*) AS total, SUM(Qnty) AS tQnty, SUM(PurPrice) AS tPurPrice FROM canteen_item WHERE Qnty > 0');
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
<div align="center"><h1>Closing Balance Date - <?= e(date('d-M-Y')) ?></h1></div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" id="tableGd">
<tr bgcolor="#f1f1f1"><th width="8%">ID</th><th width="30%">Name of Item</th><th width="13%">Pur Price</th><th width="9%">Quantity</th><th width="13%">Total</th></tr>
<?php foreach ($groups as $group): $PurPrice = 0; $Qnty = 0; $Total = 0; ?>
<tr align="left" bgcolor="#fff"><td colspan="5"><b><?= e($group['name']) ?></b></td></tr>
<?php foreach ($group['items'] as $row): $Price = (float)$row->PurPrice * (float)$row->Qnty; ?>
<tr>
<td><?= e($row->ItemID) ?></td>
<td><?= e($row->ItemName) ?></td>
<td><?= e($row->PurPrice) ?></td>
<td><?= e($row->Qnty) ?></td>
<td><?= e($Price) ?></td>
</tr>
<?php $PurPrice += (float)$row->PurPrice; $Qnty += (float)$row->Qnty; $Total += $Price; endforeach; $FTotal += $Total; ?>
<tr><th><b>&nbsp;</b></th><th><b>Sub Total - <?= e(count($group['items'])) ?></b></th><th align="left"><b><?= e(round($PurPrice, 2)) ?></b></th><th align="left"><b><?= e($Qnty) ?></b></th><th align="left"><b><?= e($Total) ?></b></th></tr>
<?php endforeach; ?>
<tr><th><b>&nbsp;</b></th><th align="left"><b>Total &nbsp;&nbsp; <?= e($summary->total) ?></b></th><th align="left"><b><?= e(round((float)($summary->tPurPrice ?? 0), 2)) ?></b></th><th align="left"><b><?= e($summary->tQnty) ?></b></th><th align="left"><b><?= e($groups ? $FTotal : '') ?></b></th></tr>
</table>
</table><br>
</div>
</body>
</html>
