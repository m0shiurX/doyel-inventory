<?php
require __DIR__ . '/lib/bootstrap.php';
require_login_json();
header('Content-Type: text/html; charset=UTF-8');
$name = mb_substr(is_string($_POST['SupplierName'] ?? null) ? $_POST['SupplierName'] : '', 0, 80, 'UTF-8');
if ($name === '') { echo 'Please fill in SupplierName fild'; exit; }
if (db_value('SELECT SupplierId FROM canteen_supplier WHERE SupplierName = ?', [$name]) !== null) {
    echo '<h2>This SupplierName is already Added, Please try anather</h2>';
} else {
    try {
        db_exec('INSERT INTO canteen_supplier (SupplierName, D) VALUES (?, ?)', [$name, 0]);
        echo '<h2>Sucses New SupplierName Added</h2>';
    } catch (PDOException $error) {
        if ($error->getCode() !== '23000') throw $error;
        echo '<h2>This SupplierName is already Added, Please try anather</h2>';
    }
}
