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

<h2>Head of Account</h2>

<div style=" height:450px;display:block; margin-bottom:30px;">
<?php
if(!empty($_GET['headID'])){
$headID=$_GET['headID'];
$sqls=mysql_query("SELECT * FROM canteen_ac_head WHERE headID='$headID'");
$sqls=mysql_fetch_object($sqls);
$headAC = $sqls->HeadOfAccount;
$con= $sqls->conCode;

	if(!empty($_POST['HeadOfAccount']) && $_POST['conCode'] != 'Control Code'){

	if(!empty($_POST['checkbox']))
	{ $d=$_POST['checkbox'];}else{$d=0;}

	$conCode=$_POST['conCode'];
	$HeadOfAccount=$_POST['HeadOfAccount'];

	$query=mysql_query("UPDATE canteen_ac_head SET HeadOfAccount='$HeadOfAccount', conCode='$conCode', D='$d'
	WHERE headID='$headID'");
	if($query){echo "Head Edited";}else{echo "Error";}

		}

}else{
	
		if(!empty($_POST['HeadOfAccount']) && $_POST['conCode'] != 'Control Code'){
		
		$conCode=$_POST['conCode'];
		$HeadOfAccount=$_POST['HeadOfAccount'];
		
		$query = mysql_query("INSERT INTO canteen_ac_head (HeadOfAccount, conCode)
					VALUES ('$HeadOfAccount','$conCode')");
		if($query){echo "Head Added";}else{echo "Error";}

		}	
}

?>


<form name="form1" method="post" action="">
    Head Of Account
	<input value="<?php if(!empty($_GET['headID'])){ echo $headAC; } ?>" name="HeadOfAccount" type="text" id="HeadOfAccount" size="50">
	Control Code
    <select name="conCode" id="conCode">
    <?php if(!empty($_GET['headID'])){ echo '<option>'.$con.'</option>'; }
	else{echo '<option>Control Code</option>';}
	 ?>
    
    <option>Expense</option>
    <option>Income</option>
    </select>
    Drop <input  type="checkbox" name="checkbox" id="checkbox" value="1">
    <input type="submit" name="Submit" value="Submit">
  </form>
  
  <div style="max-height:400px; overflow:auto; margin-bottom:10px;">
	<table id="tableGd" width="87%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
			  <td width="36%"><b>Account Head</b></td>
              <td width="21%"><b>Control Code</b></td>
              <td width="9%"><b>Drop</b></td>
			  <td width="9%"><b>Edit</b></td>
            </tr>
 <?php
 $sql=mysql_query("SELECT * FROM canteen_ac_head ORDER BY HeadOfAccount ASC");
        while ($Paid=mysql_fetch_object($sql)){
		print '<tr>' ;
		print "<td>$Paid->HeadOfAccount</td>\n";
		print "<td>$Paid->conCode</td>\n";
		print "<td>$Paid->D</td>\n";
		print "<td><a href=\"HeadOfAccount.php?headID=$Paid->headID\">Edit</a></td></tr>\n";
	
 }
 ?>
 
 </table>
	</div> 


</div>
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
