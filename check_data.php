<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT e.full_name, e.division_id, e.unit_id, p.name as position_name, p.level FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.full_name LIKE '%Mulyana%' OR e.full_name LIKE '%Ahmad Ghozali%'");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
