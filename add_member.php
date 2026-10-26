<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
<link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<div align="center"><div id="main">
<?php include("menu.php"); ?>
<h2>Member Area</h2>	
<?php

$Nickname='';

include 'connect.php';

if (!empty($_POST['name']) && !empty($_POST['Nickname']))
  {
    $Nickname=$_POST['Nickname'];
    $name=$_POST['name'];
    $mobile=$_POST['mobile'];
    $mobile2=$_POST['mobile2'];
    $address=$_POST['address'];
    date_default_timezone_set('Asia/Dacca');
    $date= date("Y-m-d");
    if (!empty($_GET['Drop'])){$Drop=$_POST['Drop'];}else{$Drop=0;}
    if (!empty($_GET['ID']))
    {
      $ID=$_GET['ID'];
      mysql_query("UPDATE canteen_customar SET MemberName='$name', mobile='$mobile', mobile2='$mobile2', address='$address', mDrop='$Drop' WHERE MemberID = '$ID'" );
    } else{

      $check = mysql_query("SELECT Nickname FROM canteen_customar WHERE Nickname = '$Nickname'");

      if (mysql_num_rows($check) == 0){
        $query = mysql_query("INSERT INTO canteen_customar (`Nickname` , `MemberName`, `mobile`, `mobile2` , `address`, `m_date`) VALUES ( '$Nickname','$name','$mobile','$mobile2','$address','$date')");
        if ($query){
          echo "<h2>New Customar seved <a href=\"Payment.php\">Go to Customar Area</a></h2>";
        }else{
          echo "<h2>Errors</h2>";
        }
      } else{
        echo "<h2>This Nickname is already registered, Please try anather Nickname</h2>";
      }
    }
  }

if (!empty($_GET['ID']))
{
  $ID=$_GET['ID'];
  $mem=mysql_query("SELECT * FROM canteen_customar WHERE MemberID = '$ID'");
  while($Member=mysql_fetch_object($mem)){
    $Nickname=$Member->Nickname;
    $name=$Member->MemberName;
    $mobile=$Member->mobile;
    $mobile2=$Member->mobile2;
    $address=$Member->address;
    $Drop=$Member->mDrop;
  }
}
  mysql_close($conn);
?>
	

<fieldset><legend>Add New Customar</legend>
    <form action="" method="post" enctype="multipart/form-data" name="add_book" id="add_book">
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td width="35%">&nbsp;</td>
          <td width="65%">&nbsp;</td>
        </tr>
        <tr>
          <td>Nickname</td>
          <td width="65%">
            <?php
              if (!empty($_GET['ID'])){
                echo "<input name=\"Nickname\" type=\"text\" id=\"Nickname\" readonly=\"YES\" value=\"$Nickname\">";
                echo "&nbsp;&nbsp;&nbsp;&nbsp;Drop ";
                if (isset($Drop)) { echo "<input type=\"checkbox\" name=\"Drop\" value=\"$Drop\"/>";}
              }else {
                echo "<input name=\"Nickname\" type=\"text\" id=\"Nickname\" value=\"$Nickname\">";
                }
            ?>
          </td>
        </tr>
        <tr>
          <td>Name (Bangla) </td>
          <td><label>
                  <input onKeyPress="return dg(event);" name="name" type="text" id="name" size="30" value="<?php if (isset($name)) {print $name;} ?>">
          </label></td>
        </tr>
        <tr>
          <td>Mobile</td>
          <td><label>
            <input name="mobile" type="text" id="mobile" value="<?php if (isset($mobile)) { echo $mobile;} ?>">
          </label></td>
        </tr>
        <tr>
          <td>Mobile 2nd</td>
          <td><label>
            <input name="mobile2" type="text" id="mobile2" value="<?php if (isset($mobile2)) { echo $mobile2;} ?>">
          </label></td>
        </tr>
        <tr>
          <td>Present Address</td>
          <td><label>
            <textarea onKeyPress="return dg(event);" name="address" cols="40" id="address"><?php if (isset($address)) { echo $address;} ?></textarea>
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