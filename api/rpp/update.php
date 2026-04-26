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
$academic_year_id = $data['academic_year_id'] ?? null;
$semester = $data['semester'] ?? null;
$education_unit_id = $data['education_unit_id'] ?? null;
$grade_level_id = $data['grade_level_id'] ?? null;
$subject_id = $data['subject_id'] ?? null;
$session_no = $data['session_no'] ?? null;
$allocation = $data['allocation'] ?? null;
$title = $data['title'] ?? '';

// Core content
$content_cp = $data['content_cp'] ?? '';
$content_atp = $data['content_atp'] ?? '';
$content_pertanyaan_pemantik = $data['content_pertanyaan_pemantik'] ?? '';
$learning_goal = $data['learning_goal'] ?? '';
$teaching_material = $data['teaching_material'] ?? '';
$teaching_profil_pancasila = $data['teaching_profil_pancasila'] ?? '';
$content_steps = $data['content_steps'] ?? '';
$content_summary = $data['content_summary'] ?? '';
$assessment = $data['assessment'] ?? '';

$is_draft = $data['is_draft'] ?? 0;

if (!$id || !$employee_id || !$academic_year_id || !$semester || !$grade_level_id || !$subject_id || !$title) {
    echo json_encode(["success" => false, "message" => "Missing required fields (ID, Title, Grade, Subject, etc)"]);
    exit;
}

try {
    $sql = "UPDATE rpp SET
                academic_year_id = ?, 
                semester = ?, 
                education_unit_id = ?,
                grade_level_id = ?, 
                subject_id = ?, 
                session_no = ?,
                allocation = ?,
                title = ?, 
                content_cp = ?, 
                content_atp = ?, 
                content_pertanyaan_pemantik = ?, 
                learning_goal = ?,
                teaching_material = ?,
                teaching_profil_pancasila = ?,
                content_steps = ?, 
                content_summary = ?, 
                assessment = ?,
                is_draft = ?
            WHERE id = ? AND employee_id = ?";
    
    $stmt = $db->prepare($sql);
    $res = $stmt->execute([
        $academic_year_id, 
        $semester, 
        $education_unit_id,
        $grade_level_id, 
        $subject_id, 
        $session_no,
        $allocation,
        $title, 
        $content_cp, 
        $content_atp, 
        $content_pertanyaan_pemantik, 
        $learning_goal,
        $teaching_material,
        $teaching_profil_pancasila,
        $content_steps, 
        $content_summary, 
        $assessment,
        $is_draft,
        $id,
        $employee_id
    ]);

    if ($res) {
        $message = $is_draft ? "Draft RPP berhasil diperbarui" : "RPP berhasil diterbitkan";
        echo json_encode([
            "success" => true,
            "message" => $message
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to update RPP"]);
    }
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
