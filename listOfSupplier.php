<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
$selectedName = is_string($_GET['Name'] ?? null) ? $_GET['Name'] : '';
$selectedId = is_scalar($_GET['ID'] ?? null) ? (string)$_GET['ID'] : '';
$paymentAdded = false;
$amountInput = is_scalar($_POST['amount'] ?? null) ? (string)$_POST['amount'] : '';
if ($selectedName !== '' && is_numeric($amountInput) && is_finite((float)$amountInput) && (float)$amountInput != 0) {
    db_exec('INSERT INTO canteen_expenses (Date, UserName, Description, HeadOfAccount, amount, Remarks, amt) VALUES (?, ?, ?, ?, ?, ?, ?)', [now_sql(), mb_substr(current_user(), 0, 30, 'UTF-8'), mb_substr($selectedName, 0, 100, 'UTF-8'), 'Supplier Payment', input_decimal($amountInput), mb_substr(is_string($_POST['Remarks'] ?? null) ? $_POST['Remarks'] : '', 0, 20, 'UTF-8'), 0]);
    $paymentAdded = true;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<script src="report/AutoSuggestDropDown/jquery-1.7.1.min.js"></script>
        <script type="text/javascript">
        $(function() {

            $('#button').click(function() {
                $('#subform').append('<img src="img/loading.gif" class="loading" width="16" height="16">');
                var SupplierName= $('#SupplierName').val();
                $.ajax({
                    url: 'AddSupplierName.php',
                    type: 'POST',
                    data: {SupplierName: SupplierName, csrf_token: <?= json_encode(csrf_token()) ?>},

                    success: function(result){
                        $('.response').remove();
                        $('#subform').append('<p class="response"' + result + '</p>');
                        $('.loading').fadeOut(500, function() {
                            $(this).remove();
                        });
                    }

                })

                return false;
        });
    });

        </script>


<div id="main">

<?php include 'menu.php';
if ($paymentAdded) echo 'Payment Sucsecc';
?>

 <form action="" method="post"><?php csrf_field(); ?>
   <div id="subform">
   <a href="#">Add New Supplier</a><input name="SupplierName" type="text" id="SupplierName" size="44">
   <input type="submit" name="button" id="button" value="Submit">
   </div>
 </form>

    <h2>List of Supplier</h2>
    <div style="overflow:auto">
        <table id="tableGd" width="96%" border="0" cellspacing="1" cellpadding="1">
          <tr bgcolor="#f1f1f1">
          <th width="8%">ID</th>
          <th width="65%">SupplierName</th>
          <th width="19%">Drop</td>
          <th width="8%">Edit</th>
        </tr>
        <?php
foreach (db_all('SELECT SupplierId, SupplierName FROM canteen_supplier WHERE D = ? ORDER BY SupplierName', [0]) as $Mem) {
    $url = 'Supplier.php?' . http_build_query(['ID' => $Mem->SupplierId, 'Name' => $Mem->SupplierName]);
    echo '<tr><td>' . e($Mem->SupplierId) . '</td><td>' . e($Mem->SupplierName) . '</td><td></td><td><a href="' . e($url) . '">Edit</a></td></tr>';
}
?>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
