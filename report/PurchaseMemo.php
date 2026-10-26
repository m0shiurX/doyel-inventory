<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
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

echo '<h1 align="center">Sales Memo</h1>';


echo '<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<th width="10%">Item ID</th>';
              echo '<th width="40%">Item Name</th>';
              echo '<th width="10%">Qnty</th>';
              echo '<th width="15%">Amount</th>';
            echo '</tr>';

$SalesID=$_GET['id'];
        $qry=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_sales_detail.*
        FROM canteen_sales_detail INNER JOIN canteen_item
        ON canteen_item.ItemID = canteen_sales_detail.ItemID
        WHERE canteen_sales_detail.SalesID='$SalesID'") ;
while ($Detail = mysql_fetch_object($qry)){

if($bgcolor=='#ffffff'){$bgcolor='#f1f1f1';}
        else{$bgcolor='#ffffff';}
		print "<tr bgcolor=$bgcolor>" ;
                print "<td>$Detail->ItemID</td>\n";
	        print "<td>$Detail->ItemName</td>\n";
		print "<td>$Detail->Qnty</td>\n";
		print "<td>$Detail->Amt</td>\n";
		print "</tr>\n";

}
echo "</table>";
 
?>
</div>
    </body>
</html>
