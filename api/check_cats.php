<?php
include_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT DISTINCT category FROM grade_levels");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($results);
?>
