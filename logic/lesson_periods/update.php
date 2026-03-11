<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $education_unit_id = $_POST['education_unit_id'];
    $period_number = $_POST['period_number'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $query_parts = [];
    foreach ($_GET as $key => $value) {
        if ($key !== 'success' && $key !== 'error') {
            $query_parts[$key] = $value;
        }
    }
    $query_string = http_build_query($query_parts);
    $redirect_base = "../../views/lesson_periods/index.php" . ($query_string ? '?' . $query_string . '&' : '?');
    $form_base = "../../views/lesson_periods/form.php" . ($query_string ? '?' . $query_string . '&' : '?');

    if (!empty($id) && !empty($education_unit_id) && !empty($period_number) && !empty($start_time) && !empty($end_time)) {
        
        $db = new Database();
        $conn = $db->getConnection();

        // Validasi duplikat jam ke- di unit yang sama (kecuali ID ini)
        $stmt_check = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND period_number = ? AND id != ?");
        $stmt_check->execute([$education_unit_id, $period_number, $id]);
        
        
        if ($stmt_check->rowCount() > 0) {
            header("Location: " . $form_base . "id=$id&error=" . urlencode("Jam ke-$period_number sudah ada untuk jenjang ini."));
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE lesson_periods SET education_unit_id = ?, period_number = ?, start_time = ?, end_time = ? WHERE id = ?");
            $stmt->execute([$education_unit_id, $period_number, $start_time, $end_time, $id]);

            header("Location: " . $redirect_base . "success=" . urlencode("Jam pelajaran berhasil diperbarui"));
        } catch (PDOException $e) {
            header("Location: " . $form_base . "id=$id&error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
        }
    } else {
        header("Location: " . $form_base . "id=$id&error=" . urlencode("Data wajib diisi semua"));
    }
}
?>
