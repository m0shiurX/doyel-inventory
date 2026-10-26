<?php session_start() ?>
<!--
To change this template, choose Tools | Templates
and open the template in the editor.
-->
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Unposted Bill</title>
		<div align="center"><div id="main">

<?php include("menu.php");
include '../connect.php';
?>
 <h1>Unposted Bill</h1>

 <div align="left"><h1>Sales</h1></div>
 <table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
            <tr>
              <th width="10%">Bill No</th>
              <th width="20%">Date</th>
              <th width="20%">Amount</th>
            </tr>
<?php
$result=mysql_query("SELECT * FROM canteen_sales WHERE Post='0'");


while ($re=mysql_fetch_object($result)){
$BillDate= date_create($re->Date);
$BillDate=date_format($BillDate, 'd-M-Y');

		print "<tr>" ;
        print "<td>$re->SalesID</td>\n";
	    print "<td>$BillDate</td>\n";
		$BillNo=$re->SalesID;
		$amount=amosales($BillNo);
		print "<td>$amount</td>\n</tr>\n";
}
?>
</table> 
 
<h1 align="left">Purchase</h1>
 <table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
            <tr>
              <th width="10%">Bill No</th>
              <th width="20%">Date</th>
              <th width="20%">Amount</th>
            </tr>
<?php
$result=mysql_query("SELECT * FROM canteen_purchase WHERE Post='0'");

while ($re=mysql_fetch_object($result)){
$BillDate= date_create($re->Date);
$BillDate=date_format($BillDate, 'd-M-Y');

		print '<tr>' ;
        print "<td>$re->BillNo</td>\n";
	    print "<td>$BillDate</td>\n";
		$BillNo=$re->BillNo;
		$amount=amo($BillNo);
		print "<td>$amount</td>\n</tr>\n";
}
?>
</table>


<?php
function amo($BillNo){
$sT="SELECT SUM(Amt) as Amt FROM canteen_purchase_detail WHERE BillNo ='$BillNo'" ;
$srs=mysql_query($sT) or die (mysql_error());	  
$sTotal=mysql_fetch_object($srs);
$sTotal=$sTotal->Amt;
$sTotal=round($sTotal, 2);
return "$sTotal";
}

function amosales($BillNo){
$sT="SELECT SUM(Amt) as Amt FROM canteen_sales_detail WHERE SalesID ='$BillNo'" ;
$srs=mysql_query($sT) or die (mysql_error());	  
$sTotal=mysql_fetch_object($srs);
$sTotal=$sTotal->Amt;
$sTotal=round($sTotal, 2);
return "$sTotal";
}

function purchaseItem($FormDate,$ToDate){

echo '<h1>Purchase of a piriod (Item wais)</h1>';
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<td width="10%"><b>Item ID</b></td>';
              echo '<td width="40%"><b>Item</b></td>';
              echo '<td width="15%"><b>Rate</b></td>';
              echo '<td width="15%"><b>Quantity</b></td>';
              echo '<td width="20%"><b>Amount</b></td>';
            echo '</tr>';

$result=mysql_query("SELECT *,SUM(Qnty) as Quntity FROM canteen_purchase_detail GROUP BY ItemName");

while ($re=mysql_fetch_object($result)){

if($bgcolor=='#ffffff'){$bgcolor='#f1f1f1';}
        else{$bgcolor='#ffffff';}
		print "<tr bgcolor=$bgcolor>" ;
                print "<td>$re->ItemID</td>\n";
	        print "<td>$re->ItemName</td>\n";
		print "<td>$re->Rate</td>\n";
		print "<td>$re->Quntity</td>\n";
                $Total=$re->Rate*$re->Quntity;
		print "<td>$Total</td></tr>\n";
                //$Amount=$Amount+$re->Amount;
                //$Discount=$Discount+$re->Discount;
                //$Total=$Total+$re->Total;

}


}




 ?>
 
<p>&nbsp;</p>

</div>
    </body>
</html>
