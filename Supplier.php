<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
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
                    data: {SupplierName: SupplierName},

                    success: function(result){
                        //console.log(result);
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
<div style=" height:450px;display:block; margin-bottom:30px; margin-top:20px;">

 <form action="" method="post">
   <div id="subform">
   <a href="#">Add New Supplier</a><input name="SupplierName" type="text" id="SupplierName" size="44">
   <input type="submit" name="button" id="button" value="Submit">
   </div>
 </form>

<div style="display:block;width:48%; float:left;" class="report">
            <h2>List of Supplier</h2>
    <div style="height:400px; overflow:auto">
    <table id="tableGd" width="96%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <th width="8%">ID</th>
              <th width="65%">SupplierName</th>
              <th width="19%">Due</td>
              <th width="8%">Show</th>
            </tr>
            <?php
$tAmount = 0;
$suppliers = db_all('SELECT s.SupplierId, s.SupplierName, COALESCE(p.total, 0) AS due, COALESCE(e.total, 0) AS paid FROM canteen_supplier s LEFT JOIN (SELECT Supplier, SUM(Total) AS total FROM canteen_purchase GROUP BY Supplier) p ON p.Supplier = s.SupplierName LEFT JOIN (SELECT Description, SUM(amount) AS total FROM canteen_expenses GROUP BY Description) e ON e.Description = s.SupplierName WHERE s.D = ? ORDER BY s.SupplierName', [0]);
foreach ($suppliers as $Mem) {
    $Total = (float)$Mem->due - (float)$Mem->paid;
    $tAmount += $Total;
    $url = 'Supplier.php?' . http_build_query(['ID' => $Mem->SupplierId, 'Name' => $Mem->SupplierName]);
    echo '<tr><td>' . e($Mem->SupplierId) . '</td><td>' . e($Mem->SupplierName) . '</td><td>' . e($Total) . '</td><td><a href="' . e($url) . '">Show</a></td></tr>';
}
?>
            <tr bgcolor="#f1f1f1">
                <td>&nbsp;</td>
                <td><b>Total Payable Amount</b></td>
                <td colspan="3"><b><?= e($tAmount) ?></b></td>
              </tr>
        </table>
            </table>
      </div>
        </div>

        <div style="width:51%; float:right" class="report">
            <h2>Bill Payment</h2>
<?php if ($selectedId !== ''): ?>
<div align="left" style=" padding:0 15px;">
 <h3><?= e($selectedName) ?></h3>


 <div style="max-height:160px; overflow:auto; margin-bottom:10px;">
    <table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="18%"><b>Date</b></td>
              <td width="23%"><b>Bill No</b></td>
              <td width="17%"><b>Amount</b></td>
              <td width="28%"><b>Discount</b></td>
              <td width="14%"><b>Total</b></td>
            </tr>
 <?php
foreach (db_all('SELECT BillNo, Date, Amount, Discount, Total FROM canteen_purchase WHERE Supplier = ? ORDER BY Date DESC, BillNo DESC LIMIT 5', [$selectedName]) as $Paid) {
    $Date = date_create($Paid->Date);
    echo '<tr><td>' . e($Date ? date_format($Date, 'd-M-y') : '') . '</td><td>' . e($Paid->BillNo) . '</td><td>' . e(fmt_numeric($Paid->Amount)) . '</td><td>' . e(fmt_numeric($Paid->Discount)) . '</td><td>' . e(fmt_numeric($Paid->Total)) . '</td></tr>';
}
?>

 </table>
 </div>
            <?php
$due = db_one('SELECT (SELECT COALESCE(SUM(Total), 0) FROM canteen_purchase WHERE Supplier = ?) AS due, (SELECT COALESCE(SUM(amount), 0) FROM canteen_expenses WHERE Description = ?) AS paid', [$selectedName, $selectedName]);
echo '<b>Total DUE : ' . e((float)$due->due - (float)$due->paid) . '</b>';
?>

  <br><br>
  <form name="form1" method="post" action="">
    Amount
    <input name="amount" type="text" id="amount" size="10">
    Remarks
    <input name="Remarks" type="text" id="Remarks">
    <input type="submit" name="Submit" value="Payment">
  </form>

  <div style="max-height:160px; overflow:auto; margin-bottom:10px;">
    <table id="tableGd" width="100%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="18%"><b>Date</b></td>
              <td width="23%"><b>Paid By</b></td>
              <td width="17%"><b>Amount</b></td>
              <td width="28%"><b>Remarks</b></td>
              <td width="14%"><b>Recept</b></td>
            </tr>
 <?php
foreach (db_all('SELECT Date, UserName, amount, Remarks FROM canteen_expenses WHERE Description = ? ORDER BY Date DESC, PaymentID DESC LIMIT 5', [$selectedName]) as $Paid) {
    $Date = date_create($Paid->Date);
    echo '<tr><td>' . e($Date ? date_format($Date, 'd-M-y') : '') . '</td><td>' . e($Paid->UserName) . '</td><td>' . e(fmt_numeric($Paid->amount)) . '</td><td>' . e($Paid->Remarks) . '</td><td><a href="#">Recept</a></td></tr>';
}
?>

 </table>
    </div>
</div>
<?php endif; ?>
        </div>
   </div>

<?php include 'footer.php'; ?>
</div>
    </body>
</html>
