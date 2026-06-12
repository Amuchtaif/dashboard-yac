<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit(0);
}

require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

try {
    if (!$user_id) {
        throw new Exception("Unauthorized");
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Basic authentication check: ensure user is a valid employee
    $stmtCheck = $conn->prepare("SELECT id FROM employees WHERE id = ?");
    $stmtCheck->execute([$user_id]);
    if (!$stmtCheck->fetch()) {
        throw new Exception("Invalid user");
    }

    // Get all active students without unit restrictions
    // Using flexible status filter to handle case variations on hosting
    $stmt = $conn->prepare("SELECT id, nama_siswa, kelas FROM students 
                           WHERE status LIKE 'Aktif%' OR status = 'Aktif' OR LOWER(status) = 'aktif'
                           ORDER BY nama_siswa ASC");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Data santri berhasil dimuat',
        'count' => count($students),
        'data' => $students
    ]);
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
