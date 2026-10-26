<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<link rel="stylesheet" href="AutoSuggestDropDown/screen.css">
<title>Item Ledgr</title>
<style>
.ledgr td{
	padding:1px 3px 1px 10px;
}


</style>

    <div align="center"><div id="main">
    <div class="printb"">
      <a href="#" onClick="javascript:window.print()">Print</a>
    </div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date']; 
?>

<div align="center">
<form name="form1" method="post" action="">
<div class="dropdown">
    <input name="ItemName" type="text" id="ItemName" size="50" autocomplete="off" value="<?php if(!empty($_POST['ItemName'])){ echo $_POST['ItemName'];} ?>" class="autosuggest" />
    
            <table class="result"></table>
            <input type="submit" name="Submit" value="Search" />
    </div>
    
   
    <script src="AutoSuggestDropDown/jquery-1.7.1.min.js"></script>
    <script src="AutoSuggestDropDown/primary.js"></script>

	</form>
</div>
<?php
if(!empty($_POST['ItemName'])){
$ItemName=$_POST['ItemName'];

$sqlc="SELECT * FROM canteen_item WHERE ItemName = '$ItemName' " ;
	$rsc=mysql_query($sqlc) or die (mysql_error());
	//echo mysql_num_rows($rsc);
        if(mysql_num_rows($rsc)>0){
            while ($rowc=mysql_fetch_object($rsc)){
			$ItemID= $rowc->ItemID;
			}
		}
} 
else {$ItemID='';}
$pQnty=''; $sQnty=''; $Total='';
?>

<p align="left"><b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>  &nbsp; 
<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?></p>

<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1 ledgr" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="21%">Date</th>
              <th width="18%">Bill No</th>
              <th width="20%">Purchas</td>
              <th width="17%">Sales</th>
              <th width="24%">Balance</th>
            </tr>
<?php

 $itemPur=itemPur("$ItemID");
 $itemSales=itemSales("$ItemID");
$Total= $itemPur-$itemSales;

echo "<tr><td colspan=\"3\"><b>Opening Balance</b></td><td>&nbsp;</td><td>$Total</td></tr>" ;


$result=mysql_query("SELECT Date, BillNo, Qnty, QT FROM canteen_purchase_detail WHERE ItemID = '$ItemID' AND Date BETWEEN '$FormDate' AND '$ToDate'
	UNION ALL
	SELECT Date, SalesID, QT, Qnty FROM canteen_sales_detail WHERE ItemID = '$ItemID' AND Date BETWEEN '$FormDate' AND '$ToDate'
	ORDER BY Date ");
	
if(mysql_num_rows($result) > 0){
while ($re=mysql_fetch_object($result)){
	print "<tr>\n";
	$BillDate= date_create($re->Date);
	$BillDate= date_format($BillDate, 'd-M-Y');	
	print "<td>$BillDate</td>\n";
	print "<td>$re->BillNo</td>\n";
	print "<td>$re->Qnty &nbsp;</td>\n";
	$Total=$Total+$re->Qnty;
	print "<td>$re->QT &nbsp;</td>\n";
	$Total=$Total-$re->QT;
	print "<td>$Total</td>\n";
	echo "</tr>\n";
	}
}
?>
</table>

<?php
function itemPur($ItemID){	
  $FormDate= $_SESSION['form_date'];
        
     $sql="SELECT ItemID,SUM(Qnty) as Qnty FROM canteen_purchase_detail WHERE ItemID='$ItemID' AND Date BETWEEN '0000-00-00 00:00:00' AND '$FormDate' GROUP BY ItemID" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            $row=mysql_fetch_object($rs);
			return $row->Qnty;
      }
	}
	
function itemSales($ItemID){	
 
 $FormDate= $_SESSION['form_date'];
        
      $sql="SELECT ItemID,SUM(Qnty) as Qnty FROM canteen_sales_detail WHERE ItemID='$ItemID' AND Date BETWEEN '0000-00-00 00:00:00' AND '$FormDate' GROUP BY ItemID" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            $row=mysql_fetch_object($rs);
			return $row->Qnty;
      }
	} 
 ?>

<p>&nbsp;</p>

</div>
    </body>
</html>
