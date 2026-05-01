<?php
// api/meal_attendance/get_stats.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $type = $_GET['meal_type'] ?? 'Siang';
    $date = $_GET['date'] ?? date('Y-m-d');
    $musyrif_id = $_GET['musyrif_id'] ?? null;
    $wali_kelas_id = $_GET['wali_kelas_id'] ?? null;
    $room_id = $_GET['room_id'] ?? null;
    $class_name = null;

    // Detect room if musyrif_id is provided
    if ($musyrif_id && !$room_id) {
        $r_stmt = $conn->prepare("SELECT id FROM boarding_rooms WHERE supervisor_id = ? LIMIT 1");
        $r_stmt->execute([$musyrif_id]);
        $room_id = $r_stmt->fetchColumn();
    }
    
    // Detect class if wali_kelas_id is provided
    if ($wali_kelas_id) {
        $c_stmt = $conn->prepare("SELECT name FROM grade_levels WHERE teacher_id = ? LIMIT 1");
        $c_stmt->execute([$wali_kelas_id]);
        $class_name = $c_stmt->fetchColumn();
    }

    // 1. Total students in scope (Global or Room-specific or Class-specific)
    if ($room_id) {
        $qTotal = "SELECT COUNT(student_id) FROM boarding_room_members WHERE room_id = ?";
        $stmtTotal = $conn->prepare($qTotal);
        $stmtTotal->execute([$room_id]);
    } else if ($class_name) {
        $qTotal = "SELECT COUNT(id) FROM students WHERE kelas = ?";
        $stmtTotal = $conn->prepare($qTotal);
        $stmtTotal->execute([$class_name]);
    } else {
        $qTotal = "SELECT COUNT(*) FROM students";
        $stmtTotal = $conn->prepare($qTotal);
        $stmtTotal->execute();
    }
    $totalStudents = (int)$stmtTotal->fetchColumn();

    // 2. Count who already ate in scope
    if ($room_id) {
        $qEaten = "SELECT COUNT(ma.id) 
                   FROM meal_attendances ma
                   JOIN boarding_room_members brm ON ma.student_id = brm.student_id
                   WHERE ma.meal_type = ? AND ma.date = ? AND brm.room_id = ?";
        $stmtEaten = $conn->prepare($qEaten);
        $stmtEaten->execute([$type, $date, $room_id]);
    } else if ($class_name) {
        $qEaten = "SELECT COUNT(ma.id) 
                   FROM meal_attendances ma
                   JOIN students s ON ma.student_id = s.id
                   WHERE ma.meal_type = ? AND ma.date = ? AND s.kelas = ?";
        $stmtEaten = $conn->prepare($qEaten);
        $stmtEaten->execute([$type, $date, $class_name]);
    } else {
        $qEaten = "SELECT COUNT(*) FROM meal_attendances WHERE meal_type = ? AND date = ?";
        $stmtEaten = $conn->prepare($qEaten);
        $stmtEaten->execute([$type, $date]);
    }
    $eatenCount = (int)$stmtEaten->fetchColumn();

    $remainingQuota = $totalStudents - $eatenCount;
    if ($remainingQuota < 0) $remainingQuota = 0;

    // 3. Recent Queue (Top 10)
    $qQueue = "SELECT ma.id, ma.check_time, s.nama_siswa 
               FROM meal_attendances ma
               JOIN students s ON ma.student_id = s.id";
    if ($room_id) {
        $qQueue .= " JOIN boarding_room_members brm ON s.id = brm.student_id 
                     WHERE ma.meal_type = ? AND ma.date = ? AND brm.room_id = ?";
        $pQueue = [$type, $date, $room_id];
    } else if ($class_name) {
        $qQueue .= " WHERE ma.meal_type = ? AND ma.date = ? AND s.kelas = ?";
        $pQueue = [$type, $date, $class_name];
    } else {
        $qQueue .= " WHERE ma.meal_type = ? AND ma.date = ?";
        $pQueue = [$type, $date];
    }
    $qQueue .= " ORDER BY ma.id DESC LIMIT 10";
    
    $stmtQueue = $conn->prepare($qQueue);
    $stmtQueue->execute($pQueue);
    $recentQueue = [];
    while ($row = $stmtQueue->fetch(PDO::FETCH_ASSOC)) {
        $recentQueue[] = [
            "id" => (int)$row['id'],
            "name" => $row['nama_siswa'],
            "time" => substr($row['check_time'] ?: '', 0, 5)
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "total_served" => $eatenCount,
            "total_quota" => $totalStudents,
            "remaining_quota" => $remainingQuota
        ],
        "summary" => [ // Keep for backward compatibility if any
            "eaten_count" => $eatenCount,
            "total_quota" => $totalStudents,
            "remaining_quota" => $remainingQuota
        ],
        "recent_queue" => $recentQueue,
        "debug_info" => [
            "room_id" => $room_id,
            "musyrif_id" => $musyrif_id,
            "meal_type" => $type,
            "date" => $date
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
