<?php
require __DIR__ . '/../lib/bootstrap.php';
require_login_json();
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(array_column(db_all('SELECT SupplierName FROM canteen_supplier WHERE D = ? ORDER BY SupplierId', [0]), 'SupplierName'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
