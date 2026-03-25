<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../helpers/class_placement_helper.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $academic_year_id = $_POST['academic_year_id'];
    $class_id = $_POST['class_id'];
    $student_ids = isset($_POST['student_ids']) ? $_POST['student_ids'] : [];

    if (empty($academic_year_id) || empty($class_id) || empty($student_ids)) {
        header("Location: ../../views/placements/index.php?error=Missing+required+data%26academic_year_id%3D%24academic_year_id");
        exit;
    }

    // Call the Helper
    $result = assignStudentsToClass($class_id, $academic_year_id, $student_ids);

    if ($result['success']) {
        header("Location: ../../views/placements/index.php?success=" . urlencode($result['message']) . "&academic_year_id=$academic_year_id");
    } else {
        header("Location: ../../views/placements/index.php?error=" . urlencode($result['message']) . "&academic_year_id=$academic_year_id");
    }
} else {
        header("Location: ../../views/placements/index.php?error=Operasi+gagal");
}
?>
