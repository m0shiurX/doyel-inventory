<?php session_start() ?>
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
<?php include 'connect.php';

function mres($input){
	if(get_magic_quotes_gpc()){
		$input= stripslashes($input);
	}
	return mysql_real_escape_string($input);
}

if(!empty($_POST['username']) && !empty($_POST['password']))
{
	$username = mres($_POST['username']);
    $password = md5(mysql_real_escape_string($_POST['password']));
	//echo "SELECT * FROM l_user WHERE UserName = '".$username."' AND password = '".$password."'";
	$checklogin = mysql_query("SELECT * FROM l_user WHERE UserName = '".$username."' AND password = '".$password."'");

    if(mysql_num_rows($checklogin) == 1)
    {
    	 $row = mysql_fetch_array($checklogin);

    $_SESSION['Username'] = $row['UserName'];
		$_SESSION['admin'] = $row['userAtho'];
    require_once 'menu.php';
    redirectTo('index.php');
    }
    else
    {
        echo '<span class="warning">Sorry, your account could not be found. Please try again.</span>';
    }
}
?>

<div align="center">
<img width="200" height="175" src="images/login_icon.gif">
<br><br>

<form method="post" action="login.php" name="loginform" id="loginform">

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

