<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=UTF-8');

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$position_id = isset($_GET['position_id']) ? (int)$_GET['position_id'] : 0;

if ($position_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Jabatan tidak valid']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch position details
    $posStmt = $conn->prepare("SELECT id, name, level FROM positions WHERE id = :id LIMIT 1");
    $posStmt->execute([':id' => $position_id]);
    $position = $posStmt->fetch(PDO::FETCH_ASSOC);

    if (!$position) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Jabatan tidak ditemukan']);
        exit;
    }

    // Fetch employees for this position
    $empStmt = $conn->prepare("
        SELECT 
            e.id, 
            e.nik, 
            e.full_name, 
            e.email, 
            e.phone_number, 
            e.profile_photo, 
            e.status,
            d.name as division_name,
            dep.name as department_name,
            u.name as unit_name
        FROM employees e
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN departments dep ON e.department_id = dep.id
        LEFT JOIN units u ON e.unit_id = u.id
        WHERE e.position_id = :position_id
        ORDER BY (CASE WHEN e.status = 'active' THEN 0 ELSE 1 END), e.full_name ASC
    ");
    $empStmt->execute([':position_id' => $position_id]);
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($employees as &$emp) {
        $photo_url = null;
        if (!empty($emp['profile_photo'])) {
            if (file_exists(BASE_PATH . '/uploads/profile_photos/' . $emp['profile_photo'])) {
                $photo_url = BASE_URL . '/uploads/profile_photos/' . $emp['profile_photo'];
            } elseif (file_exists(BASE_PATH . '/public/uploads/employees/' . $emp['profile_photo'])) {
                $photo_url = BASE_URL . '/public/uploads/employees/' . $emp['profile_photo'];
            }
        }
        if (!$photo_url) {
            $photo_url = 'https://ui-avatars.com/api/?name=' . urlencode($emp['full_name']) . '&background=0284c7&color=fff&bold=true';
        }
        $emp['avatar_url'] = $photo_url;
    }
    unset($emp);

    echo json_encode([
        'success' => true,
        'position' => $position,
        'total' => count($employees),
        'data' => $employees
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan server: ' . $e->getMessage()
    ]);
}
