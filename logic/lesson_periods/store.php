<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    if (!empty($education_unit_id) && !empty($period_number) && !empty($start_time) && !empty($end_time)) {
        
        $db = new Database();
        $conn = $db->getConnection();

        // Validasi duplikat jam ke- di unit yang sama
        $stmt_check = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND period_number = ?");
        $stmt_check->execute([$education_unit_id, $period_number]);
        
        
        if ($stmt_check->rowCount() > 0) {
            header("Location: " . $form_base . "error=" . urlencode("Jam ke-$period_number sudah ada untuk jenjang ini."));
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO lesson_periods (education_unit_id, period_number, start_time, end_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$education_unit_id, $period_number, $start_time, $end_time]);
            $new_period_id = $conn->lastInsertId();

            $u_stmt = $conn->prepare("SELECT name FROM education_units WHERE id = ? LIMIT 1");
            $u_stmt->execute([$education_unit_id]);
            $unit_name = $u_stmt->fetchColumn() ?: "ID $education_unit_id";

            Logger::activity(
                'Jam Pelajaran',
                'CREATE',
                "Menambahkan Jam Ke-$period_number ($start_time - $end_time) pada unit '$unit_name'",
                [
                    'table' => 'lesson_periods',
                    'record_id' => $new_period_id,
                    'new_data' => [
                        'education_unit' => $unit_name,
                        'period_number' => $period_number,
                        'start_time' => $start_time,
                        'end_time' => $end_time
                    ]
                ]
            );

            header("Location: " . $redirect_base . "success=" . urlencode("Jam pelajaran berhasil ditambahkan"));
        } catch (PDOException $e) {
            header("Location: " . $form_base . "error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
        }
    } else {
        header("Location: " . $form_base . "error=" . urlencode("Data wajib diisi semua"));
    }
}
?>
