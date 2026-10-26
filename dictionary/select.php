<?php

if (!empty ($_GET["q"])){
$q=$_GET["q"];
//$q="Apachi My SQL PHP";
include '../connect.php';

  $sql = mysql_query("SELECT ItemID, PurPrice, SalesPrice, Qnty FROM canteen_item WHERE ItemName = '$q' ");
//  $rs=mysql_query($sql, $conn) or die (mysql_error());
        if(mysql_num_rows($sql)>0){
            while ($row=mysql_fetch_object($sql)){

$ItemID=$row->ItemID;
$TQnty= $row->Qnty;
$PurPrice=$row->PurPrice;
$SalesPrice=$row->SalesPrice;
       //echo "<input name=\"rate\" type=\"text\" id=\"rate\"  value=".$Price .">";
echo $ItemID;
echo "/$TQnty";
echo "/$PurPrice";
echo "/$SalesPrice";



      }
        }
}
?> 