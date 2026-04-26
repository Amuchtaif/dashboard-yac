<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || empty($data['id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID tidak ditemukan"]);
    exit;
}

$id = $data['id'];

try {
    $db->beginTransaction();
    
    // Delete details first
    $del_details = "DELETE FROM student_assessment_details WHERE assessment_id = :id";
    $stmt_details = $db->prepare($del_details);
    $stmt_details->bindParam(':id', $id);
    $stmt_details->execute();
    
    // Delete header
    $del_header = "DELETE FROM student_assessments WHERE id = :id";
    $stmt_header = $db->prepare($del_header);
    $stmt_header->bindParam(':id', $id);
    $stmt_header->execute();
    
    $db->commit();
    echo json_encode(["success" => true, "message" => "Data penilaian berhasil dihapus"]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
}
?>
