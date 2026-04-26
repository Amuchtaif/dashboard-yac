<?php
// api/work_reports/delete_category.php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    $id = $data['id'] ?? $_POST['id'] ?? null;

    if (!$id) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "ID kategori wajib diisi."]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM work_report_categories WHERE id = ?");
    if ($stmt->execute([$id])) {
        ob_clean();
        echo json_encode(["success" => true, "message" => "Kategori berhasil dihapus."]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal menghapus kategori."]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
