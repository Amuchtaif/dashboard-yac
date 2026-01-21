<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Grade Levels IDs ---\n";
$stmt = $conn->query("SELECT id, name FROM grade_levels ORDER BY id ASC");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " - " . $r['name'] . "\n";
}
?>