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

<h2>Cash Collection</h2>

<div style=" min-height:450px;display:block; margin-bottom:30px;">
<?php
if(!empty($_POST['amount']) && !empty($_POST['Description']) && $_POST['HeadOfAccount'] != 'Head Of Accounts'){

$Description=$_POST['Description'];
$HeadOfAccount=$_POST['HeadOfAccount'];
$amount= $_POST['amount'];
date_default_timezone_set('Asia/Dacca');
$date = date("Y-m-d, H-i-s");
$UserName=$_SESSION['Username'];
$Remarks=$_POST['Remarks'];

$query = mysql_query("INSERT INTO canteen_collection (Date, UserName, Description, HeadOfAccount, amount, Remarks)
            VALUES ('$date','$UserName','$Description','$HeadOfAccount','$amount','$Remarks')");
if($query){echo "Payment Sucess";}else{echo "Error";}
}	?>

<form name="form1" method="post" action="CashCollection.php">
    <fieldset>
      <legend>Cash Collection</legend>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td width="10%">Description</td>
        <td width="38%"><input name="Description" type="text" id="Description" size="50"></td>
        <td width="11%">Accounts Head</td>
        <td width="41%"><select name="HeadOfAccount" id="HeadOfAccount">
        <option>Head Of Accounts</option>
		<?php
		$hea=mysql_query("SELECT HeadOfAccount FROM canteen_ac_head WHERE conCode='Income'");
		while ($heal=mysql_fetch_object($hea)){
			echo "<option>$heal->HeadOfAccount</option>";
		}
        ?>
        </select></td>
      </tr>
      <tr>
        <td>Amount</td>
        <td><input name="amount" type="text" id="amount" size="10"></td>
        <td>Remarks</td>
        <td><input name="Remarks" type="text" id="Remarks">
          <input type="submit" name="Submit" value="Collection"></td>
      </tr>
  </table>
      </fieldset> 
</form>
  
  <div style="max-height:400px; overflow:auto; margin-bottom:10px;">
	<table id="tableGd" width="98%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="17%"><b>Date</b></td>
              <td width="11%"><b>Receved By</b></td>
			  <td width="34%"><b>Description</b></td>
              <td width="8%"><b>Amount</b></td>
			  <td width="20%"><b>Remarks</b></td>
			  <td width="10%"><b>Action</b></td>
            </tr>
<?php


if ($_SESSION['admin']==1) {
  if(!empty($_GET['d'])){
  $p=$_GET['d'];
  $deleted= mysql_query("DELETE FROM canteen_collection WHERE PaymentID='$p'");
  if ($deleted){echo "<span class=\"warning\">Collection no $p is Removed.</span>";} }


  $sql=mysql_query("SELECT * FROM canteen_collection ORDER BY Date DESC LIMIT 30");


 } else {
   $sql=mysql_query("SELECT * FROM canteen_collection WHERE Remarks!='Personal' ORDER BY Date DESC LIMIT 30");
 }
        while ($Paid=mysql_fetch_object($sql)){
		print '<tr>' ;
		$Date=date_create($Paid->Date);
        $Date=date_format($Date, 'd-M-y h:i A');
		print "<td>$Date</td>\n";
		print "<td>$Paid->UserName</td>\n";
		print "<td>$Paid->Description</td>\n";
		print "<td>$Paid->amount</td>\n";
		print "<td>$Paid->Remarks</td>\n";
		print "<td><a href=\"CashCollection.php?d=$Paid->PaymentID\">Delete</a></td></tr>\n";
    }
?>
 
 </table>
</div> 


</div>
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
