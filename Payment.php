<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
    <script type="text/javascript" src="codes/jquery-1.5.min.js"></script>
    <style type="text/css">
      @media print {
          body{
            visibility: hidden;
          }
          .hidep{
            visibility: hidden;
          }
          .hide{
            text-align: center;
            display: block;
          }
          .listou{
            visibility: hidden;
          }
          .treport{
            font-size: .7em;
            font-weight: bold;
          }
          .statement{
            color:#000;
            font-size: 2em;
            font-weight: bold;
            visibility: visible;
            margin: 10px auto;
            width:1300px;
            height: auto; 
            position: fixed;
            top:0;
            left:0;
            overflow: visible;
            display: block;
            border: none;
          }
          .report h2 {
              visibility: hidden;
          }
        }
    </style>
<div id="main">
	<?php include("menu.php"); include 'connect.php'; ?>
<div style="margin:20px 0;">
<div class="report listou">
  <h2><strong>Search Customer &nbsp;</strong><input onKeyPress="return dg(event);" name="MemberName" type="text" id="MemberName"></h2>


  <div  id="userGrid" class="scrollbar">
  <form id="grid_form_id">

  <table width="100%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
          <tr bgcolor="#f1f1f1">
          <th width="70%"><b>Name of Customer</b></th>
          <th width="20%"><strong>Address</strong></th>
  			  <th width="5%"><b>Due</b></th>
  			  <th width="5%"><b>Show</b></th>
          </tr>         
  <?php
  $amount="";
  $tAmount="";
          $sql="SELECT * FROM canteen_customar ORDER BY Nickname" ;

         $rs=mysql_query($sql) or die (mysql_error());
          if(mysql_num_rows($rs)>0){
              while ($row=mysql_fetch_object($rs)){

  		print "<tr>" ;
  		print "<td id='MemberName'>$row->MemberName</td>\n";
      print "<td>$row->address</td>\n";
  		$Description=$row->Nickname;
  		$amount=netTotal($Description);
  		$tAmount=$tAmount+$amount;
  		print "<td>$amount</td>\n";
  		print "<td><a href=\"Payment.php?ID=$row->MemberID&Name=$row->Nickname\">Show</a></td>\n";
              }
          }

  function netTotal($Description){
    $Total = mysql_query("SELECT SUM(Total) as 'dTotal' FROM `canteen_sales` WHERE CustomerName='$Description'");
    $S=mysql_fetch_object($Total);
    $dueTotal=$S->dTotal;

    $Total = mysql_query("SELECT SUM(amount) as 'pTotal' FROM `canteen_collection` WHERE Description='$Description'");
    $S2=mysql_fetch_object($Total);
    $paidTotal=$S2->pTotal;
    return $netTotal = $dueTotal-$paidTotal;
  }


  ?>
  		<tr bgcolor="#f1f1f1">
                  <td colspan="2"><b>Total Receivable Amount</b></td>
                  <td colspan="2"><b><?php echo $tAmount ?></b></td>
      </tr>
  </table>

  </form>
    </div>
</div>

<div style="" class="report statement">
    <h2 class="hidep">Customer Payment <a href="#" style="float: right;" onclick="javascript:window.print()" >Print</a></h2>

	  <table class="treport" width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td><b><?php if(!empty($_GET['Name'])){ 
      $Nickname=$_GET['Name'];
      $cName = mysql_query("SELECT * FROM `canteen_customar` WHERE Nickname='$Nickname'");
      $cName=mysql_fetch_object($cName);
      echo "<strong>Name  &nbsp;&nbsp;&nbsp;:</strong> $cName->MemberName <br />";
      echo "<strong>Phone &nbsp;&nbsp;&nbsp;:</strong> $cName->mobile <br />";
      echo "<strong>Address  :</strong> $cName->address <br />";
      } ?>
          </b></td>
        </tr>
      </table>
