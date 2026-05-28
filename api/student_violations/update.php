<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

$data = json_decode(file_get_contents('php://input'), true);

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $data['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
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

    if (!$data) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }

    $id = $data['id'] ?? '';
    $santri_id = $data['santri_id'] ?? '';
    $kategori_id = $data['kategori_id'] ?? '';
    $deskripsi = $data['deskripsi'] ?? '';
    $tanggal_raw = $data['tanggal'] ?? '';
    $lokasi = $data['lokasi'] ?? '';
    $status = $data['status'] ?? '';

    if (!$id || !$santri_id || !$deskripsi || !$tanggal_raw || !$kategori_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'ID, santri, kategori, deskripsi, dan tanggal wajib ada']);
        exit;
    }

    // Robust date parsing
    $timestamp = strtotime($tanggal_raw);
    if (!$timestamp) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid: ' . $tanggal_raw]);
        exit;
    }
    $tanggal = date('Y-m-d', $timestamp);

    // Access control
    $stmtCheck = $conn->prepare("SELECT pelapor FROM pelanggaran WHERE id = ?");
    $stmtCheck->execute([$id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Record not found']);
        exit;
    }

    $is_admin = hasPermission($user_id, 'can_access_kabid');
    if (!$is_admin && $row['pelapor'] != $user_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE pelanggaran SET santri_id=?, kategori_id=?, deskripsi=?, tanggal_pelanggaran=?, lokasi=?, status=? 
                            WHERE id = ?");
    $stmt->execute([$santri_id, $kategori_id, $deskripsi, $tanggal, $lokasi, $status, $id]);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Data pelanggaran diperbarui'
    ]);
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


