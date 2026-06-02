<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

$json_data = json_decode(file_get_contents('php://input'), true);
$data = is_array($json_data) ? $json_data : [];

// Combine parsed JSON data and $_POST for maximum compatibility
$input = array_merge($data, $_POST);

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $input['user_id'] ?? $_GET['user_id'] ?? null;

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

    if (empty($input)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No data received']);
        exit;
    }

    $santri_id = $input['santri_id'] ?? '';
    $kategori_id = $input['kategori_id'] ?? '';
    $deskripsi = $input['deskripsi'] ?? '';
    $tanggal_raw = $input['tanggal'] ?? '';
    $lokasi = $input['lokasi'] ?? '';
    $status = $input['status'] ?? 'dilaporkan';

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

    // Penanganan Berkas Unggahan (Attachment)
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        // Format ekstensi gambar yang diperbolehkan
        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Generate nama berkas unik
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            
            // Folder tujuan di uploads/violations/ (menggunakan path absolut dinamis agar support shared hosting)
            $uploadFileDir = dirname(__DIR__, 2) . '/uploads/violations/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $attachment = $newFileName;
            } else {
                throw new Exception('Gagal memindahkan file unggahan ke folder tujuan.');
            }
        } else {
            throw new Exception('Ekstensi file tidak diizinkan. Hanya menerima: ' . implode(', ', $allowedfileExtensions));
        }
    }

    $stmt = $conn->prepare("INSERT INTO pelanggaran (santri_id, kategori_id, deskripsi, tanggal_pelanggaran, lokasi, pelapor, status, attachment) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$santri_id, $kategori_id, $deskripsi, $tanggal, $lokasi, $user_id, $status, $attachment]);

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

