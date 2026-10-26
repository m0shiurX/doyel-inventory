<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Closing Balance</title>
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php");
include '../connect.php';

	$SlNo=$_GET['SlN'];
	
    $sql="SELECT * FROM canteen_stock WHERE SlNo='$SlNo'" ;
	       $rs=mysql_query($sql) or die (mysql_error());
           $row=mysql_fetch_object($rs);
		   $Date=date_create($row->Date);
			$Date=date_format($Date, 'd-M-Y h:i:s A');	
	
?> 
<div align="center"><h1>Closing Balance Date - <?php echo $Date; ?></h1></div>

 
 
 <table width="100%" border="0" cellspacing="0" cellpadding="1" id="tableGd">
              <tr>
              <th width="10%">ID</th>
              <th width="51%">Name of Item</th>
              <th width="11%">Pur Price</th>
			         <th width="14%">Quantity</th>
              <th width="14%">Total</th>
            </tr>
<?php
    $FTotal="";
    $sqlc="SELECT SupplierName FROM canteen_stock_detail WHERE SlNo='$SlNo' GROUP BY SupplierName" ;
	       $rsc=mysql_query($sqlc) or die (mysql_error());
            while ($rowc=mysql_fetch_object($rsc)){
			echo '<tr><td colspan="5"><b>';
			echo $SupplierName=$rowc->SupplierName;
			echo "</b></td></tr>\n";
			list($itemPrint, $Total) =item("$SupplierName");
			echo $itemPrint;
			$FTotal=$FTotal+$Total;
	    }
		
		
//$SlNo=$_GET['SlN'];		
$sT="SELECT count(*) as total,SUM(Qnty) as tQnty,SUM(PurPrice) as tPurPrice FROM canteen_stock_detail WHERE SlNo ='$SlNo'" ;
$srs=mysql_query($sT) or die (mysql_error());	  
$sTotal=mysql_fetch_object($srs);
$tPurPrice=$sTotal->tPurPrice;
$tPurPrice=round($tPurPrice, 2);
		
		?>
		<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Total: <?php echo $sTotal->total; ?></b></th>
              <th align="left"><b><?php echo $tPurPrice; ?></b></th>
              <th align="left"><b><?php echo $sTotal->tQnty; ?></b></th>
			  <th align="left"><b><?php echo $FTotal; ?></b></th>
    </tr>
  </table>
 
<!-- <table width="100%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="5%"><b>ID</b></th>
              <th width="24%"><b>Name of Item </b></th>
              <th width="21%"><strong>GenericName</strong></th>
              <th width="8%"><b>Pur Price</b></th>
              <th width="8%"><b>Qnty</b></th>
			  <th width="12%"><b>Price</b></th>
            </tr>-->
<?php
function item($SupplierName){
    $itemPrint="";
    $PurPrice="";
    $Qnty="";
    $tQnty="";
    $Total="";
    $sl="0";
        $SlNo=$_GET['SlN'];
       $sql="SELECT * FROM canteen_stock_detail WHERE SupplierName='$SupplierName' AND SlNo ='$SlNo' ORDER BY ItemName" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            while ($row=mysql_fetch_object($rs)){
			
		$itemPrint.= "<tr>" ;
                $itemPrint.= "<td>$row->ItemID</td>\n";
		$itemPrint.= "<td>$row->ItemName</td>\n";
		$itemPrint.= "<td>$row->PurPrice</td>\n";
		$PurPrice=$PurPrice+$row->PurPrice;
		$itemPrint.= "<td>$row->Qnty</td>\n";
		$Qnty=$Qnty+$row->Qnty;
		$Price=$row->PurPrice*$row->Qnty;
		$itemPrint.= "<td>$Price</td>\n";
		$itemPrint.= "</tr>\n";
		$tQnty=$tQnty+$row->Qnty;
		$Total=$Total+$Price;
                $sl++;
        }
		$rows=mysql_fetch_object($rs);
		$PurPrice=round($PurPrice, 2);
		//$rows->sub_total
                $itemPrint.= "<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Sub Total - $sl</b></th>
              <th align=\"left\"><b>$PurPrice</b></th>
			  <th align=\"left\"><b>$Qnty</b></th>
              <th align=\"left\"><b>$Total</b></th>
            </tr>";
			
		return array($itemPrint,$Total);
        }
		
	}
	  
 ?>

</table><br>

</div>
    </body>
</html>
