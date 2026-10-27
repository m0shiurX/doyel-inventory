<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
function purchase_input(array $input, string $key): string {
    return is_scalar($input[$key] ?? null) ? (string)$input[$key] : '';
}
function purchase_number(array $input, string $key): float {
    $value = purchase_input($input, $key);
    return is_numeric($value) && is_finite((float)$value) ? (float)$value : 0;
}
$BillNo = purchase_input($_POST, 'bill_no') ?: purchase_input($_GET, 'Bill');
$BillNo = ctype_digit($BillNo) && (int)$BillNo > 0 ? (int)$BillNo : '';
$UserName = current_user();
if (purchase_input($_GET, 'NBill') === 'add_new') {
    $BillNo = db_tx(function () use ($UserName) {
        // Serialize this user's open-bill lookup without guessing the last ID.
        db_all('SELECT user_id FROM l_user WHERE UserName = ? ORDER BY user_id FOR UPDATE', [$UserName]);
        $today = date('Y-m-d');
        $bill = db_one("SELECT BillNo FROM canteen_purchase WHERE UserName = ? AND PR = ? AND Post = ? AND Date >= ? AND Date < ? + INTERVAL 1 DAY ORDER BY BillNo LIMIT 1 FOR UPDATE", [$UserName, '', 0, $today, $today]);
        if ($bill !== null) return (int)$bill->BillNo;
        db_exec('INSERT INTO canteen_purchase (PR, UserName, Supplier, Amount, Discount, Total, Date, Post) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', ['', $UserName, '', 0, 0, 0, now_sql(), 0]);
        return (int)db_insert_id();
    });
}
if ($BillNo !== '' && purchase_input($_GET, 'hPost') !== '') {
    db_tx(function () use ($BillNo) {
        $bill = db_one('SELECT BillNo, Post FROM canteen_purchase WHERE BillNo = ? FOR UPDATE', [$BillNo]);
        if ($bill === null || (int)$bill->Post !== 0) return;
        $amount = db_value('SELECT COALESCE(SUM(Amt), 0) FROM canteen_purchase_detail WHERE BillNo = ?', [$BillNo]);
        $discount = purchase_number($_GET, 'discount');
        // The entered discount is allowed; readonly amount/total are not trusted.
        db_exec('UPDATE canteen_purchase SET Supplier = ?, Amount = ?, Discount = ?, Total = ?, Post = ? WHERE BillNo = ? AND Post = ?', [mb_substr(purchase_input($_GET, 'supplier'), 0, 80, 'UTF-8'), $amount, $discount, (float)$amount - $discount, 1, $BillNo, 0]);
    });
}
if ($BillNo !== '' && purchase_input($_POST, 'Item') !== '' && purchase_number($_POST, 'qnty') != 0) {
    $quantity = abs(purchase_number($_POST, 'qnty'));
    $rate = purchase_number($_POST, 'rate');
    $itemId = purchase_input($_POST, 'ItemID');
    if (ctype_digit($itemId) && (int)$itemId > 0) db_tx(function () use ($BillNo, $quantity, $rate, $itemId) {
        $bill = db_one('SELECT BillNo, Post FROM canteen_purchase WHERE BillNo = ? FOR UPDATE', [$BillNo]);
        if ($bill === null || (int)$bill->Post !== 0) return;
        $item = db_one('SELECT ItemID FROM canteen_item WHERE ItemID = ? FOR UPDATE', [$itemId]);
        if ($item === null || !is_finite($quantity * $rate)) return;
        db_exec('INSERT INTO canteen_purchase_detail (BillNo, Date, ItemID, Qnty, Rate, Amt) VALUES (?, ?, ?, ?, ?, ?)', [$BillNo, now_sql(), $itemId, $quantity, $rate, abs($quantity * $rate)]);
        db_exec('UPDATE canteen_item SET Qnty = Qnty + ?, PurPrice = ? WHERE ItemID = ?', [$quantity, $rate, $itemId]);
        db_exec('UPDATE canteen_purchase SET PR = ? WHERE BillNo = ?', ['S', $BillNo]);
    });
}
$detailId = purchase_input($_GET, 'id');
if ($BillNo !== '' && ctype_digit($detailId) && (int)$detailId > 0) {
    db_tx(function () use ($BillNo, $detailId) {
        $bill = db_one('SELECT BillNo, Post FROM canteen_purchase WHERE BillNo = ? FOR UPDATE', [$BillNo]);
        if ($bill === null || (int)$bill->Post !== 0) return;
        $detail = db_one('SELECT ID, ItemID, Qnty FROM canteen_purchase_detail WHERE ID = ? AND BillNo = ? FOR UPDATE', [$detailId, $BillNo]);
        if ($detail === null) return;
        $item = db_one('SELECT ItemID FROM canteen_item WHERE ItemID = ? FOR UPDATE', [$detail->ItemID]);
        if ($item === null) throw new RuntimeException('The purchase stock item no longer exists.');
        // Restore stored quantities only; URL ItemID/qnty cannot alter stock.
        db_exec('UPDATE canteen_item SET Qnty = Qnty - ? WHERE ItemID = ?', [$detail->Qnty, $detail->ItemID]);
        db_exec('DELETE FROM canteen_purchase_detail WHERE ID = ? AND BillNo = ?', [$detailId, $BillNo]);
    });
}
$BillInfo = $BillNo !== '' ? db_one('SELECT UserName, Supplier, Date, Discount, Post FROM canteen_purchase WHERE BillNo = ?', [$BillNo]) : null;
$Post = $BillInfo !== null ? ((int)$BillInfo->Post === 1 ? 'Posted' : 'UnPosted') : '';
$Disc = $BillInfo->Discount ?? 0;
$Supplier = $BillInfo->Supplier ?? '';
$details = $BillNo !== '' ? db_all('SELECT i.ItemID, i.ItemName, d.ID, d.Qnty, d.Amt FROM canteen_purchase_detail d INNER JOIN canteen_item i ON i.ItemID = d.ItemID WHERE d.BillNo = ? ORDER BY d.ID', [$BillNo]) : [];
$Sum = $BillNo !== '' ? (float)(db_value('SELECT SUM(Amt) FROM canteen_purchase_detail WHERE BillNo = ?', [$BillNo]) ?? 0) : 0;
// Preserve the legacy gross-total display; stored header total includes discount.
$TotalAmount = $Sum;
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
            bComplete.loadData("dictionary/PurItem.php")
            var bComplete = new BComplete("supplier",50);
            bComplete.loadData("dictionary/supplier.php")
            document.dm.Item.focus() // Oftional supplier
        };
    </script>
