<?php
// Environment overrides are available to the local regression stacks. With no
// overrides, retain the original Windows/PHP Desktop connection defaults.
define('HOST', getenv('DOYEL_DB_HOST') ?: 'localhost');
define('USER', getenv('DOYEL_DB_USER') ?: 'root');
define('PASSWORD', getenv('DOYEL_DB_PASS') ?: '');
define('DB_NAME', getenv('DOYEL_DB_NAME') ?: 'software');
$dbPort = getenv('DOYEL_DB_PORT');
$dbServer = HOST . ($dbPort ? ':' . $dbPort : '');
$conn = mysql_connect($dbServer, USER, PASSWORD) or die("Couldn't Connect to Database Server");
mysql_select_db(DB_NAME, $conn) or die("Couldn't Select Database");
?>