<?php
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== LATEST 5 DOCUMENTS ===\n";
$stmt = $conn->query("
    SELECT d.id, d.creator_id, e.full_name as creator_name, d.title, d.type, d.status, 
           d.receiver_division_id, divs.name as receiver_division_name,
           d.receiver_unit_id, u.name as receiver_unit_name, d.created_at
    FROM documents d
    LEFT JOIN employees e ON d.creator_id = e.id
    LEFT JOIN divisions divs ON d.receiver_division_id = divs.id
    LEFT JOIN units u ON d.receiver_unit_id = u.id
    ORDER BY d.id DESC
    LIMIT 5
");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== EMPLOYEES IN DIVISION PERSONALIA ===\n";
$stmtEmp = $conn->query("
    SELECT e.id, e.full_name, e.division_id, e.unit_id, divs.name as division_name, p.name as position_name
    FROM employees e
    LEFT JOIN divisions divs ON e.division_id = divs.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE divs.name LIKE '%Personalia%' OR e.full_name LIKE '%Andi%'
");
print_r($stmtEmp->fetchAll(PDO::FETCH_ASSOC));
?>
