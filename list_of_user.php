<?php session_start() ?>
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
include 'connect.php';

if (!empty($_POST['username']) && !empty($_POST['name']))
    {
    $username=$_POST['username'];
    $password=md5($_POST['password']);
    $name=$_POST['name'];
    $email=$_POST['email'];
    $mobile=$_POST['mobile'];
    $addess=$_POST['addess'];
    date_default_timezone_set('Asia/Dacca');
    $date= date("Y-m-d");

    $query = mysql_query("INSERT INTO l_user (UserName, password, FullName, email, address, mobile, m_date)
            VALUES ('$username','$password','$name','$email','$addess','$mobile','$date')");
    
if ($query)
    {
 echo   "<h2>User Added.</h2>";
    }
    else{
      echo   "<h2>Errors</h2>";
    }
    }
    else
        {
form();
    }
?>
	
	
        <?php
  function form(){
        ?><fieldset><legend>Add User</legend>
        <form action="" method="post" name="add_book" id="add_book">
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
                <input name="username" type="text" id="username" size="30">
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
        $sql="SELECT * FROM l_user" ;

       $rs=mysql_query($sql, $conn) or die (mysql_error());
        if(mysql_num_rows($rs)>0){
            while ($row=mysql_fetch_object($rs)){
			
			echo '<tr>';
                print "<td>$row->user_id</td><td>$row->FullName</td><td>$row->UserName</td><td>$row->mobile</td>" ;
             echo '</tr>';
            }
        }

        ?>
        </table>          
        </p>
        <?php include 'footer.php'; ?>
  </div>
    </body>
</html>
