<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$employee_id = $data['employee_id'] ?? null;

if (!$id) {
    echo json_encode(["success" => false, "message" => "RPP ID is required"]);
    exit;
}

try {
    // If employee_id is provided, use it for security check
    if ($employee_id) {
        $sql = "DELETE FROM rpp WHERE id = ? AND employee_id = ?";
        $stmt = $db->prepare($sql);
        $res = $stmt->execute([$id, $employee_id]);
    } else {
        $sql = "DELETE FROM rpp WHERE id = ?";
        $stmt = $db->prepare($sql);
        $res = $stmt->execute([$id]);
    }

    if ($res && $stmt->rowCount() > 0) {
        echo json_encode([
            "success" => true,
            "message" => "RPP berhasil dihapus"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Gagal menghapus RPP atau RPP tidak ditemukan"
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false, 
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
