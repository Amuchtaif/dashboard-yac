<?php
// api/feedback/admin/list.php
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

require_once __DIR__ . '/../../../config/app.php';
require_once __DIR__ . '/../../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Query filters
    $status = trim($_GET['status'] ?? '');
    $type = trim($_GET['type'] ?? '');
    $module = trim($_GET['module'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    // 1. Overall Status Summary Counter
    $summaryStmt = $db->query("
        SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
            COUNT(CASE WHEN status = 'reviewed' THEN 1 END) AS reviewed,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS in_progress,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected
        FROM user_feedbacks
    ");
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total' => 0, 'pending' => 0, 'reviewed' => 0, 'in_progress' => 0, 'completed' => 0, 'rejected' => 0
    ];

    $summaryData = [
        'total' => (int)$summary['total'],
        'pending' => (int)$summary['pending'],
        'reviewed' => (int)$summary['reviewed'],
        'in_progress' => (int)$summary['in_progress'],
        'completed' => (int)$summary['completed'],
        'rejected' => (int)$summary['rejected']
    ];

    // 2. Build Filtered Query
    $where = [];
    $params = [];

    if (!empty($status) && in_array($status, ['pending', 'reviewed', 'in_progress', 'completed', 'rejected'], true)) {
        $where[] = "uf.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($type) && in_array($type, ['feature', 'improvement', 'bug', 'general'], true)) {
        $where[] = "uf.type = :type";
        $params[':type'] = $type;
    }

    if (!empty($module) && $module !== 'all') {
        $where[] = "uf.module = :module";
        $params[':module'] = $module;
    }

    if (!empty($search)) {
        $where[] = "(uf.title LIKE :search OR uf.description LIKE :search OR e.full_name LIKE :search OR e.nik LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

    // 3. Count Total Filtered Records
    $countSql = "
        SELECT COUNT(*) 
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        {$whereSql}
    ";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRecords / $limit) ?: 1;

    // 4. Fetch Paginated Records
    $query = "
        SELECT 
            uf.id,
            uf.user_id,
            COALESCE(e.full_name, 'Pengguna') AS user_name,
            COALESCE(u.name, d.name, 'Umum') AS user_division,
            COALESCE(e.phone_number, '') AS user_phone,
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
            uf.admin_id,
            COALESCE(adm.full_name, 'Tim IT') AS admin_name,
            uf.created_at,
            uf.updated_at
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN employees adm ON uf.admin_id = adm.id
        {$whereSql}
        ORDER BY uf.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseUrl = rtrim(BASE_URL, '/');
    $data = [];
    foreach ($rows as $row) {
        $attachmentUrl = null;
        if (!empty($row['attachment_path'])) {
            $attachmentUrl = $baseUrl . '/' . ltrim($row['attachment_path'], '/');
        }

        $data[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'user_name' => $row['user_name'],
            'user_division' => $row['user_division'],
            'user_phone' => $row['user_phone'],
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
        'summary' => $summaryData,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages
        ],
        'data' => $data
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan di server: ' . $e->getMessage()
    ]);
}
