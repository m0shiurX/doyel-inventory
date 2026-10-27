<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
function sales_text(array $source, string $key): string
{
    return is_string($source[$key] ?? null) ? $source[$key] : '';
}
function sales_number(array $source, string $key): float
{
    $value = sales_text($source, $key);
    return is_numeric($value) && is_finite((float)$value) ? (float)$value : 0.0;
}
function sales_id(array $source, string $key): int
{
    $value = sales_text($source, $key);
    return ctype_digit($value) && strlen($value) <= 10 && (float)$value <= 2147483647 ? (int)$value : 0;
}
$SalesID = 0;
if (sales_text($_GET, 'NBill') === 'add_new') {
    $SalesID = db_tx(function (): int {
        // The user row serializes simultaneous Add New requests for one user.
        db_one('SELECT user_id FROM l_user WHERE UserName = ? ORDER BY user_id LIMIT 1 FOR UPDATE', [current_user()]);
        $day = date('Y-m-d');
        $open = db_one('SELECT SalesID FROM canteen_sales WHERE UserName = ? AND Post = ? AND Date >= ? AND Date < ? + INTERVAL 1 DAY ORDER BY SalesID LIMIT 1 FOR UPDATE', [current_user(), 0, $day, $day]);
        if ($open !== null) return (int)$open->SalesID;
        db_exec('INSERT INTO canteen_sales (UserName, Amount, Discount, Total, SR, Date, Credit) VALUES (?, ?, ?, ?, ?, ?, ?)', [current_user(), 0, 0, 0, '', now_sql(), 0]);
        return (int)db_insert_id();
    });
}
if (sales_id($_GET, 'Bill') > 0) $SalesID = sales_id($_GET, 'Bill');
if (sales_id($_POST, 'bill_no') > 0) $SalesID = sales_id($_POST, 'bill_no');
if ($SalesID > 0 && sales_text($_GET, 'hPost') !== '') {
    db_tx(function () use ($SalesID): void {
        $parent = db_one('SELECT Post, Credit FROM canteen_sales WHERE SalesID = ? FOR UPDATE', [$SalesID]);
        if ($parent === null || (int)$parent->Post !== 0 || (int)$parent->Credit === 2) return;
        // Header totals are derived from stored lines, never the readonly form fields.
        $amount = (float)(db_value('SELECT SUM(Amt) FROM canteen_sales_detail WHERE SalesID = ?', [$SalesID]) ?? 0);
        $discount = sales_number($_GET, 'discount');
        $nickname = sales_text($_GET, 'Nickname');
        db_exec('UPDATE canteen_sales SET CustomerName = ?, Amount = ?, Discount = ?, Total = ?, Post = ?, Note = ?, Credit = ? WHERE SalesID = ?', [$nickname, $amount, $discount, $amount - $discount, 1, sales_text($_GET, 'Note'), $nickname === 'Select Member' ? 0 : 1, $SalesID]);
    });
}
$quantity = sales_number($_POST, 'qnty');
if ($SalesID > 0 && sales_text($_POST, 'Item') !== '' && $quantity != 0) {
    $itemID = sales_id($_POST, 'ItemID');
    db_tx(function () use ($SalesID, $itemID, $quantity): void {
        $parent = db_one('SELECT Post FROM canteen_sales WHERE SalesID = ? FOR UPDATE', [$SalesID]);
        if ($parent === null || (int)$parent->Post !== 0) return;
        $item = db_one('SELECT ItemID, PurPrice, SalesPrice FROM canteen_item WHERE ItemID = ? FOR UPDATE', [$itemID]);
        if ($item === null) return;
        if (db_exec('UPDATE canteen_item SET Qnty = Qnty - ? WHERE ItemID = ? AND Qnty >= ?', [$quantity, $itemID, $quantity]) !== 1) return;
        db_exec('INSERT INTO canteen_sales_detail (SalesID, Date, ItemID, Qnty, PurAmt, Rate, Amt) VALUES (?, ?, ?, ?, ?, ?, ?)', [$SalesID, now_sql(), $itemID, $quantity, $quantity * (float)$item->PurPrice, $item->SalesPrice, $quantity * (float)$item->SalesPrice]);
        db_exec('UPDATE canteen_sales SET SR = ? WHERE SalesID = ?', ['S', $SalesID]);
    });
}
if ($SalesID > 0 && sales_id($_GET, 'id') > 0) {
    $detailID = sales_id($_GET, 'id');
    db_tx(function () use ($SalesID, $detailID): void {
        $parent = db_one('SELECT Post FROM canteen_sales WHERE SalesID = ? FOR UPDATE', [$SalesID]);
        if ($parent === null || (int)$parent->Post !== 0) return;
        $detail = db_one('SELECT ID, ItemID, Qnty FROM canteen_sales_detail WHERE ID = ? AND SalesID = ? FOR UPDATE', [$detailID, $SalesID]);
        if ($detail === null) return;
        if (db_one('SELECT ItemID FROM canteen_item WHERE ItemID = ? FOR UPDATE', [$detail->ItemID]) === null) throw new RuntimeException('Cannot reverse a line with a missing stock item');
        db_exec('UPDATE canteen_item SET Qnty = Qnty + ? WHERE ItemID = ?', [$detail->Qnty, $detail->ItemID]);
        db_exec('DELETE FROM canteen_sales_detail WHERE ID = ? AND SalesID = ?', [$detailID, $SalesID]);
    });
}
$bill = $SalesID > 0 ? db_one('SELECT UserName, CustomerName, Date, Discount, Total, Note, Post FROM canteen_sales WHERE SalesID = ?', [$SalesID]) : null;
$Post = $bill === null ? '' : ((int)$bill->Post === 1 ? 'Posted' : 'UnPosted');
$Disc = $bill->Discount ?? '';
$Note = $bill->Note ?? '';
$CustomerName = $bill->CustomerName ?? '';
if (sales_text($_POST, 'Member') !== '') $CustomerName = sales_text($_POST, 'Member');
// Legacy SELECT Nickname used the covering Nickname index (S1.1 EXPLAIN evidence).
$customers = db_all('SELECT Nickname FROM canteen_customar ORDER BY Nickname');
$details = db_all('SELECT i.ItemID, i.ItemName, d.ID, d.Qnty, d.PurAmt, d.Amt FROM canteen_sales_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE d.SalesID = ? ORDER BY d.ID', [$SalesID]);
$totals = db_one('SELECT SUM(Amt) AS Sum, SUM(PurAmt) AS PurAmt FROM canteen_sales_detail WHERE SalesID = ?', [$SalesID]);
$Sum = $totals->Sum ?? '';
$PurAmt = $totals->PurAmt ?? '';
$TotalAmount = $Post === 'Posted' ? $bill->Total : $Sum;
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" type="text/css" href="codes/bnspell.css">

