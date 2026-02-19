<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $academic_year_id = $_POST['academic_year_id'];
    $grade_level_id = $_POST['grade_level_id'];
    $employee_id = $_POST['employee_id'];
    $subject_id = $_POST['subject_id'];
    $day = $_POST['day'];
    $lesson_period_id = $_POST['lesson_period_id'];

    if (!empty($id) && !empty($academic_year_id) && !empty($grade_level_id) && !empty($employee_id) && !empty($subject_id) && !empty($day) && !empty($lesson_period_id)) {

        $db = new Database();
        $conn = $db->getConnection();

        // VALIDASI: Deteksi Konflik (Kecuali ID saat ini)
        $sql_conflict = "
            SELECT cs.id, s.name as subject_name, gl.name as class_name 
            FROM class_schedules cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN grade_levels gl ON cs.grade_level_id = gl.id
            WHERE cs.employee_id = :emp_id 
              AND cs.day = :day 
              AND cs.academic_year_id = :ay_id
              AND cs.lesson_period_id = :lp_id
              AND cs.id != :id
        ";
        $stmt_conflict = $conn->prepare($sql_conflict);
        $stmt_conflict->execute([
            ':emp_id' => $employee_id,
            ':day' => $day,
            ':ay_id' => $academic_year_id,
            ':lp_id' => $lesson_period_id,
            ':id' => $id
        ]);

        if ($stmt_conflict->rowCount() > 0) {
            $conflict = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
            $error_msg = "Konflik Jadwal: Guru sudah mengajar " . $conflict['subject_name'] . " di " . $conflict['class_name'] . " pada jam tersebut.";
            header("Location: ../../views/class_schedules/form.php?id=$id&error=" . urlencode($error_msg));
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE class_schedules SET academic_year_id = :ay, grade_level_id = :gl, employee_id = :emp, subject_id = :sub, day = :day, lesson_period_id = :lp WHERE id = :id");
            $stmt->execute([
                ':ay' => $academic_year_id,
                ':gl' => $grade_level_id,
                ':emp' => $employee_id,
                ':sub' => $subject_id,
                ':day' => $day,
                ':lp' => $lesson_period_id,
                ':id' => $id
            ]);

            header("Location: ../../views/class_schedules/index.php?success=Jadwal berhasil diperbarui");
        } catch (PDOException $e) {
            header("Location: ../../views/class_schedules/form.php?id=$id&error=Kesalahan Database: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/class_schedules/form.php?id=$id&error=Data tidak lengkap");
    }
}
?>