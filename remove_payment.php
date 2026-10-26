<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
 <?php include 'connect.php'; ?>

 <?php

if ($_SESSION['admin']==1) {
  if(!empty($_GET['d'])){
  $p=$_GET['d'];
  $deleted= mysql_query("DELETE FROM canteen_collection WHERE PaymentID='$p'");
  if ($deleted){
  	echo "<span class=\"warning\">Collection no $p is Removed.</span>";
  	print '<script type="text/javascript">';
    print 'window.location.href="payment.php";';
    print 'alert("Deletation Complete")';
  	print '</script>';
  	}
}
}
else{
 		print '<script type="text/javascript">';
    print 'window.location.href="payment.php";';
    print 'alert("Deletation Can not be Completed")';
 		print '</script>';
 	}

?>
</body>
</html>