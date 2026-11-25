<?php
// api/feedback/my_feedbacks.php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: GET");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode ' . $_SERVER['REQUEST_METHOD'] . ' tidak diizinkan. Gunakan GET.'
    ]);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Parameter user_id wajib diisi'
        ]);
        exit();
    }

    $baseUrl = rtrim(BASE_URL, '/');

    $query = "
        SELECT 
            uf.id,
            uf.user_id,
            COALESCE(e.full_name, 'Pengguna') AS user_name,
            COALESCE(u.name, d.name, 'Umum') AS user_division,
            uf.type,
            uf.module,
            uf.priority,
            uf.title,
            uf.description,
            uf.attachment_path,
            uf.app_version,
            uf.device_info,
            uf.status,
            uf.admin_notes,
            COALESCE(adm.full_name, 'Tim IT YAC') AS admin_name,
            uf.created_at,
            uf.updated_at
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN employees adm ON uf.admin_id = adm.id
        WHERE uf.user_id = :user_id
        ORDER BY uf.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $user_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $feedbacks = [];
    foreach ($rows as $row) {
        $attachmentUrl = null;
        if (!empty($row['attachment_path'])) {
            $attachmentUrl = $baseUrl . '/' . ltrim($row['attachment_path'], '/');
        }

        $feedbacks[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'user_division' => $row['user_division'],
            'type' => $row['type'],
            'module' => $row['module'],
            'priority' => $row['priority'],
            'title' => $row['title'],
            'description' => $row['description'],
            'attachment_url' => $attachmentUrl,
            'app_version' => $row['app_version'],
            'device_info' => $row['device_info'],
            'status' => $row['status'],
            'admin_notes' => $row['admin_notes'],
            'admin_name' => (!empty($row['admin_notes']) || !empty($row['admin_id'])) ? $row['admin_name'] : null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $feedbacks
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan di server: ' . $e->getMessage()
    ]);
}
