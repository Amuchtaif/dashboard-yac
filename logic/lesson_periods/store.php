<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $education_unit_id = $_POST['education_unit_id'];
    $period_number = $_POST['period_number'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    if (!empty($education_unit_id) && !empty($period_number) && !empty($start_time) && !empty($end_time)) {
        
        $db = new Database();
        $conn = $db->getConnection();

        // Validasi duplikat jam ke- di unit yang sama
        $stmt_check = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND period_number = ?");
        $stmt_check->execute([$education_unit_id, $period_number]);
        
        if ($stmt_check->rowCount() > 0) {
            header("Location: ../../views/lesson_periods/form.php?error=Jam ke-$period_number sudah ada untuk jenjang ini.");
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO lesson_periods (education_unit_id, period_number, start_time, end_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$education_unit_id, $period_number, $start_time, $end_time]);

            header("Location: ../../views/lesson_periods/index.php?success=Jam pelajaran berhasil ditambahkan");
        } catch (PDOException $e) {
            header("Location: ../../views/lesson_periods/form.php?error=Kesalahan Database: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/lesson_periods/form.php?error=Data wajib diisi semua");
    }
}
?>
