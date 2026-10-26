<?php
if (!empty($_POST['SupplierName'])){
include 'connect.php';

    $SupplierName=$_POST['SupplierName'];

$check = mysql_query("SELECT SupplierName FROM canteen_supplier WHERE SupplierName = '$SupplierName'");
if (mysql_num_rows($check) == 0){

$query = mysql_query("INSERT INTO canteen_supplier(SupplierName) VALUES('$SupplierName')");
if ($query)
    { echo   "<h2>Sucses New SupplierName Added</h2>";   }
    else{  echo   "<h2>Errors</h2>";   }
    }
    else { echo "<h2>This SupplierName is already Added, Please try anather</h2>";  }
    mysql_close($conn);

}else{ echo 'Please fill in SupplierName fild';}

?>