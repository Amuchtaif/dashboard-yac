<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT e.id, e.full_name, d.name as dept_name FROM employees e LEFT JOIN departments d ON e.department_id = d.id LIMIT 10");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "{$row['id']} | {$row['full_name']} | {$row['dept_name']}\n";
}