<br>
<?php 
if(!empty($_GET['Name'])){
$Nickname=$_GET['Name'];  

$sql=mysql_query("SELECT * FROM canteen_sales WHERE CustomerName='$Nickname' ORDER BY SalesID desc LIMIT 100") ;
?>
    <table class="treport" width="98%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
    <h3 class="hide">Sales History</h3>
        <tr>
          <td width="10%">SalesID</td>
          <td width="30%">Date</td>
          <td width="20%">Selesman</td>
          <td width="15%">Amount</td>
          <td width="10%">Discount</td>
          <td width="15%">Total</td>
        </tr>
 <?php
 while ($BillInfo = mysql_fetch_object($sql)){
		print "<tr>" ;
                $Date=date_create($BillInfo->Date);
                $Date=date_format($Date, 'd-M-y');
				
      echo "<td>$BillInfo->SalesID</td>";
      echo "<td>$Date</td>";
      echo "<td>$BillInfo->UserName</td>";
      echo "<td>$BillInfo->Amount</td>";
      echo "<td>$BillInfo->Discount</td>";
		  echo "<td>$BillInfo->Total</td>";
      echo "</tr>";
		}
	?>	
    </table><br>
<?php	

if(!empty($_POST['amount'])){
date_default_timezone_set('Asia/Dacca');
$Remarks=$_POST['Remarks'];
$UserName=$_SESSION['Username'];
$date = date("Y-m-d, H-i-s");

//Admin can store negetive value
if ($_SESSION['admin']==1) {
  $Amount=$_POST['amount'];
} else {
  $Amount=abs($_POST['amount']);
}

$query = mysql_query("INSERT INTO canteen_collection (UserName, Date, Description, HeadOfAccount, amount, Remarks)
            VALUES ('$UserName','$date','$Nickname','Collection from Customer','$Amount','$Remarks')");
if($query){
echo 'Sucsess';
}else{echo "Error";}
}
?>

<?php
$Description=$_GET['Name'];
$amount=netTotal($Description);
echo ' Total Due '.$amount.' Taka';
?>

	  <br><br>
<form name="form1" method="post" action="" class="hidep">
    <table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1">
      <tr>
        <td width="10%">Amount</td>
        <td width="20%"><input name="amount" type="text" id="amount" size="12"></td>
        <td width="10%">Remarks</td>
        <td width="30%"><input name="Remarks" type="text" id="Remarks" size="20"></td>
        <td width="15%"><input type="submit" name="Submit" value="Paid"></td>
        <td width="15%"><a href="payment.php">Refrsh</a></td>
      </tr>
    </table>
</form>


<table class="treport" width="98%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">
<h3 class="hide">Payment History</h3>
              <tr bgcolor="#f1f1f1">
              <td width="5%"><b>ID</b></td>
              <td width="15%"><b>Date</b></td>
              <td width="10%"><b>Collect by</b></td>
              <td class="hide" width="30%"><b>Name</b></td>
              <td width="10%"><b>Amount</b></td>
              <td width="20%"><b>Remarks</b></td>
              <td width="10%" class="hidep"><b>Action</b></td>
            </tr>
<?php

$qry=mysql_query("SELECT * FROM canteen_collection WHERE Description = '$Nickname'  ORDER BY PaymentID desc LIMIT 1000") ;
while ($Detail = mysql_fetch_object($qry)){

		print "<tr>" ;
    $Date=date_create($Detail->Date);
    $Date=date_format($Date, 'd-M-y');
    print "<td>$Detail->PaymentID</td>\n";
    print "<td>$Date</td>\n";
	  print "<td>$Detail->UserName</td>\n";
		print "<td class=\"hide\">$Detail->Description</td>\n";
		print "<td>$Detail->amount</td>\n";
    print "<td>$Detail->Remarks</td>";
		print "<td class=\"hidep\"><a href=\"remove_payment.php?d=$Detail->PaymentID\">Delete</a></td>\n</tr>\n";

}
?>
</table>

<?php
}

?>
	</div>	
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