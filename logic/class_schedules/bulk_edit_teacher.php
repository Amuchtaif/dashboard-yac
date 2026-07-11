<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('manage_academic');

$query_parts = [];
foreach ($_GET as $key => $value) {
    if ($key !== 'success' && $key !== 'error') {
        $query_parts[$key] = $value;
    }
}
$query_string = http_build_query($query_parts);
$redirect_url = "../../views/class_schedules/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids']) && !empty($_POST['teacher_id'])) {
    $ids = $_POST['ids'];
    $new_teacher_id = $_POST['teacher_id'];
    
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $conn->beginTransaction();

        // 1. Fetch new teacher details to verify they are active and exist
        $stmt_t = $conn->prepare("SELECT full_name FROM employees WHERE id = ? AND status = 'active'");
        $stmt_t->execute([$new_teacher_id]);
        $teacher_name = $stmt_t->fetchColumn();
        if (!$teacher_name) {
            throw new Exception("Guru yang dipilih tidak aktif atau tidak ditemukan.");
        }

        // 2. Process each schedule in the batch
        foreach ($ids as $id) {
            // Get current schedule details
            $stmt_sch = $conn->prepare("
                SELECT cs.academic_year_id, cs.grade_level_id, cs.day, cs.lesson_period_id, cs.end_lesson_period_id,
                       gl.education_unit_id,
                       lp_start.period_number as start_num,
                       COALESCE(lp_end.period_number, lp_start.period_number) as end_num
                FROM class_schedules cs
                JOIN grade_levels gl ON cs.grade_level_id = gl.id
                JOIN lesson_periods lp_start ON cs.lesson_period_id = lp_start.id
                LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
                WHERE cs.id = ?
            ");
            $stmt_sch->execute([$id]);
            $sch = $stmt_sch->fetch(PDO::FETCH_ASSOC);

            if (!$sch) {
                continue; // Skip if schedule does not exist
            }

            // Check for conflict for this schedule with the new teacher
            $sql_conflict = "
                SELECT cs.id, s.name as subject_name, gl.name as class_name 
                FROM class_schedules cs
                JOIN subjects s ON cs.subject_id = s.id
                JOIN grade_levels gl ON cs.grade_level_id = gl.id
                JOIN lesson_periods lp_start ON cs.lesson_period_id = lp_start.id
                LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
                WHERE cs.employee_id = :emp_id 
                  AND cs.day = :day 
                  AND cs.academic_year_id = :ay_id
                  AND lp_start.education_unit_id = :unit_id
                  AND cs.id != :id
                  AND (
                      GREATEST(:start_num, lp_start.period_number) <= LEAST(:end_num, COALESCE(lp_end.period_number, lp_start.period_number))
                  )
                LIMIT 1
            ";
            $stmt_conflict = $conn->prepare($sql_conflict);
            $stmt_conflict->execute([
                ':emp_id' => $new_teacher_id,
                ':day' => $sch['day'],
                ':ay_id' => $sch['academic_year_id'],
                ':unit_id' => $sch['education_unit_id'],
                ':start_num' => $sch['start_num'],
                ':end_num' => $sch['end_num'],
                ':id' => $id
            ]);

            $conflict = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
            if ($conflict) {
                throw new Exception("Konflik Jadwal: Guru '$teacher_name' sudah memiliki jadwal mengajar '" . $conflict['subject_name'] . "' di kelas '" . $conflict['class_name'] . "' pada hari dan jam yang tumpang tindih.");
            }

            // Update teacher for this schedule
            $stmt_upd = $conn->prepare("UPDATE class_schedules SET employee_id = ? WHERE id = ?");
            $stmt_upd->execute([$new_teacher_id, $id]);
        }

        $conn->commit();
        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil mengubah guru untuk " . count($ids) . " jadwal"));
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header("Location: " . $redirect_url . "error=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("Data tidak lengkap untuk mengubah guru"));
    exit;
}
?>
