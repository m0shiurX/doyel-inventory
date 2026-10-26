<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">
<title>List of Item</title>
<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php");
include '../connect.php';

 $FormDate= $_SESSION['form_date'];
 $ToDate=$_SESSION['to_date'];
 
?>
<div align="center"><h1>List of Item</h1></div>

<table width="100%" border="0" cellspacing="0" cellpadding="1" id="tableGd">
              <tr>
              <th width="17%"><b>ID</b></th>
              <th width="42%"><b>Name of Item </b></th>
              <th width="20%"><b>Pur Price</b></th>
              <th width="21%"><b>Sales Price</b></th>
            </tr>
<?php
    $Total=""; $TotalPrice="";
    $sqlc="SELECT GenericName,count(*) as sub_total,SUM(PurPrice) as PurPrice,SUM(SalesPrice) as SalesPrice FROM canteen_item WHERE D='0' GROUP BY GenericName" ;
	$rsc=mysql_query($sqlc, $conn) or die (mysql_error());
        if(mysql_num_rows($rsc)>0){
            while ($rowc=mysql_fetch_object($rsc)){
			echo '<tr align="left" bgcolor="#fff"><td colspan="4"><b>';
			echo $GenericName=$rowc->GenericName;
			echo "</b></td></tr>\n";
			item("$GenericName");
			$PurPrice=round($rowc->PurPrice, 2);
			$SalesPrice=round($rowc->SalesPrice, 2);
			print "<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Sub Total &nbsp;&nbsp; $rowc->sub_total</b></th>
              <th><b>$PurPrice</b></th>
              <th><b>$SalesPrice</b></th>
            </tr>";
			$Total=$Total+$rowc->sub_total;
			$TotalPrice=$TotalPrice+round($rowc->PurPrice, 2);
			}
	    }
		?>
		<tr>
              <th><b>&nbsp;</b></th>
              <th><b>Total &nbsp;&nbsp; <?php echo $Total ?></b></th>
              <th><b><?php echo $TotalPrice ?></b></th>
              <th><b>&nbsp;</b></th>
            </tr>
	  </table>
 	<?php
		function item($GenericName){
       $sql="SELECT * FROM canteen_item WHERE GenericName='$GenericName' AND D='0' ORDER BY ItemName";

       $rs=mysql_query($sql) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            while ($row=mysql_fetch_object($rs)){

		print "<tr id=\"$row->ItemID\">" ;
                print "<td>$row->ItemID</td>\n";
		print "<td>$row->ItemName</td>\n";
		print "<td>$row->PurPrice</td>\n";
		print "<td>$row->SalesPrice</td>\n";
                print "</tr>\n";
            	}
        	}
		}
        ?>

</div>
    </body>
</html>
