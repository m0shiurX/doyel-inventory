<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
function payment_text(array $source, string $key, int $length = 0): string
{
    $value = is_string($source[$key] ?? null) ? $source[$key] : '';
    return $length > 0 ? mb_substr($value, 0, $length, 'UTF-8') : $value;
}
$Nickname = payment_text($_GET, 'Name');
// Aggregate once per source table, but subtract in PHP as the legacy API did.
// Keeping the SUM strings separate preserves its floating-point display behavior.
$customers = db_all('SELECT c.MemberID, c.MemberName, c.Nickname, c.address, s.dueTotal, p.paidTotal FROM canteen_customar c LEFT JOIN (SELECT CustomerName, SUM(Total) AS dueTotal FROM canteen_sales GROUP BY CustomerName) s ON s.CustomerName = c.Nickname LEFT JOIN (SELECT Description, SUM(amount) AS paidTotal FROM canteen_collection GROUP BY Description) p ON p.Description = c.Nickname ORDER BY c.Nickname');
$paymentMessage = '';
$rawAmount = payment_text($_POST, 'amount');
if ($Nickname !== '' && is_numeric($rawAmount) && is_finite((float)$rawAmount) && $rawAmount !== '0') {
    $amount = input_decimal($rawAmount);
    if (!is_admin()) $amount = ltrim($amount, '-');
    db_exec('INSERT INTO canteen_collection (UserName, Date, Description, HeadOfAccount, amount, Remarks) VALUES (?, ?, ?, ?, ?, ?)', [mb_substr(current_user(), 0, 50, 'UTF-8'), now_sql(), mb_substr($Nickname, 0, 100, 'UTF-8'), 'Collection from Customer', $amount, payment_text($_POST, 'Remarks', 100)]);
    $paymentMessage = 'Sucsess';
}
$member = $Nickname !== '' ? db_one('SELECT MemberName, mobile, address FROM canteen_customar WHERE Nickname = ? ORDER BY MemberID LIMIT 1', [$Nickname]) : null;
$sales = $Nickname !== '' ? db_all('SELECT SalesID, Date, UserName, Amount, Discount, Total FROM canteen_sales WHERE CustomerName = ? ORDER BY SalesID DESC LIMIT 100', [$Nickname]) : [];
$due = $Nickname !== '' ? db_one('SELECT (SELECT SUM(Total) FROM canteen_sales WHERE CustomerName = ?) AS dueTotal, (SELECT SUM(amount) FROM canteen_collection WHERE Description = ?) AS paidTotal', [$Nickname, $Nickname]) : null;
$payments = $Nickname !== '' ? db_all('SELECT PaymentID, Date, UserName, Description, amount, Remarks FROM canteen_collection WHERE Description = ? ORDER BY PaymentID DESC LIMIT 1000', [$Nickname]) : [];
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="codes/jquery-1.5.min.js"></script>
    <style type="text/css">
      @media print {
          body{
            visibility: hidden;
          }
          .hidep{
            visibility: hidden;
          }
          .hide{
            text-align: center;
            display: block;
          }
          .listou{
            visibility: hidden;
          }
          .treport{
            font-size: .7em;
            font-weight: bold;
          }
          .statement{
            color:#000;
            font-size: 2em;
            font-weight: bold;
            visibility: visible;
            margin: 10px auto;
            width:1300px;
            height: auto;
            position: fixed;
            top:0;
            left:0;
            overflow: visible;
            display: block;
            border: none;
          }
          .report h2 {
              visibility: hidden;
          }
        }
    </style>
<div id="main">
    <?php include("menu.php"); ?>
<div style="margin:20px 0;">
<div class="report listou">
  <h2><strong>Search Customer &nbsp;</strong><input onKeyPress="return dg(event);" name="MemberName" type="text" id="MemberName"></h2>


  <div  id="userGrid" class="scrollbar">
  <form id="grid_form_id">

  <table width="100%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
          <tr bgcolor="#f1f1f1">
          <th width="70%"><b>Name of Customer</b></th>
          <th width="20%"><strong>Address</strong></th>
              <th width="5%"><b>Due</b></th>
              <th width="5%"><b>Show</b></th>
          </tr>
  <?php
  $tAmount = 0;
  foreach ($customers as $row) {
      $amount = (float)($row->dueTotal ?? 0) - (float)($row->paidTotal ?? 0);
      $tAmount += $amount;
      print '<tr><td id="MemberName">' . e($row->MemberName) . "</td>\n";
      print '<td>' . e($row->address) . "</td>\n";
      print '<td>' . e($amount) . "</td>\n";
      $url = 'Payment.php?' . http_build_query(['ID' => $row->MemberID, 'Name' => $row->Nickname]);
      print '<td><a href="' . e($url) . '">Show</a></td>' . "\n";
  }
  ?>
        <tr bgcolor="#f1f1f1">
                  <td colspan="2"><b>Total Receivable Amount</b></td>
                  <td colspan="2"><b><?= e($tAmount) ?></b></td>
      </tr>
  </table>

  </form>
    </div>
