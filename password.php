<?php session_start() ?>

<?php
$UserName=$_SESSION['Username'];

include 'connect.php';

if(!empty($_POST['oPass']) && !empty($_POST['nPass']) && !empty($_POST['cPass']))
{
    $oPass = md5(mysql_real_escape_string($_POST['oPass']));
	$nPass = md5(mysql_real_escape_string($_POST['nPass']));
	$cPass = md5(mysql_real_escape_string($_POST['cPass']));

	 $checklogin = mysql_query("SELECT * FROM l_user WHERE UserName = '".$UserName."' AND password = '".$oPass."'");

    if(mysql_num_rows($checklogin) == 1)
    {

// Update Password

if ($nPass==$cPass)
	{
	
	mysql_query("UPDATE l_user SET password ='$nPass' WHERE UserName='$UserName'");
	echo '<b>Your password is succesfuly change.</b>';
	}
	else { echo '<span class="warning">The psassword confirmation does not match. Please try again.</span>';}

// Update Password Colse
    }
    else
    {
        echo '<span class="warning">Sorry, your current password is not Correct. Please try again.</span>';
    }
}



?>


<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Change Password</title>
		<link href="css/Style.css" rel="stylesheet" type="text/css">
</head>

<body>
  <div align="left"><br />
  </div>
<form id="form1" name="form1" method="post" action="">
  <div align="left">
    <table width="433" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td width="64">&nbsp;</td>
        <td width="153">User Name </td>
        <td width="216"><input name="Pass" type="text" id="Pass" value="<?php echo $UserName ; ?>" readonly="Yes" /></td>
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
