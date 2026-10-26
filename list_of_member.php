<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
 <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
 <script type="text/javascript" src="codes/jquery-1.5.min.js"></script>
<div id="main">
	<?php include("menu.php"); ?>

    <h2>Customer Area</h2>

    <form name="form" method="post" action="">
      <table width="100%" border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td width="11%"><strong>Search Customer &nbsp;</strong><input onKeyPress="return dg(event);" name="MemberName" type="text" id="MemberName"></td>
            <td width="23%"><a href="add_member.php">Add Customer</a> </td>
          </tr>
        </table>
    </form>

<div  id="userGrid" class="scrollbar">
<form id="grid_form_id">
<table width="100%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
  <tr bgcolor="#f1f1f1">
    <td width="6%"><b>ID</b></td>
    <td width="6%"><b>Select</b></td>
    <td width="13%"><strong>Nickname</strong></td>
    <td width="24%"><b>Name of Member</b></td>
    <td width="20%"><b>Mobile</b></td>
    <td width="35%"><b>Address</b></td>
  </tr>

	<?php
    include 'connect.php';
    $sql="SELECT * FROM canteen_customar" ;
    $rs=mysql_query($sql, $conn) or die (mysql_error());
      if(mysql_num_rows($rs)>0){
        while ($row=mysql_fetch_object($rs)){

        print '<tr>' ;
        print "<td>$row->MemberID</td>\n";
        print "<td><a href=\"add_member.php?ID=$row->MemberID\">Edit</a></td>";
        print "<td>$row->Nickname</td>\n";
        print "<td id='MemberName'>$row->MemberName</td>\n";
        print "<td>$row->mobile, $row->mobile2</td>\n";
        print "<td>$row->address</td>\n";
        }
      }
        ?>
</table>
</form>
  </div>
  <?php include 'footer.php'; ?>
  </div>


  <!-- For Searching Members -->
  <script type="text/javascript">
    $(function() {
      grid = $('#userGrid');

      // handle search fields of members key up event
      $('#MemberName').keyup(function(e) { 
        text = $(this).val(); // grab search term

        if(text.length > 1) {
          grid.find('tr:has(td)').hide(); // hide data rows, leave header row showing

          // iterate through all grid rows
          grid.find('tr').each(function(i) {
            // check to see if search term matches Name column
            if($(this).find('#MemberName').text().toUpperCase().match(text.toUpperCase()))
              $(this).show(); // show matching row
          });
        }
        else 
          grid.find('tr').show(); // if no matching name is found, show all rows
      });
      
    }); 
  </script>
    </body>
</html>
