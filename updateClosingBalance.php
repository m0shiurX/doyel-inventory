<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>Update Closing Balance</title>

<?php include 'connect.php'; ?> 


	<?php
	
	
    $sqlc="SELECT ItemID,ItemName FROM canteen_item WHERE D='0'" ;
	       $rsc=mysql_query($sqlc, $conn) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
            while ($rowc=mysql_fetch_object($rsc)){
			

			$ItemID=$rowc->ItemID;
			$itemPur=itemPur("$ItemID");
			$itemSales=itemSales("$ItemID");
			$Qnty=$itemPur-$itemSales;
			mysql_query("UPDATE canteen_item SET Qnty='$Qnty' WHERE ItemID='$ItemID'");		
			
			}
	    }
	?>
<h2 align="center">Update Complete</h2>
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

	function  qnttity($ItemID){
	$sql=mysql_query("SELECT SUM(Qnty) as Qnty FROM canteen_purchase_detail WHERE ItemID='$ItemID'") ;

	$Qnty=mysql_fetch_object($sql);
	echo $Qnty = $Qnty->Qnty;

	}
?>
</body>
</html>
