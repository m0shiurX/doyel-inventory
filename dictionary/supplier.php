<?php
        
include '../connect.php';


echo "[";	
$sql="SELECT SupplierName FROM canteen_supplier WHERE D = '0'";

$rs=mysql_query($sql, $conn) or die (mysql_error());    //Get Result set

//Do we Have Results?
if(mysql_num_rows($rs) > 0){
    //print "You Have Row?";
   while ($row=mysql_fetch_object($rs)){
       print '"'.$row->SupplierName.'", ';

   }
   }else{
print "Sorry, No Result Found";
}
echo "]";		
?>
