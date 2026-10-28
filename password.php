<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
$UserName = current_user();
$message = '';
$success = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $oldPassword = (is_string($_POST['oPass'] ?? null) ? $_POST['oPass'] : '');
    $newPassword = (is_string($_POST['nPass'] ?? null) ? $_POST['nPass'] : '');
    $confirmation = (is_string($_POST['cPass'] ?? null) ? $_POST['cPass'] : '');
    $id = current_user_id();
    $users = $id !== null
        ? db_all('SELECT user_id, password FROM l_user WHERE user_id = ? AND UserName = ?', [$id, $UserName])
        : db_all('SELECT user_id, password FROM l_user WHERE UserName = ? ORDER BY user_id', [$UserName]);
    $matched = null;
    foreach ($users as $user) {
        if (auth_password_matches($oldPassword, $user->password)) { $matched = $user; break; }
    }
    if ($matched === null) $message = 'Sorry, your current password is not Correct. Please try again.';
    elseif ($newPassword === '') $message = 'Please enter a new password.';
    elseif ($newPassword !== $confirmation) $message = 'The psassword confirmation does not match. Please try again.';
    else {
        try {
            $changed = db_exec('UPDATE l_user SET password = ? WHERE user_id = ? AND password = ?', [auth_hash_password($newPassword), $matched->user_id, $matched->password]);
            if ($changed === 1) {
                $_SESSION['user_id'] = (int)$matched->user_id;
                session_regenerate_id(true);
                $success = true;
                $message = 'Your password is succesfuly change.';
            } else $message = 'Your password changed in another session. Please log in again.';
        } catch (UnsupportedPasswordHash $error) { $message = $error->getMessage(); }
    }
}
if ($message !== '') echo $success ? '<b>' . e($message) . '</b>' : '<span class="warning">' . e($message) . '</span>';
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Change Password</title>
		<link href="css/Style.css" rel="stylesheet" type="text/css">
</head>

<body>
  <div align="left"><br />
  </div>
<form id="form1" name="form1" method="post" action=""><?php csrf_field(); ?>
  <div align="left">
    <table width="433" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td width="64">&nbsp;</td>
        <td width="153">User Name </td>
        <td width="216"><input name="Pass" type="text" id="Pass" value="<?= e($UserName) ?>" readonly="Yes" /></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>Current Password </td>
        <td><input name="oPass" type="password" id="oPass" /></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>New Password </td>
        <td><input name="nPass" type="password" id="nPass" /></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>Confirm Password</td>
        <td><input name="cPass" type="password" id="cPass" /></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td><input type="submit" name="Submit" value="Change Now" /></td>
      </tr>
            </table>
  </div>
</form>
</body>
</html>
