<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission($user_id, 'can_access_kesantrian')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Check if user is a designated officer
$stmtOfficer = $conn->prepare("SELECT COUNT(*) FROM petugas_pelanggaran WHERE employee_id = ?");
$stmtOfficer->execute([$user_id]);
$is_officer = $stmtOfficer->fetchColumn() > 0;

// Also check if is Administrator (level 1 or similar)
$stmtAdmin = $conn->prepare("SELECT p.name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
$stmtAdmin->execute([$user_id]);
$role_name = $stmtAdmin->fetchColumn();
if ($role_name === 'Administrator') $is_officer = true;

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID needed']);
    exit;
}

try {
    // 1. Get Violation Info
    $stmt = $conn->prepare("SELECT p.*, s.nama_siswa, k.nama_kategori, k.poin, e.full_name as pelapor_name
                            FROM pelanggaran p
                            JOIN students s ON p.santri_id = s.id
                            JOIN kategori_pelanggaran k ON p.kategori_id = k.id
                            JOIN employees e ON p.pelapor = e.id
                            WHERE p.id = ?");
    $stmt->execute([$id]);
    $violation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$violation) {
        echo json_encode(['success' => false, 'message' => 'Pelanggaran tidak ditemukan']);
        exit;
    }

    // 2. Get Follow-ups
    $stmtFollowup = $conn->prepare("SELECT tl.*, e.full_name as penindak_name
                                   FROM tindak_lanjut tl
                                   JOIN employees e ON tl.penindak = e.id
                                   WHERE tl.pelanggaran_id = ?
                                   ORDER BY tl.tanggal_tindakan DESC, tl.created_at DESC");
    $stmtFollowup->execute([$id]);
    $followups = $stmtFollowup->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Detail pelanggaran berhasil dimuat',
        'data' => [
            'violation' => $violation,
            'followups' => $followups,
            'is_officer' => (bool)$is_officer
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
