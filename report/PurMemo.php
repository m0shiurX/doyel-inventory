<?php session_start() ?>
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
<?php include("menu.php");
include '../connect.php';

if (!empty($_GET['id'])) {
$SalesID = $_GET['id'];

$sql=mysql_query("SELECT * FROM canteen_purchase WHERE BillNo='$SalesID'") ;
while ($BillInfo = mysql_fetch_object($sql)){
$User=$BillInfo->UserName;
$BDate= date_create($BillInfo->Date);
$BillDate= date_format($BDate, 'd-M-Y');
$BillTime = date_format($BDate, 'h:i:s A');
$Disc=$BillInfo->Discount;
$NetTotal=$BillInfo->Total;
$Total=$BillInfo->Amount;
}
?>
<div style="float:left; width:33%;">Invoice No: <?php echo $SalesID; ?></div>
<div align="center" style="width:33%; float:left; margin-bottom:10px;"><h1>PURCHASE MEMO</h1></div>
<div style="float:right; text-align:right; width:33%;">Date: <?php print $BillDate; echo '<br />Time: '.$BillTime ?></div>
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
$Qnty="";
$sl=1;
$SalesID=$_GET['id'];
        $qry=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_purchase.Supplier, canteen_purchase_detail.*
        FROM canteen_purchase_detail INNER JOIN canteen_item
        ON canteen_item.ItemID = canteen_purchase_detail.ItemID
        INNER JOIN canteen_purchase ON canteen_purchase_detail.BillNo = canteen_purchase.BillNo
        WHERE canteen_purchase_detail.BillNo='$SalesID'") ;
		while ($Detail = mysql_fetch_object($qry)){
		print "<tr>" ;
        print "<td>$sl</td>\n";
        // print "<td>$Detail->ItemID</td>\n";
        print "<td>$Detail->ItemName</td>\n";
        print "<td>$Detail->Supplier</td>\n";
		print "<td>$Detail->Rate</td>\n";
		print "<td>$Detail->Qnty</td>\n";
		print "<td>$Detail->Amt</td>\n";
		print "</tr>\n";
		$Qnty=$Qnty+$Detail->Qnty;
		$sl++;
}
?>
		<tr>
              <th>&nbsp;</th>
              <th colspan="3">Total :</th>
              <th><?php echo $Qnty; ?></th>
              <th><?php echo $Total; ?> Tk</th>
            </tr>
            <?php
            if ($Disc==0) {
                print "<tr style='display:none;'>";
                }
            ?>
                          <td>&nbsp;</td>
                          <td colspan='3'><b>Discount :</b></td>
                          <td><b>&nbsp;</b></td>
                          <td><b><?php echo $Disc; ?> Tk</b></td>
                        </tr>
			<tr>
              <th>&nbsp;</th>
              <th colspan="3"><b>Net Amount :</b></th>
              <th>&nbsp;</td>
              <th><?php echo $NetTotal; ?> Tk</th>
            </tr>
</table>
<br>
<br><strong>In Word: </strong> <?php echo getStringOfAmount($NetTotal); ?>
<br>
<br>
<br>
<div align="center" id="print" style="width:200px; float:left;">
<a id="print" href="#" onClick="javascript:window.print()">Print</a></div>
<div align="center" style="float:right; width:100px; margin-right:80px;">
<hr><?php echo $User; ?>
</div>

<?php } ?>
</div>
    </body>
</html>