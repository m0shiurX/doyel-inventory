<?php
require __DIR__ . '/lib/bootstrap.php';
require_admin();
csrf_check_post();

/** Header first, then detail rows and items, matching the bill-entry lock order. */
function delete_inventory_bill(string $kind, int $id): bool
{
    // Identifiers and arithmetic are selected only from this fixed map.
    $types = [
        'sale' => ['canteen_sales', 'canteen_sales_detail', 'SalesID', '+'],
        'purchase' => ['canteen_purchase', 'canteen_purchase_detail', 'BillNo', '-'],
    ];
    [$header, $detail, $key, $operator] = $types[$kind];
    return db_tx(static function () use ($header, $detail, $key, $operator, $id): bool {
        if (db_one("SELECT $key FROM $header WHERE $key = ? FOR UPDATE", [$id]) === null) return false;
        $lines = db_all("SELECT ID, ItemID FROM $detail WHERE $key = ? ORDER BY ItemID, ID FOR UPDATE", [$id]);
        if ($lines) {
            $items = db_all("SELECT ItemID FROM canteen_item WHERE ItemID IN (SELECT ItemID FROM $detail WHERE $key = ?) ORDER BY ItemID FOR UPDATE", [$id]);
            if (count($items) !== count(array_unique(array_column($lines, 'ItemID')))) throw new RuntimeException('Cannot reverse stock for a missing item');
            // Reverse each item's total once, including unposted lines: stock changes during entry.
            db_exec("UPDATE canteen_item i JOIN (SELECT ItemID, SUM(Qnty) AS quantity FROM $detail WHERE $key = ? GROUP BY ItemID) d ON d.ItemID = i.ItemID SET i.Qnty = i.Qnty $operator d.quantity", [$id]);
        }
        db_exec("DELETE FROM $detail WHERE $key = ?", [$id]);
        db_exec("DELETE FROM $header WHERE $key = ?", [$id]);
        return true;
    });
}
$messages = [];
$action = mutation_action(['p' => isset($_POST['p']), 's' => isset($_POST['s']), 'dl' => isset($_POST['dl']), 'd' => isset($_POST['d'])]);
$id = filter_var($_POST[$action] ?? null, FILTER_VALIDATE_INT);
if ($id !== false && $id > 0) {
    if ($action === 'p' || $action === 's') {
        [$table, $key] = $action === 'p' ? ['canteen_purchase', 'BillNo'] : ['canteen_sales', 'SalesID'];
        db_exec("UPDATE $table SET Post = ? WHERE $key = ?", [0, $id]);
        $messages[] = "Bill no $id is Unposted.";
    } elseif ($action === 'dl' || $action === 'd') {
        confirm_delete([$action => $id], 'Unpost.php');
        if (delete_inventory_bill($action === 'dl' ? 'purchase' : 'sale', $id)) $messages[] = "Bill no $id is Deleted.";
    }
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">

<div id="main">
  <?php include("menu.php"); ?>
<h1 align="center">Bill Unpost</h1>

<?php foreach ($messages as $message): ?>
<span class="warning"><?= e($message) ?></span>
<?php endforeach; ?>

<div style=" height:450px;display:block; margin-bottom:30px;">
    <div style="display:block;width:48%; float:left" class="report">
        <h2>Add to Stock Bill</h2>
        <div style="height:400px; overflow:auto">
        <table width="98%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="22%">Bill No</th>
              <th width="41%">Date</th>
              <th width="21%">Status</th>
              <th width="16%">Unpost</th>
            </tr>
        <?php foreach (db_all('SELECT BillNo, Date FROM canteen_purchase ORDER BY BillNo DESC LIMIT 500') as $Mem): ?>
            <tr>
                <td><?= e($Mem->BillNo) ?></td>
                <td><?= e(date_format(date_create($Mem->Date), 'd-M-y h:i:s A')) ?></td>
                <td><a href="purchase.php?Bill=<?= e(urlencode($Mem->BillNo)) ?>" target="_blank">View Bill</a></td>
                <td><?= action_form('Unpost.php', ['p' => $Mem->BillNo], 'Unpost') ?></td>
                <td><?= action_form('Unpost.php', ['dl' => $Mem->BillNo], 'Delete') ?></td>
            </tr>
        <?php endforeach; ?>
        </table>
        </div>
    </div>
   <div style="width:48%; float:right" class="report">
        <h2>Sales Bill</h2>
        <div style="height:400px; overflow:auto">
        <table width="98%" border="0" cellspacing="1" cellpadding="1" id="tableGd">
              <tr bgcolor="#f1f1f1">
              <th width="22%">Bill No</th>
              <th width="41%">Date</th>
              <th width="21%">Status</th>
              <th width="16%">Unpost</th>
            </tr>
        <?php foreach (db_all('SELECT SalesID, Date FROM canteen_sales ORDER BY SalesID DESC LIMIT 500') as $Mem): ?>
            <tr>
                <td><?= e($Mem->SalesID) ?></td>
                <td><?= e(date_format(date_create($Mem->Date), 'd-M-y h:i:s A')) ?></td>
                <td><a href="Sales.php?Bill=<?= e(urlencode($Mem->SalesID)) ?>" target="_blank">View Bill</a></td>
                <td><?= action_form('Unpost.php', ['s' => $Mem->SalesID], 'Unpost') ?></td>
                <td><?= action_form('Unpost.php', ['d' => $Mem->SalesID], 'Delete') ?></td>
            </tr>
        <?php endforeach; ?>
        </table>
        </div>

   </div>
</div>
</div>
</body>
</html>
