<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>Update Closing Balance</title>
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php"); ?> 
<?php include '../connect.php'; ?> 

 
 <table width="100%" border="0" cellspacing="1" cellpadding="1">
    <tr bgcolor="#f1f1f1">
      <th width="8%"><b>ID</b></th>
      <th align="left" width="35%"><b>Name of Item </b></th>
      <th align="left" width="13%"><b>Quantity</b></th>
      <th align="left" width="9%"><b>Purchase</b></th>
      <th align="left" width="13%"><b>Total Sales</b></th>
    </tr>
	<?php
      $sqlc="SELECT ItemID,ItemName FROM canteen_item WHERE D='0'" ;
      $rsc=mysql_query($sqlc, $conn) or die (mysql_error());
      if(mysql_num_rows($rsc)>0){
            while ($rowc=mysql_fetch_object($rsc)){
      			echo '<tr align="" bgcolor="#fff"><th>';
      			echo $ItemID=$rowc->ItemID;
      			echo "</th>";
            echo '<td>';
            echo $ItemName=$rowc->ItemName;
            echo "</td>";
      			echo '<td align="center">';
      			echo $Qnty=quantity($ItemID);
      			echo "</td>";
            echo '<td align="center">';
            echo $itemPur=itemPur($ItemID);
            echo "</td>";
            echo '<td align="center">';
            echo $itemSales=itemSales($ItemID);
            echo "</td></tr>";
      			}
	    }
		
      $sT="SELECT count(*) as total, SUM(Qnty) as tQnty, SUM(PurPrice) as tPurPrice FROM canteen_item" ;
      $srs=mysql_query($sT) or die (mysql_error());	  
      $sTotal=mysql_fetch_object($srs);
      $tPurPrice=$sTotal->tPurPrice;
      $tPurPrice=round($tPurPrice, 2);
      $tTotal=$tPurPrice*$sTotal->total;
		?>
		<tr>
      <th><b>&nbsp;</b></th>
      <th align="left"><b>Total</b> &nbsp; <b><?php echo $sTotal->total; ?> Products</b></th>
      <th align="left"><b><?php echo $sTotal->tQnty; ?></b></th>
      <th align="left"><b><?php echo $tPurPrice; ?></b></th>
		  <th align="left"><b><?php echo $tTotal; ?></b></th>
    </tr>
  </table>
 
<?php
		function itemPur($ItemID){
       $sql="SELECT ItemID,SUM(Amt) as Qnty FROM canteen_purchase_detail WHERE ItemID='$ItemID' GROUP BY ItemID" ;

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
          $row=mysql_fetch_object($rs);
  			  return $row->Qnty;
      }
	}
	
		function itemSales($ItemID){	
       $sql="SELECT ItemID,SUM(Amt) as Qnty FROM canteen_sales_detail WHERE ItemID='$ItemID' GROUP BY ItemID" ;
       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
          $row=mysql_fetch_object($rs);
    			return $row->Qnty;
          }
	} 

      function  quantity($ItemID){
      $sql=mysql_query("SELECT SUM(Qnty) as Qnty FROM canteen_purchase_detail WHERE ItemID='$ItemID'") ;
      $Qnty=mysql_fetch_object($sql);
        echo $Qnty = $Qnty->Qnty;

      }
?>


</div>
    </body>
</html>
