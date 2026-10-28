<?php
require __DIR__ . '/lib/bootstrap.php';
require_admin();
csrf_check_post();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">

<div id="main">
	<?php include("menu.php"); ?>
<h2>User Area</h2>
	<?php
$added = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = (is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = (is_string($_POST['password'] ?? null) ? $_POST['password'] : '');
    $name = (is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
    if ($username === '' || $name === '' || $password === '') echo '<span class="warning">Name, username and a nonempty password are required.</span>';
    elseif (preg_match('/\A.{1,30}\z/us', $username) !== 1) echo '<span class="warning">Username must be 30 characters or fewer.</span>';
    elseif (db_value('SELECT user_id FROM l_user WHERE UserName = ? LIMIT 1', [$username]) !== null) echo '<span class="warning">That username already exists. Choose another username.</span>';
    else {
        try {
        db_exec('INSERT INTO l_user (UserName, password, FullName, email, address, mobile, m_date, userAtho) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$username, auth_hash_password($password), $name, (is_string($_POST['email'] ?? null) ? $_POST['email'] : ''), (is_string($_POST['addess'] ?? null) ? $_POST['addess'] : ''), (is_string($_POST['mobile'] ?? null) ? $_POST['mobile'] : ''), date('Y-m-d'), 0]);
        echo '<h2>User Added.</h2>';
        $added = true;
        } catch (PDOException $error) {
            // The unique key also closes the race between precheck and insert.
            if (($error->errorInfo[1] ?? null) !== 1062) throw $error;
            echo '<span class="warning">That username already exists. Choose another username.</span>';
        } catch (UnsupportedPasswordHash $error) { echo '<span class="warning">' . e($error->getMessage()) . '</span>'; }
    }
}
if (!$added) form();
?>


        <?php
  function form(){
        ?><fieldset><legend>Add User</legend>
        <form action="" method="post" name="add_book" id="add_book"><?php csrf_field(); ?>
          <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td width="27%">&nbsp;</td>
              <td width="73%">&nbsp;</td>
            </tr>
            <tr>
              <td> Name</td>
              <td><label>
                <input name="name" type="text" id="name" size="30">
              </label></td>
            </tr>
            <tr>
              <td>User Name</td>
              <td><label>
                <input name="username" type="text" id="username" size="30" maxlength="30">
              </label></td>
            </tr>
            <tr>
              <td>Password</td>
              <td><label>
                <input name="password" type="password" id="password" size="30">
              </label></td>
            </tr>
            <tr>
              <td>E-Mail</td>
              <td><label>
                <input name="email" type="text" id="email" size="30">
              </label></td>
            </tr>
            <tr>
              <td>Mobile</td>
              <td><label>
                <input name="mobile" type="text" id="mobile">
              </label></td>
            </tr>
            <tr>
              <td>Address</td>
              <td><textarea name="addess" cols="40" id="addess"></textarea></td>
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
        <p>
          <?php
  }
  ?>
        </p>
		<p>&nbsp;</p>
        <table width="500" border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td width="54">Sl No </td>
            <td width="171">Full Name </td>
            <td width="154">User Name </td>
            <td width="121">Mobile No</td>
          </tr>

        <p>
          <?php
        foreach (db_all('SELECT user_id, FullName, UserName, mobile FROM l_user ORDER BY user_id') as $row) {
            echo '<tr><td>' . e($row->user_id) . '</td><td>' . e($row->FullName) . '</td><td>' . e($row->UserName) . '</td><td>' . e($row->mobile) . '</td></tr>';
        }

        ?>
        </table>
        </p>
        <?php include 'footer.php'; ?>
  </div>
    </body>
</html>
