<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<div align="center"><div id="main">

<?php include("menu.php"); ?>
<div class="content">

<div class="dbleft">
  <h2>List of products below the minimum quantity</h2>
  <table width="" border="0" cellspacing="0" cellpadding="1" id="tableGd" >
  <tr>
      <th><b>SL</b></th>
<!--       <th>ID</th> -->
      <th>Name</th>
      <th>Available</th>
    </tr>
    <?php
      include 'connect.php';
      $sql="SELECT * FROM `canteen_item` WHERE D='0' and Qnty<MinimumQuantity";
      $sl=1;
      $rs=mysql_query($sql) or die (mysql_error());
      if(mysql_num_rows($rs)>0){
        while ($row=mysql_fetch_object($rs)){
          print "<tr>" ;
          print "<td>$sl</td>\n";
          // print "<td>$row->ItemID</td>\n";
          print "<td>$row->ItemName</td>\n";
          print "<td>$row->Qnty</td>\n";
          print "</tr>\n";
          $sl++;
        }
      }
    ?>
  </table>
</div>
<div class="dbright">
  <h2>List of products above the maximum quantity</h2>
  <table width="" border="0" cellspacing="0" cellpadding="1" id="tableGd" >
    <tr>
      <th><b>SL</b></th>
<!--       <th>ID</th> -->
      <th>Name</th>
      <th>Quantity</th>
    </tr>

    <?php
      $sql="SELECT * FROM `canteen_item` WHERE D='0' and Qnty>MaximumQuantity";
      $sl=1;
      $rs=mysql_query($sql) or die (mysql_error());
      if(mysql_num_rows($rs)>0){
        while ($row=mysql_fetch_object($rs)){
          print "<tr>" ;
          print "<td>$sl</td>\n";
          // print "<td>$row->ItemID</td>\n";
          print "<td>$row->ItemName</td>\n";
          print "<td>$row->Qnty</td>\n";
          print "</tr>\n";
          $sl++;
        }
      }
    ?>
  </table>
</div>

</div>
<?php include 'footer.php'; ?>
</div>
</div>
</body>
</html>
