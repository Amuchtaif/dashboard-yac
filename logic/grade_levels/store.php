<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $name = trim($_POST['name']);
    $education_unit_id = $_POST['education_unit_id'];
    $level = !empty($_POST['level']) ? $_POST['level'] : '-';
    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    $return_filters = isset($_POST['return_filters']) ? $_POST['return_filters'] : '';
    $redirect_qs = $return_filters ? '&' . $return_filters : '';

    if (empty($name) || empty($education_unit_id)) {
        redirect('views/grade_levels/create.php?error=' . urlencode('Semua field wajib diisi.') . $redirect_qs);
        exit;
    }

    $capacity = !empty($_POST['capacity']) ? $_POST['capacity'] : 36;

    $query = "INSERT INTO grade_levels (name, education_unit_id, level, teacher_id, capacity) VALUES (:name, :education_unit_id, :level, :teacher_id, :capacity)";
    $stmt = $conn->prepare($query);

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':education_unit_id', $education_unit_id);
    $stmt->bindParam(':level', $level);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->bindParam(':capacity', $capacity);

    if ($stmt->execute()) {
        redirect('views/grade_levels/index.php?success=' . urlencode('Kelas berhasil ditambahkan.') . $redirect_qs);
    } else {
        redirect('views/grade_levels/create.php?error=' . urlencode('Gagal menambahkan kelas.') . $redirect_qs);
    }
}
?>
