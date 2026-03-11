<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->name)) {
    echo json_encode(["success" => false, "message" => "Nama penilaian harus diisi"]);
    exit;
}

try {
    if (!empty($data->id)) {
        // Update
        $query = "UPDATE assessment_types SET name = :name, code = :code, weight = :weight, description = :description WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id);
    } else {
        // Create
        $query = "INSERT INTO assessment_types (name, code, weight, description) VALUES (:name, :code, :weight, :description)";
        $stmt = $db->prepare($query);
    }

    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':code', $data->code);
    $stmt->bindParam(':weight', $data->weight);
    $stmt->bindParam(':description', $data->description);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Data berhasil disimpan"]);
    } else {
        echo json_encode(["success" => false, "message" => "Gagal menyimpan data"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
