<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
$today = new DateTimeImmutable('today');
$month = $today->modify('first day of this month');
$end = $today->modify('+1 day')->format('Y-m-d H:i:s');
$bounds = [$month->format('Y-m-d H:i:s'), $end, $month->modify('-1 month')->format('Y-m-d H:i:s'), $month->format('Y-m-d H:i:s'), $today->format('Y-01-01 00:00:00'), $end];
// The table name is selected exclusively from this fixed map.
$totals = [];
foreach (['collection' => 'canteen_collection', 'expenses' => 'canteen_expenses'] as $kind => $table) {
    $totals[$kind] = db_one("SELECT SUM(amount) AS allTotal, SUM(CASE WHEN Date >= ? AND Date < ? THEN amount END) AS monthTotal, SUM(CASE WHEN Date >= ? AND Date < ? THEN amount END) AS previousTotal, SUM(CASE WHEN Date >= ? AND Date < ? THEN amount END) AS yearTotal FROM $table", $bounds);
}
$cash = (float)($totals['collection']->allTotal ?? 0) - (float)($totals['expenses']->allTotal ?? 0);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">

<div align="center"><div id="main">
<?php include("menu.php"); ?>
<div style=" height:450px;display:block; margin-bottom:30px; margin-top:50px;">
  <div align="left" style="display:block;width:48%; float:left;overflow: hidden;" class="report">
    <h2>Menu</h2>
    <ul style="margin:0 15px 20px 0; padding-left:10px;"><!--
      <li><a href="Supplier.php">Supplier Payment</a></li> -->
      <li><a href="CashCollection.php">Cash Collection</a></li>
      <li><a href="Expenses.php">Expenses</a></li>
      <li><a href="HeadOfAccount.php">Head Of Account</a></li>
    </ul>
    <p align="right">
    <a href="Backup/new_backup.php" target="_blank">Data Backup </a>
    <a href="Unpost.php">Administrator</a>
    <a href="list_of_user.php">Add User</a>
    <a href="javascript:void(0);" onClick=window.open("password.php","Ratting","width=450,height=300,0,status=0,");>Change Password</a>
    </p>
  </div>

  <div align="left" style="display:block;width:48%; float:right;overflow: hidden" class="report">
    <h2>Report</h2>
    <div style="margin:0 15px 20px 5px; padding-left:10px;">
      <div class="list">
        <div style="width:230px; float:left">Cash in Hand</div>
        <div style="float:left"><?= e($cash) ?> Taka</div>
    </div>

    <div class="list">
        <div style="width:230px; float:left">Cash Collection This Month</div>
        <div style="float:left"><?= e($totals['collection']->monthTotal) ?> Taka</div>
    </div>
    <div class="list">
        <div style="width:230px; float:left">Cash Expenses This Month</div>
        <div style="float:left"><?= e($totals['expenses']->monthTotal) ?> Taka</div>
    </div>
    <div class="list">
        <div style="width:230px; float:left">Cash Collection Previous Month</div>
        <div style="float:left"><?= e($totals['collection']->previousTotal) ?> Taka</div>
    </div>
    <div class="list">
        <div style="width:230px; float:left">Cash Expenses Previous Month</div>
        <div style="float:left"><?= e($totals['expenses']->previousTotal) ?> Taka</div>
    </div>
    <div class="list">
        <div style="width:230px; float:left">Cash Collection This Year</div>
        <div style="float:left"><?= e($totals['collection']->yearTotal) ?> Taka</div>
    </div>
    <div class="list">
        <div style="width:230px; float:left">Cash Expenses This Year</div>
        <div style="float:left"><?= e($totals['expenses']->yearTotal) ?> Taka</div>
    </div>

    </div>
  </div>

</div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
