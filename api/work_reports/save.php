<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->user_id) &&
    !empty($data->report_date) &&
    !empty($data->category) &&
    !empty($data->title) &&
    !empty($data->description)
) {
    try {
        $query = "INSERT INTO work_reports 
                  (user_id, report_date, category, title, description, evidence_photo) 
                  VALUES (:user_id, :report_date, :category, :title, :description, :evidence_photo)";

        $stmt = $db->prepare($query);

        $stmt->bindParam(":user_id", $data->user_id);
        $stmt->bindParam(":report_date", $data->report_date);
        $stmt->bindParam(":category", $data->category);
        $stmt->bindParam(":title", $data->title);
        $stmt->bindParam(":description", $data->description);
        $stmt->bindParam(":evidence_photo", $data->evidence_photo);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(array("success" => true, "message" => "Laporan kerja berhasil disimpan."));
        } else {
            http_response_code(503);
            echo json_encode(array("success" => false, "message" => "Gagal menyimpan laporan."));
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Database error: " . $e->getMessage()));
    }
} else {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Data tidak lengkap."));
}
?>
