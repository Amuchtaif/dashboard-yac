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

// Check if user is a violation officer
$is_officer = false;
try {
    $stmtOfficer = $conn->prepare("SELECT COUNT(*) FROM petugas_pelanggaran WHERE employee_id = ?");
    $stmtOfficer->execute([$user_id]);
    $is_officer = $stmtOfficer->fetchColumn() > 0;
    
    if (!$is_officer) {
        $stmtAdmin = $conn->prepare("SELECT p.name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
        $stmtAdmin->execute([$user_id]);
        $role = $stmtAdmin->fetchColumn();
        if ($role && strtolower($role) === 'administrator') {
            $is_officer = true;
        }
    }
} catch (Exception $e) {
    // If table doesn't exist yet, default to false or handle gracefully
}

$status = $_GET['status'] ?? $data['status'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? $data['kategori_id'] ?? '';
$santri_id = $_GET['santri_id'] ?? $data['santri_id'] ?? '';
$search = $_GET['search'] ?? $data['search'] ?? '';

$query = "SELECT p.*, s.nama_siswa, k.type_name as nama_kategori, k.points as poin, k.category, e.full_name as pelapor_name
          FROM pelanggaran p
          JOIN students s ON p.santri_id = s.id
          JOIN boarding_violation_types k ON p.kategori_id = k.id
          JOIN employees e ON p.pelapor = e.id
          WHERE 1=1";

$params = [];

// If not an officer, only show violations reported by this user
if (!$is_officer) {
    $query .= " AND p.pelapor = ?";
    $params[] = $user_id;
}

if ($status) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($kategori_id) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $kategori_id;
}

if ($santri_id) {
    $query .= " AND p.santri_id = ?";
    $params[] = $santri_id;
}

if ($search) {
    $query .= " AND (s.nama_siswa LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.tanggal_pelanggaran DESC, p.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tambahkan URL lengkap lampiran berkas untuk Flutter
    foreach ($violations as &$v) {
        $v['attachment_url'] = !empty($v['attachment']) ? BASE_URL . '/uploads/violations/' . $v['attachment'] : null;
    }

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Data pelanggaran berhasil dimuat',
        'data' => $violations
    ]);
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

