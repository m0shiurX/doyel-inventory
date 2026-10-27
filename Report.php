<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
		<script language="JavaScript" src="css/calendar_eu.js"></script>
		<link rel="stylesheet" href="css/calendar.css">
    <div id="main">

<?php include("menu.php"); ?>

<?php
//
$FormDate= date("d-m-Y");
$ToDate= date("d-m-Y");
$FormTime='00:00:00';
$ToTime='23:59:59';


    if (!empty ($_POST['start_date']) && !empty ($_POST['end_date'])){
		
        $FormDate= $_POST['start_date'];
        $ToDate=$_POST['end_date'];
		
		$FormTime = $_POST['start_time'];
	   	$ToTime = $_POST['end_time'];
		
		}
        $d1=date_create($FormDate);
        $d2=date_create($ToDate);

       $d1=  date_format($d1, 'Y-m-d');
	   $d2=  date_format($d2, 'Y-m-d');
	   $d1.=' '.$FormTime;
	   $d2.=' '.$ToTime;
	   
	   $_SESSION['form_date'] = $d1;
       $_SESSION['to_date'] = $d2;
?>

<p><form action="" method="POST" name="form" >
<table width="778" border="0" cellpadding="0" cellspacing="10">
    <tr>
       <td width="44">Form</td>
       <td>
       		<script language="JavaScript">
       			new tcal ({
       				// form name
       				'formname': 'form',
       				// input name
       				'controlname': 'start_date'
       			});
       	    </script>
       </td>
       <td width="192">
       		    <input name="start_date" type="text" id="start_date"  value="<?php echo $FormDate ?>"/>
        </td>
        <td width="62"><input name="start_time" type="text" id="start_time"  value="<?php echo $FormTime ?>" size="10"/></td>
        <td width="35">To</td>
        <td>
        	<script language="JavaScript">
        		new tcal ({
        			// form name
        			'formname': 'form',
        			// input name
        			'controlname': 'end_date'
        		});
        	</script>
        </td>
    <td width="198">
    	<input name="end_date" type="text" id="end_date" value="<?php echo $ToDate ?>" />
	</td>
    <td width="67"><input name="end_time" type="text" id="end_time" value="<?php echo $ToTime ?>" size="10" /></td>
    <td width="100"><input type="submit" name="submit" value="Set" /></td>
    </tr>
</table>
</form>
		</p>
<div style=" height:450px;display:block; margin-bottom:30px;">
	<div style="display:block;width:48%; float:left" class="report">
	  <ul style="margin:0 0 20px 0; padding-left:10px;">
		<li><a href="Report/ListOfItem.php" target="_blank">Product List</a></li>
		<li><a href="Report/ItemLedgr.php" target="_blank">Product List : Ledgr</a></li>
		<li><a href="Report/ListOfItemWithQun.php" target="_blank">Product List : Quantity</a></li>
    <li><a href="Report/list_item_with_minimum.php" target="_blank">Product List : Below Minimum Quantity</a></li>
    <li><a href="Report/list_item_with_maximum.php" target="_blank">Product List : Above Maximum Quantity</a></li>
		<hr />
		<li><a href="Report/salesOfaPeriod-itemName.php" target="_blank">Sales of a period : Short by Name</a></li>
    <li><a href="Report/sales_of_a_period_category_wise.php" target="_blank">Sales of a period : Category Wise</a></li>
    <li><a href="Report/salesOfaPeriod-itemName-By-user.php" target="_blank">Sales of a period : Short By User</a></li>
    <li><a href="Report/salesOfaPeriod-Bill.php" target="_blank">Sales of a period : Short by Bill</a></li>
    <hr />
    <!-- <li><a href="Report/SendToProcessOfaPeriod.php" target="_blank">Send to Process of a period</a></li> -->
    <li><a href="Report/Purchase of a period.php" target="_blank">Purchase of a period </a></li>
    <li><a href="Report/PurchaseOfaPeriod-Bill-wise.php" target="_blank">Purchase of a period : Bill Wise</a></li>
    <hr />
    <li><a href="Report/CashCollectionOfaPeriod.php" target="_blank">Cash Collection</a></li>
    <li><a href="Report/CashExpensesOfaPeriod.php" target="_blank">Cash Expenses</a></li>
    <hr />
    <li><a href="Report/list_of_members.php" target="_blank">Customer info with Dues</a></li>
    <li><a href="Report/Employee ledgr-Summary.php" target="_blank">Accountant Summary</a></li>
		<li><a href="Report/closingBalance.php" target="_blank">Closing Balance</a></li>
<!-- 		<li><a href="Report/UnpostedBill.php" target="_blank">Unposted Bills</a></li> -->
	  </ul>
	</div>
		
		<div style="width:48%; float:right" class="report">
		<h2>Present Stock</h2>
		<p>
			<a href="javascript:void(0);"onClick=window.open("SavePrestntStock.php","Ratting","width=450,height=300,0,status=0,");>Save Prestnt Stock</a>
		</p>

	<ul style="margin:20px 0 20px 5px; padding-left:10px; height:370px; overflow:auto;">
	<?php
	include 'connect.php';
		$SlNo=mysql_query("SELECT * FROM canteen_stock ORDER BY SlNo desc") ;
	while ($SlN = mysql_fetch_object($SlNo)){
	$Date=date_create($SlN->Date);
	$Date=date_format($Date, 'd-M-Y h:i:s A');
	echo "<li><a href=\"Report/savedClosingBalance.php?SlN=$SlN->SlNo\" target=\"_blank\">";
	echo $SlN->SlNo.' &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	echo $Date;
	echo '</a></li>';
	}
	?>
	</ul>

		</div>
</div>     
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
