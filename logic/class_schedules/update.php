<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_params = isset($_POST['redirect_params']) ? $_POST['redirect_params'] : '';
    $redirect_qs = $redirect_params ? "&" . $redirect_params : "";

    $id = $_POST['id'];
    $academic_year_id = $_POST['academic_year_id'];
    $grade_level_id = $_POST['grade_level_id'];
    $employee_id = $_POST['employee_id'];
    $subject_id = $_POST['subject_id'];
    $day = $_POST['day'];
    $lesson_period_ids = isset($_POST['lesson_period_ids']) ? $_POST['lesson_period_ids'] : [];

    if (!empty($id) && !empty($academic_year_id) && !empty($grade_level_id) && !empty($employee_id) && !empty($subject_id) && !empty($day) && !empty($lesson_period_ids) && is_array($lesson_period_ids)) {

        $db = new Database();
        $conn = $db->getConnection();
        
        $redirect_url = "../../views/class_schedules/index.php" . ($redirect_params ? "?$redirect_params" : "");
        $error_redirect_url = "../../views/class_schedules/form.php?id=$id" . ($redirect_params ? "&$redirect_params" : "");

        $conn->beginTransaction();

        try {
            // Get period details to find min and max period numbers
            $in_slots = implode(',', array_fill(0, count($lesson_period_ids), '?'));
            $lp_stmt = $conn->prepare("SELECT id, period_number FROM lesson_periods WHERE id IN ($in_slots) ORDER BY period_number ASC");
            $lp_stmt->execute($lesson_period_ids);
            $selected_periods = $lp_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($selected_periods) == 0) {
                throw new Exception("Jam pelajaran tidak valid.");
            }

            $start_lp_id = $selected_periods[0]['id'];
            $end_lp_id = $selected_periods[count($selected_periods) - 1]['id'];
            $start_p_num = $selected_periods[0]['period_number'];
            $end_p_num = $selected_periods[count($selected_periods) - 1]['period_number'];

            // Get education unit of the selected grade level to ensure we compare periods within the same unit
            $unit_stmt = $conn->prepare("SELECT education_unit_id FROM grade_levels WHERE id = ?");
            $unit_stmt->execute([$grade_level_id]);
            $unit_id = $unit_stmt->fetchColumn();

            // Check for conflicts ignoring current id
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
            ";
            $stmt_conflict = $conn->prepare($sql_conflict);
            $stmt_conflict->execute([
                ':emp_id' => $employee_id,
                ':day' => $day,
                ':ay_id' => $academic_year_id,
                ':unit_id' => $unit_id,
                ':start_num' => $start_p_num,
                ':end_num' => $end_p_num,
                ':id' => $id
            ]);

            if ($stmt_conflict->rowCount() > 0) {
                $conflict = $stmt_conflict->fetch(PDO::FETCH_ASSOC);
                throw new Exception("Konflik Jadwal: Guru sudah mengajar " . $conflict['subject_name'] . " di " . $conflict['class_name'] . " pada range jam waktu tersebut.");
            }

            // Calculate day_of_week (1=Monday ... 7=Sunday)
            $day_map = [
                'Monday' => 1,
                'Tuesday' => 2,
                'Wednesday' => 3,
                'Thursday' => 4,
                'Friday' => 5,
                'Saturday' => 6,
                'Sunday' => 7
            ];
            $day_of_week = $day_map[$day] ?? 0;

            // Update original record with start and end
            $stmt = $conn->prepare("UPDATE class_schedules SET academic_year_id = :ay, grade_level_id = :gl, employee_id = :emp, subject_id = :sub, day = :day, day_of_week = :dow, lesson_period_id = :lp, end_lesson_period_id = :elp WHERE id = :id");
            $stmt->execute([
                ':ay' => $academic_year_id,
                ':gl' => $grade_level_id,
                ':emp' => $employee_id,
                ':sub' => $subject_id,
                ':day' => $day,
                ':dow' => $day_of_week,
                ':lp' => $start_lp_id,
                ':elp' => ($start_lp_id != $end_lp_id) ? $end_lp_id : null,
                ':id' => $id
            ]);

            $conn->commit();
            $final_redirect = $redirect_url . ($redirect_params ? "&" : "?") . "success=" . urlencode("Jadwal berhasil diperbarui");
            header("Location: " . $final_redirect);
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            header("Location: " . $error_redirect_url . "&error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: ../../views/class_schedules/index.php?error=" . urlencode("Data wajib diisi semua, minimal pilih 1 jam pelajaran.") . $redirect_qs);
    }
}
?>