<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <title>Add to Stock : Group by Bill</title>
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

<h1 align="center">Purchase of a period</h1>

<form name="form1" method="GET" action="">
 User Name:
	 <select name="user" id="user" onChange="form1.submit();">
	 <?php
	  if(!empty($_GET['user']))
	  {
		$UserName=$_GET['user'];
		echo "<option>$UserName</option>";
	  }
	  
	  $sqluser="SELECT UserName FROM canteen_purchase WHERE Post = '1' AND Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY UserName" ;
	  $sqluser=mysql_query($sqluser) or die (mysql_error());

	  if(mysql_num_rows($sqluser)>0){
		  while ($sqluser1=mysql_fetch_object($sqluser))
		  {
		  echo "<option>$sqluser1->UserName</option>";
		  }
	  }
	 ?>
	 </select>
</form>

<p align="left">
	<b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>&nbsp; 
	<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?>
</p>

<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1"  id="tableGd">
<tr>
              <th width="12%">SL</th>
              <th width="48%">Item Name</th>
              <th width="14%">Qunty</th>
              <th width="13%">Amount</th>
              <th width="13%">Total</th>
	</tr>
    
<?php
if(!empty($_GET['user'])){
	$UserName=$_GET['user'];
	$sqlc="SELECT * FROM canteen_purchase WHERE UserName = '$UserName' AND Post = '1' AND Date BETWEEN '$FormDate' AND '$ToDate'" ;	
	}
	else
		{
			$sqlc="SELECT * FROM canteen_purchase WHERE Post = '1' AND Date BETWEEN '$FormDate' AND '$ToDate'" ;
		}

	$FTotal=0; 
    $rsc=mysql_query($sqlc) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
		
            while ($rowc=mysql_fetch_object($rsc)){
			echo '<tr align="left" bgcolor="#fff">
			<td colspan="5"><b>';
			echo 'Bill No : '.$BillNo=$rowc->BillNo;
			echo "&nbsp;&nbsp;&nbsp; Date : &nbsp;&nbsp;&nbsp;";
			$BillDate=date_create($rowc->Date);
			print date_format($BillDate, 'd-M-Y, h:i:s A');
			echo "</td>\n";
			echo "</tr>\n";
			list($itemPrint) =item("$BillNo");
			echo $itemPrint;
			echo '<tr>';
			echo "<th>&nbsp;</th><th>&nbsp;</th><th>$rowc->Amount</th><th>$rowc->Discount</th><th>$rowc->Total</th>";
			echo "</tr>\n";
			$FTotal=$FTotal+$rowc->Total;
			}
	    }
		

?>
		<tr>
              <th>&nbsp;</th>
              <th align="left"><b>Total &nbsp;&nbsp; <?php //echo $sTotal->total; ?></b></th>
              <th align="left"><b>&nbsp;</b></th>
              <th align="left"><b><?php //echo $fQnty; ?></b></th>
            <th align="left"><b><?php echo $FTotal; ?></b></th>
    </tr>

 </table>

<?php
function item($BillNo){

$sl=0; $subTotal=0; $totalAmt=0; $itemPrint='';
$result=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_purchase_detail.* FROM canteen_purchase_detail INNER JOIN canteen_item ON canteen_item.ItemID = canteen_purchase_detail.ItemID
        WHERE canteen_purchase_detail.BillNo='$BillNo'"
);

		if(mysql_num_rows($result) > 0){
		while ($re=mysql_fetch_object($result)){

		$sl++;
		$itemPrint.= "<tr>" ;
		$itemPrint.= "<td>$sl</td>\n";
		$itemPrint.= "<td>$re->ItemName</td>\n";
		$itemPrint.= "<td>$re->Qnty</td>\n";
		$itemPrint.= "<td>$re->Rate</td>\n";
		$itemPrint.= "<td>$re->Amt</td>\n";
		$itemPrint.= "</tr>\n";
		}
		
		return array($itemPrint);
	}
}

?>

</div>
    </body>
</html>
