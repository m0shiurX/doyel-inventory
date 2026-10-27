<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
function account_head_text(array $source, string $key, int $length = 0): string
{
    $value = is_string($source[$key] ?? null) ? $source[$key] : '';
    return $length > 0 ? mb_substr($value, 0, $length, 'UTF-8') : $value;
}
$headID = filter_var($_GET['headID'] ?? null, FILTER_VALIDATE_INT);
$headID = $headID !== false && $headID > 0 ? $headID : 0;
$selected = $headID > 0 ? db_one('SELECT HeadOfAccount, conCode FROM canteen_ac_head WHERE headID = ?', [$headID]) : null;
$message = '';
$name = account_head_text($_POST, 'HeadOfAccount', 50);
$conCode = account_head_text($_POST, 'conCode', 20);
if ($name !== '' && $conCode !== '' && $conCode !== 'Control Code') {
    try {
        if ($headID > 0) {
            db_exec('UPDATE canteen_ac_head SET HeadOfAccount = ?, conCode = ?, D = ? WHERE headID = ?', [$name, $conCode, account_head_text($_POST, 'checkbox') === '1' ? 1 : 0, $headID]);
            $message = 'Head Edited';
        } else {
            db_exec('INSERT INTO canteen_ac_head (HeadOfAccount, conCode, D) VALUES (?, ?, ?)', [$name, $conCode, 0]);
            $message = 'Head Added';
        }
    } catch (PDOException $error) {
        if (!str_starts_with((string)$error->getCode(), '23')) throw $error;
        $message = 'Error';
    }
}
$rows = db_all('SELECT headID, HeadOfAccount, conCode, D FROM canteen_ac_head ORDER BY HeadOfAccount ASC');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
        <div align="center"><div id="main">

<?php include("menu.php"); ?>

<h2>Head of Account</h2>

<div style=" height:450px;display:block; margin-bottom:30px;">
<?php if ($message !== '') echo e($message); ?>


<form name="form1" method="post" action="">
    Head Of Account
    <input value="<?= e($selected->HeadOfAccount ?? '') ?>" name="HeadOfAccount" type="text" id="HeadOfAccount" size="50">
    Control Code
    <select name="conCode" id="conCode">
    <?php if ($headID > 0) echo '<option>' . e($selected->conCode ?? '') . '</option>';
    else echo '<option>Control Code</option>'; ?>

    <option>Expense</option>
    <option>Income</option>
    </select>
    Drop <input  type="checkbox" name="checkbox" id="checkbox" value="1">
    <input type="submit" name="Submit" value="Submit">
  </form>

  <div style="max-height:400px; overflow:auto; margin-bottom:10px;">
    <table id="tableGd" width="87%" border="0" cellspacing="1" cellpadding="1">
              <tr bgcolor="#f1f1f1">
              <td width="36%"><b>Account Head</b></td>
              <td width="21%"><b>Control Code</b></td>
              <td width="9%"><b>Drop</b></td>
              <td width="9%"><b>Edit</b></td>
            </tr>
 <?php foreach ($rows as $Paid) {
    print '<tr><td>' . e($Paid->HeadOfAccount) . "</td>\n";
    print '<td>' . e($Paid->conCode) . "</td>\n";
    print '<td>' . e($Paid->D) . "</td>\n";
    print '<td><a href="HeadOfAccount.php?headID=' . e(urlencode($Paid->headID)) . '">Edit</a></td></tr>' . "\n";
} ?>

 </table>
    </div>


</div>
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
