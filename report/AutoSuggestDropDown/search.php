<?php
require __DIR__ . '/../../lib/bootstrap.php';
require_login_json();
header('Content-Type: text/html; charset=UTF-8');
$term = is_string($_POST['search_term'] ?? null) ? $_POST['search_term'] : '';
// Legacy EXPLAIN used the covering ItemName index; retain its suggestion order.
foreach (db_all('SELECT ItemID,ItemName FROM canteen_item WHERE ItemName LIKE ? ORDER BY ItemName,ItemID LIMIT 10', ['%' . $term . '%']) as $row) {
    echo '<tr><td class="re">' . e($row->ItemName) . '</td><td>' . e($row->ItemID) . '</td></tr>';
}
