<?php
// logic/permissions/update_employee_permission.php
require_once '../../config/database.php';

header('Content-Type: application/json');

// Get and decode input data
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['employee_id']) || !isset($data['permission_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
    exit;
}

$employee_id = (int)$data['employee_id'];
$permission_name = $data['permission_name'];
// Default is_allowed to 0 if not provided, but typically 1/0 from checkbox
$is_allowed = isset($data['is_allowed']) ? (int)$data['is_allowed'] : 0;

$db = new Database();
$conn = $db->getConnection();

try {
    if (isset($data['revert_to_role']) && $data['revert_to_role'] === true) {
        $sql = "DELETE FROM user_permissions WHERE employee_id = :employee_id AND permission_name = :permission_name";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':permission_name', $permission_name);
    } else {
        // Upsert (Insert or Update on duplicate key)
        $sql = "INSERT INTO user_permissions (employee_id, permission_name, is_allowed) 
                VALUES (:employee_id, :permission_name, :is_allowed)
                ON DUPLICATE KEY UPDATE is_allowed = :is_allowed_update";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':employee_id', $employee_id);
        $stmt->bindParam(':permission_name', $permission_name);
        $stmt->bindParam(':is_allowed', $is_allowed);
        $stmt->bindParam(':is_allowed_update', $is_allowed);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Hak akses berhasil diperbarui']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui database']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
