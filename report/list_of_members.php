<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="report.css">
    <link href="../css/Style.css" rel="stylesheet" type="text/css">
    <link href="../css/lessframework4.css" rel="stylesheet" type="text/css">
    <title>List of members with dues</title>

<div id="main" style="width: 80%; margin: 5px auto 10px auto; padding:0;">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include("menu.php");
include '../connect.php';

?>
    <h2 align="center">List Of Members With Dues</h2>

<table width="95%" border="0" cellspacing="0" cellpadding="1" id="tableGd"  style="
    border-spacing: 0; font-size:1em; margin-bottom:10px;
">
              <tr bgcolor="#f1f1f1">
              <td width="3%"><b>ID</b></td>
              <td width="25%"><b>Name of Member</b></td>
              <td width="22%"><b>Mobile</b></td>
              <td width="40%"><b>Address</b></td>
              <td width="10%"><b>DUE</b></td>
            </tr>

	<?php
       $sql="SELECT * FROM canteen_customar" ;
       $rs=mysql_query($sql, $conn) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            while ($row=mysql_fetch_object($rs)){

    	    $Description=$row->Nickname;
    	    $Due=netTotal($Description);
    	    print "<tr>";
    	    if ($Due>0) {
    	       	print "<td>$row->MemberID</td>\n";
    	    	print "<td>$row->MemberName</td>\n";
    	    	print "<td>$row->mobile, $row->mobile2</td>\n";
    	    	print "<td>$row->address</td>\n";
    	        print "<td style=\"text-align:right; padding-right: 5px;\">$Due</td>\n";
    	        print "</tr>";
    	    }
            }
        }

        function netTotal($Description){
          $Total = mysql_query("SELECT SUM(Total) as 'dTotal' FROM `canteen_sales` WHERE CustomerName='$Description'");
          $S=mysql_fetch_object($Total);
          $dueTotal=$S->dTotal;

          $Total = mysql_query("SELECT SUM(amount) as 'pTotal' FROM `canteen_collection` WHERE Description='$Description'");
          $S2=mysql_fetch_object($Total);
          $paidTotal=$S2->pTotal;
          $netTotal = $dueTotal-$paidTotal;
          return $netTotal;
        }

  ?>
		</table>
		
  </div>
    </body>
</html>
