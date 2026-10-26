<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">    
        window.onload = function()
        {                
          var bComplete = new BComplete("supplier",10);
          bComplete.loadData("dictionary/supplier.php")
        };
    </script>     
    <div align="center"><div id="main">
  <?php include("menu.php"); ?>
  <h2>Single Product </h2>
<?php 

$ItemName='';

include 'connect.php';

if (!empty($_POST['Item'])){
  $ItemName=$_POST['Item'];
  $SupplierName=$_POST['supplier'];
  $GenericName=$_POST['GenericName'];
  $ItemType=$_POST['ItemType'];
  $PurPrice=$_POST['PurPrice'];
  $SalesPrice=$_POST['SalesPrice'];
  $totalQnty = $_POST['totalQnty'];
  $MaximumQuantity=$_POST['MaximumQuantity'];
  $MinimumQuantity=$_POST['MinimumQuantity'];
  $Qnty=0;
  if (!empty($_GET['Drop'])) {$Drop=$_POST['Drop'];}else{$Drop=0;}
  if (!empty($_GET['ID'])) {
    $ID=$_GET['ID'];
    mysql_query("UPDATE canteen_item SET ItemName='$ItemName', PurPrice='$PurPrice', SalesPrice='$SalesPrice', SupplierName='$SupplierName', GenericName='$GenericName', ItemType='$ItemType', Qnty = '$totalQnty', MaximumQuantity='$MaximumQuantity', MinimumQuantity='$MinimumQuantity' WHERE ItemID = '$ID'" );
  }else{
    $check = mysql_query("SELECT ItemName FROM canteen_item WHERE ItemName = '$ItemName'");
    if (mysql_num_rows($check) == 0){
      $query = mysql_query("INSERT INTO canteen_item (ItemName, PurPrice, SalesPrice, SupplierName, GenericName, ItemType, MaximumQuantity, MinimumQuantity, Qnty) VALUES('$ItemName','$PurPrice','$SalesPrice','$SupplierName','$GenericName','$ItemType','$MaximumQuantity','$MinimumQuantity','$Qnty')");
        if ($query)
          { echo   "<h2>You have successfully added an item</h2>";
        }else{  echo   "<h2>Sorry ! Operation failed.</h2>";   }
    }else {
        echo "<h2>This Item exists ! Try a new one.</h2>";
    }
  }
}

if (!empty($_GET['ID']))
{
  $ID=$_GET['ID'];
  $mem=mysql_query("SELECT * FROM canteen_item WHERE ItemID = '$ID'");
  while($Itemm=mysql_fetch_object($mem)){
    $ItemID=$Itemm->ItemID;
    $ItemName=$Itemm->ItemName;
    $PurPrice=$Itemm->PurPrice;
    $SalesPrice=$Itemm->SalesPrice;
    $SupplierName=$Itemm->SupplierName;
    $GenericName=$Itemm->GenericName;
    $ItemType=$Itemm->ItemType;
    $getTotal=$Itemm->Qnty;
    $MaximumQuantity=$Itemm->MaximumQuantity;
    $MinimumQuantity=$Itemm->MinimumQuantity;
    $Drop=$Itemm->D;
  }
}

mysql_close($conn);
?>
<fieldset>
  <legend>Adding/Updating an Item</legend>
  <form action="" method="post" enctype="multipart/form-data" name="add_book" id="add_book">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td>Item ID</td>
        <td width="65%">
          <?php
            if (!empty($_GET['ID'])){
              echo "<input name=\"ItemID\" type=\"text\" id=\"ItemID\" readonly=\"YES\" value=\"$ItemID\">";
              echo "&nbsp;&nbsp;&nbsp;&nbsp;Drop ";
              if (isset($Drop)) { echo "<input type=\"checkbox\" name=\"Drop\" value=\"$Drop\"/>";}
            }else {
              echo "&nbsp;";
              }
          ?>
        </td>
          <td>Available</td>
          <td><input name="totalQnty" type="number" id="totalQnty" <?=(!empty($_GET['ID']) && $_SESSION['admin'] !='1')? "" : "disabled"?>  value="<?php if (isset($getTotal)) {print $getTotal;} ?>"></td>
      </tr>
      <tr>
        <td width="17%">Item Name</td>
        <td width="27%"><input onKeyPress="return dg(event);" name="Item" type="text" id="Item" size="30" value="<?php if (isset($ItemName)) {print $ItemName;} ?>"></td>
        <td width="11%">Pur Price</td>
        <td width="19%"><input name="PurPrice" type="text" id="PurPrice" value="<?php if (isset($PurPrice)) {print $PurPrice;} ?>"></td>
        <td width="26%">Min</td>
        <td width="19%"><input name="MinimumQuantity" type="text" id="MinimumQuantity" value="<?php if (isset($MinimumQuantity)) {print $MinimumQuantity;} ?>"></td>
      </tr>
      <tr>
        <td>Supplier Name</td>
        <td>
          <label>
            <input onKeyPress="return dg(event);" name="supplier" value="Doyel Eng" type="text" id="supplier" size="30" value="<?php if (isset($SupplierName)) {print $SupplierName;} ?>"/>
          </label>
        </td>
        <td>Sales Price</td>
        <td><input name="SalesPrice" type="text" id="SalesPrice" value="<?php if (isset($SalesPrice)) {print $SalesPrice;} ?>"/></td>
        <td width="26%">Max</td>
        <td width="19%"><input name="MaximumQuantity" type="text" id="MaximumQuantity" value="<?php if (isset($MinimumQuantity)) {print $MinimumQuantity;} ?>"></td>
      </tr>
      <tr>
        <td>Catagory</td>
        <td>
          <select name="GenericName" id="select">
            <option selected><?php if (isset($GenericName)) {print $GenericName;} else print "Select Catagory" ?></option>
            <option>Row Material</option>
            <option>Sales Item</option>
          </select>
        </td>
        <td>Item Type</td>
        <td>
          <select name="ItemType" id="select">
            <option value="<?php if (isset($ItemType)) {print $ItemType;} else print "Select Catagory" ?>" selected><?php if (isset($ItemType)) {print $ItemType;} else print "Select Catagory" ?></option>
            <option value="P">Piston</option>
            <option value="L">Liner</option>
            <option value="DP">Dhalay Puli</option>
            <option value="AP">Alay Puli</option>
            <option value="BC">Bearing Case</option>
            <option value="H">Haps</option>
            <option value="DT">Dhalay Taoa</option>
            <option value="DK">Dhalay Koray</option>
            <option value="AK">Alay Koray</option>
            <option value="AK">Alay Taoa</option>
            <option value="BD">Break Drum</option>
            <option value="AF">Alay Fan</option>
            <option value="O">Others</option>
          </select>
        </td>
        <td>&nbsp;</td>
        <td><input type="submit" name="Submit" value="Submit"></td>
      </tr>
    </table>
  </form>
</fieldset>
<?php include 'footer.php'; ?>
</div>
</body>
</html>
