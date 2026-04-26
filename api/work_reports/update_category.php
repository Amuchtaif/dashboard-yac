<?php
// api/work_reports/update_category.php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    $id = $data['id'] ?? $_POST['id'] ?? null;
    $name = $data['name'] ?? $_POST['name'] ?? '';

    if (!$id || empty($name)) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "ID dan Nama wajib diisi."]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE work_report_categories SET name = ? WHERE id = ?");
    if ($stmt->execute([$name, $id])) {
        ob_clean();
        echo json_encode(["success" => true, "message" => "Kategori berhasil diperbarui."]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal memperbarui kategori."]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
