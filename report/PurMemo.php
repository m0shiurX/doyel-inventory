<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$id = is_scalar($_GET['id'] ?? null) ? (string)$_GET['id'] : '';
$SalesID = ctype_digit($id) ? (int)$id : 0;
$BillInfo = $SalesID > 0 ? db_one('SELECT UserName, Date, Discount, Total, Amount FROM canteen_purchase WHERE BillNo = ?', [$SalesID]) : null;
?>
<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Purchase Memo</title>
    <link rel="stylesheet" type="text/css" href="Report.css">
    <style>
        body {
            width: 800px;
            margin-left:auto;
            margin-right:auto;
            }

        h1{margin:0 0 0 10px;}
    </style>
    <style type="text/css" media="print">#print{display:none;}</style>
<div id="main" class=" memo">
<?php include 'menu.php';
if ($BillInfo !== null) {
    $User = $BillInfo->UserName;
    $BDate = date_create($BillInfo->Date);
    $BillDate = $BDate ? date_format($BDate, 'd-M-Y') : '';
    $BillTime = $BDate ? date_format($BDate, 'h:i:s A') : '';
    $Disc = $BillInfo->Discount;
    $NetTotal = $BillInfo->Total;
    $Total = $BillInfo->Amount;
?>
<div style="float:left; width:33%;">Invoice No: <?= e($SalesID) ?></div>
<div align="center" style="width:33%; float:left; margin-bottom:10px;"><h1>PURCHASE MEMO</h1></div>
<div style="float:right; text-align:right; width:33%;">Date: <?= e($BillDate) ?><br />Time: <?= e($BillTime) ?></div>
<div class="clear">
<div class="bg"><img src="../images/logo.png"/></div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
              <tr>
              <th width="5%">SL</th>
<!--               <th width="10%">Item ID</th> -->
              <th width="35%">Item Name</th>
              <th width="15%">Supplier</th>
              <th width="10%">Rate</th>
              <th width="15%">Qnty</th>
              <th width="20%">Amount</th>
            </tr>
<?php
$Qnty = 0;
$sl = 1;
foreach (db_all('SELECT i.ItemName, p.Supplier, d.Rate, d.Qnty, d.Amt FROM canteen_purchase_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID INNER JOIN canteen_purchase p ON p.BillNo = d.BillNo WHERE d.BillNo = ? ORDER BY d.ID', [$SalesID]) as $Detail) {
    echo '<tr><td>' . $sl++ . '</td><td>' . e($Detail->ItemName) . '</td><td>' . e($Detail->Supplier) . '</td><td>' . e($Detail->Rate) . '</td><td>' . e($Detail->Qnty) . '</td><td>' . e($Detail->Amt) . '</td></tr>';
    $Qnty += (float)$Detail->Qnty;
}
?>
        <tr>
              <th>&nbsp;</th>
              <th colspan="3">Total :</th>
              <th><?= e($Qnty) ?></th>
              <th><?= e($Total) ?> Tk</th>
            </tr>
            <?php
            if ($Disc==0) {
                print "<tr style='display:none;'>";
                }
            ?>
                          <td>&nbsp;</td>
                          <td colspan='3'><b>Discount :</b></td>
                          <td><b>&nbsp;</b></td>
                          <td><b><?= e($Disc) ?> Tk</b></td>
                        </tr>
            <tr>
              <th>&nbsp;</th>
              <th colspan="3"><b>Net Amount :</b></th>
              <th>&nbsp;</td>
              <th><?= e($NetTotal) ?> Tk</th>
            </tr>
</table>
<br>
<br><strong>In Word: </strong> <?= e(getStringOfAmount($NetTotal)) ?>
<br>
<br>
<br>
<div align="center" id="print" style="width:200px; float:left;">
<a id="print" href="#" onClick="javascript:window.print()">Print</a></div>
<div align="center" style="float:right; width:100px; margin-right:80px;">
<hr><?= e($User) ?>
</div>

<?php } ?>
</div>
    </body>
</html>
