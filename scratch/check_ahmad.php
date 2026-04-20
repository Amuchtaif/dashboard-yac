<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- AHMAD GHOZALI DETAILS ---\n";
$stmt = $conn->prepare("SELECT e.id, e.full_name, e.division_id, d.name as division_name, e.unit_id, u.name as unit_name, p.name as position_name, p.level 
                       FROM employees e 
                       LEFT JOIN divisions d ON e.division_id = d.id 
                       LEFT JOIN units u ON e.unit_id = u.id
                       LEFT JOIN positions p ON e.position_id = p.id 
                       WHERE e.full_name LIKE ?");
$stmt->execute(['%Ahmad Ghozali%']);
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n--- ALL DIVISIONS ---\n";
$stmt = $conn->query("SELECT id, name FROM divisions");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
