<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

check_permission('can_access_kesantrian');

$db = new Database();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SELECT vo.*, e.full_name, e. nik, p.name as position_name 
                          FROM petugas_pelanggaran vo
                          JOIN employees e ON vo.employee_id = e.id
                          LEFT JOIN positions p ON e.position_id = p.id
                          ORDER BY e.full_name ASC");
    $officers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $officers
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
