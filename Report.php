<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
        <script language="JavaScript" src="css/calendar_eu.js"></script>
        <link rel="stylesheet" href="css/calendar.css">
    <div id="main">

<?php include("menu.php"); ?>

<?php
function report_date(string $value): string
{
    if (!preg_match('/^[0-9]{2}-[0-9]{2}-[0-9]{4}$/D', $value)) return date('d-m-Y');
    $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);
    return $date !== false && (int)$date->format('Y') >= 1000 && $date->format('d-m-Y') === $value ? $value : date('d-m-Y');
}
function report_time(string $value, string $fallback): string
{
    if (!preg_match('/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value)) return $fallback;
    $time = DateTimeImmutable::createFromFormat('!H:i:s', $value);
    return $time !== false && $time->format('H:i:s') === $value ? $value : $fallback;
}
$posted = static fn(string $key): string => is_string($_POST[$key] ?? null) ? $_POST[$key] : '';
$FormDate = report_date($posted('start_date'));
$ToDate = report_date($posted('end_date'));
$FormTime = report_time($posted('start_time'), '00:00:00');
$ToTime = report_time($posted('end_time'), '23:59:59');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
$_SESSION['form_date'] = DateTimeImmutable::createFromFormat('!d-m-Y', $FormDate)->format('Y-m-d') . ' ' . $FormTime;
$_SESSION['to_date'] = DateTimeImmutable::createFromFormat('!d-m-Y', $ToDate)->format('Y-m-d') . ' ' . $ToTime;
}
?>

<p><form action="" method="POST" name="form" ><?php csrf_field(); ?>
<table width="778" border="0" cellpadding="0" cellspacing="10">
    <tr>
       <td width="44">Form</td>
       <td>
            <script language="JavaScript">
                new tcal ({
                    // form name
                    'formname': 'form',
                    // input name
                    'controlname': 'start_date'
                });
            </script>
       </td>
       <td width="192">
                <input name="start_date" type="text" id="start_date"  value="<?= e($FormDate) ?>"/>
        </td>
        <td width="62"><input name="start_time" type="text" id="start_time"  value="<?= e($FormTime) ?>" size="10"/></td>
        <td width="35">To</td>
        <td>
            <script language="JavaScript">
                new tcal ({
                    // form name
                    'formname': 'form',
                    // input name
                    'controlname': 'end_date'
                });
            </script>
        </td>
    <td width="198">
        <input name="end_date" type="text" id="end_date" value="<?= e($ToDate) ?>" />
    </td>
    <td width="67"><input name="end_time" type="text" id="end_time" value="<?= e($ToTime) ?>" size="10" /></td>
    <td width="100"><input type="submit" name="submit" value="Set" /></td>
    </tr>
</table>
</form>
        </p>
<div style=" height:450px;display:block; margin-bottom:30px;">
    <div style="display:block;width:48%; float:left" class="report">
      <ul style="margin:0 0 20px 0; padding-left:10px;">
        <li><a href="report/ListOfItem.php" target="_blank">Product List</a></li>
        <li><a href="report/ItemLedgr.php" target="_blank">Product List : Ledgr</a></li>
        <li><a href="report/ListOfItemWithQun.php" target="_blank">Product List : Quantity</a></li>
    <li><a href="report/list_item_with_minimum.php" target="_blank">Product List : Below Minimum Quantity</a></li>
    <li><a href="report/list_item_with_maximum.php" target="_blank">Product List : Above Maximum Quantity</a></li>
        <hr />
        <li><a href="report/salesOfaPeriod-itemName.php" target="_blank">Sales of a period : Short by Name</a></li>
    <li><a href="report/sales_of_a_period_category_wise.php" target="_blank">Sales of a period : Category Wise</a></li>
    <li><a href="report/salesOfaPeriod-itemName-By-user.php" target="_blank">Sales of a period : Short By User</a></li>
    <li><a href="report/salesOfaPeriod-Bill.php" target="_blank">Sales of a period : Short by Bill</a></li>
    <hr />
    <!-- <li><a href="report/SendToProcessOfaPeriod.php" target="_blank">Send to Process of a period</a></li> -->
    <li><a href="report/Purchase of a period.php" target="_blank">Purchase of a period </a></li>
    <li><a href="report/PurchaseOfaPeriod-Bill-wise.php" target="_blank">Purchase of a period : Bill Wise</a></li>
    <hr />
    <li><a href="report/CashCollectionOfaPeriod.php" target="_blank">Cash Collection</a></li>
    <li><a href="report/CashExpensesOfaPeriod.php" target="_blank">Cash Expenses</a></li>
    <hr />
    <li><a href="report/list_of_members.php" target="_blank">Customer info with Dues</a></li>
    <li><a href="report/Employee ledgr-Summary.php" target="_blank">Accountant Summary</a></li>
        <li><a href="report/closingBalance.php" target="_blank">Closing Balance</a></li>
<!--        <li><a href="report/UnpostedBill.php" target="_blank">Unposted Bills</a></li> -->
      </ul>
    </div>

        <div style="width:48%; float:right" class="report">
        <h2>Present Stock</h2>
        <p>
            <a href="javascript:void(0);"onClick=window.open("SavePrestntStock.php","Ratting","width=450,height=300,0,status=0,");>Save Prestnt Stock</a>
        </p>

    <ul style="margin:20px 0 20px 5px; padding-left:10px; height:370px; overflow:auto;">
    <?php foreach (db_all('SELECT SlNo, Date FROM canteen_stock ORDER BY SlNo DESC') as $SlN): ?>
        <li><a href="report/savedClosingBalance.php?SlN=<?= e(urlencode($SlN->SlNo)) ?>" target="_blank"><?= e($SlN->SlNo) ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?= e(date_format(date_create($SlN->Date), 'd-M-Y h:i:s A')) ?></a></li>
    <?php endforeach; ?>

    </ul>

        </div>
</div>
<?php include 'footer.php'; ?>
</div>
    </body>
</html>
