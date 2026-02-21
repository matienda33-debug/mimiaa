<?php
// public/admin/logout.php
require_once '../../app/config/database.php';
require_once '../../app/models/Auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$auth->logout();

header('Location: ../../login.php');
exit();