</div>

<div style="" class="report statement">
    <h2 class="hidep">Customer Payment <a href="#" style="float: right;" onclick="javascript:window.print()" >Print</a></h2>

      <table class="treport" width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td><b><?php if ($member !== null) {
      echo '<strong>Name  &nbsp;&nbsp;&nbsp;:</strong> ' . e($member->MemberName) . ' <br />';
      echo '<strong>Phone &nbsp;&nbsp;&nbsp;:</strong> ' . e($member->mobile) . ' <br />';
      echo '<strong>Address  :</strong> ' . e($member->address) . ' <br />';
      } ?>
          </b></td>
        </tr>
      </table>
<br>
<?php if ($Nickname !== '') { ?>
    <table class="treport" width="98%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
    <h3 class="hide">Sales History</h3>
        <tr>
          <td width="10%">SalesID</td>
          <td width="30%">Date</td>
          <td width="20%">Selesman</td>
          <td width="15%">Amount</td>
          <td width="10%">Discount</td>
          <td width="15%">Total</td>
        </tr>
 <?php
 foreach ($sales as $BillInfo) {
      $Date = date_format(date_create($BillInfo->Date), 'd-M-y');
      echo '<tr><td>' . e($BillInfo->SalesID) . '</td>';
      echo '<td>' . e($Date) . '</td><td>' . e($BillInfo->UserName) . '</td>';
      echo '<td>' . e(fmt_numeric($BillInfo->Amount)) . '</td><td>' . e(fmt_numeric($BillInfo->Discount)) . '</td><td>' . e(fmt_numeric($BillInfo->Total)) . '</td></tr>';
 }
 ?>
    </table><br>
<?php
if ($paymentMessage !== '') echo e($paymentMessage);
$amount = (float)($due->dueTotal ?? 0) - (float)($due->paidTotal ?? 0);
echo ' Total Due ' . e($amount) . ' Taka';
?>

  <br><br>
<form name="form1" method="post" action="" class="hidep"><?php csrf_field(); ?>
    <table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1">
      <tr>
        <td width="10%">Amount</td>
        <td width="20%"><input name="amount" type="text" id="amount" size="12"></td>
        <td width="10%">Remarks</td>
        <td width="30%"><input name="Remarks" type="text" id="Remarks" size="20"></td>
        <td width="15%"><input type="submit" name="Submit" value="Paid"></td>
        <td width="15%"><a href="Payment.php">Refrsh</a></td>
      </tr>
    </table>
</form>


<table class="treport" width="98%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
<h3 class="hide">Payment History</h3>
              <tr bgcolor="#f1f1f1">
              <td width="5%"><b>ID</b></td>
              <td width="15%"><b>Date</b></td>
              <td width="10%"><b>Collect by</b></td>
              <td class="hide" width="30%"><b>Name</b></td>
              <td width="10%"><b>Amount</b></td>
              <td width="20%"><b>Remarks</b></td>
              <td width="10%" class="hidep"><b>Action</b></td>
            </tr>
<?php

foreach ($payments as $Detail) {
    $Date = date_format(date_create($Detail->Date), 'd-M-y');
    print '<tr><td>' . e($Detail->PaymentID) . "</td>\n";
    print '<td>' . e($Date) . "</td>\n";
    print '<td>' . e($Detail->UserName) . "</td>\n";
    print '<td class="hide">' . e($Detail->Description) . "</td>\n";
    print '<td>' . e(fmt_numeric($Detail->amount)) . "</td>\n";
    print '<td>' . e($Detail->Remarks) . '</td>';
    print '<td class="hidep">' . action_form('remove_payment.php', ['d' => $Detail->PaymentID], 'Delete') . '</td>' . "\n</tr>\n";
}
?>
</table>

<?php
}

?>
    </div>
</div>
  <?php include 'footer.php'; ?>
</div>

<!-- For Searching Members -->
<script type="text/javascript">
  $(function() {
    grid = $('#userGrid');

    // handle search fields of members key up event
    $('#MemberName').keyup(function(e) {
      text = $(this).val(); // grab search term

      if(text.length > 1) {
        grid.find('tr:has(td)').hide(); // hide data rows, leave header row showing

        // iterate through all grid rows
        grid.find('tr').each(function(i) {
          // check to see if search term matches Name column
          if($(this).find('#MemberName').text().toUpperCase().match(text.toUpperCase()))
            $(this).show(); // show matching row
        });
      }
      else
        grid.find('tr').show(); // if no matching name is found, show all rows
    });

  });
</script>
    </body>
</html>
