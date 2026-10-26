<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" type="text/css" href="codes/bnspell.css">
<script type="text/javascript" src="codes/keyshortcut.js"></script>
<script type="text/javascript" src="codes/prototype.js"></script>
<script type="text/javascript" src="codes/bnspell.js"></script>
<script type="text/javascript" src="codes/select.js"></script>
<script type="text/javascript">var L="bnw2";</script>

<script type="text/javascript">
    
        window.onload = function()
        {                
            var bComplete = new BComplete("Item",50);
            bComplete.loadData("dictionary/PurItem.php")
            var bComplete = new BComplete("supplier",50);
            bComplete.loadData("dictionary/supplier.php")
            document.dm.Item.focus() // Oftional supplier
        };
    </script> 
<script type="text/javascript">
  shortcut.add("alt+a", function() {
  window.location="purchase.php?NBill=add_new";
      });   
      shortcut.add("alt+p", function() {
  	document.forms["form2"].submit();
      });
  	shortcut.add("alt+m", function() {
      return bill();
      }); 
  	
  function setfocus1(x)
  {
  switch (x)
  {
  case 'Item':
    document.dm.rate.focus();
    document.dm.rate.select();
    break;
  case 'rate':
    ThisAmount();
    document.dm.qnty.focus();
    document.dm.qnty.select();
    break;
  case 'qnty':
    document.forms["dm"].submit();
    break;
    default:
    document.dm.Item.focus();
  }
  	
  }	

  function bill()
  { //alert('This Bill Posted');
      var sid=document.dm.bill_no.value;
      //window.location="Report/SalesMemo.php?id="+Pid;
      window.open("Report/PurMemo.php?id="+ sid+"","Ratting","width=750,height=700,0,status=0,scrollbars=1");
  }
  	
  function submitformPost()
  {
  document.form2.supplier.value=document.dm.supplier.value;
          if(document.form2.Posted.value=='Posted')
  		{
  		alert('This Bill Posted');
  		return false;
  		}

          else if(document.form2.supplier.value=='')
  		{
  		alert('Please Fill in Supplier Name');
  		return false;
  		}
         else{document.forms["form2"].submit();}
      
  }	
  function addPost()
  {
      document.forms["dm"].submit();
  }

</script>
<script  LANGUAGE="JavaScript"  type="text/javascript">
  function incr(i){
  var i=i*1;
  var i=i+1;
  document.dm.qnty.value=(i);
  ThisAmount();
  }

  function decr(i){
  var i=i*1;
  var i=i-1;
  document.dm.qnty.value=(i);
  ThisAmount();
  }

  function ThisAmount() {

  var qnty=dm.qnty.value;
  var rate=dm.rate.value;
  var amt=qnty*rate;
  document.dm.amount.value=(amt);
  }

  function TotalAmount(str) {
  var rate2=form2.amount2.value;
  var amt=rate2-str;
  //document.write(rate2);
  document.form2.total.value=(amt);
  //document.dm.total.value=(amt);
  }
</script>

<div align="center"><div id="main">
<?php include("menu.php"); ?>
<h2>Stock Management Area</h2>
<?php
$BillNo="";
$Post="";
$Disc="";
$Supplier="";
$TotalAmount="";
include 'connect.php';
$UserName=$_SESSION['Username'];
date_default_timezone_set('Asia/Dacca');
$date = date("Y-m-d, H-i-s");
$date2 = date("Y-m-d");

//Adding_new_bill
if(!empty($_GET['NBill'])){
if ($_GET['NBill']=="add_new"){
$check = mysql_query("SELECT * FROM canteen_purchase WHERE UserName = '$UserName' AND PR = '' AND Post = '0' AND Date LIKE '$date2%' LIMIT 1");
if (mysql_num_rows($check) == 1){
while ($Bill1 = mysql_fetch_object($check)){ $BillNo=$Bill1->BillNo;}
                                }
        else {
            mysql_query("INSERT INTO `canteen_purchase`(`UserName` , `Date`)VALUES('$UserName','$date')");

            $Bill=mysql_query("SELECT * FROM canteen_purchase WHERE UserName='$UserName' ORDER BY BillNo desc LIMIT 1") ;
            while ($Bill1 = mysql_fetch_object($Bill)){ $BillNo=$Bill1->BillNo;  }
            }
                            }
}
// Find Bill

if (!empty($_GET['Bill'])){
    $BillNo = $_GET['Bill'];}
    
if (!empty($_POST['bill_no'])){
 $BillNo = $_POST['bill_no'];
}

