<?php
include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT DISTINCT category FROM grade_levels");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
