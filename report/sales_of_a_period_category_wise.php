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
<?php

$query=mysql_query("SELECT ItemType FROM canteen_item GROUP BY ItemType");
// $rsc=mysql_query($query, $conn) or die (mysql_error());
    if(mysql_num_rows($query)>0){
      while ($rowc=mysql_fetch_object($query)){
        echo '<tr align="left" bgcolor="#fff"><td colspan="5"><b>';
        $ItemType=$rowc->ItemType;
        echo "Category : ".typeName($ItemType);
        echo "</b></td></tr>\n";
        print "<tr>
        <th width=\"17%\"><b>ID</b></th>
        <th width=\"42%\"><b>Name of Item </b></th>
        <th width=\"21%\"><b>Quantity</b></th>
        <th width=\"20%\"><b>Amount</b></th>
        </tr>";
        salesItem("$ItemType");
        }
    }
 echo "</table>";




function salesItem($productCat)
{	
	$FormDate= $_SESSION['form_date'];
 	$ToDate=$_SESSION['to_date'];
	$sl=0; $subTotal=""; $totalAmt="";
	$result=mysql_query("SELECT *, SUM(canteen_sales_detail.Qnty) AS 'sQnty' FROM canteen_sales
		INNER JOIN canteen_sales_detail ON canteen_sales.SalesID=canteen_sales_detail.SalesID 
		INNER JOIN canteen_item ON canteen_sales_detail.ItemID=canteen_item.ItemID 
		WHERE canteen_sales.Post='1' AND ItemType='$productCat' AND canteen_sales_detail.Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY canteen_item.itemName"
	);

			if(mysql_num_rows($result) > 0){
			while ($re=mysql_fetch_object($result))
			{
				
				$sl++;
				print "<tr>" ;
				print "<td>$sl</td>\n";
				print "<td>$re->ItemName</td>\n";
				// print "<td>$re->SalesPrice</td>\n";
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
				  <th align=\"left\"><b>$subTotal</b></th>
	              <th align=\"left\"><b>$totalAmt</b></th>
	            </tr>";
	}
}

function typeName($Name){
  switch ($Name) {
    case 'P':
      return 'PISTON';
      break;
    case 'L':
      return 'LINER';
      break;
    case 'AP':
      return 'Alay Puli';
      break;
    case 'DP':
      return 'Dhalay Puli';
      break;
    case 'BC':
      return 'Bearing Case';
      break;
    case 'H':
      return 'Haps'; 
      break;
    case 'DT':
      return 'Dhalay Taoa';
      break;
    case 'AK':
      return 'Alay Koray';
      break;
    case 'BD':
      return 'Break Drum';
      break;
    case 'AF':
      return 'Alay Fan';
      break;
    default:
      return 'Others';
      break;
  }
}

?>
<p>&nbsp;</p>
</div>
    </body>
</html>
