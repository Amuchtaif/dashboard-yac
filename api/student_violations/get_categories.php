<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

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

    // Fetch from boarding_violation_types as requested
    // Mapping type_name to nama_kategori and points to poin for frontend compatibility
    $stmt = $conn->query("SELECT id, type_name as nama_kategori, points as poin, category FROM boarding_violation_types ORDER BY category ASC, type_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Data jenis pelanggaran berhasil dimuat',
        'data' => $categories
    ]);
} catch (Throwable $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
