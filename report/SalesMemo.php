<?php session_start() ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link rel="stylesheet" type="text/css" href="Report.css" />
    <title>Sales Memo</title>
<div id="main" class='memo'>

<?php include("menu.php");
include '../connect.php';
  $Nickname='';

if (!empty($_GET['id']))
{
  $SalesID = $_GET['id'];
  $sql=mysql_query("SELECT * FROM canteen_sales WHERE SalesID='$SalesID'") ;
  while ($BillInfo = mysql_fetch_object($sql)){
  $User=$BillInfo->UserName;
  $BDate= date_create($BillInfo->Date);
  $BillDate= date_format($BDate, 'd-M-Y');
  $BillTime = date_format($BDate, 'h:i:s A');
  $Disc=$BillInfo->Discount;
  $NetTotal=$BillInfo->Total;
  $Total=$BillInfo->Amount;
  $CustomerName=$BillInfo->CustomerName;
}


?>

<div style=""><h1>SALES MEMO</h1></div>
<div style="float:left;">Invoice No: <?php echo $SalesID; ?></div>
<div style="float:right;text-align:left;">Date: <?php print $BillDate; echo '<br />Time: '.$BillTime ?></div>
<div class="clear customer">
<div class="bg"><img src="../images/logo.png"/></div>
<?php
$mem=mysql_query("SELECT * FROM canteen_customar WHERE Nickname = '$CustomerName'");

while($Member=mysql_fetch_object($mem)){
    $Nickname=$Member->Nickname;
?>
<h4><span>CustomerName: </span><?php echo $Member->MemberName ?> </h4>
<h4><span>Address: </span><?php echo $Member->address ?> </h4>
<h4><span>Mobile: </span><?php echo $Member->mobile.', '.$Member->mobile2 ?> </h4>

<?php
}
?>
</div>
<table width="100%" border="0" cellspacing="0" cellpadding="1" class="table1" id="tableGd">
            <tr>
              <th width="5%">SL</th>
<!--               <th width="5%">ID</th> -->
              <th width="40%">Item Name</th>
              <th width="10%">Rate</th>
              <th width="10%">Quantity</th>
              <th width="10%" style="padding: 0 30px">Amount</th>
            </tr>
<?php
$Qnty="";
$sl=1;
$SalesID=$_GET['id'];
        $qry=mysql_query("SELECT canteen_item.ItemID, canteen_item.ItemName, canteen_sales_detail.*
        FROM canteen_sales_detail INNER JOIN canteen_item
        ON canteen_item.ItemID = canteen_sales_detail.ItemID
        WHERE canteen_sales_detail.SalesID='$SalesID'") ;
while ($Detail = mysql_fetch_object($qry))
  {
		print "<tr>" ;
    print "<td>$sl</td>\n";
    // print "<td>$Detail->ItemID</td>\n";
    print "<td>$Detail->ItemName</td>\n";
		print "<td>$Detail->Rate</td>\n";
		print "<td style=\"text-align:right; padding-right: 40px;\">$Detail->Qnty</td>\n";
		print "<td style=\"text-align:right; padding-right: 40px;\">$Detail->Amt</td>\n";
		print "</tr>\n";
		$Qnty=$Qnty+$Detail->Qnty;
		$sl++;
  }
?>
            <tr>
              <th colspan="3">Total :</th>
<!--               <th>&nbsp;</th> -->
              <th style="text-align:right; padding-right: 40px;"><?php echo $Qnty ?></th>
              <th style="text-align:right; padding-right: 15px;"><?php echo $Total; ?> Tk</th>
            </tr>
            <?php
            if ($Disc==0) {
                print "<tr style='display:none;'>";
                }
            ?>
              <td colspan='3'>Discount :</td>
<!--               <td>&nbsp;</td> -->
              <td>&nbsp;</td>
              <td  style="text-align:right; padding-right: 15px;"><?php echo $Disc; ?> Tk</td>
            </tr>
            
            <tr>
              <th colspan="3">Net Amount :</th>
<!--               <td>&nbsp;</td> -->
              <td>&nbsp;</td>
              <th style="text-align:right; padding-right: 15px;"><?php echo $NetTotal; ?> Tk</th>
            </tr>
</table>

<div class="footer">
  <br><strong>In Word: </strong>  <?php echo getStringOfAmount($NetTotal); ?> Taka Only
  <br>
  <br>

  <?php
  function statTotal($Nickname){
    $Total = mysql_query("SELECT SUM(Total) as 'dTotal' FROM `canteen_sales` WHERE CustomerName='$Nickname'");
    $S=mysql_fetch_object($Total);
    $dueTotal=$S->dTotal;

    $Total = mysql_query("SELECT SUM(amount) as 'pTotal' FROM `canteen_collection` WHERE Description='$Nickname'");
    $S2=mysql_fetch_object($Total);
    $paidTotal=$S2->pTotal;
    return $statTotal = $dueTotal-$paidTotal;
  }
          $amount=statTotal($Nickname);

          if ($amount>0) {
              print "<b><h1>TOTAL DUE TAKA &nbsp; $amount /=</h1> </b>";
          }else{
              $amount=abs($amount);
              print "<b><h1>ADVANCE TAKA &nbsp; $amount /=</h1></b>";
          }
          


  ?>

  <br />
  <br />
  <?php
      $notez=mysql_query("SELECT Note FROM `canteen_sales` WHERE SalesID=$SalesID");
      while ($Detail = mysql_fetch_object($notez))
      {
        print "<b> Note: </b> $Detail->Note\n";
      }
  ?>
</div>
<br />
<div align="center" id="print" style="width:200px; float:left;">
<a href="#" onClick="javascript:window.print()">Print</a></div>
<div align="center" style="float:right; width:100px; margin-right:80px;">
<hr><?php echo $User; ?>
</div>
<?php } ?>

</div>
    </body>
</html>
