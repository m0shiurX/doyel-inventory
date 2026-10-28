<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$FormDate = is_string($_SESSION['form_date'] ?? null) ? $_SESSION['form_date'] : date('Y-m-d 00:00:00');
$ToDate = is_string($_SESSION['to_date'] ?? null) ? $_SESSION['to_date'] : date('Y-m-d 23:59:59');
$ItemName = is_string($_POST['ItemName'] ?? null) ? $_POST['ItemName'] : '';
$item = $ItemName !== '' ? db_one('SELECT ItemID FROM canteen_item WHERE ItemName = ? ORDER BY ItemID LIMIT 1', [$ItemName]) : null;
$ItemID = $item->ItemID ?? 0;
// DATETIME has no values below zero; <= preserves the old inclusive zero-to-start range
// without an invalid zero-date literal under MySQL 8's default sql_mode.
$opening = db_one('SELECT
    (SELECT SUM(Qnty) FROM canteen_purchase_detail WHERE ItemID = ? AND Date <= ?) AS purchases,
    (SELECT SUM(Qnty) FROM canteen_sales_detail WHERE ItemID = ? AND Date <= ?) AS sales', [$ItemID, $FormDate, $ItemID, $FormDate]);
$Total = (float)($opening->purchases ?? 0) - (float)($opening->sales ?? 0);
// Equal-date rows retain purchase-first, source-primary-key order explicitly.
$movements = db_all('SELECT Date, BillNo, Qnty, QT FROM (
    SELECT Date, BillNo, Qnty, QT, 0 AS source_order, ID AS source_id FROM canteen_purchase_detail WHERE ItemID = ? AND Date BETWEEN ? AND ?
    UNION ALL
    SELECT Date, SalesID AS BillNo, QT AS Qnty, Qnty AS QT, 1 AS source_order, ID AS source_id FROM canteen_sales_detail WHERE ItemID = ? AND Date BETWEEN ? AND ?
    ) movements ORDER BY Date, source_order, source_id', [$ItemID, $FormDate, $ToDate, $ItemID, $FormDate, $ToDate]);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<link rel="stylesheet" href="AutoSuggestDropDown/screen.css">
<title>Item Ledgr</title>
<style>.ledgr td{padding:1px 3px 1px 10px;}</style>
<div align="center"><div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include __DIR__ . '/menu.php'; ?>
<div align="center">
<form name="form1" method="post" action="">
<div class="dropdown">
<input name="ItemName" type="text" id="ItemName" size="50" autocomplete="off" value="<?= e($ItemName) ?>" class="autosuggest" />
<table class="result"></table>
<input type="submit" name="Submit" value="Search" />
</div>
<script src="AutoSuggestDropDown/jquery-1.7.1.min.js"></script>
<script src="AutoSuggestDropDown/primary.js"></script>
</form>
</div>
<p align="left"><b>From:</b> <?= e(date('d-M-Y', strtotime($FormDate))) ?> &nbsp;
<b>To:</b> <?= e(date('d-M-Y', strtotime($ToDate))) ?></p>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1 ledgr" id="tableGd">
<tr bgcolor="#f1f1f1">
<th width="21%">Date</th><th width="18%">Bill No</th><th width="20%">Purchas</td><th width="17%">Sales</th><th width="24%">Balance</th>
</tr>
<tr><td colspan="3"><b>Opening Balance</b></td><td>&nbsp;</td><td><?= e($Total) ?></td></tr>
<?php foreach ($movements as $movement): ?>
<tr>
<td><?= e(date('d-M-Y', strtotime($movement->Date))) ?></td>
<td><?= e($movement->BillNo) ?></td>
<td><?= e(fmt_numeric($movement->Qnty)) ?> &nbsp;</td>
<?php $Total += (float)($movement->Qnty ?? 0); ?>
<td><?= e(fmt_numeric($movement->QT)) ?> &nbsp;</td>
<?php $Total -= (float)($movement->QT ?? 0); ?>
<td><?= e($Total) ?></td>
</tr>
<?php endforeach; ?>
</table>
<p>&nbsp;</p>
</div>
</div>
</body>
</html>