//Posting_new_bill
if (!empty($_GET['hPost'])){
$Supplier = $_GET['supplier'];
$Amount = $_GET['amount2'];
$Discount = $_GET['discount'];
$Total = $_GET['total'];
mysql_query("UPDATE canteen_purchase SET Supplier='$Supplier' , Amount='$Amount' , Discount = '$Discount' , Total = '$Total' , Post = '1' WHERE BillNo='$BillNo' && Post='0'");

}

// Bill Information #########################

if (isset($BillNo)) {
    $check = mysql_query("SELECT * FROM canteen_purchase WHERE BillNo = '$BillNo'");
    if(mysql_num_rows($check) == 1)
{

$sql=mysql_query("SELECT * FROM canteen_purchase WHERE BillNo='$BillNo'") ;
while ($BillInfo = mysql_fetch_object($sql)){
$User=$BillInfo->UserName;
$Supplier=$BillInfo->Supplier;
$BillDate= date_create($BillInfo->Date);
$Disc=$BillInfo->Discount;
$TotalAmount=$BillInfo->Amount - $Disc;
if ($BillInfo->Post==1)
{$Post="Posted";}
else {
    $Post="UnPosted";
     }
}
	echo '<div class="table1">';
	echo "User Name : $User";
	echo "&nbsp;&nbsp;&nbsp;&nbsp;Date :";
	print date_format($BillDate, 'd-M-Y, h:i:s A');
  echo "&nbsp;&nbsp;&nbsp;&nbsp;";
  echo "<b>$Post</b>";
	echo "</div>";
}
else {echo "Bill No. Not Found";}
}
        ?>

<fieldset>
<legend>Add to Stock</legend>
<form action="" method="post" name="dm" id="dm">
  <table width="100%" border="0" cellspacing="1" cellpadding="1">
    <tr>
      <td><table width="100%" border="0" cellspacing="1" cellpadding="1" >
        <tr>
          <td width="14%" height="32"><strong>Bill NO </strong></td>
          <td width="36%"><input name="bill_no" type="text" id="bill_no" value="<?php echo $BillNo; ?>" readonly></td>
          <td width="20%"><strong>Rate</strong></td>
          <td width="30%"><input name="rate" type="text" id="rate" value="0" onKeyUp="if(event.keyCode==13) setfocus1(this.name);" onClick="showPrice(Item.value, 'Pur')" >
            <input name="ItemID" type="hidden" id="ItemID"></td>
        </tr>
        <tr>
          <td><strong>Supplier</strong></td>
          <td><input onKeyDown="return dn(event);" name="supplier" type="text" id="supplier" value="Doyel Eng" size="42"></td>
          <td><strong>Quantity</strong></td>
          <td><input name="qnty" type="text" id="qnty" autocomplete="off" onKeyUp="if(event.keyCode==13)  setfocus1(this.name); else ThisAmount(this.value);" onKeyPress="if(event.keyCode==38) incr(this.value); else if(event.keyCode==40) decr(this.value);" onClick="showPrice(Item.value, 'Pur')"></td>
        </tr>
        <tr>
          <td><strong>Item</strong></td>
          <td><input onKeyDown="return dn(event);" onKeyUp="if(event.keyCode==13) showPrice(this.value, 'Pur') & setfocus1(this.name);" type="text" id="Item" name="Item" size="32" style="font-size: 14pt">		  </td>
          <td><label><strong>Amount</strong></label></td>
          <td><input name="amount" type="text" id="amount" readonly></td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td><input onClick="addPost();" type="button" name="Button" value="   ADD   "></td>
          <td>&nbsp;</td>
        </tr>
        
      </table></td>
    </tr>
    <tr>
      <td>
          
<?php

//Add_Item

