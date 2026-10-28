<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
function collection_text(array $source, string $key, int $length = 0): string
{
    $value = is_string($source[$key] ?? null) ? $source[$key] : '';
    return $length > 0 ? mb_substr($value, 0, $length, 'UTF-8') : $value;
}
$message = '';
$rawAmount = collection_text($_POST, 'amount');
$description = collection_text($_POST, 'Description', 100);
$head = collection_text($_POST, 'HeadOfAccount', 50);
if (is_numeric($rawAmount) && is_finite((float)$rawAmount) && $rawAmount !== '0' && $description !== '' && $head !== '' && $head !== 'Head Of Accounts') {
    db_exec('INSERT INTO canteen_collection (Date, UserName, Description, HeadOfAccount, amount, Remarks) VALUES (?, ?, ?, ?, ?, ?)', [now_sql(), mb_substr(current_user(), 0, 50, 'UTF-8'), $description, $head, input_decimal($rawAmount), collection_text($_POST, 'Remarks', 100)]);
    $message = 'Payment Sucess';
}
$deleteMessage = '';
$id = filter_var($_GET['d'] ?? null, FILTER_VALIDATE_INT);
if (is_admin() && $id !== false && $id > 0) {
    db_exec('DELETE FROM canteen_collection WHERE PaymentID = ?', [$id]);
    $deleteMessage = 'Collection no ' . $id . ' is Removed.';
}
$heads = db_all('SELECT HeadOfAccount FROM canteen_ac_head WHERE conCode = ? ORDER BY headID', ['Income']);
// Date's secondary index returned tied rows in descending primary-key order.
$rows = is_admin()
    ? db_all('SELECT Date, UserName, Description, amount, Remarks, PaymentID FROM canteen_collection ORDER BY Date DESC, PaymentID DESC LIMIT 30')
    : db_all('SELECT Date, UserName, Description, amount, Remarks, PaymentID FROM canteen_collection WHERE Remarks <> ? ORDER BY Date DESC, PaymentID DESC LIMIT 30', ['Personal']);
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
        <div align="center"><div id="main">

<?php include("menu.php"); ?>

<h2>Cash Collection</h2>

<div style=" min-height:450px;display:block; margin-bottom:30px;">
<?php if ($message !== '') echo e($message); ?>

<form name="form1" method="post" action="CashCollection.php">
    <fieldset>
      <legend>Cash Collection</legend>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td width="10%">Description</td>
        <td width="38%"><input name="Description" type="text" id="Description" size="50"></td>
        <td width="11%">Accounts Head</td>
        <td width="41%"><select name="HeadOfAccount" id="HeadOfAccount">
        <option>Head Of Accounts</option>
        <?php foreach ($heads as $headRow) echo '<option>' . e($headRow->HeadOfAccount) . '</option>'; ?>
        </select></td>
      </tr>
      <tr>
        <td>Amount</td>
        <td><input name="amount" type="text" id="amount" size="10"></td>
        <td>Remarks</td>
        <td><input name="Remarks" type="text" id="Remarks">
          <input type="submit" name="Submit" value="Collection"></td>
      </tr>
  </table>
      </fieldset>
</form>

  <div style="max-height:400px; overflow:auto; margin-bottom:10px;">
    <table id="tableGd" width="98%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="17%"><b>Date</b></td>
              <td width="11%"><b>Receved By</b></td>
              <td width="34%"><b>Description</b></td>
              <td width="8%"><b>Amount</b></td>
              <td width="20%"><b>Remarks</b></td>
              <td width="10%"><b>Action</b></td>
            </tr>
<?php
if ($deleteMessage !== '') echo '<span class="warning">' . e($deleteMessage) . '</span>';
foreach ($rows as $Paid) {
    $Date = date_format(date_create($Paid->Date), 'd-M-y h:i A');
    print '<tr><td>' . e($Date) . "</td>\n";
    print '<td>' . e($Paid->UserName) . "</td>\n";
    print '<td>' . e($Paid->Description) . "</td>\n";
    print '<td>' . e(fmt_numeric($Paid->amount)) . "</td>\n";
    print '<td>' . e($Paid->Remarks) . "</td>\n";
    print '<td><a href="CashCollection.php?d=' . e(urlencode($Paid->PaymentID)) . '">Delete</a></td></tr>' . "\n";
}
?>

 </table>
</div>


</div>
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
