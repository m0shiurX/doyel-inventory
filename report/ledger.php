<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<div id="main">

<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 

echo '<div align="center"><h1>Book Issue Member wais Ledger</h1></div>';


$nic=$_GET['member'];

echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<td width="10%"><b>Date</b></td>';
                echo '<td width="10%"><b>ID</b></td>';
              echo '<td width="35%"><b>Item</b></td>';
              echo '<td width="15%"><b>Isuue</b></td>';
              echo '<td width="10%"><b>Return</b></td>';
              echo '<td width="10%"><b>Amount</b></td>';
			  echo '<td width="10%"><b>Balance</b></td>';
            echo '</tr>';
            
$Qa=mysql_query("SELECT canteen_sales.SalesID, canteen_sales.Date, canteen_sales_detail.*
FROM canteen_sales_detail INNER JOIN canteen_sales
ON canteen_sales_detail.SalesID=canteen_sales.SalesID
WHERE canteen_sales.Nickname='$nic'
ORDER by canteen_sales.Date ASC");
//WHERE Nickname='$nic'

while ($res=mysql_fetch_object($Qa)){
if($bgcolor=='#ffffff'){$bgcolor='#f1f1f1';}
        else{$bgcolor='#ffffff';}
		print "<tr bgcolor=$bgcolor>" ;
                $Date=date_create($res->Date);
                $Date=date_format($Date, 'd-M-y');
                print "<td>$Date</td>\n";
		print "<td>$res->ItemID</td>\n";
	   	print "<td>$res->ItemName</td>\n";
	if ($res->Qnty>0)
	{$Issue=$res->Qnty; unset($receved);}
	else{$receved=$res->Qnty*$res->Qnty; unset($Issue);}
		print "<td>$Issue</td>\n";
		print "<td>$receved</td>\n";
		print "<td>$res->Amount</td>\n";
		$Amount=$Amount+$res->Amount;
		print "<td>$Amount</td></tr>\n";
                //$Amount=$Amount+$re->Amount;
                //$Discount=$Discount+$re->Discount;
                //$Total=$Total+$re->Total;
}



 ?>
 

</div>
    </body>
</html>
