<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $education_unit_id = $_POST['education_unit_id'];
    // $level = $_POST['level']; // Removed from form
    $teacher_id = !empty($_POST['teacher_id']) ? $_POST['teacher_id'] : null;
    $return_filters = isset($_POST['return_filters']) ? $_POST['return_filters'] : '';
    $redirect_qs = $return_filters ? '&' . $return_filters : '';

    if (empty($id) || empty($name) || empty($education_unit_id)) {
        redirect('views/grade_levels/edit.php?id=' . $id . '&error=' . urlencode('Semua field wajib diisi.') . $redirect_qs);
        exit;
    }

    $capacity = !empty($_POST['capacity']) ? $_POST['capacity'] : 36;

    // Do not update the level column
    $query = "UPDATE grade_levels SET name = :name, education_unit_id = :education_unit_id, teacher_id = :teacher_id, capacity = :capacity WHERE id = :id";
    $stmt = $conn->prepare($query);

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':education_unit_id', $education_unit_id);
    // $stmt->bindParam(':level', $level);
    $stmt->bindParam(':teacher_id', $teacher_id);
    $stmt->bindParam(':capacity', $capacity);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        redirect('views/grade_levels/index.php?success=' . urlencode('Kelas berhasil diperbarui.') . $redirect_qs);
    } else {
        redirect('views/grade_levels/edit.php?id=' . $id . '&error=' . urlencode('Gagal memperbarui kelas.') . $redirect_qs);
    }
}
?>
