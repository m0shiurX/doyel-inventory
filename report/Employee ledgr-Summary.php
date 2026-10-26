<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Employee ledgr-Summairy</title>
<div align="center">
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
?> 

<h1>Employee Summery</h1>

<p align="left"><b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>  &nbsp; 
<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?></p>

<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
            <tr bgcolor="#f1f1f1">
              <th width="5%">&nbsp;</th>
              <th width="25%">Salesman</th>
              <th width="25%">Amount</th>
              <th width="25%">Discount</th>
              <th width="20%">Total</th>
            </tr>
<?php
  $Amount="";
  $Discount="";
  $Total="";         
  $result=mysql_query("SELECT UserName, SUM(Amount) AS 'Amount', SUM(Discount) AS 'Discount', SUM(Total) AS 'Total' FROM canteen_sales WHERE Post='1' AND Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY UserName");
  while ($re=mysql_fetch_object($result)){
    print "<tr>" ;
    print "<th width=\"5%\">&nbsp;</th>";
    print "<td>$re->UserName</td>\n";
    $Amount1=$re->Amount;
    $Amount1=round($Amount1,2);
    print "<td>$Amount1</td>\n";
    print "<td>$re->Discount</td>\n";
    $Total1=$re->Total;
    $Total1=round($Total1,2);
    print "<td>$Total1</td></tr>\n";
    $Amount=$Amount+$Amount1;
    $Discount=$Discount+$re->Discount;
    $Total=$Total+$Total1;
  }
  echo "<tr><th width=\"5%\">&nbsp;</th><th>Total</th><th>$Amount</th><th>$Discount</th><th>$Total</th>" ;
  echo '</table>';
?>

<br />
</div>
</div>
</body>
</html>
