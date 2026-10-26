<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Cash Collection Of a Period</title>
<div id="main">
	<div class="printb"">
		<a href="#" onClick="javascript:window.print()">Print</a>
	</div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
?> 
<div align="center"><h1>Cash Collection Of a Period</h1></div>
<p align="left"><b>From:</b> <?php $fDate=date_create($FormDate); echo $fDate=date_format($fDate, 'd-M-Y'); ?>  &nbsp; 
<b>To:</b> <?php $tDate=date_create($ToDate); echo $tDate=date_format($tDate, 'd-M-Y'); ?></p>

<table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
            <tr>
              <th width="17%">Date</th>
              <th width="11%">Receved By</th>
			  <th width="34%">Description</th>
              <th width="8%">Amount</th>
			  <th width="20%">Remarks</th>
            </tr>
<?php
$TotalAmt=0;
//WHERE Date BETWEEN '$FormDate' AND '$ToDate' ORDER BY Date ASC 
 $sqlc=mysql_query("SELECT HeadOfAccount, SUM(amount) as amt FROM canteen_collection WHERE Date BETWEEN '$FormDate' AND '$ToDate' GROUP BY HeadOfAccount");
        while ($sqls=mysql_fetch_object($sqlc)){
		print '<tr>' ;
		print "<td colspan=\"5\"><b>$sqls->HeadOfAccount</b></td>";
		print '</tr>' ;
		$HeadOfAccount=$sqls->HeadOfAccount;
		item($HeadOfAccount);
		print '<tr>' ;
		print '<th>&nbsp;</th>';
		print '<th>&nbsp;</th>';
		print "<th>Sub Total</th>";
		print "<th>$sqls->amt</th>";
		$TotalAmt=$TotalAmt+$sqls->amt;
		print '<th>&nbsp;</th>';
		print '</tr>' ;
		}
		
		print '<tr>' ;
		print '<th>&nbsp;</th>';
		print '<th>&nbsp;</th>';
		print "<th>Ground Total</th>";
		print "<th>$TotalAmt</th>";
		print '<th>&nbsp;</th>';
		print '</tr>' ;
?>            
</table>

 <?php
 
function item($HeadOfAccount){
 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
$sql=mysql_query("SELECT * FROM canteen_collection WHERE HeadOfAccount='$HeadOfAccount' AND Date BETWEEN '$FormDate' AND '$ToDate'");
        while ($Paid=mysql_fetch_object($sql)){
		print '<tr>' ;
		$Date=date_create($Paid->Date);
        $Date=date_format($Date, 'd-M-y h:i A');
		print "<td>$Date</td>\n";
		print "<td>$Paid->UserName</td>\n";
		print "<td>$Paid->Description</td>\n";
		print "<td>$Paid->amount</td>\n";
		print "<td>$Paid->Remarks&nbsp;</td>\n";
		print "</tr>\n";
 }
}
 ?>



<br>
</div>
    </body>
</html>
