<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");
date_default_timezone_set('Asia/Jakarta');
error_reporting(0);
ini_set('display_errors', 0);

include_once '../../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Support both JSON POST and GET for flexibility
$action = isset($data['action']) ? $data['action'] : (isset($_GET['action']) ? $_GET['action'] : '');
$user_id = isset($data['user_id']) ? $data['user_id'] : (isset($_GET['user_id']) ? $_GET['user_id'] : 0);

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit;
}

// 1. Identify Homeroom Class
$stmt_wk = $conn->prepare("
    SELECT gl.id, gl.name, eu.name as unit_name
    FROM grade_levels gl
    JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE gl.teacher_id = :uid
");
$stmt_wk->execute([':uid' => $user_id]);
$my_class = $stmt_wk->fetch(PDO::FETCH_ASSOC);

if (!$my_class) {
    echo json_encode(["success" => false, "message" => "You are not a homeroom teacher"]);
    exit;
}

$grade_id = $my_class['id'];

// 2. Routing Actions
switch ($action) {
    case 'get_class_info':
        // Get active academic year
        $active_year = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
        
        // Get students
        $stmt_students = $conn->prepare("
            SELECT s.id, s.nama_siswa, s.nomor_induk, s.foto
            FROM students s
            JOIN student_class_history sch ON s.id = sch.student_id
            WHERE sch.class_id = :grade_id AND sch.academic_year_id = :year_id AND s.status = 'Aktif'
            ORDER BY s.nama_siswa ASC
        ");
        $stmt_students->execute([':grade_id' => $grade_id, ':year_id' => $active_year]);
        $students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

        // Get existing attendance for today
        $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $stmt_att = $conn->prepare("SELECT student_id, status FROM daily_student_attendances WHERE grade_level_id = :grade_id AND date = :date");
        $stmt_att->execute([':grade_id' => $grade_id, ':date' => $date]);
        $attendance = $stmt_att->fetchAll(PDO::FETCH_KEY_PAIR); // student_id => status

        echo json_encode([
            "success" => true,
            "class" => $my_class,
            "students" => $students,
            "attendance" => (object)$attendance,
            "date" => $date
        ]);
        break;

    case 'submit_attendance':
        $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $att_list = isset($data['attendance']) ? $data['attendance'] : [];

        if (empty($att_list)) {
            echo json_encode(["success" => false, "message" => "No attendance data provided"]);
            exit;
        }

        $conn->beginTransaction();
        try {
            $count = 0;
            foreach ($att_list as $row) {
                $sid = $row['student_id'];
                $status = $row['status'];
                $notes = isset($row['notes']) ? $row['notes'] : '';

                $sql = "INSERT INTO daily_student_attendances (student_id, grade_level_id, date, status, notes, created_by)
                        VALUES (:sid, :gid, :date, :status, :notes, :uid)
                        ON DUPLICATE KEY UPDATE status = :status2, notes = :notes2, updated_at = CURRENT_TIMESTAMP";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':sid' => $sid, ':gid' => $grade_id, ':date' => $date, ':status' => $status, 
                    ':notes' => $notes, ':uid' => $user_id, ':status2' => $status, ':notes2' => $notes
                ]);
                $count++;
            }
            $conn->commit();
            echo json_encode([
                "success" => true, 
                "message" => "Attendance submitted successfully", 
                "debug" => [
                    "count" => $count,
                    "date" => $date,
                    "grade_id" => $grade_id
                ]
            ]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
        }
        break;

    case 'get_journals':
        $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $sql = "
            SELECT 
                cj.id, cj.date, lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time,
                s.name as subject_name, e.full_name as teacher_name, cj.topic, cj.notes
            FROM class_journals cj
            JOIN class_schedules cs ON cj.class_schedule_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
            LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
            JOIN employees e ON cj.teacher_id = e.id
            WHERE cs.grade_level_id = :grade_id AND cj.date = :date
            ORDER BY lp.start_time ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':grade_id' => $grade_id, ':date' => $date]);
        $journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $journals]);
        break;

    case 'get_schedule':
        // First, find the latest academic year that has schedules for this class
        $stmt_year = $conn->prepare("
            SELECT academic_year_id 
            FROM class_schedules 
            WHERE grade_level_id = :grade_id 
            ORDER BY academic_year_id DESC 
            LIMIT 1
        ");
        $stmt_year->execute([':grade_id' => $grade_id]);
        $target_year_id = $stmt_year->fetchColumn();

        if (!$target_year_id) {
            $target_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
        }

        $sql = "
            SELECT cs.*, s.name as subject_name, e.full_name as teacher_name,
                   lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time
            FROM class_schedules cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN employees e ON cs.employee_id = e.id
            LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
            LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
            WHERE cs.grade_level_id = :grade_id AND cs.academic_year_id = :year_id
            ORDER BY FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), lp.start_time ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':grade_id' => $grade_id, ':year_id' => $target_year_id]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $schedules]);
        break;

    case 'get_grades':
        $sql = "
            SELECT 
                sa.id, sa.assessment_date, at.name as assessment_type, 
                s.name as subject_name, e.full_name as teacher_name,
                (SELECT AVG(score) FROM student_assessment_details WHERE assessment_id = sa.id) as avg_score
            FROM student_assessments sa
            JOIN assessment_types at ON sa.assessment_type_id = at.id
            JOIN subjects s ON sa.subject_id = s.id
            JOIN employees e ON sa.teacher_id = e.id
            WHERE sa.grade_level_id = :grade_id
            ORDER BY sa.assessment_date DESC, sa.created_at DESC
            LIMIT 20
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':grade_id' => $grade_id]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $assessments]);
        break;

    case 'get_grade_detail':
        $assessment_id = isset($data['assessment_id']) ? $data['assessment_id'] : (isset($_GET['assessment_id']) ? $_GET['assessment_id'] : 0);
        if (!$assessment_id) {
            echo json_encode(["success" => false, "message" => "Assessment ID required"]);
            exit;
        }

        $sql = "
            SELECT sad.score, s.nama_siswa, s.nomor_induk
            FROM student_assessment_details sad
            JOIN students s ON sad.student_id = s.id
            WHERE sad.assessment_id = :aid
            ORDER BY s.nama_siswa ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':aid' => $assessment_id]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $details]);
        break;

    case 'get_subject_attendance':
        $date = isset($data['date']) ? $data['date'] : date('Y-m-d');
        $english_day = date('l', strtotime($date));
        
        $sql = "
            SELECT 
                cs.id as schedule_id,
                lp.start_time,
                COALESCE(lp_end.end_time, lp.end_time) as end_time,
                s.name as subject_name,
                e.full_name as teacher_name,
                cj.id as journal_id,
                cj.topic,
                (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as present,
                (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as absent,
                (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as sick,
                (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as permit,
                (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as late
            FROM class_schedules cs
            JOIN subjects s ON cs.subject_id = s.id
            JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
            LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
            JOIN employees e ON cs.employee_id = e.id
            LEFT JOIN class_journals cj ON cs.id = cj.class_schedule_id AND cj.date = :date
            WHERE cs.day = :day AND cs.grade_level_id = :grade_id
            ORDER BY lp.start_time ASC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':date' => $date, ':day' => $english_day, ':grade_id' => $grade_id]);
        $subject_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["success" => true, "data" => $subject_attendance]);
        break;

    case 'get_journal_attendance_detail':
        $journal_id = isset($data['journal_id']) ? $data['journal_id'] : (isset($_GET['journal_id']) ? $_GET['journal_id'] : 0);
        if (!$journal_id) {
            echo json_encode(["success" => false, "message" => "Journal ID required"]);
            exit;
        }

        try {
            // Get journal info
            $stmt_j = $conn->prepare("
                SELECT cj.*, s.name as subject_name, e.full_name as teacher_name,
                       lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time
                FROM class_journals cj
                JOIN class_schedules cs ON cj.class_schedule_id = cs.id
                JOIN subjects s ON cs.subject_id = s.id
                JOIN employees e ON cj.teacher_id = e.id
                LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
                LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
                WHERE cj.id = :jid
            ");
            $stmt_j->execute([':jid' => $journal_id]);
            $journal = $stmt_j->fetch(PDO::FETCH_ASSOC);

            if (!$journal) {
                echo json_encode(["success" => false, "message" => "Journal not found"]);
                exit;
            }

            // Get student attendance
            $stmt_att = $conn->prepare("
                SELECT sa.status, sa.note, s.nama_siswa, s.nomor_induk
                FROM student_attendances sa
                JOIN students s ON sa.student_id = s.id
                WHERE sa.class_journal_id = :jid
                ORDER BY s.nama_siswa ASC
            ");
            $stmt_att->execute([':jid' => $journal_id]);
            $attendance = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "journal" => $journal,
                "attendance" => $attendance
            ]);
        } catch (Throwable $e) {
            echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["success" => false, "message" => "Unknown action"]);
        break;
}
