<?php
require __DIR__ . '/lib/bootstrap.php';
csrf_check_post();
$loginError = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = (is_string($_POST['username'] ?? null) ? $_POST['username'] : '');
    $password = (is_string($_POST['password'] ?? null) ? $_POST['password'] : '');
    $user = null;
    try { $user = authenticate_user($username, $password); }
    catch (UnsupportedPasswordHash $error) { $loginError = $error->getMessage(); }
    if ($user !== null) {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        csrf_token();
        $_SESSION['Username'] = $user->UserName;
        $_SESSION['admin'] = $user->userAtho;
        $_SESSION['user_id'] = (int)$user->user_id;
        $_SESSION['restore_generation'] = app_config()['restore_generation'];
        redirect('index.php');
    }
    if ($loginError === '') $loginError = 'Sorry, your account could not be found. Please try again.';
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link href="css/Style.css" rel="stylesheet" type="text/css">
	<title>Login Form</title>
<script type="text/javascript">

     window.onload = function()
        {
			document.loginform.username.focus()
        };

function logsubmit()
{
    document.forms["loginform"].submit();
}

function setfocus1(x)
{
switch (x)
{
case 'username':
  document.loginform.password.focus();
  break;
case 'password':
  document.forms["loginform"].submit();
  break;
}


}
</script>


<div class="loginforms">
<?php if ($loginError !== ''): ?><span class="warning"><?= e($loginError) ?></span><?php endif; ?>

<div align="center">
<img width="200" height="175" src="images/login_icon.gif">
<br><br>

<form method="post" action="login.php" name="loginform" id="loginform"><?php csrf_field(); ?>

		Username:
                <input autocomplete="off" onKeyUp="if(event.keyCode==13) setfocus1(this.name);" type="text" name="username" id="username" /><br />
		Password:
                <input onKeyUp="if(event.keyCode==13) setfocus1(this.name);" type="password" name="password" id="password" /><br />
		<input onClick="logsubmit()" type="button" name="login" id="login" value="Login" />
  </form>
</div>

</div>

</head>
</html>

