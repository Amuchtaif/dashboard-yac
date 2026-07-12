<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    header("Location: " . BASE_URL . "/views/grade_levels/index.php?error=" . urlencode("ID Kelas tidak valid"));
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Reusable Filter Query for redirects
$redirect_params = $_GET;
unset($redirect_params['id']);
$redirect_qs = http_build_query($redirect_params);
$redirect_url = BASE_URL . "/views/grade_levels/index.php" . ($redirect_qs ? "?" . $redirect_qs : "");

try {
    // Get current status
    $stmt = $conn->prepare("SELECT name, is_active FROM grade_levels WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        header("Location: " . $redirect_url . (strpos($redirect_url, '?') !== false ? '&' : '?') . "error=" . urlencode("Kelas tidak ditemukan."));
        exit;
    }
    
    $new_status = $current['is_active'] ? 0 : 1;
    $status_label = $new_status ? "diaktifkan" : "dinonaktifkan";
    
    $stmtUpdate = $conn->prepare("UPDATE grade_levels SET is_active = :status WHERE id = :id");
    $stmtUpdate->execute([':status' => $new_status, ':id' => $id]);
    
    header("Location: " . $redirect_url . (strpos($redirect_url, '?') !== false ? '&' : '?') . "success=" . urlencode("Kelas '{$current['name']}' berhasil {$status_label}."));
    exit;
    
} catch (Exception $e) {
    header("Location: " . $redirect_url . (strpos($redirect_url, '?') !== false ? '&' : '?') . "error=" . urlencode("Kesalahan: " . $e->getMessage()));
    exit;
}
