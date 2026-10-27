<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login_json();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array_column(db_all('SELECT ItemName FROM canteen_item WHERE GenericName <> ? AND D = ? ORDER BY ItemID', ['Cooked food', 0]), 'ItemName'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
