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
        bComplete.loadData("dictionary/SalesItem.php")
			   document.dm.Item.focus()
        };
</script>

<script type="text/javascript">

  shortcut.add("alt+a", function() {
  window.location="Sales.php?NBill=add_new";
  });   
  shortcut.add("alt+p", function() {
  return formPost();
  });
  shortcut.add("alt+m", function() {
  return bill();
  });
	
  function setfocus1(x)
  {
    switch (x)
    {
    case 'supplier':
      document.dm.Item.focus();
      break;    
    case 'Item':
    document.dm.qnty.select();
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
    var Pid=document.dm.bill_no.value;
    //window.location="Report/SalesMemo.php?id="+Pid;
    window.open("Report/SalesMemo.php?id="+ Pid+"","Ratting","width=850,height=700,0,status=0,scrollbars=1");
  }
  	
  function addPost()
  {   
    if(document.form2.Posted.value=='Posted')
    {
    alert('This Bill Posted');
    return false;
    }

    else if(document.dm.Member.value=='Select Member')
    {
    alert('Please select any one Select Member');
    return false;
    }
    else if(document.dm.Item.value=='')
    {
    alert('Insert an Item Please');
    return false;
    }
    else if(document.dm.qnty.value=='')
    {
    alert('Insert Quantity Please');
    return false;
    }
    else  {
    document.forms["dm"].submit();
    return true;
      }
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


  function ThisAmount(str) {
  var rate=dm.rate.value;
  var amt=str*rate;
  document.dm.amount.value=(amt);
  }

  function TotalAmount(str) {
  var rate2=form2.amount2.value;
  var amt=rate2-str;
  //document.write(rate2);
  document.form2.total.value=(amt);
  //document.dm.total.value=(amt);
  }

  function formPost() {
    var nic=document.dm.Member.value;
    document.form2.Nickname.value=nic;

    if(document.form2.Posted.value=='Posted')
  	{
  	alert('This Bill Posted');
  	return false;
  	}

    else if(document.dm.Member.value=='Select Member')
  	{
  	alert('Please select any one Select Member');
  	return false;
  	}
    else if(document.form2.Note.value=='')
    {
    alert('Insert a Note Please');
    return false;
    }
    else	{
  	document.forms["form2"].submit();
  	return true;
  		}
  }
</script>
  <div align="center"><div id="main">
  <?php include("menu.php"); ?>

  <h2> Sales Area</h2>
	
<?php
$SalesID="";
$Post="";
$Disc="";
$Note="";
$CustomerName=null;
include 'connect.php';
$UserName=$_SESSION['Username'];
date_default_timezone_set('Asia/Dacca');
$date = date("Y-m-d, H-i-s");
$date2 = date("Y-m-d");

// Add New Bill #############################
if(!empty($_GET['NBill'])){
if ($_GET['NBill']=="add_new"){
$check = mysql_query("SELECT * FROM canteen_sales WHERE UserName = '$UserName' AND Post = '0' AND Date LIKE '$date2%' LIMIT 1");
if (mysql_num_rows($check) == 1){
while ($Bill1 = mysql_fetch_object($check)){ $SalesID=$Bill1->SalesID;}
                                }
        else {
            mysql_query("INSERT INTO `canteen_sales`(`UserName` , `Date`)VALUES('$UserName','$date')");

            $Bill=mysql_query("SELECT * FROM canteen_sales WHERE UserName='$UserName' ORDER BY SalesID desc LIMIT 1") ;
            while ($Bill1 = mysql_fetch_object($Bill)){ $SalesID=$Bill1->SalesID;  }
            }
                            }
}

// Find Bill #############################

if (!empty($_GET['Bill'])){
    $SalesID = $_GET['Bill'];}
    
if (!empty($_POST['bill_no'])){
 $SalesID = $_POST['bill_no'];
}

// Bill Removing #################################3
if (!empty($_GET['Remove'])){

}

// Bill Posting #################################3

if (!empty($_GET['hPost']) ){

$Amount = $_GET['amount2'];
$Discount = $_GET['discount'];
$Total = $_GET['total'];
$Nickname = $_GET['Nickname'];
$Note = $_GET['Note'];
if ($Nickname == "Select Member")
{ $Credit='0'; } else { $Credit='1';}

mysql_query("UPDATE canteen_sales SET CustomerName='$Nickname', Amount='$Amount' , Discount = '$Discount' , Total = '$Total' , Post = '1' , Note = '$Note' , Credit = '$Credit' WHERE SalesID='$SalesID' && Credit != '2' && Post='0'");
}

// Bill Information #########################

if (isset($SalesID)) {
    $check = mysql_query("SELECT * FROM canteen_sales WHERE SalesID = '$SalesID'");
    if(mysql_num_rows($check) == 1)
{

$sql=mysql_query("SELECT * FROM canteen_sales WHERE SalesID='$SalesID'") ;
while ($BillInfo = mysql_fetch_object($sql)){
$User=$BillInfo->UserName;
$CustomerName=$BillInfo->CustomerName;
$BillDate= date_create($BillInfo->Date);
$Disc=$BillInfo->Discount;
$BTotal=$BillInfo->Total;
$Note=$BillInfo->Note;
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
<legend>Product Sales </legend>
<form action="" method="post" name="dm" id="dm">
  <table width="100%" border="0" cellspacing="1" cellpadding="1">
    <tr>
      <td><table width="100%" border="0" cellspacing="1" cellpadding="1" >
        <tr>
          <td width="13%" height="32"><strong>Bill NO </strong></td>
          <td width="35%"><input name="bill_no" type="text" id="bill_no" value="<?php echo $SalesID; ?>" readonly></td>
          <td width="11%"><strong>Rate</strong></td>
          <td width="41%"><input name="rate" type="text" id="rate" size="12" readonly >
            <input name="ItemID" type="hidden" id="ItemID">
            <input name="PurAmt" type="hidden" id="PurAmt">
            <strong>Total Quantity</strong>           
<input name="TQnty" type="text" id="TQnty" size="12" readonly></td>
        </tr>
        <tr>
          <td><strong>Member</strong></td>
          <td>
          
          <select name="Member" id="Member">
          	       
            <?php
			if(!empty($_POST['Member'])){$CustomerName=$_POST['Member'];}
			
			if($CustomerName !=''){ echo "<option>$CustomerName</option>"; }
			echo '<option>Select Member</option>';
            $sql=mysql_query("SELECT Nickname FROM canteen_customar");
            while ($Mem=mysql_fetch_object($sql)){
                echo "<option>$Mem->Nickname</option>";
            }

            ?>
          </select>
          </td>
          <td><strong>Quantity</strong></td>
          <td><input name="qnty" id="qnty" type="text" autocomplete="off" onKeyUp="if(event.keyCode==13) setfocus1(this.name); else ThisAmount(this.value);" onKeyPress="if(event.keyCode==38) incr(this.value); else if(event.keyCode==40) decr(this.value);" onClick="showPrice(Item.value, 'sales')" size="12">		  </td>
        </tr>
        <tr>
          <td><strong>Item</strong></td>
          <td><input onKeyDown="return dn(event);" onKeyPress="return dg(event);"onKeyUp="if(event.keyCode==13) showPrice(this.value, 'sales') & setfocus1(this.name);" type="text" id="Item" name="Item" size="32" style="font-size: 14pt">		  </td>
          <td><label><strong>Amount</strong></label></td>
          <td><input name="amount" type="text" id="amount" size="12" readonly></td>
        </tr>
        <tr>
          <td><!--<label><strong>Iten ID</strong></label>-->&nbsp;</td>
          <td><!--<input name="sItenID" type="text" size="4" maxlength="4" onKeyUp="if(event.keyCode==13) showPrice(this.value, 'sales')" />-->&nbsp;</td>
          <td>&nbsp;</td>
          <td><input onClick="addPost()" type="button" name="Button" value="   ADD   "></td>
        </tr>
        
      </table></td>
    </tr>
    <tr>
      <td>
          
<?php

//  Add Item ##########################33

if (!empty($_POST['bill_no']) && !empty($_POST['Item']) && ($_POST['qnty']) != 0 && $Post=="UnPosted")
    {
$ItemID = $_POST['ItemID'];
$ItemName = $_POST['Item'];
$Qnty = $_POST['qnty'];
$PurAmt = $Qnty * $_POST['PurAmt'];
$Rate = $_POST['rate'];
$Amount = $_POST['amount'];

$qa = mysql_query("SELECT Qnty, GenericName FROM canteen_item WHERE ItemID='$ItemID'");
while ($q=mysql_fetch_object($qa)){ $TQnty=$q->Qnty; $GenericName=$q->GenericName; }


if ($TQnty>=$Qnty){
	$TQnty = $TQnty - $Qnty;

	mysql_query("INSERT INTO canteen_sales_detail( SalesID, Date , ItemID , Qnty , PurAmt , Rate , Amt)
        VALUES( '$SalesID','$date','$ItemID','$Qnty','$PurAmt','$Rate','$Amount')");
    
	mysql_query("UPDATE canteen_item SET Qnty='$TQnty' WHERE ItemID='$ItemID'");
	mysql_query("UPDATE canteen_sales SET SR ='S' WHERE SalesID='$SalesID'");
	}
}

// Remove Item

if (!empty($_GET['id']) && !empty($_GET['qnty']) && $Post=="UnPosted")
    {
		$ItemID = $_GET['ItemID'];
		$DetailID = $_GET['id'];
		$Qnty = $_GET['qnty'];

		mysql_query("DELETE FROM canteen_sales_detail WHERE ID='$DetailID'");
		$qa = mysql_query("SELECT Qnty, GenericName FROM canteen_item WHERE ItemID='$ItemID'");
		while ($q=mysql_fetch_object($qa)){ $TQnty=$q->Qnty; $GenericName=$q->GenericName; }
		$TQnty = $TQnty+$Qnty;
		mysql_query("UPDATE canteen_item SET Qnty='$TQnty' WHERE ItemID='$ItemID'");
    }


// View Grid
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<td width="10%"><b>Item ID</b></td>';
              echo '<td width="40%"><b>Item Name</b></td>';
              echo '<td width="10%"><b>Qnty</b></td>';
              echo '<td width="15%"><b>Purchase Price</b></td>';
              echo '<td width="15%"><b>Sale Price</b></td>';
              echo '<td width="10%"><b>Delete</b></td>';
            echo '</tr>';
        $qry=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_sales_detail.*
        FROM canteen_sales_detail INNER JOIN canteen_item
        ON canteen_item.ItemID = canteen_sales_detail.ItemID
        WHERE canteen_sales_detail.SalesID='$SalesID'") ;
while ($Detail = mysql_fetch_object($qry)){

		print "<tr>" ;
                print "<td>$Detail->ItemID</td>\n";
	        print "<td>$Detail->ItemName</td>\n";
		print "<td>$Detail->Qnty</td>\n";
    print "<td>$Detail->PurAmt</td>\n";
		print "<td>$Detail->Amt</td>\n";
		print "<td><a href=\"Sales.php?Bill=$SalesID&ItemID=$Detail->ItemID&qnty=$Detail->Qnty&id=$Detail->ID\"> Delete </a></td></tr>\n";
}
echo "</table>";

// Count Of Total Amount
$Total = mysql_query("SELECT SUM(Amt) as 'Sum', SUM(PurAmt) as 'PurAmt' FROM `canteen_sales_detail` WHERE SalesID='$SalesID'");
$S=mysql_fetch_object($Total);
$Sum=$S->Sum;
$PurAmt = $S->PurAmt;
if ($Post=="Posted")
    {$TotalAmount=$BTotal;}
    else {$TotalAmount=$Sum;}
	  ?>
         
	  </td>
    </tr>
  </table>
</form>

<form name="form2" method="GET" id="form2" action="">
<table width="100%" border="0" cellspacing="0" cellpadding="1" style="font-size: 14pt">
<tr bgcolor="#f1f1f1">
  <td><a class="battun" href="Sales.php?NBill=add_new" title="Alt+A"> Add New </a>
	<a class="battun" onClick="bill();" href="#" title="Alt+M" >Bill</a>
	<input name="Bill" type="hidden" id="Bill" value="<?php echo $SalesID ; ?>">
	<input name="Nickname" type="hidden" id="Nickname">
	<input name="Posted" type="hidden" id="Postedt" value="<?php echo $Post ; ?>">
	<input name="hPost" type="hidden" id="hPost" value="YES">
	<input onClick="formPost()" type="button" name="Post" value="  Post  " title="Alt+P" style="font-size: 14pt"></td>
              
              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>Discount : </td>
              <td><input name="discount" type="text" size="10" onKeyUp ="TotalAmount(this.value)" value="<?php echo $Disc ; ?>" style="font-size: 14pt"></td>
              <td>Pur Amount : </td>
              <td><input name="amount1" type="text" id="amount1" style="font-size: 14pt" value = "<?php echo  round($PurAmt, 2);  ?>" size="10" readonly></td>
</tr>
<tr bgcolor="#f1f1f1">
  <td>Note : &nbsp;<input name="Note" type="text" size="30" value="<?php  echo $Note ; ?>"></td>
  <td>&nbsp;</td>
  <td>&nbsp;</td>
  <td>Total :</td>
  <td><input name="total" type="text" size="10" readonly value = "<?php echo $TotalAmount ; ?>" style="font-size: 14pt"></td>
  <td>Sales Amount :</td>
  <td><input name="amount2" type="text" id="amount2" style="font-size: 14pt" value = "<?php echo $Sum ; ?>" size="10" readonly></td>
</tr>
</table>
</form> 


<form name="find" method="get" id="form1" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1">
  <tr>
    <td width="25%">
      <a href="Sales.php?Bill=<?php echo $b=$SalesID-1 ; ?>">Pre</a>
    <input name="Bill" type="text" id="Bill" value="<?php echo $SalesID ; ?>" size="15">
    <a href="Sales.php?Bill=<?php echo $b=$SalesID+1 ; ?>">Next</a></td>
    <td width="66%"><input name="Find" type="submit" id="Find" value="  Find  "></td>
    <td width="9%">&nbsp;</td>
  </tr>
</table>
</form>
</fieldset>

		
  <?php include 'footer.php'; ?>
        </div>
    </body>
</html>
