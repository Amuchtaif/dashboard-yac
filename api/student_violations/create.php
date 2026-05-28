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
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login again']);
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
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }

    if (!$data) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    $santri_id = $data['santri_id'] ?? '';
    $kategori_id = $data['kategori_id'] ?? '';
    $deskripsi = $data['deskripsi'] ?? '';
    $tanggal_raw = $data['tanggal'] ?? '';
    $lokasi = $data['lokasi'] ?? '';
    $status = $data['status'] ?? 'dilaporkan';

    if (!$santri_id || !$deskripsi || !$tanggal_raw || !$kategori_id) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Santri, kategori, deskripsi, dan tanggal wajib diisi']);
        exit;
    }

    // Robust date parsing (handle formats like "15 May 2026")
    $timestamp = strtotime($tanggal_raw);
    if (!$timestamp) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid: ' . $tanggal_raw]);
        exit;
    }
    $tanggal = date('Y-m-d', $timestamp);

    $stmt = $conn->prepare("INSERT INTO pelanggaran (santri_id, kategori_id, deskripsi, tanggal_pelanggaran, lokasi, pelapor, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$santri_id, $kategori_id, $deskripsi, $tanggal, $lokasi, $user_id, $status]);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Pelanggaran berhasil dilaporkan',
        'id' => $conn->lastInsertId()
    ]);
} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

