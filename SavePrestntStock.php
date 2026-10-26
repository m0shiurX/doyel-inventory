<?php session_start() ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Update Closing Balance</title>

<?php include 'connect.php'; ?> 


	<?php
	date_default_timezone_set('Asia/Dacca');
	$date = date("Y-m-d, H-i-s");
	mysql_query("INSERT INTO `canteen_stock`(`Date`)VALUES('$date')");
	$SlNo=mysql_query("SELECT * FROM canteen_stock ORDER BY SlNo desc LIMIT 1") ;
	$SlNo = mysql_fetch_object($SlNo);
	$SlNo = $SlNo->SlNo;
	
    $sqlc="SELECT * FROM canteen_item WHERE D='0'" ;
	       $rsc=mysql_query($sqlc, $conn) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
            while ($rowc=mysql_fetch_object($rsc)){
			
			$SupplierName=$rowc->SupplierName;
			$ItemID=$rowc->ItemID;
			$ItemName=$rowc->ItemName;
			$PurPrice=$rowc->PurPrice;
			$itemPur=itemPur("$ItemID");
			$itemSales=itemSales("$ItemID");
			$Qnty=$itemPur-$itemSales;
			$Qnty=$rowc->Qnty;
			if($Qnty>0){
mysql_query("INSERT INTO `canteen_stock_detail`(`SlNo` , `Date`,`SupplierName`,`ItemID`,`ItemName`,`PurPrice`,`Qnty` ) VALUES('$SlNo','$date','$SupplierName','$ItemID','$ItemName','$PurPrice','$Qnty')");
}
			}
	    }


	?>
<h2 align="center">Stock Save Complite</h2>

<?php

		function itemPur($ItemID){	
        
       $sql="SELECT ItemID,SUM(Qnty) as Qnty FROM canteen_purchase_detail WHERE ItemID='$ItemID' GROUP BY ItemID" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            $row=mysql_fetch_object($rs);
			return $row->Qnty;
      }
	}
	
	function itemSales($ItemID){	
        
       $sql="SELECT ItemID,SUM(Qnty) as Qnty FROM canteen_sales_detail WHERE ItemID='$ItemID' GROUP BY ItemID" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            $row=mysql_fetch_object($rs);
			return $row->Qnty;
      }
	} 
 ?>

    </body>
</html>
<!-- INSERT INTO `canteen_stock_detail`(`SlNo` , `Date`, `SupplierName`, `ItemID`, `ItemName` ,`PurPrice` , `Qnty` ) VALUES('$SlNo','$date','$SupplierName','$ItemID','$ItemName','$PurPrice','$Qnty') -->