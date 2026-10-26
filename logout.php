<?php session_start();
require_once 'menu.php';
$_SESSION = array();
session_destroy();
redirectTo('login.php');
?>
