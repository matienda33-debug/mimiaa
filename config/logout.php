<?php
require_once 'config.php';
require_once 'database.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);
$auth->logout();
?>