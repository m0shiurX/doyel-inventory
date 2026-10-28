<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$SalesID = is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$bill = $SalesID > 0 ? db_one('SELECT UserName, Date, Discount, Total, Amount, CustomerName, Note FROM canteen_sales WHERE SalesID = ?', [$SalesID]) : null;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="Report.css" />
    <title>Sales Memo</title>
<div id="main" class='memo'>

<?php include __DIR__ . '/menu.php';
$Nickname = '';
if ($bill !== null) {
    $User = $bill->UserName;
    $BillDate = date('d-M-Y', strtotime($bill->Date));
    $BillTime = date('h:i:s A', strtotime($bill->Date));
    $Disc = $bill->Discount;
    $NetTotal = $bill->Total;
    $Total = $bill->Amount;
    $members = db_all('SELECT Nickname, MemberName, address, mobile, mobile2 FROM canteen_customar WHERE Nickname = ? ORDER BY MemberID', [$bill->CustomerName ?? '']);
    $details = db_all('SELECT i.ItemName, d.Rate, d.Qnty, d.Amt FROM canteen_sales_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE d.SalesID = ? ORDER BY d.ID', [$SalesID]);
?>

<div style=""><h1>SALES MEMO</h1></div>
<div style="float:left;">Invoice No: <?= e($SalesID) ?></div>
<div style="float:right;text-align:left;">Date: <?= e($BillDate) ?><br />Time: <?= e($BillTime) ?></div>
<div class="clear customer">
<div class="bg"><img src="../images/logo.png"/></div>
<?php
foreach ($members as $Member) {
    $Nickname = $Member->Nickname;
?>
<h4><span>CustomerName: </span><?= e($Member->MemberName) ?> </h4>
<h4><span>Address: </span><?= e($Member->address) ?> </h4>
<h4><span>Mobile: </span><?= e($Member->mobile . ', ' . $Member->mobile2) ?> </h4>

<?php
}
?>
</div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
            <tr>
              <th width="5%">SL</th>
<!--               <th width="5%">ID</th> -->
              <th width="40%">Item Name</th>
              <th width="10%">Rate</th>
              <th width="10%">Quantity</th>
              <th width="10%" style="padding: 0 30px">Amount</th>
            </tr>
<?php
$Qnty = 0;
$sl = 1;
foreach ($details as $Detail) {
    print '<tr><td>' . $sl++ . '</td><td>' . e($Detail->ItemName) . '</td><td>' . e(fmt_numeric($Detail->Rate)) . '</td>';
    print '<td style="text-align:right; padding-right: 40px;">' . e(fmt_numeric($Detail->Qnty)) . '</td>';
    print '<td style="text-align:right; padding-right: 40px;">' . e(fmt_numeric($Detail->Amt)) . '</td></tr>';
    $Qnty += (float)$Detail->Qnty;
}
?>
            <tr>
              <th colspan="3">Total :</th>
<!--               <th>&nbsp;</th> -->
              <th style="text-align:right; padding-right: 40px;"><?= e($details ? $Qnty : '') ?></th>
              <th style="text-align:right; padding-right: 15px;"><?= e(fmt_money($Total)) ?> Tk</th>
            </tr>
            <?php
            if ($Disc==0) {
                print "<tr style='display:none;'>";
                }
            ?>
              <td colspan='3'>Discount :</td>
<!--               <td>&nbsp;</td> -->
              <td>&nbsp;</td>
              <td  style="text-align:right; padding-right: 15px;"><?= e(fmt_numeric($Disc)) ?> Tk</td>
            </tr>

            <tr>
              <th colspan="3">Net Amount :</th>
<!--               <td>&nbsp;</td> -->
              <td>&nbsp;</td>
              <th style="text-align:right; padding-right: 15px;"><?= e(fmt_money($NetTotal)) ?> Tk</th>
            </tr>
</table>

<div class="footer">
  <br><strong>In Word: </strong>  <?= e(getStringOfAmount($NetTotal)) ?> Taka Only
  <br>
  <br>

  <?php
    $dues = db_one('SELECT (SELECT SUM(Total) FROM canteen_sales WHERE CustomerName = ?) AS dueTotal, (SELECT SUM(amount) FROM canteen_collection WHERE Description = ?) AS paidTotal', [$Nickname, $Nickname]);
    $amount = (float)($dues->dueTotal ?? 0) - (float)($dues->paidTotal ?? 0);
    if ($amount > 0) print '<b><h1>TOTAL DUE TAKA &nbsp; ' . e($amount) . ' /=</h1> </b>';
    else print '<b><h1>ADVANCE TAKA &nbsp; ' . e(abs($amount)) . ' /=</h1></b>';

  ?>

  <br />
  <br />
  <?php
      print '<b> Note: </b> ' . e($bill->Note) . "\n";
  ?>
</div>
<br />
<div align="center" id="print" style="width:200px; float:left;">
<a href="#" onClick="javascript:window.print()">Print</a></div>
<div align="center" style="float:right; width:100px; margin-right:80px;">
<hr><?= e($User) ?>
</div>
<?php } ?>

</div>
    </body>
</html>
