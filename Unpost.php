<?php session_start() ?>
<?php if($_SESSION['admin'] !='1' ){
	header ('location:index.php');
}
 ?>
 
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">

<div id="main">
  <?php include("menu.php");
  include 'connect.php'; ?>
<h1 align="center">Bill Unpost</h1>

<?php
if(!empty($_GET['p'])){
$p=$_GET['p'];
$unpost= mysql_query("UPDATE canteen_purchase SET Post='0' WHERE BillNo='$p'");
if ($unpost){echo "<span class=\"warning\">Bill no $p is Unposted.</span>";} }

if(!empty($_GET['dl'])){
$p=$_GET['dl'];
$deletedz= mysql_query("DELETE FROM canteen_purchase WHERE BillNo='$p'");
if ($deletedz){echo "<span class=\"warning\">Bill no $p is Deleted.</span>";} }


if(!empty($_GET['s'])){
$p=$_GET['s'];
$unpost= mysql_query("UPDATE canteen_sales SET Post='0' WHERE SalesID='$p'");
if ($unpost){echo "<span class=\"warning\">Bill no $p is Unposted.</span>";} }


if(!empty($_GET['d'])){
$p=$_GET['d'];
$deleted= mysql_query("DELETE FROM canteen_sales WHERE SalesID='$p'");
if ($deleted){echo "<span class=\"warning\">Bill no $p is Deleted.</span>";} }
 ?>

<div style=" height:450px;display:block; margin-bottom:30px;">
	<div style="display:block;width:48%; float:left" class="report">
		<h2>Add to Stock Bill</h2>
        <div style="height:400px; overflow:auto">
        <table width="98%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="22%">Bill No</th>
              <th width="41%">Date</th>
			  <th width="21%">Status</th>
			  <th width="16%">Unpost</th>
            </tr>
        <?php
		$sql=mysql_query("SELECT * FROM canteen_purchase ORDER BY BillNo DESC LIMIT 500");
		while ($Mem=mysql_fetch_object($sql)){
			print '<tr>' ;
                print "<td>$Mem->BillNo</td>\n";
				$Date=date_create($Mem->Date);
        		$Date=date_format($Date, 'd-M-y h:i:s A');
				print "<td>$Date</td>\n";
				print "<td><a href=\"purchase.php?Bill=$Mem->BillNo\" target=\"_blank\">View Bill</a></td>\n";
				print "<td><a href=\"Unpost.php?p=$Mem->BillNo\">Unpost</a></td>\n";
        print "<td><a href=\"Unpost.php?dl=$Mem->BillNo\">Delete</a></td>\n";
			print '</tr>';
		}
        ?>
     	</table> 
        </div> 
	</div>
   <div style="width:48%; float:right" class="report">
		<h2>Sales Bill</h2>
        <div style="height:400px; overflow:auto">
        <table width="98%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="22%">Bill No</th>
              <th width="41%">Date</th>
			  <th width="21%">Status</th>
			  <th width="16%">Unpost</th>
            </tr>
        <?php
		$sql=mysql_query("SELECT * FROM canteen_sales ORDER BY SalesID DESC LIMIT 500");
		while ($Mem=mysql_fetch_object($sql)){
			print '<tr>' ;
        print "<td>$Mem->SalesID</td>\n";
				$Date=date_create($Mem->Date);
        $Date=date_format($Date, 'd-M-y h:i:s A');
				print "<td>$Date</td>\n";
				print "<td><a href=\"sales.php?Bill=$Mem->SalesID\" target=\"_blank\">View Bill</a></td>\n";
				print "<td><a href=\"Unpost.php?s=$Mem->SalesID\">Unpost</a></td>\n";
        print "<td><a href=\"Unpost.php?d=$Mem->SalesID\">Delete</a></td>\n";
			print '</tr>';
		}
        ?>
     	</table> 
        </div> 
        
   </div>
</div>  
</div>
</body>
</html>