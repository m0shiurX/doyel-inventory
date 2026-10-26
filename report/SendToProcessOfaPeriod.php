<?php session_start() ?>
<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <title>Send To Factory of a period</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">

<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 ?>
<h1 align="center">Send To Factory of a period</h1>

<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1"  id="tableGd">
        <tr>
              <th width="12%">SL</th>
              <th width="48%">Item Name</th>
              <th width="14%">Amount</th>
              <th width="13%">Qunty</th>
              <th width="13%">Total</th>
	</tr>
<?php
/*Supplier, SUM(Amount) AS 'Amount', SUM(Discount) AS 'Discount', SUM(Total) AS 'Total'*/ 
$sl=0; $subTotal=""; $totalAmt="";
$result=mysql_query("
SELECT *, SUM(canteen_sales_detail.Qnty) AS 'sQnty'
FROM canteen_sales_detail 
INNER JOIN canteen_item ON canteen_sales_detail.ItemID=canteen_item.ItemID 
WHERE canteen_item.GenericName ='Row Material' AND canteen_sales.Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY canteen_item.ItemName"
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
              <th>&nbsp;</th>
              <th>Sub Total</th>
              <th>&nbsp;</th>
			  <th>$subTotal</th>
              <th>$totalAmt</th>
            </tr>";
}


?>
 </table>
<p>&nbsp;</p>

</div>
    </body>
</html>