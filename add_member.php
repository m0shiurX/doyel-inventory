<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
function member_text(string $key, ?int $length = null): string
{
    $value = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    return $length === null ? mb_strcut($value, 0, 65535, 'UTF-8') : mb_substr($value, 0, $length, 'UTF-8');
}
$ID = filter_var($_GET['ID'] ?? null, FILTER_VALIDATE_INT);
$ID = $ID !== false && $ID > 0 ? $ID : 0;
$Nickname = $name = $mobile = $mobile2 = $address = '';
$Drop = null;
$message = '';
if (member_text('name') !== '' && member_text('Nickname', 30) !== '') {
    $Nickname=member_text('Nickname',30); $name=member_text('name'); $mobile=member_text('mobile',15);
    $mobile2=member_text('mobile2',15); $address=member_text('address');
    $dropInput = is_string($_POST['Drop'] ?? null) ? $_POST['Drop'] : '';
    $Drop = !empty($_GET['Drop']) && is_numeric($dropInput) ? (int)$dropInput : 0;
    if ($ID > 0) {
        // Nickname remains readonly and unchanged, matching the original edit handler.
        db_exec('UPDATE canteen_customar SET MemberName=?, mobile=?, mobile2=?, address=?, mDrop=? WHERE MemberID=?', [$name,$mobile,$mobile2,$address,$Drop,$ID]);
    } elseif (db_value('SELECT MemberID FROM canteen_customar WHERE Nickname=?', [$Nickname]) === null) {
        db_exec('INSERT INTO canteen_customar (Nickname,MemberName,mobile,mobile2,address,m_date,mDrop) VALUES (?,?,?,?,?,?,?)', [$Nickname,$name,$mobile,$mobile2,$address,date('Y-m-d'),0]);
        $message = 'saved';
    } else $message = 'This Nickname is already registered, Please try anather Nickname';
}
if ($ID > 0) {
    $member=db_one('SELECT Nickname,MemberName,mobile,mobile2,address,mDrop FROM canteen_customar WHERE MemberID=?',[$ID]);
    if ($member !== null) {
        $Nickname=$member->Nickname; $name=$member->MemberName; $mobile=$member->mobile;
        $mobile2=$member->mobile2; $address=$member->address; $Drop=$member->mDrop;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<div align="center"><div id="main">
<?php include("menu.php"); ?>
<h2>Member Area</h2>
<?php if ($message === 'saved'): ?><h2>New Customar seved <a href="Payment.php">Go to Customar Area</a></h2>
<?php elseif ($message !== ''): ?><h2><?= e($message) ?></h2><?php endif; ?>
<?php if ($ID > 0): ?><p class="warning">Past sales and collections are linked by nickname. Changing a nickname does not update those records.</p><?php endif; ?>



<fieldset><legend>Add New Customar</legend>
    <form action="" method="post" enctype="multipart/form-data" name="add_book" id="add_book"><?php csrf_field(); ?>
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td width="35%">&nbsp;</td>
          <td width="65%">&nbsp;</td>
        </tr>
        <tr>
          <td>Nickname</td>
          <td width="65%">
            <input name="Nickname" type="text" id="Nickname"<?= $ID > 0 ? ' readonly="YES"' : '' ?> value="<?= e($Nickname) ?>">
            <?php if ($ID > 0): ?>&nbsp;&nbsp;&nbsp;&nbsp;Drop
                <?php if ($Drop !== null): ?><input type="checkbox" name="Drop" value="<?= e($Drop) ?>"/><?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td>Name (Bangla) </td>
          <td><label>
                  <input onKeyPress="return dg(event);" name="name" type="text" id="name" size="30" value="<?= e($name) ?>">
          </label></td>
        </tr>
        <tr>
          <td>Mobile</td>
          <td><label>
            <input name="mobile" type="text" id="mobile" value="<?= e($mobile) ?>">
          </label></td>
        </tr>
        <tr>
          <td>Mobile 2nd</td>
          <td><label>
            <input name="mobile2" type="text" id="mobile2" value="<?= e($mobile2) ?>">
          </label></td>
        </tr>
        <tr>
          <td>Present Address</td>
          <td><label>
            <textarea onKeyPress="return dg(event);" name="address" cols="40" id="address"><?= e($address) ?></textarea>
          </label></td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td><input type="submit" name="Submit" value="Submit"></td>
        </tr>
      </table>
    </form>
</fieldset>
<?php include 'footer.php'; ?>
</div>
</body>
</html>
