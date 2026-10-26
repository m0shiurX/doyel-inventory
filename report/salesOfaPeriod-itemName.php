<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <title>Sales of a period Item wise</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">
<div class="printb"">
	<a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 ?>
<h1 align="center">Sales of a period</h1>
<p align="left"><b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>  &nbsp; 
<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?></p>
<h2></h2>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1"  id="tableGd">
    <tr>
          <th width="12%">SL</th>
          <th width="48%">Item Name</th>
          <th width="14%">Amount</th>
          <th width="13%">Qunty</th>
          <th width="13%">Total</th>
	</tr>
<?php
$sl=0; $subTotal=""; $totalAmt="";
$result=mysql_query("SELECT *, canteen_item.ItemType, SUM(canteen_sales_detail.Qnty) AS 'sQnty' FROM canteen_sales
	INNER JOIN canteen_sales_detail ON canteen_sales.SalesID=canteen_sales_detail.SalesID 
	INNER JOIN canteen_item ON canteen_sales_detail.ItemID=canteen_item.ItemID 
	WHERE canteen_sales.Post='1' AND canteen_sales_detail.Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY canteen_item.ItemName"
);

		if(mysql_num_rows($result) > 0){
		while ($re=mysql_fetch_object($result)){

		$sl++;
		print "<tr>" ;
		print "<td>$sl</td>\n";
		print "<td>$re->ItemName</td>\n";
		print "<td>$re->SalesPrice</td>\n";
		print "<td>$re->sQnty</td>\n";
		$tAmt=$re->sQnty*$re->SalesPrice;
		$subTotal=$subTotal+$re->sQnty;
		$totalAmt=$totalAmt+$tAmt;
		print "<td>$tAmt</td>\n";
		print "</tr>\n";
		}
		
		print "<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Sub Total</b></th>
              <th align=\"left\"><b>&nbsp;</b></th>
			  <th align=\"left\"><b>$subTotal</b></th>
              <th align=\"left\"><b>$totalAmt</b></th>
            </tr>";
}


?>
 </table>
<p>&nbsp;</p>
</div>
    </body>
</html>
