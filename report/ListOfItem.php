<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
// Aggregate and ordered rows travel together; no query is issued per category.
$rows = db_all('SELECT i.ItemID, i.ItemName, i.PurPrice, i.SalesPrice, i.Qnty,
    g.GenericName AS GroupName, g.GroupID, g.sub_total, g.GroupPurPrice, g.GroupSalesPrice, g.Quantity
    FROM (SELECT GenericName, MIN(ItemID) AS GroupID, COUNT(*) AS sub_total,
        SUM(PurPrice) AS GroupPurPrice, SUM(SalesPrice) AS GroupSalesPrice, SUM(Qnty) AS Quantity
        FROM canteen_item WHERE D = ? GROUP BY GenericName) g
    JOIN canteen_item i ON i.GenericName = g.GenericName AND i.D = ?
    ORDER BY g.GenericName, i.ItemName, i.ItemID', [0, 0]);
$groups = [];
foreach ($rows as $row) {
    $groups[$row->GroupID]['summary'] = $row;
    $groups[$row->GroupID]['items'][] = $row;
}
$Total = 0;
$TotalPrice = 0;
$TotalQnty = 0;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>List of Item</title>
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include __DIR__ . '/menu.php'; ?>
<div align="center"><h1>List of Item</h1></div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" id="tableGd">
<tr>
<th width="17%"><b>ID</b></th>
<th width="42%"><b>Name of Item </b></th>
<th width="20%"><b>Pur Price</b></th>
<th width="21%"><b>Sales Price</b></th>
</tr>
<?php foreach ($groups as $group): $summary = $group['summary']; ?>
<tr align="left" bgcolor="#fff"><td colspan="4"><b><?= e($summary->GroupName) ?></b></td></tr>
<?php foreach ($group['items'] as $row): ?>
<tr id="<?= e($row->ItemID) ?>">
<td><?= e($row->ItemID) ?></td>
<td><?= e($row->ItemName) ?></td>
<td><?= e(fmt_numeric($row->PurPrice)) ?></td>
<td><?= e(fmt_numeric($row->SalesPrice)) ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th><b>&nbsp;</b></th>
<th><b>Sub Total &nbsp;&nbsp; <?= e($summary->sub_total) ?></b></th>
<th><b><?= e(round((float)$summary->GroupPurPrice, 2)) ?></b></th>
<th><b><?= e(round((float)$summary->GroupSalesPrice, 2)) ?></b></th>
</tr>
<?php
$Total += (int)$summary->sub_total;
$TotalPrice += round((float)$summary->GroupPurPrice, 2);
$TotalQnty += (float)$summary->Quantity;
endforeach;
?>
<tr>
<th><b>&nbsp;</b></th>
<th><b>Total &nbsp;&nbsp; <?= e($groups ? $Total : '') ?></b></th>
<th><b><?= e($groups ? $TotalPrice : '') ?></b></th>
<th><b>&nbsp;</b></th>
</tr>
</table>
</div>
</body>
</html>
