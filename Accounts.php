<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">

<div align="center"><div id="main">
<?php include("menu.php");
include 'connect.php'; ?>
<div style=" height:450px;display:block; margin-bottom:30px; margin-top:50px;">
  <div align="left" style="display:block;width:48%; float:left;overflow: hidden;" class="report">
    <h2>Menu</h2>
    <ul style="margin:0 15px 20px 0; padding-left:10px;"><!-- 
      <li><a href="Supplier.php">Supplier Payment</a></li> -->
	  <li><a href="CashCollection.php">Cash Collection</a></li>
	  <li><a href="Expenses.php">Expenses</a></li>
      <li><a href="HeadOfAccount.php">Head Of Account</a></li>
    </ul>
    <p align="right">
    <a href="Backup/new_backup.php" target="_blank">Data Backup </a>
    <a href="Unpost.php">Administrator</a>
    <a href="list_of_user.php">Add User</a>
    <a href="javascript:void(0);" onClick=window.open("password.php","Ratting","width=450,height=300,0,status=0,");>Change Password</a>
    </p>
  </div>
  
  <div align="left" style="display:block;width:48%; float:right;overflow: hidden" class="report">
    <h2>Report</h2>
	<div style="margin:0 15px 20px 5px; padding-left:10px;">
      <div class="list">
		<div style="width:230px; float:left">Cash in Hand</div>
		<div style="float:left"><?php $amount=netTotal(); echo $amount.' Taka'; ?></div>
	</div>
	<?php	date_default_timezone_set('Asia/Dacca');?>
	<div class="list">
		<div style="width:230px; float:left">Cash Collection This Month</div>
		<div style="float:left"><?php 
			$FormDate=date('Y-m-1');
			$ToDate=date('Y-m-d');
		 echo CCThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
	<div class="list">
		<div style="width:230px; float:left">Cash Expenses This Month</div>
		<div style="float:left"><?php echo CEThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
	<div class="list">
		<div style="width:230px; float:left">Cash Collection Previous Month</div>
		<div style="float:left"><?php
			$FormDate=date('Y-m-01', strtotime(' -1 month'));
			$ToDate=date('Y-m-31', strtotime(' -1 month'));
		 echo CCThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
	<div class="list">
		<div style="width:230px; float:left">Cash Expenses Previous Month</div>
		<div style="float:left"><?php echo CEThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
	<div class="list">
		<div style="width:230px; float:left">Cash Collection This Year</div>
		<div style="float:left"><?php 
			$FormDate=date('Y-01-01');
			$ToDate=date('Y-m-d');
		 echo CCThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
	<div class="list">
		<div style="width:230px; float:left">Cash Expenses This Year</div>
		<div style="float:left"><?php echo CEThisMonth($FormDate,$ToDate); echo ' Taka'; ?></div>
	</div>
<?php
function CCThisMonth($FormDate,$ToDate){

$Total = mysql_query("SELECT SUM(amount) as 'cTotal' FROM `canteen_collection` WHERE Date BETWEEN '$FormDate 00:00:00' AND '$ToDate 23:59:59'");
$S2=mysql_fetch_object($Total);
$collectionTotal=$S2->cTotal;

return $collectionTotal;
//return $cTotal = $collectionTotal-$expensesTotal;
}

function CEThisMonth($FormDate,$ToDate){
$Total = mysql_query("SELECT SUM(amount) as 'cTotal' FROM `canteen_expenses` WHERE Date BETWEEN '$FormDate 00:00:00' AND '$ToDate 23:59:59'");
$S2=mysql_fetch_object($Total);
$collectionTotal=$S2->cTotal;

return $collectionTotal;
//return $cTotal = $collectionTotal-$expensesTotal;
}

function netTotal(){
$Total = mysql_query("SELECT SUM(amount) as 'eTotal' FROM `canteen_expenses`");
$S=mysql_fetch_object($Total);
$expensesTotal=$S->eTotal;

$Total = mysql_query("SELECT SUM(amount) as 'cTotal' FROM `canteen_collection`");
$S2=mysql_fetch_object($Total);
$collectionTotal=$S2->cTotal;

return $cTotal = $collectionTotal-$expensesTotal;
}
?>
    </div>
  </div>
  
</div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
