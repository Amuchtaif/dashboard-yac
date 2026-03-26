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

    $type = $_GET['meal_type'] ?? 'Siang'; // Default Siang
    $today = date('Y-m-d');

    // 1. Hitung Total Santri (Jatah)
    $stmtTotal = $conn->query("SELECT COUNT(*) FROM students");
    $totalStudents = (int)$stmtTotal->fetchColumn();

    // 2. Hitung Sudah Makan
    $stmtEaten = $conn->prepare("SELECT COUNT(*) FROM meal_attendances WHERE meal_type = ? AND date = ?");
    $stmtEaten->execute([$type, $today]);
    $eatenCount = (int)$stmtEaten->fetchColumn();

    $remainingQuota = $totalStudents - $eatenCount;
    if ($remainingQuota < 0) $remainingQuota = 0;

    // 3. Antrian Terakhir (Top 10)
    $stmtQueue = $conn->prepare("
        SELECT 
            ma.id, 
            ma.check_time, 
            s.nama_siswa 
        FROM meal_attendances ma
        JOIN students s ON ma.student_id = s.id
        WHERE ma.meal_type = ? AND ma.date = ?
        ORDER BY ma.id DESC
        LIMIT 10
    ");
    $stmtQueue->execute([$type, $today]);
    $recentQueue = [];
    while ($row = $stmtQueue->fetch(PDO::FETCH_ASSOC)) {
        $recentQueue[] = [
            "id" => $row['id'],
            "name" => $row['nama_siswa'],
            "time" => substr($row['check_time'] ?: '', 0, 5)
        ];
    }

    echo json_encode([
        "success" => true,
        "summary" => [
            "eaten_count" => $eatenCount,
            "remaining_quota" => $remainingQuota,
            "total_quota" => $totalStudents
        ],
        "recent_queue" => $recentQueue
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