<script type="text/javascript">
  shortcut.add("alt+a", function() {
  window.location="purchase.php?NBill=add_new";
      });
      shortcut.add("alt+p", function() {
    document.forms["form2"].submit();
      });
    shortcut.add("alt+m", function() {
      return bill();
      });

  function setfocus1(x)
  {
  switch (x)
  {
  case 'Item':
    document.dm.rate.focus();
    document.dm.rate.select();
    break;
  case 'rate':
    ThisAmount();
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
      var sid=document.dm.bill_no.value;
      //window.location="report/SalesMemo.php?id="+Pid;
      window.open("report/PurMemo.php?id="+ sid+"","Ratting","width=750,height=700,0,status=0,scrollbars=1");
  }

  function submitformPost()
  {
  document.form2.supplier.value=document.dm.supplier.value;
          if(document.form2.Posted.value=='Posted')
        {
        alert('This Bill Posted');
        return false;
        }

          else if(document.form2.supplier.value=='')
        {
        alert('Please Fill in Supplier Name');
        return false;
        }
         else{document.forms["form2"].submit();}

  }
  function addPost()
  {
      document.forms["dm"].submit();
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

  function ThisAmount() {

  var qnty=dm.qnty.value;
  var rate=dm.rate.value;
  var amt=qnty*rate;
  document.dm.amount.value=(amt);
  }

  function TotalAmount(str) {
  var rate2=form2.amount2.value;
  var amt=rate2-str;
  //document.write(rate2);
  document.form2.total.value=(amt);
  //document.dm.total.value=(amt);
  }
</script>

<div align="center"><div id="main">
<?php include("menu.php"); ?>
<h2>Stock Management Area</h2>
<?php
if ($BillInfo !== null) {
    $BillDate = date_create($BillInfo->Date);
    echo '<div class="table1">User Name : ' . e($BillInfo->UserName);
    echo '&nbsp;&nbsp;&nbsp;&nbsp;Date :' . e($BillDate ? date_format($BillDate, 'd-M-Y, h:i:s A') : '');
    echo '&nbsp;&nbsp;&nbsp;&nbsp;<b>' . e($Post) . '</b></div>';
} else { echo 'Bill No. Not Found'; }
?>

<fieldset>
<legend>Add to Stock</legend>
<form action="" method="post" name="dm" id="dm">
  <table width="100%" border="0" cellspacing="1" cellpadding="1">
    <tr>
      <td><table width="100%" border="0" cellspacing="1" cellpadding="1" >
        <tr>
          <td width="14%" height="32"><strong>Bill NO </strong></td>
          <td width="36%"><input name="bill_no" type="text" id="bill_no" value="<?= e($BillNo) ?>" readonly></td>
          <td width="20%"><strong>Rate</strong></td>
          <td width="30%"><input name="rate" type="text" id="rate" value="0" onKeyUp="if(event.keyCode==13) setfocus1(this.name);" onClick="showPrice(Item.value, 'Pur')" >
            <input name="ItemID" type="hidden" id="ItemID"></td>
        </tr>
        <tr>
          <td><strong>Supplier</strong></td>
          <td><input onKeyDown="return dn(event);" name="supplier" type="text" id="supplier" value="Doyel Eng" size="42"></td>
          <td><strong>Quantity</strong></td>
          <td><input name="qnty" type="text" id="qnty" autocomplete="off" onKeyUp="if(event.keyCode==13)  setfocus1(this.name); else ThisAmount(this.value);" onKeyPress="if(event.keyCode==38) incr(this.value); else if(event.keyCode==40) decr(this.value);" onClick="showPrice(Item.value, 'Pur')"></td>
        </tr>
        <tr>
          <td><strong>Item</strong></td>
          <td><input onKeyDown="return dn(event);" onKeyUp="if(event.keyCode==13) showPrice(this.value, 'Pur') & setfocus1(this.name);" type="text" id="Item" name="Item" size="32" style="font-size: 14pt">          </td>
          <td><label><strong>Amount</strong></label></td>
          <td><input name="amount" type="text" id="amount" readonly></td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
          <td><input onClick="addPost();" type="button" name="Button" value="   ADD   "></td>
          <td>&nbsp;</td>
        </tr>

      </table></td>
    </tr>
    <tr>
      <td>

<?php
echo '<table width="100%" border="0" cellspacing="1" cellpadding="1" class="table1" id="tableGd">';
echo '<tr><td width="10%"><b>Item ID</b></td><td width="40%"><b>Item Name</b></td><td width="15%"><b>Qnty</b></td><td width="15%"><b>Amount</b></td><td width="20%"><b>Delete</b></td></tr>';
foreach ($details as $Detail) {
    echo '<tr><td>' . e($Detail->ItemID) . '</td> <td>' . e($Detail->ItemName) . '</td> <td>' . e($Detail->Qnty) . '</td> <td>' . e($Detail->Amt) . '</td> ';
    $removeUrl = 'purchase.php?' . http_build_query(['Bill' => $BillNo, 'ItemID' => $Detail->ItemID, 'qnty' => $Detail->Qnty, 'id' => $Detail->ID]);
    echo '<td><a href="' . e($removeUrl) . '"> Delete </a></td> </tr>';
}
echo '</table>';
?>

      </td>
    </tr>
  </table>
</form>

<form name="form2" method="GET" id="form2" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1" style="font-size: 14pt">
<tr bgcolor="#f1f1f1">
    <td><a class="battun" href="purchase.php?NBill=add_new" title="Alt+A"> Add New </a>
    <a class="battun" onClick="bill();" title="Alt+M" href="#"> Bill </a></td>

              <td>Amount :
              <input name="amount2" type="text" size="10" readonly value = "<?= e(round($Sum, 2)) ?>" style="font-size: 14pt"></td>
              <td>Discount :
              <input name="discount" type="text" size="10" onKeyUp ="TotalAmount(this.value)" value="<?= e(round((float)$Disc, 2)) ?>" style="font-size: 14pt"></td>
              <td></td>
              <td>Total : <input name="total" type="text" size="10" readonly value = "<?= e(round($TotalAmount, 2)) ?>" style="font-size: 14pt"></td>
              <td><input name="Bill" type="hidden" id="Bill" value="<?= e($BillNo) ?>">
                <input name="Posted" type="hidden" id="Posted" value="<?= e($Post) ?>">
                <input name="supplier" type="hidden" id="supplier" value="">
                <input name="hPost" type="hidden" id="hPost" value="YES">
                <input title="Alt+P" onClick="submitformPost()" type="button" name="Post" value="  Post  " style="font-size: 14pt"></td>
</tr>
</table>
</form>


<form name="find" method="get" id="form1" action="">
<table width="100%" border="0" cellspacing="1" cellpadding="1">
  <tr>
    <td width="25%">
      <a href="purchase.php?Bill=<?= e((int)$BillNo - 1) ?>">Pre</a>
    <input name="Bill" type="text" id="Bill" value="<?= e($BillNo) ?>" size="15">
    <a href="purchase.php?Bill=<?= e((int)$BillNo + 1) ?>">Next</a></td>
    <td width="69%"><input name="Find" type="submit" id="Find" value="  Find  "></td>
    <td width="6%">&nbsp;</td>
  </tr>
</table>
</form>
</fieldset>
<?php include 'footer.php'; ?>
    </div>
</body>
</html>
