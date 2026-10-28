<?php
require __DIR__ . '/lib/bootstrap.php';
require_login();
csrf_check_post();
if (!is_admin()) {
    echo '<script type="text/javascript">window.location.href="Payment.php";alert("Deletation Can not be Completed");</script>';
    exit;
}
require_admin();
$id = filter_var($_POST['d'] ?? null, FILTER_VALIDATE_INT);
if ($id !== false && $id > 0) {
    confirm_delete(['d' => $id], 'Payment.php');
    db_exec('DELETE FROM canteen_collection WHERE PaymentID = ?', [$id]);
    echo '<span class="warning">Collection no ' . e($id) . ' is Removed.</span>';
    echo '<script type="text/javascript">window.location.href="Payment.php";alert("Deletation Complete");</script>';
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link href="css/Style.css" rel="stylesheet" type="text/css">
    <link href="css/lessframework4.css" rel="stylesheet" type="text/css">
</body>
</html>
