<?php session_start() ?>
<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <title>Sales of a period supplier name wise</title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
		<div align="center"><div id="main">
		<div class="printb"">
			<a href="#" onClick="javascript:window.print()">Print</a>
		</div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 ?>
<h1>Sales of a period supplier name wise</h1>
<p align="left"><b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>  &nbsp; 
<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?></p>

<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <td width="12%"><b>SL</b></td>
              <td width="48%"><b>Item Name</b></td>
              <td width="14%"><b>Amount</b></td>
              <td width="13%"><b>Qunty</b></td>
              <td width="13%"><b>Total</b></td>
			  </tr>
<?php
$gTotal=""; $gTotalQunt="";
    $sqlc="SELECT SupplierName FROM canteen_item WHERE D='0' GROUP BY SupplierName" ;
	       $rsc=mysql_query($sqlc, $conn) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
		
            while ($rowc=mysql_fetch_object($rsc)){
			$SupplierName=$rowc->SupplierName;
			list($itemPrint, $sl, $subTotal, $totalAmt) =itemNamereport("$SupplierName");
			if($sl>0){
			echo '<tr align="left" bgcolor="#fff"><th colspan="5">';
			echo $rowc->SupplierName;
			echo "</th></tr>\n";
			echo $itemPrint;
			$gTotal=$gTotal+$totalAmt;
			$gTotalQunt=$gTotalQunt+$subTotal;
				}
			}
			
			print "<tr bgcolor=\"#f1f1f1\">
              <th><b>&nbsp;</b></th>
              <th><b>Gand Total</b></th>
              <th align=\"left\"><b>&nbsp;</b></th>
			  <th align=\"left\"><b>$gTotalQunt</b></th>
              <th align=\"left\"><b>$gTotal</b></th>
            </tr>\n";
	    }




function itemNamereport ($SupplierName){

$itemPrint=""; $tAm=""; $subTotal=""; $totalAmt="";

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
$result=mysql_query("
SELECT *, SUM(canteen_sales_detail.Qnty) AS 'sQnty'
FROM canteen_sales_detail 
INNER JOIN canteen_item ON canteen_sales_detail.ItemID=canteen_item.ItemID 
WHERE canteen_item.SupplierName='$SupplierName' AND canteen_sales_detail.Date 
BETWEEN '$FormDate' AND '$ToDate' GROUP BY canteen_item.ItemName"
);

	if(mysql_num_rows($result) > 0){
            $sl=0; 
		while ($re=mysql_fetch_object($result)){

		$sl++;
		$itemPrint.= '<tr>' ;
		$itemPrint.= "<td>$sl</td>\n";
		$itemPrint.= "<td>$re->ItemName</td>\n";
		$itemPrint.= "<td>$re->SalesPrice</td>\n";
		$itemPrint.= "<td>$re->sQnty</td>\n";
		$tAmt=$re->sQnty*$re->SalesPrice;
		$subTotal=$subTotal+$re->sQnty;
		//$totalQunt=$totalQunt+$subTotal;
		$totalAmt=$totalAmt+$tAmt;
		$itemPrint.= "<td>$tAmt</td>\n";
		$itemPrint.= "</tr>\n";
		}
		
		$itemPrint.="<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Sub Total :- $sl</b></th>
              <th align=\"left\"><b>&nbsp;</b></th>
            <th align=\"left\"><b>$subTotal</b></th>
              <th align=\"left\"><b>$totalAmt</b></th>
            </tr>";
		
		return array($itemPrint,$sl,$subTotal,$totalAmt);
	}

}
?>
 </table>
<p>&nbsp;</p>

</div>
    </body>
</html>
