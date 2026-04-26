<?php
// api/work_reports/create_category.php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    $name = $data['name'] ?? $_POST['name'] ?? '';

    if (empty($name)) {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Nama kategori wajib diisi."]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO work_report_categories (name) VALUES (?)");
    if ($stmt->execute([$name])) {
        ob_clean();
        echo json_encode(["success" => true, "message" => "Kategori berhasil ditambahkan.", "id" => $conn->lastInsertId()]);
    } else {
        ob_clean();
        echo json_encode(["success" => false, "message" => "Gagal menyimpan kategori."]);
    }
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
