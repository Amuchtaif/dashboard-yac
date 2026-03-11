<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    try {
        // 1. Get header data
        $query = "
            SELECT sa.*, s.name as subject_name, gl.name as class_name, at.name as assessment_type_name
            FROM student_assessments sa
            JOIN subjects s ON sa.subject_id = s.id
            JOIN grade_levels gl ON sa.grade_level_id = gl.id
            JOIN assessment_types at ON sa.assessment_type_id = at.id
            WHERE sa.id = :id
        ";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $header = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($header) {
            // 2. Get student details
            $query_details = "
                SELECT sad.*, st.nama_siswa, st.nomor_induk
                FROM student_assessment_details sad
                JOIN students st ON sad.student_id = st.id
                WHERE sad.assessment_id = :assessment_id
                ORDER BY st.nama_siswa ASC
            ";
            $stmt_details = $db->prepare($query_details);
            $stmt_details->bindParam(':assessment_id', $id);
            $stmt_details->execute();
            $header['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(["success" => true, "data" => $header]);
        } else {
            http_response_code(404);
            echo json_encode(["success" => false, "message" => "Data tidak ditemukan"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "ID tidak valid"]);
}
?>
