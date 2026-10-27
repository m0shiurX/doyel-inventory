<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login_json();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array_column(db_all('SELECT ItemName FROM canteen_item WHERE GenericName <> ? AND D = ? ORDER BY ItemID', ['Row Material', 0]), 'ItemName'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
