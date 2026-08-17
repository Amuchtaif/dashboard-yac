<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = null;
$report_date = null;
$category = null;
$title = null;
$description = null;
$evidence_photo = '';

// Check if request is multipart/form-data ($_POST) or JSON
if (!empty($_POST['user_id'])) {
    $user_id = $_POST['user_id'] ?? null;
    $report_date = $_POST['report_date'] ?? null;
    $category = $_POST['category'] ?? null;
    $title = $_POST['title'] ?? null;
    $description = $_POST['description'] ?? null;
    $evidence_photo = $_POST['evidence_photo'] ?? '';
} else {
    $data = json_decode(file_get_contents("php://input"));
    if ($data) {
        $user_id = $data->user_id ?? null;
        $report_date = $data->report_date ?? null;
        $category = $data->category ?? null;
        $title = $data->title ?? null;
        $description = $data->description ?? null;
        $evidence_photo = $data->evidence_photo ?? '';
    }
}

// Handle file upload if present in $_FILES
if (isset($_FILES['evidence_photo']) && $_FILES['evidence_photo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['evidence_photo']['tmp_name'];
    $fileName = $_FILES['evidence_photo']['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($fileExtension, $allowedExtensions)) {
        $uploadDir = '../../uploads/work_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $newFileName = 'wr_' . time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            $evidence_photo = $newFileName;
        }
    }
}

if (!empty($user_id) && !empty($report_date) && !empty($category) && !empty($title) && !empty($description)) {
    try {
        $query = "INSERT INTO work_reports 
                  (user_id, report_date, category, title, description, evidence_photo) 
                  VALUES (:user_id, :report_date, :category, :title, :description, :evidence_photo)";

        $stmt = $db->prepare($query);

        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":report_date", $report_date);
        $stmt->bindParam(":category", $category);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":evidence_photo", $evidence_photo);

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
