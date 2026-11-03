<?php
// logic/guardians/unlink_student.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id = isset($_POST['guardian_id']) ? (int)$_POST['guardian_id'] : 0;
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;

    if ($guardian_id <= 0 || $student_id <= 0) {
        $_SESSION['error'] = "Parameter pemutusan tidak valid";
        header("Location: " . BASE_URL . "/views/guardians/index.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("
        DELETE FROM guardian_students 
        WHERE guardian_id = :gid AND student_id = :sid
    ");
    $stmt->execute([
        ':gid' => $guardian_id,
        ':sid' => $student_id
    ]);

    $_SESSION['success'] = "Keterhubungan santri dengan wali murid berhasil diputus.";
    header("Location: " . BASE_URL . "/views/guardians/index.php");
    exit;
}

header("Location: " . BASE_URL . "/views/guardians/index.php");
exit;
