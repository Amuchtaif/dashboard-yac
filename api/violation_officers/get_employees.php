<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

check_permission('can_access_kesantrian');

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SELECT id, full_name, nik FROM employees WHERE status = 'active' ORDER BY full_name ASC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $employees]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