<script type="text/javascript" src="codes/keyshortcut.js"></script>
<script type="text/javascript" src="codes/prototype.js"></script>
<script type="text/javascript" src="codes/bnspell.js"></script>
<script type="text/javascript" src="codes/select.js"></script>
<script type="text/javascript">var L="bnw2";</script>

<script type="text/javascript">
        window.onload = function()
        {
        var bComplete = new BComplete("Item",50);
        bComplete.loadData("dictionary/SalesItem.php")
               document.dm.Item.focus()
        };
</script>

<script type="text/javascript">

  shortcut.add("alt+a", function() {
  window.location="Sales.php?NBill=add_new";
  });
  shortcut.add("alt+p", function() {
  return formPost();
  });
  shortcut.add("alt+m", function() {
  return bill();
  });

  function setfocus1(x)
  {
    switch (x)
    {
    case 'supplier':
      document.dm.Item.focus();
      break;
    case 'Item':
    document.dm.qnty.select();
      document.dm.qnty.focus();
      document.dm.qnty.select();
      break;
    case 'qnty':
      document.forms["dm"].submit();
      break;
      default:
      document.dm.Item.focus();
    }

  }

  function bill()
  { //alert('This Bill Posted');
    var Pid=document.dm.bill_no.value;
    //window.location="report/SalesMemo.php?id="+Pid;
    window.open("report/SalesMemo.php?id="+ Pid+"","Ratting","width=850,height=700,0,status=0,scrollbars=1");
  }

  function addPost()
  {
    if(document.form2.Posted.value=='Posted')
    {
    alert('This Bill Posted');
    return false;
    }

    else if(document.dm.Member.value=='Select Member')
    {
    alert('Please select any one Select Member');
    return false;
    }
    else if(document.dm.Item.value=='')
    {
    alert('Insert an Item Please');
    return false;
    }
    else if(document.dm.qnty.value=='')
    {
    alert('Insert Quantity Please');
    return false;
    }
    else  {
    document.forms["dm"].submit();
    return true;
      }
  }
