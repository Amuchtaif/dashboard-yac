<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$res = $db->query("SELECT lat_in, long_in FROM attendance LIMIT 5")->fetchAll();
print_r($res);
