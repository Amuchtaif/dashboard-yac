<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- All Education Units ---\n";
$stmt = $conn->query("SELECT id, name FROM education_units");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($units as $u) {
    echo sprintf("[%s] %s\n", $u['id'], $u['name']);
}
?>