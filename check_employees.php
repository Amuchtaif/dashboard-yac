<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, full_name, division_id, unit_id, position_id FROM employees WHERE full_name LIKE '%Mulyana%' OR full_name LIKE '%Ahmad Ghozali%'");
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('employees_check.txt', print_r($data, true));