</script>


<script  LANGUAGE="JavaScript"  type="text/javascript">

  function incr(i){
  var i=i*1;
  var i=i+1;
  document.dm.qnty.value=(i);
  ThisAmount();
  }

  function decr(i){
  var i=i*1;
  var i=i-1;
  document.dm.qnty.value=(i);
  ThisAmount();
  }


  function ThisAmount(str) {
  var rate=dm.rate.value;
  var amt=str*rate;
  document.dm.amount.value=(amt);
  }

  function TotalAmount(str) {
  var rate2=form2.amount2.value;
  var amt=rate2-str;
  //document.write(rate2);
  document.form2.total.value=(amt);
  //document.dm.total.value=(amt);
  }

  function formPost() {
    var nic=document.dm.Member.value;
    document.form2.Nickname.value=nic;

    if(document.form2.Posted.value=='Posted')
    {
    alert('This Bill Posted');
    return false;
    }

    else if(document.dm.Member.value=='Select Member')
    {
    alert('Please select any one Select Member');
    return false;
    }
    else if(document.form2.Note.value=='')
    {
    alert('Insert a Note Please');
    return false;
    }
    else    {
    document.forms["form2"].submit();
    return true;
        }
  }
</script>
  <div align="center"><div id="main">
  <?php include("menu.php"); ?>

  <h2> Sales Area</h2>

<?php
if ($bill !== null) {
    echo '<div class="table1">User Name : ' . e($bill->UserName);
    echo '&nbsp;&nbsp;&nbsp;&nbsp;Date :' . e(date('d-M-Y, h:i:s A', strtotime($bill->Date)));
    echo '&nbsp;&nbsp;&nbsp;&nbsp;<b>' . e($Post) . '</b></div>';
} else echo 'Bill No. Not Found';
?>

<fieldset>
<legend>Product Sales </legend>
<form action="" method="post" name="dm" id="dm">
  <table width="100%" border="0" cellspacing="1" cellpadding="1">
    <tr>
      <td><table width="100%" border="0" cellspacing="1" cellpadding="1" >
        <tr>
          <td width="13%" height="32"><strong>Bill NO </strong></td>
          <td width="35%"><input name="bill_no" type="text" id="bill_no" value="<?= e($SalesID ?: '') ?>" readonly></td>
          <td width="11%"><strong>Rate</strong></td>
          <td width="41%"><input name="rate" type="text" id="rate" size="12" readonly >
            <input name="ItemID" type="hidden" id="ItemID">
            <input name="PurAmt" type="hidden" id="PurAmt">
            <strong>Total Quantity</strong>
<input name="TQnty" type="text" id="TQnty" size="12" readonly></td>
        </tr>
        <tr>
          <td><strong>Member</strong></td>
          <td>

          <select name="Member" id="Member">

            <?php
            if ($CustomerName !== '') echo '<option>' . e($CustomerName) . '</option>';
            echo '<option>Select Member</option>';
            foreach ($customers as $Mem) echo '<option>' . e($Mem->Nickname) . '</option>';

            ?>
          </select>
          </td>
          <td><strong>Quantity</strong></td>
          <td><input name="qnty" id="qnty" type="text" autocomplete="off" onKeyUp="if(event.keyCode==13) setfocus1(this.name); else ThisAmount(this.value);" onKeyPress="if(event.keyCode==38) incr(this.value); else if(event.keyCode==40) decr(this.value);" onClick="showPrice(Item.value, 'sales')" size="12">        </td>
        </tr>
        <tr>
          <td><strong>Item</strong></td>
          <td><input onKeyDown="return dn(event);" onKeyPress="return dg(event);"onKeyUp="if(event.keyCode==13) showPrice(this.value, 'sales') & setfocus1(this.name);" type="text" id="Item" name="Item" size="32" style="font-size: 14pt">          </td>
          <td><label><strong>Amount</strong></label></td>
          <td><input name="amount" type="text" id="amount" size="12" readonly></td>
        </tr>
        <tr>
          <td><!--<label><strong>Iten ID</strong></label>-->&nbsp;</td>
          <td><!--<input name="sItenID" type="text" size="4" maxlength="4" onKeyUp="if(event.keyCode==13) showPrice(this.value, 'sales')" />-->&nbsp;</td>
          <td>&nbsp;</td>
          <td><input onClick="addPost()" type="button" name="Button" value="   ADD   "></td>
        </tr>

      </table></td>
    </tr>
    <tr>
      <td>

