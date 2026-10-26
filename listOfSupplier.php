<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<script src="Report/AutoSuggestDropDown/jquery-1.7.1.min.js"></script>
        <script type="text/javascript">
		$(function() {

            $('#button').click(function() {
                $('#subform').append('<img src="img/loading.gif" class="loading" width="16" height="16">');
				var SupplierName= $('#SupplierName').val();
				$.ajax({
					url: 'AddSupplierName.php',
					type: 'POST',
					data: 'SupplierName='+SupplierName,
					
					success: function(result){
						$('.response').remove();
						$('#subform').append('<p class="response"' + result + '</p>');
						$('.loading').fadeOut(500, function() {
							$(this).remove();
						});
					}
					
				})
				
				return false;
        });
	});
		
		</script>


<div id="main">

<?php include("menu.php");
include 'connect.php';

if(!empty($_POST['amount']) && !empty($_GET['Name'])){

$Description=$_GET['Name'];
$amount= $_POST['amount'];
date_default_timezone_set('Asia/Dacca');
$date = date("Y-m-d, H-i-s");
$UserName=$_SESSION['Username'];
$Remarks=$_POST['Remarks'];

$query = mysql_query("INSERT INTO canteen_expenses (Date, UserName, Description, HeadOfAccount, amount, Remarks)
            VALUES ('$date','$UserName','$Description','Supplier Payment','$amount','$Remarks')");
if($query){
echo "Payment Sucsecc";
}else{echo "Error";}

}

 ?>

 <form action="" method="post">
   <div id="subform">
   <a href="#">Add New Supplier</a><input name="SupplierName" type="text" id="SupplierName" size="44">
   <input type="submit" name="button" id="button" value="Submit">
   </div>
 </form>
 
	<h2>List of Supplier</h2>
	<div style="overflow:auto">
		<table id="tableGd" width="96%" border="0" cellspacing="1" cellpadding="1">
		  <tr bgcolor="#f1f1f1">
		  <th width="8%">ID</th>
		  <th width="65%">SupplierName</th>
		  <th width="19%">Drop</td>
		  <th width="8%">Edit</th>
		</tr>
		<?php
		$sql=mysql_query("SELECT * FROM canteen_supplier WHERE D='0' ORDER BY SupplierName ASC");
	
		$tAmount=0;
		while ($Mem=mysql_fetch_object($sql)){

		print '<tr>' ;
		print "<td>$Mem->SupplierId</td>\n";
		print "<td>$Mem->SupplierName</td>\n";
		$SupplierName=$Mem->SupplierName;
		print "<td></td>\n";
		print "<td><a href=\"Supplier.php?ID=$Mem->SupplierId&Name=$Mem->SupplierName\">Edit</a></td></tr>\n";

		}	
		?>
		</table>
	</div>
</div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