if (!empty($_POST['bill_no']) && !empty($_POST['Item']) && ($_POST['qnty']) != 0 && $Post=="UnPosted")
    {
$ItemID = $_POST['ItemID'];
$ItemName = $_POST['Item'];
$Qnty =abs($_POST['qnty']);
$PurRate = $_POST['rate'];
$Rate = $_POST['rate'];
$Amount = abs($_POST['amount']);

$qa = mysql_query("SELECT Qnty FROM canteen_item WHERE ItemID='$ItemID'");
while ($q=mysql_fetch_object($qa)){ $TQnty=$q->Qnty; }

$TQnty = $TQnty + $Qnty;

mysql_query("INSERT INTO `canteen_purchase_detail`( `BillNo` , `Date` , `ItemID` , `Qnty` , `Rate` , `Amt`)
        VALUES( '$BillNo','$date','$ItemID','$Qnty','$Rate','$Amount')");
    
mysql_query("UPDATE canteen_item SET Qnty='$TQnty' , PurPrice = '$Rate' WHERE ItemID='$ItemID'");
mysql_query("UPDATE canteen_purchase SET PR ='S' WHERE BillNo='$BillNo'");
    }

//Remove_Item

if (!empty($_GET['id']) && !empty($_GET['qnty']) && $Post=="UnPosted")
    {
      $ItemID = $_GET['ItemID'];
      $DetailID = $_GET['id'];
      $Qnty = $_GET['qnty'];
      $qa = mysql_query("SELECT Qnty FROM canteen_item WHERE ItemID='$ItemID'");
      while ($q=mysql_fetch_object($qa)){ $TQnty=$q->Qnty; }
      $TQnty = $TQnty-$Qnty;
      mysql_query("DELETE FROM canteen_purchase_detail WHERE ID='$DetailID'");
      mysql_query("UPDATE canteen_item SET Qnty='$TQnty' WHERE ItemID='$ItemID'");
      // header ('location:purchase.php?Bill='.$BillNo.'');
    }


// View Grid
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<td width="10%"><b>Item ID</b></td>';
              echo '<td width="40%"><b>Item Name</b></td>';
              echo '<td width="15%"><b>Qnty</b></td>';
              echo '<td width="15%"><b>Amount</b></td>';
              echo '<td width="20%"><b>Delete</b></td>';
            echo '</tr>';

$qry=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_purchase_detail.*
        FROM canteen_purchase_detail INNER JOIN canteen_item
        ON canteen_item.ItemID = canteen_purchase_detail.ItemID
        WHERE canteen_purchase_detail.BillNo='$BillNo'") ;
            
while ($Detail = mysql_fetch_object($qry)){

		print "<tr>" ;
                print "<td>$Detail->ItemID</td>\n";
	        print "<td>$Detail->ItemName</td>\n";
		print "<td>$Detail->Qnty</td>\n";
		print "<td>$Detail->Amt</td>\n";
		print "<td><a href=\"purchase.php?Bill=$BillNo&ItemID=$Detail->ItemID&qnty=$Detail->Qnty&id=$Detail->ID\"> Delete </a></td></tr>\n";

}
echo "</table>";

// Count Of Total Amount
$Total = mysql_query("SELECT SUM(Amt) as 'Sum' FROM `canteen_purchase_detail` WHERE BillNo='$BillNo'");
$Sum=mysql_fetch_object($Total);
$Sum=$Sum->Sum;
if($Post='UnPosted"'){ $TotalAmount= $Sum; }
?>
         
	  </td>
    </tr>
  </table>
</form>

<form name="form2" method="GET" id="form2" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1" style="font-size: 14pt">
<tr bgcolor="#f1f1f1">
    <td><a class="battun" href="purchase.php?NBill=add_new" title="Alt+A"> Add New </a>
	<a class="battun" onClick="bill();" title="Alt+M" href="#"> Bill </a></td>
              
              <td>Amount :              
              <input name="amount2" type="text" size="10" readonly value = "<?php echo  round($Sum, 2);  ?>" style="font-size: 14pt"></td>
              <td>Discount : 
              <input name="discount" type="text" size="10" onKeyUp ="TotalAmount(this.value)" value="<?php echo  round($Disc, 2) ; ?>" style="font-size: 14pt"></td>
              <td></td>
              <td>Total : <input name="total" type="text" size="10" readonly value = "<?php echo round($TotalAmount, 2) ; ?>" style="font-size: 14pt"></td>
              <td><input name="Bill" type="hidden" id="Bill" value="<?php echo $BillNo ; ?>">
                <input name="Posted" type="hidden" id="Posted" value="<?php echo $Post ; ?>">
                <input name="supplier" type="hidden" id="supplier" value="">
                <input name="hPost" type="hidden" id="hPost" value="YES">
                <input title="Alt+P" onClick="submitformPost()" type="button" name="Post" value="  Post  " style="font-size: 14pt"></td>
</tr>
</table>
</form> 


<form name="find" method="get" id="form1" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1">
  <tr>
    <td width="25%">
      <a href="purchase.php?Bill=<?php echo $b=$BillNo-1 ; ?>">Pre</a>
    <input name="Bill" type="text" id="Bill" value="<?php echo $BillNo ; ?>" size="15">
    <a href="purchase.php?Bill=<?php echo $b=$BillNo+1 ; ?>">Next</a></td>
    <td width="69%"><input name="Find" type="submit" id="Find" value="  Find  "></td>
    <td width="6%">&nbsp;</td>
  </tr>
</table>
</form>
</fieldset>
<?php include 'footer.php'; ?>
    </div>
</body>
</html>
