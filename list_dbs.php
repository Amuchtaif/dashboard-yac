<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$dbs = $db->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $dbs);
