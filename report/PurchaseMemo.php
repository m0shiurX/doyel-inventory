<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login();
$id = is_scalar($_GET['id'] ?? null) ? (string)$_GET['id'] : '';
$SalesID = ctype_digit($id) ? (int)$id : 0;
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="Report.css">

<div id="main">
<div class="printb"">
  <a href="#" onClick="javascript:window.print()">Print</a>
</div>
<?php include 'menu.php';

echo '<h1 align="center">Sales Memo</h1>';


echo '<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<th width="10%">Item ID</th>';
              echo '<th width="40%">Item Name</th>';
              echo '<th width="10%">Qnty</th>';
              echo '<th width="15%">Amount</th>';
            echo '</tr>';

// Despite its filename this legacy page displays sale lines; preserve that contract.
$bgcolor = '';
foreach (db_all('SELECT i.ItemID, i.ItemName, d.Qnty, d.Amt FROM canteen_sales_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE d.SalesID = ? ORDER BY d.ID', [$SalesID]) as $Detail) {
    $bgcolor = $bgcolor === '#ffffff' ? '#f1f1f1' : '#ffffff';
    echo '<tr bgcolor="' . e($bgcolor) . '"><td>' . e($Detail->ItemID) . '</td><td>' . e($Detail->ItemName) . '</td><td>' . e(fmt_numeric($Detail->Qnty)) . '</td><td>' . e(fmt_numeric($Detail->Amt)) . '</td></tr>';
}
echo "</table>";

?>
</div>
    </body>
</html>
