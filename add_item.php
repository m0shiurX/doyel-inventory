<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
function item_text(string $key, int $length): string
{
    return mb_substr(is_string($_POST[$key] ?? null) ? $_POST[$key] : '', 0, $length, 'UTF-8');
}
function item_number(string $key): float
{
    $value = is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
    return is_numeric($value) && is_finite((float)$value) ? (float)$value : 0.0;
}
$ID = filter_var($_GET['ID'] ?? null, FILTER_VALIDATE_INT);
$ID = $ID !== false && $ID > 0 ? $ID : 0;
$ItemName = $PurPrice = $SalesPrice = $SupplierName = $getTotal = $MaximumQuantity = $MinimumQuantity = '';
$GenericName = $ItemType = null;
$ItemID = $ID;
$Drop = null;
$message = '';
if (item_text('Item', 80) !== '') {
    $ItemName = item_text('Item', 80);
    $SupplierName = item_text('supplier', 80);
    $GenericName = item_text('GenericName', 25);
    $ItemType = item_text('ItemType', 2);
    $PurPrice = input_decimal(item_text('PurPrice', 200), 14, 3);
    $SalesPrice = input_decimal(item_text('SalesPrice', 200), 14, 3);
    // Explicit stock edits retain their existing workflow; a disabled/omitted field cannot erase stock.
    $hasStock = is_string($_POST['totalQnty'] ?? null);
    $totalQnty = input_decimal(item_text('totalQnty', 200), 12, 3);
    $MaximumQuantity = item_number('MaximumQuantity');
    $MinimumQuantity = item_number('MinimumQuantity');
    $Drop = !empty($_GET['Drop']) ? item_number('Drop') : 0;
    if ($ID > 0) {
        if ($hasStock) {
            db_exec('UPDATE canteen_item SET ItemName=?, PurPrice=?, SalesPrice=?, SupplierName=?, GenericName=?, ItemType=?, Qnty=?, MaximumQuantity=?, MinimumQuantity=? WHERE ItemID=?', [$ItemName,$PurPrice,$SalesPrice,$SupplierName,$GenericName,$ItemType,$totalQnty,$MaximumQuantity,$MinimumQuantity,$ID]);
        } else {
            db_exec('UPDATE canteen_item SET ItemName=?, PurPrice=?, SalesPrice=?, SupplierName=?, GenericName=?, ItemType=?, MaximumQuantity=?, MinimumQuantity=? WHERE ItemID=?', [$ItemName,$PurPrice,$SalesPrice,$SupplierName,$GenericName,$ItemType,$MaximumQuantity,$MinimumQuantity,$ID]);
        }
    } elseif (db_value('SELECT ItemID FROM canteen_item WHERE ItemName=?', [$ItemName]) === null) {
        db_exec('INSERT INTO canteen_item (ItemName,PurPrice,SalesPrice,SupplierName,GenericName,ItemType,MaximumQuantity,MinimumQuantity,Qnty,D) VALUES (?,?,?,?,?,?,?,?,?,?)', [$ItemName,$PurPrice,$SalesPrice,$SupplierName,$GenericName,$ItemType,$MaximumQuantity,$MinimumQuantity,0,0]);
        $message = 'You have successfully added an item';
    } else $message = 'This Item exists ! Try a new one.';
}
if ($ID > 0) {
    $item = db_one('SELECT ItemID,ItemName,PurPrice,SalesPrice,SupplierName,GenericName,ItemType,Qnty,MaximumQuantity,MinimumQuantity,D FROM canteen_item WHERE ItemID=?', [$ID]);
    if ($item !== null) {
        $ItemID=$item->ItemID; $ItemName=$item->ItemName; $PurPrice=$item->PurPrice; $SalesPrice=$item->SalesPrice;
        $SupplierName=$item->SupplierName; $GenericName=$item->GenericName; $ItemType=$item->ItemType;
        $getTotal=$item->Qnty; $MaximumQuantity=$item->MaximumQuantity; $MinimumQuantity=$item->MinimumQuantity; $Drop=$item->D;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
    <script type="text/javascript">
        window.onload = function()
        {
          var bComplete = new BComplete("supplier",10);
          bComplete.loadData("dictionary/supplier.php")
        };
    </script>
    <div align="center"><div id="main">
  <?php include("menu.php"); ?>
  <h2>Single Product </h2>
<?php if ($message !== ''): ?><h2><?= e($message) ?></h2><?php endif; ?>

<fieldset>
  <legend>Adding/Updating an Item</legend>
  <form action="" method="post" enctype="multipart/form-data" name="add_book" id="add_book"><?php csrf_field(); ?>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td>Item ID</td>
        <td width="65%">
          <?php if ($ID > 0): ?>
              <input name="ItemID" type="text" id="ItemID" readonly="YES" value="<?= e($ItemID) ?>">&nbsp;&nbsp;&nbsp;&nbsp;Drop
              <?php if ($Drop !== null): ?><input type="checkbox" name="Drop" value="<?= e($Drop) ?>"/><?php endif; ?>
          <?php else: ?>&nbsp;<?php endif; ?>
        </td>
          <td>Available</td>
          <td><input name="totalQnty" type="number" id="totalQnty" <?= ($ID > 0 && !is_admin()) ? '' : 'disabled' ?>  value="<?= e(fmt_numeric($getTotal)) ?>"></td>
      </tr>
      <tr>
        <td width="17%">Item Name</td>
        <td width="27%"><input onKeyPress="return dg(event);" name="Item" type="text" id="Item" size="30" value="<?= e($ItemName) ?>"></td>
        <td width="11%">Pur Price</td>
        <td width="19%"><input name="PurPrice" type="text" id="PurPrice" value="<?= e(fmt_numeric($PurPrice)) ?>"></td>
        <td width="26%">Min</td>
        <td width="19%"><input name="MinimumQuantity" type="text" id="MinimumQuantity" value="<?= e($MinimumQuantity) ?>"></td>
      </tr>
      <tr>
        <td>Supplier Name</td>
        <td>
          <label>
            <input onKeyPress="return dg(event);" name="supplier" value="Doyel Eng" type="text" id="supplier" size="30" value="<?= e($SupplierName) ?>"/>
          </label>
        </td>
        <td>Sales Price</td>
        <td><input name="SalesPrice" type="text" id="SalesPrice" value="<?= e(fmt_numeric($SalesPrice)) ?>"/></td>
        <td width="26%">Max</td>
        <td width="19%"><input name="MaximumQuantity" type="text" id="MaximumQuantity" value="<?= e($MinimumQuantity) ?>"></td>
      </tr>
      <tr>
        <td>Catagory</td>
        <td>
          <select name="GenericName" id="select">
            <option selected><?= e($GenericName ?? 'Select Catagory') ?></option>
            <option>Row Material</option>
            <option>Sales Item</option>
          </select>
        </td>
        <td>Item Type</td>
        <td>
          <select name="ItemType" id="select">
            <option value="<?= e($ItemType ?? 'Select Catagory') ?>" selected><?= e($ItemType ?? 'Select Catagory') ?></option>
            <option value="P">Piston</option>
            <option value="L">Liner</option>
            <option value="DP">Dhalay Puli</option>
            <option value="AP">Alay Puli</option>
            <option value="BC">Bearing Case</option>
            <option value="H">Haps</option>
            <option value="DT">Dhalay Taoa</option>
            <option value="DK">Dhalay Koray</option>
            <option value="AK">Alay Koray</option>
            <option value="AK">Alay Taoa</option>
            <option value="BD">Break Drum</option>
            <option value="AF">Alay Fan</option>
            <option value="O">Others</option>
          </select>
        </td>
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
