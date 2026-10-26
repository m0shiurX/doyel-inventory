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
						//console.log(result);
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
<div style=" height:450px;display:block; margin-bottom:30px; margin-top:20px;">

 <form action="" method="post">
   <div id="subform">
   <a href="#">Add New Supplier</a><input name="SupplierName" type="text" id="SupplierName" size="44">
   <input type="submit" name="button" id="button" value="Submit">
   </div>
 </form>
 
<div style="display:block;width:48%; float:left;" class="report">
			<h2>List of Supplier</h2>
	<div style="height:400px; overflow:auto">
	<table id="tableGd" width="96%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <th width="8%">ID</th>
              <th width="65%">SupplierName</th>
              <th width="19%">Due</td>
			  <th width="8%">Show</th>
            </tr>
			<?php
			$sql=mysql_query("SELECT * FROM canteen_supplier WHERE D='0' ORDER BY SupplierName ASC");
        
            $tAmount=0;
            while ($Mem=mysql_fetch_object($sql)){

		print '<tr>' ;
                print "<td>$Mem->SupplierId</td>\n";
		print "<td>$Mem->SupplierName</td>\n";
			$SupplierName=$Mem->SupplierName;
			$dueAmount=dueAmount($SupplierName);
			$paidAmount=paidAmount($SupplierName);
			$Total=$dueAmount-$paidAmount;
			$tAmount=$tAmount+$Total;
		print "<td>$Total</td>\n";
		print "<td><a href=\"Supplier.php?ID=$Mem->SupplierId&Name=$Mem->SupplierName\">Show</a></td></tr>\n";

        }
			
		function dueAmount($SupplierName){
		$sql=mysql_query("SELECT SUM(Total) as 'total' FROM canteen_purchase WHERE Supplier='$SupplierName'");
        $Mem=mysql_fetch_object($sql);
		$dueAmount=$Mem->total;
		return $dueAmount;
		}
			
		function paidAmount($SupplierName){
		$sql=mysql_query("SELECT SUM(amount) as 'total' FROM canteen_expenses WHERE Description='$SupplierName'");
        $Mem=mysql_fetch_object($sql);
		$paidAmount=$Mem->total;
		return $paidAmount;
		
		}	
			?>
			<tr bgcolor="#f1f1f1">
                <td>&nbsp;</td>
                <td><b>Total Payable Amount</b></td>
                <td colspan="3"><b><?php echo $tAmount ?></b></td>
              </tr>
		</table>		
			</table>
	  </div>
		</div>
		
		<div style="width:51%; float:right" class="report">
			<h2>Bill Payment</h2>
<?php if(!empty($_GET['ID'])){ $SupplierId=$_GET['ID']; $SupplierName=$_GET['Name']; ?>
<div align="left" style=" padding:0 15px;">
 <h3><?php echo $SupplierName;  ?></h3>
 
 
 <div style="max-height:160px; overflow:auto; margin-bottom:10px;">
	<table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="18%"><b>Date</b></td>
              <td width="23%"><b>Bill No</b></td>
              <td width="17%"><b>Amount</b></td>
			  <td width="28%"><b>Discount</b></td>
			  <td width="14%"><b>Total</b></td>
            </tr>
 <?php
 $sql=mysql_query("SELECT * FROM canteen_purchase WHERE Supplier='$SupplierName' ORDER BY Date DESC LIMIT 5");
        while ($Paid=mysql_fetch_object($sql)){
		print '<tr>' ;
		$Date=date_create($Paid->Date);
        $Date=date_format($Date, 'd-M-y');
		print "<td>$Date</td>\n";
		print "<td>$Paid->BillNo</td>\n";
		print "<td>$Paid->Amount</td>\n";
		print "<td>$Paid->Discount</td>\n";
		print "<td>$Paid->Total</td></tr>\n";
	
 }
 ?>
 
 </table>
 </div>	
 			<?php
 			$dueAmount=dueAmount($SupplierName);
			$paidAmount=paidAmount($SupplierName);
			$Total=$dueAmount-$paidAmount;
			echo "<b>Total DUE : $Total</b>";
			?>
 
  <br><br>
  <form name="form1" method="post" action="">
    Amount
	<input name="amount" type="text" id="amount" size="10">
    Remarks 
    <input name="Remarks" type="text" id="Remarks">
	<input type="submit" name="Submit" value="Payment">
  </form>
  
  <div style="max-height:160px; overflow:auto; margin-bottom:10px;">
	<table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="18%"><b>Date</b></td>
              <td width="23%"><b>Paid By</b></td>
              <td width="17%"><b>Amount</b></td>
			  <td width="28%"><b>Remarks</b></td>
			  <td width="14%"><b>Recept</b></td>
            </tr>
 <?php
 $SupplierName=$_GET['Name'];
 $sql=mysql_query("SELECT * FROM canteen_expenses WHERE Description='$SupplierName' ORDER BY Date DESC LIMIT 5");
        while ($Paid=mysql_fetch_object($sql)){
		
		print '<tr>' ;
		$Date=date_create($Paid->Date);
        $Date=date_format($Date, 'd-M-y');
		print "<td>$Date</td>\n";
		print "<td>$Paid->UserName</td>\n";
		print "<td>$Paid->amount</td>\n";
		print "<td>$Paid->Remarks</td>\n";
		print "<td><a href=\"#\">Recept</a></td></tr>\n";
	
 }
 ?>
 
 </table>
	</div> 
</div>
<?php } ?>
		</div>
   </div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