<?php

// View Grid
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">';
              echo '<tr bgcolor="#f1f1f1">';
              echo '<td width="10%"><b>Item ID</b></td>';
              echo '<td width="40%"><b>Item Name</b></td>';
              echo '<td width="10%"><b>Qnty</b></td>';
              echo '<td width="15%"><b>Purchase Price</b></td>';
              echo '<td width="15%"><b>Sale Price</b></td>';
              echo '<td width="10%"><b>Delete</b></td>';
            echo '</tr>';
foreach ($details as $Detail) {
    print '<tr><td>' . e($Detail->ItemID) . "</td>\n";
    print '<td>' . e($Detail->ItemName) . "</td>\n";
    print '<td>' . e($Detail->Qnty) . "</td>\n";
    print '<td>' . e($Detail->PurAmt) . "</td>\n";
    print '<td>' . e($Detail->Amt) . "</td>\n";
    $removeUrl = 'Sales.php?' . http_build_query(['Bill' => $SalesID, 'ItemID' => $Detail->ItemID, 'qnty' => $Detail->Qnty, 'id' => $Detail->ID]);
    print '<td><a href="' . e($removeUrl) . '"> Delete </a></td></tr>' . "\n";
}
echo "</table>";

?>

      </td>
    </tr>
  </table>
</form>

<form name="form2" method="GET" id="form2" action="">
<table width="100%" border="0" cellspacing="0" cellpadding="1" style="font-size: 14pt">
<tr bgcolor="#f1f1f1">
  <td><a class="battun" href="Sales.php?NBill=add_new" title="Alt+A"> Add New </a>
    <a class="battun" onClick="bill();" href="#" title="Alt+M" >Bill</a>
    <input name="Bill" type="hidden" id="Bill" value="<?= e($SalesID ?: '') ?>">
    <input name="Nickname" type="hidden" id="Nickname">
    <input name="Posted" type="hidden" id="Postedt" value="<?= e($Post) ?>">
    <input name="hPost" type="hidden" id="hPost" value="YES">
    <input onClick="formPost()" type="button" name="Post" value="  Post  " title="Alt+P" style="font-size: 14pt"></td>

              <td>&nbsp;</td>
              <td>&nbsp;</td>
              <td>Discount : </td>
              <td><input name="discount" type="text" size="10" onKeyUp ="TotalAmount(this.value)" value="<?= e($Disc) ?>" style="font-size: 14pt"></td>
              <td>Pur Amount : </td>
              <td><input name="amount1" type="text" id="amount1" style="font-size: 14pt" value = "<?= e(round((float)$PurAmt, 2)) ?>" size="10" readonly></td>
</tr>
<tr bgcolor="#f1f1f1">
  <td>Note : &nbsp;<input name="Note" type="text" size="30" value="<?= e($Note) ?>"></td>
  <td>&nbsp;</td>
  <td>&nbsp;</td>
  <td>Total :</td>
  <td><input name="total" type="text" size="10" readonly value = "<?= e($TotalAmount) ?>" style="font-size: 14pt"></td>
  <td>Sales Amount :</td>
  <td><input name="amount2" type="text" id="amount2" style="font-size: 14pt" value = "<?= e($Sum) ?>" size="10" readonly></td>
</tr>
</table>
</form>


<form name="find" method="get" id="form1" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1">
  <tr>
    <td width="25%">
      <a href="Sales.php?Bill=<?= e($SalesID - 1) ?>">Pre</a>
    <input name="Bill" type="text" id="Bill" value="<?= e($SalesID ?: '') ?>" size="15">
    <a href="Sales.php?Bill=<?= e($SalesID + 1) ?>">Next</a></td>
    <td width="66%"><input name="Find" type="submit" id="Find" value="  Find  "></td>
    <td width="9%">&nbsp;</td>
  </tr>
</table>
</form>
</fieldset>


  <?php include 'footer.php'; ?>
        </div>
    </body>
</html>
