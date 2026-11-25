<?php
// api/feedback/submit.php
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metode ' . $_SERVER['REQUEST_METHOD'] . ' tidak diizinkan. Gunakan POST.'
    ]);
    exit();
}

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Support both multipart/form-data and JSON input
    $raw_input = file_get_contents("php://input");
    $json_data = json_decode($raw_input, true) ?: [];

    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : (int)($json_data['user_id'] ?? 0);
    $type = trim($_POST['type'] ?? ($json_data['type'] ?? ''));
    $module = trim($_POST['module'] ?? ($json_data['module'] ?? 'Umum'));
    $priority = trim($_POST['priority'] ?? ($json_data['priority'] ?? 'medium'));
    $title = trim($_POST['title'] ?? ($json_data['title'] ?? ''));
    $description = trim($_POST['description'] ?? ($json_data['description'] ?? ''));
    $app_version = trim($_POST['app_version'] ?? ($json_data['app_version'] ?? ''));
    $device_info = trim($_POST['device_info'] ?? ($json_data['device_info'] ?? ''));

    // Validation
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter user_id wajib diisi']);
        exit();
    }

    // Verify employee exists
    $checkUser = $db->prepare("SELECT id FROM employees WHERE id = :user_id LIMIT 1");
    $checkUser->execute([':user_id' => $user_id]);
    if (!$checkUser->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User/Pegawai tidak ditemukan']);
        exit();
    }

    $valid_types = ['feature', 'improvement', 'bug', 'general'];
    if (!in_array($type, $valid_types, true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tipe usulan tidak valid. Pilihan: ' . implode(', ', $valid_types)
        ]);
        exit();
    }

    $valid_priorities = ['low', 'medium', 'high'];
    if (!in_array($priority, $valid_priorities, true)) {
        $priority = 'medium';
    }

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Judul usulan / kendala wajib diisi']);
        exit();
    }

    if (mb_strlen($title) > 255) {
        $title = mb_substr($title, 0, 255);
    }

    if (empty($description)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Penjelasan usulan / kendala wajib diisi']);
        exit();
    }

    if (empty($module)) {
        $module = 'Umum';
    }

    // Handle File Attachment (Optional)
    $attachment_path = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal mengunggah lampiran gambar (Kode Error: ' . $_FILES['attachment']['error'] . ')'
            ]);
            exit();
        }

        // Check file size (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($_FILES['attachment']['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ukuran lampiran melebihi batas maksimal 5MB'
            ]);
            exit();
        }

        $tmpName = $_FILES['attachment']['tmp_name'];
        $origName = $_FILES['attachment']['name'];
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowedExts, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format file tidak didukung. Harap unggah foto dengan format: .jpg, .jpeg, .png, atau .webp'
            ]);
            exit();
        }

        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'MIME type file tidak valid (' . htmlspecialchars($mime) . '). Hanya gambar yang diizinkan.'
            ]);
            exit();
        }

        $targetDir = __DIR__ . '/../../uploads/feedbacks/';
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $safeRand = bin2hex(random_bytes(4));
        $newFilename = 'fb_' . $user_id . '_' . time() . '_' . $safeRand . '.' . $ext;
        $destination = $targetDir . $newFilename;

        if (!move_uploaded_file($tmpName, $destination)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal menyimpan lampiran di server'
            ]);
            exit();
        }

        $attachment_path = 'uploads/feedbacks/' . $newFilename;
    }

    // Insert feedback
    $stmt = $db->prepare("
        INSERT INTO user_feedbacks 
        (user_id, type, module, priority, title, description, attachment_path, app_version, device_info, status, created_at)
        VALUES 
        (:user_id, :type, :module, :priority, :title, :description, :attachment_path, :app_version, :device_info, 'pending', NOW())
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':type' => $type,
        ':module' => $module,
        ':priority' => $priority,
        ':title' => $title,
        ':description' => $description,
        ':attachment_path' => $attachment_path,
        ':app_version' => $app_version ?: null,
        ':device_info' => $device_info ?: null,
    ]);

    $feedback_id = (int)$db->lastInsertId();

    // Fetch created timestamp
    $stmtCreated = $db->prepare("SELECT created_at FROM user_feedbacks WHERE id = :id");
    $stmtCreated->execute([':id' => $feedback_id]);
    $created_at = $stmtCreated->fetchColumn() ?: date('Y-m-d H:i:s');

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Usulan berhasil dikirim ke Tim IT & Manajemen',
        'data' => [
            'id' => $feedback_id,
            'user_id' => $user_id,
            'title' => $title,
            'status' => 'pending',
            'created_at' => $created_at
        ]
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan di server: ' . $e->getMessage()
    ]);
}
