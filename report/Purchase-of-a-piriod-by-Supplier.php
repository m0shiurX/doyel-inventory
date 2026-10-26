<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Add to Stock</title>

		<div align="center"><div id="main">
    <div class="printb"">
      <a href="#" onClick="javascript:window.print()">Print</a>
    </div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 
 if(!empty($_GET['s']))
 {
$Supplier=$_GET['s'];

print "<h1>Purchase of a period  $Supplier</h1>";
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">';
              echo '<tr>';
              echo '<td width="10%"><b>Bill No</b></td>';
              echo '<td width="20%"><b>Date</b></td>';
              echo '<td width="15%"><b>Amount</b></td>';
              echo '<td width="15%"><b>Discount</b></td>';
              echo '<td width="15%"><b>Total</b></td>';
            echo '<td width="15%"><b>Action</b></td>';
            echo '</tr>';

$result=mysql_query("SELECT * FROM canteen_purchase WHERE Supplier='$Supplier' AND Post='1' AND Date BETWEEN '$FormDate' AND '$ToDate'");

$Amount="";$Discount="";$Total="";

while ($re=mysql_fetch_object($result)){
$BillDate= date_create($re->Date);
$BillDate=date_format($BillDate, 'd-M-Y');

                print '<tr>' ;
                print "<td>$re->BillNo</td>\n";
	        print "<td>$BillDate</td>\n";
		print "<td>$re->Amount</td>\n";
		print "<td>$re->Discount</td>\n";
		print "<td>$re->Total</td>\n";
		print "<td><a href=\"PurMemo.php?id=$re->BillNo\">View</a></td></tr>\n";
                $Amount=$Amount+$re->Amount;
                $Discount=$Discount+$re->Discount;
                $Total=$Total+$re->Total;

}
echo "<tr><td></td><td>Total</td><td>$Amount</td><td>$Discount</td><td>$Total</td><td>&nbsp;</td>" ;
echo '</table>';
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
