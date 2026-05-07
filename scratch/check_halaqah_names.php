<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT group_name FROM halaqah_groups ORDER BY LENGTH(group_name) ASC, group_name ASC LIMIT 20");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
