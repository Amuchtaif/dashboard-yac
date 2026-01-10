<?php
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->query("SELECT 1 FROM work_schedules LIMIT 1");
    echo "Table exists";
} catch (PDOException $e) {
    echo "Table missing";
}
?>