<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
?>
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
      $sl = 1;
      foreach (db_all("SELECT ItemID, ItemName, Qnty FROM canteen_item WHERE D = ? AND Qnty < MinimumQuantity ORDER BY ItemID", [0]) as $row) {
          print '<tr><td>' . $sl++ . '</td>';
          print '<td>' . e($row->ItemName) . '</td>';
          print '<td>' . e(fmt_numeric($row->Qnty)) . '</td></tr>';
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
      $sl = 1;
      foreach (db_all("SELECT ItemID, ItemName, Qnty FROM canteen_item WHERE D = ? AND Qnty > MaximumQuantity ORDER BY ItemID", [0]) as $row) {
          print '<tr><td>' . $sl++ . '</td>';
          print '<td>' . e($row->ItemName) . '</td>';
          print '<td>' . e(fmt_numeric($row->Qnty)) . '</td></tr>';
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
