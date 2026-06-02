<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

$data = json_decode(file_get_contents('php://input'), true);

// Auth check: support session, JSON body, and request parameters
$user_id = $_SESSION['user_id'] ?? $data['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Basic authentication check: ensure user is a valid employee
$stmtCheck = $conn->prepare("SELECT id FROM employees WHERE id = ?");
$stmtCheck->execute([$user_id]);
if (!$stmtCheck->fetch()) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid user']);
    exit;
}

// Check officer status
$is_officer = false;
try {
    $stmtOfficer = $conn->prepare("SELECT COUNT(*) FROM petugas_pelanggaran WHERE employee_id = ?");
    $stmtOfficer->execute([$user_id]);
    $is_officer = $stmtOfficer->fetchColumn() > 0;

    if (!$is_officer) {
        $stmtAdmin = $conn->prepare("SELECT p.name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
        $stmtAdmin->execute([$user_id]);
        $role_name = $stmtAdmin->fetchColumn();
        if ($role_name && strtolower($role_name) === 'administrator') {
            $is_officer = true;
        }
    }
} catch (Exception $e) {
    // Handle gracefully if table missing
}

$id = $_GET['id'] ?? $data['id'] ?? '';

if (!$id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID needed']);
    exit;
}

try {
    // 1. Get Violation Info - JOIN with boarding_violation_types, include category
    $stmt = $conn->prepare("SELECT p.*, s.nama_siswa, k.type_name as nama_kategori, k.points as poin, k.category, e.full_name as pelapor_name
                            FROM pelanggaran p
                            JOIN students s ON p.santri_id = s.id
                            JOIN boarding_violation_types k ON p.kategori_id = k.id
                            JOIN employees e ON p.pelapor = e.id
                            WHERE p.id = ?");
    $stmt->execute([$id]);
    $violation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$violation) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Pelanggaran tidak ditemukan']);
        exit;
    }

    // Tambahkan URL lengkap lampiran berkas untuk Flutter
    $violation['attachment_url'] = !empty($violation['attachment']) ? BASE_URL . '/uploads/violations/' . $violation['attachment'] : null;

    // 2. Get Follow-ups
    $stmtFollowup = $conn->prepare("SELECT tl.*, e.full_name as penindak_name
                                   FROM tindak_lanjut tl
                                   JOIN employees e ON tl.penindak = e.id
                                   WHERE tl.pelanggaran_id = ?
                                   ORDER BY tl.tanggal_tindakan DESC, tl.created_at DESC");
    $stmtFollowup->execute([$id]);
    $followups = $stmtFollowup->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    header('Content-Type: application/json');
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
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

