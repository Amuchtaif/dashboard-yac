<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    $unit = $_GET['unit'] ?? null;
    $class_id = $_GET['class_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    if ($class_id) {
        // Get active academic year
        $active_year_id = $db->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();

        // Get subjects for this class on the target date's day of week and their attendance status
        $timestamp = strtotime($date);
        $dayNum = date('N', $timestamp); // 1 (Mon) - 7 (Sun)
        $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
        $day_name = $days[$dayNum];

        $query = "SELECT 
                    cs.id as schedule_id,
                    s.name as subject_name,
                    e.full_name as teacher_name,
                    lp.start_time,
                    COALESCE(lp_end.end_time, lp.end_time) as end_time,
                    (SELECT COUNT(*) FROM class_journals cj 
                     WHERE cj.class_schedule_id = cs.id AND cj.date = :target_date) as is_attended
                  FROM class_schedules cs
                  JOIN subjects s ON cs.subject_id = s.id
                  JOIN employees e ON cs.employee_id = e.id
                  LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
                  LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
                  WHERE cs.grade_level_id = :class_id 
                    AND cs.day = :day_name 
                    AND cs.academic_year_id = :active_year_id
                  ORDER BY lp.start_time ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':day_name', $day_name);
        $stmt->bindParam(':active_year_id', $active_year_id);
        $stmt->bindParam(':target_date', $date);
        $stmt->execute();
        
        $subjects = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $subjects[] = [
                "schedule_id" => (int)$row['schedule_id'],
                "subject_name" => $row['subject_name'],
                "teacher_name" => $row['teacher_name'],
                "start_time" => $row['start_time'],
                "end_time" => $row['end_time'],
                "is_attended" => (int)$row['is_attended'] > 0
            ];
        }
        
        echo json_encode(["success" => true, "data" => $subjects]);
    } else if ($unit) {
        // Get classes for this unit and their attendance status on the specified date
        $query = "SELECT 
                    gl.id, 
                    gl.name as class_name, 
                    (SELECT COUNT(*) FROM class_journals cj 
                     JOIN class_schedules cs ON cj.class_schedule_id = cs.id
                     WHERE cs.grade_level_id = gl.id AND cj.date = :target_date) as attendance_count
                  FROM grade_levels gl
                  WHERE gl.category = :unit
                  ORDER BY gl.name ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':unit', $unit);
        $stmt->bindParam(':target_date', $date);
        $stmt->execute();
        
        $classes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $classes[] = [
                "id" => (int)$row['id'],
                "name" => $row['class_name'],
                "is_attended" => (int)$row['attendance_count'] > 0
            ];
        }
        
        echo json_encode(["success" => true, "data" => $classes]);
    } else {
        // Get list of unique units with specific ordering
        // Note: Field values must match exact DB strings (including apostrophes)
        $query = "SELECT DISTINCT category as unit_name FROM grade_levels 
                  WHERE category IS NOT NULL AND category != '' 
                  ORDER BY FIELD(category, 'Mahad Aly', 'MA', 'Idad Lughoh', 'MTs', 'SDIT', 'TKIT', 'Playgroup'), category ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["success" => true, "data" => $units]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
