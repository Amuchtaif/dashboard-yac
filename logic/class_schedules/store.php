<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $academic_year_id = $_POST['academic_year_id'];
    $grade_level_id = $_POST['grade_level_id'];
    $employee_id = $_POST['employee_id'];
    $subject_id = $_POST['subject_id'];
    $day = $_POST['day'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    if (!empty($academic_year_id) && !empty($grade_level_id) && !empty($employee_id) && !empty($subject_id) && !empty($day) && !empty($start_time) && !empty($end_time)) {

        $db = new Database();
        $conn = $db->getConnection();

        // VALIDASI: Deteksi Konflik (Guru tidak boleh mengajar dua kelas berbeda di waktu yang sama)
        // Logika: Cari jadwal guru pada hari yang sama dimana waktu bertabrakan
        // Tabrakan terjadi jika: (start_baru < end_lama) DAN (end_baru > start_lama)
        $sql_conflict = "
            SELECT cs.id, s.name as subject_name, gl.name as class_name 
            FROM class_schedules cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN grade_levels gl ON cs.grade_level_id = gl.id
            WHERE cs.employee_id = :emp_id 
              AND cs.day = :day 
              AND cs.academic_year_id = :ay_id
              AND (:start < cs.end_time AND :end > cs.start_time)
        ";
        $stmt_conflict = $conn->prepare($sql_conflict);
        $stmt_conflict->execute([
            ':emp_id' => $employee_id,
            ':day' => $day,
            ':ay_id' => $academic_year_id,
            ':start' => $start_time,
            ':end' => $end_time
        ]);

        if ($stmt_conflict->rowCount() > 0) {
            $conflict = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
            $error_msg = "Konflik Jadwal: Guru tersebut sudah memiliki jadwal mengajar mapel " . $conflict['subject_name'] . " di kelas " . $conflict['class_name'] . " pada waktu yang sama.";
            header("Location: ../../views/class_schedules/form.php?error=" . urlencode($error_msg));
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO class_schedules (academic_year_id, grade_level_id, employee_id, subject_id, day, start_time, end_time) VALUES (:ay, :gl, :emp, :sub, :day, :start, :end)");
            $stmt->execute([
                ':ay' => $academic_year_id,
                ':gl' => $grade_level_id,
                ':emp' => $employee_id,
                ':sub' => $subject_id,
                ':day' => $day,
                ':start' => $start_time,
                ':end' => $end_time
            ]);

            header("Location: ../../views/class_schedules/index.php?success=Jadwal berhasil ditambahkan");
        } catch (PDOException $e) {
            header("Location: ../../views/class_schedules/form.php?error=Kesalahan Database: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/class_schedules/form.php?error=Data wajib diisi semua");
    }
}
?>