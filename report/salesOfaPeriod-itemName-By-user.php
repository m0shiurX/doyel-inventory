<?php session_start() ?>
<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
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

<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1"  id="tableGd">
        <tr>
              <th width="12%">SL</th>
              <th width="48%">Item Name</th>
              <th width="14%">Amount</th>
              <th width="13%">Qunty</th>
              <th width="13%">Total</th>
	</tr>
    
    <?php
$FTotal=0; $fQnty=0;
    $sqlc="SELECT UserName,SUM(Amount) as Amount FROM canteen_sales WHERE Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY UserName" ;
    $rsc=mysql_query($sqlc) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
		
            while ($rowc=mysql_fetch_object($rsc)){
			if($rowc->Amount>0){
			echo '<tr align="left" bgcolor="#fff"><td colspan="5"><b>';
			echo $UserName=$rowc->UserName;
			echo "</b></td></tr>\n";
			list($itemPrint, $subTotal, $totalAmt) =item("$UserName");
			echo $itemPrint;
			$fQnty=$fQnty+$subTotal;
			$FTotal=$FTotal+$totalAmt;
			//$Total=$Total+item("$SupplierName");
			//echo $Total=item("$SupplierName")->$Total;
			//echo 'test';
				}
			}
	    }
		

		
		?>
		<tr>
              <th>&nbsp;</th>
              <th align="left"><b>Total &nbsp;&nbsp; <?php //echo $sTotal->total; ?></b></th>
              <th align="left"><b>&nbsp;</b></th>
              <th align="left"><b><?php echo $fQnty; ?></b></th>
            <th align="left"><b><?php echo $FTotal; ?></b></th>
    </tr>

 </table>

<?php
function item($UserName){

/*Supplier, SUM(Amount) AS 'Amount', SUM(Discount) AS 'Discount', SUM(Total) AS 'Total'*/ 
$FormDate= $_SESSION['form_date']; $ToDate=$_SESSION['to_date'];
$sl=0; $subTotal=0; $totalAmt=0; $itemPrint='';
$result=mysql_query("
SELECT *, SUM(canteen_sales_detail.Qnty) AS 'sQnty'
FROM canteen_sales 
INNER JOIN canteen_sales_detail ON canteen_sales.SalesID=canteen_sales_detail.SalesID 
INNER JOIN canteen_item ON canteen_sales_detail.ItemID=canteen_item.ItemID 
WHERE canteen_sales.UserName = '$UserName' AND canteen_sales.Post='1' AND canteen_item.GenericName !='Row Material' AND canteen_sales.Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY canteen_item.ItemName"
);

		if(mysql_num_rows($result) > 0){
		while ($re=mysql_fetch_object($result)){

		$sl++;
		$itemPrint.= "<tr>" ;
		$itemPrint.= "<td>$sl</td>\n";
		$itemPrint.= "<td>$re->ItemName</td>\n";
		$itemPrint.= "<td>$re->SalesPrice</td>\n";
		$itemPrint.= "<td>$re->sQnty</td>\n";
		$tAmt=$re->sQnty*$re->SalesPrice;
		$subTotal=$subTotal+$re->sQnty;
		$totalAmt=$totalAmt+$tAmt;
		$itemPrint.= "<td>$tAmt</td>\n";
		$itemPrint.= "</tr>\n";
		}
		
		$itemPrint.= "<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Sub Total</b></th>
              <th align=\"left\"><b>&nbsp;</b></th>
			  <th align=\"left\"><b>$subTotal</b></th>
              <th align=\"left\"><b>$totalAmt</b></th>
            </tr>";
			return array($itemPrint,$subTotal,$totalAmt);
	}
}

?>

</div>
    </body>
</html>
