<?php
require_once 'config.php';

$auth = new Auth($db);
$auth->logout();
?>