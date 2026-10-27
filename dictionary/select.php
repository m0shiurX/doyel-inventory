<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login_json();
header('Content-Type: text/plain; charset=UTF-8');
$q = is_string($_GET['q'] ?? null) ? $_GET['q'] : '';
if ($q !== '') {
    $item = db_one('SELECT ItemID, Qnty, PurPrice, SalesPrice FROM canteen_item WHERE ItemName = ? ORDER BY ItemID LIMIT 1', [$q]);
    if ($item !== null) echo implode('/', [$item->ItemID, $item->Qnty, $item->PurPrice, $item->SalesPrice]);
}
// The legacy endpoint ends with one space; keep the exact slash protocol bytes.
echo ' ';
