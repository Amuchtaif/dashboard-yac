<?php
/**
 * Get Performance Data API
 * Endpoint: GET /api/get_performance_data.php?user_id=5
 * 
 * This API calculates and returns the performance points and activity history for an employee.
 */

// Enable Error Reporting for Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!isset($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "User ID diperlukan"]);
    exit();
}

$user_id = $_GET['user_id'];

try {
    // 1. Calculate Attendance Points
    // Masuk: Hadir/Tepat Waktu = +10, Telat = -5
    // Pulang: Pulang/Tepat Waktu = +10, Pulang Cepat = -5
    $queryAttendance = "SELECT 
                            status, 
                            status_out,
                            date, 
                            time_in,
                            time_out
                        FROM attendances 
                        WHERE user_id = :uid";
    $stmtAtt = $conn->prepare($queryAttendance);
    $stmtAtt->bindParam(':uid', $user_id);
    $stmtAtt->execute();
    $attendances = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    $totalPoints = 0;
    $activityHistory = [];

    foreach ($attendances as $row) {
        // --- POINTS FOR CHECK-IN ---
        $pointsIn = 0;
        $titleIn = "";
        
        if ($row['status'] == 'Hadir' || $row['status'] == 'Tepat Waktu') {
            $pointsIn = 10;
            $titleIn = "Presensi Masuk Tepat Waktu";
        } elseif ($row['status'] == 'Telat') {
            $pointsIn = -5;
            $titleIn = "Presensi Masuk Terlambat";
        }

        if ($pointsIn != 0) {
            $totalPoints += $pointsIn;
            $activityHistory[] = [
                "title" => $titleIn,
                "points" => ($pointsIn > 0 ? "+" : "") . $pointsIn,
                "date" => $row['date'],
                "time" => substr($row['time_in'], 0, 5),
                "type" => "attendance"
            ];
        }

        // --- POINTS FOR CHECK-OUT ---
        if (!empty($row['status_out']) && !empty($row['time_out'])) {
            $pointsOut = 0;
            $titleOut = "";
            
            if ($row['status_out'] == 'Pulang' || $row['status_out'] == 'Tepat Waktu') {
                $pointsOut = 10;
                $titleOut = "Presensi Pulang Sesuai Waktu";
            } elseif ($row['status_out'] == 'Pulang Cepat') {
                $pointsOut = -5;
                $titleOut = "Presensi Pulang Sebelum Waktunya";
            }

            if ($pointsOut != 0) {
                $totalPoints += $pointsOut;
                $activityHistory[] = [
                    "title" => $titleOut,
                    "points" => ($pointsOut > 0 ? "+" : "") . $pointsOut,
                    "date" => $row['date'],
                    "time" => substr($row['time_out'], 0, 5),
                    "type" => "attendance"
                ];
            }
        }
    }

    // 2. Calculate Meeting Points
    // Present = +10
    $queryMeetings = "SELECT 
                        m.title, 
                        mp.attendance_time 
                      FROM meeting_participants mp
                      JOIN meetings m ON mp.meeting_id = m.id
                      WHERE mp.employee_id = :uid AND mp.status = 'present'";
    $stmtMeet = $conn->prepare($queryMeetings);
    $stmtMeet->bindParam(':uid', $user_id);
    $stmtMeet->execute();
    $meetings = $stmtMeet->fetchAll(PDO::FETCH_ASSOC);

    foreach ($meetings as $row) {
        $points = 10;
        $totalPoints += $points;
        
        $activityHistory[] = [
            "title" => "Kehadiran Rapat: " . $row['title'],
            "points" => "+10",
            "date" => date('Y-m-d', strtotime($row['attendance_time'])),
            "time" => date('H:i', strtotime($row['attendance_time'])),
            "type" => "meeting"
        ];
    }

    // Sort History by Date and Time DESC
    usort($activityHistory, function($a, $b) {
        $datetimeA = $a['date'] . ' ' . $a['time'];
        $datetimeB = $b['date'] . ' ' . $b['time'];
        return strcmp($datetimeB, $datetimeA);
    });

    // Determine Status Text
    $statusText = "Belum Ada Data";
    $statusColor = "#94A3B8"; // Gray
    
    if ($totalPoints > 800) {
        $statusText = "Sangat Baik";
        $statusColor = "#4CAF50"; // Green
    } elseif ($totalPoints > 500) {
        $statusText = "Baik";
        $statusColor = "#8BC34A"; // Light Green
    } elseif ($totalPoints >= 100) {
        $statusText = "Cukup";
        $statusColor = "#FFA000"; // Orange
    } elseif ($totalPoints > 0) {
        $statusText = "Kurang";
        $statusColor = "#FF9800"; // Amber
    } elseif ($totalPoints < 0) {
        $statusText = "Perlu Perbaikan";
        $statusColor = "#F44336"; // Red
    }

    echo json_encode([
        "success" => true,
        "data" => [
            "total_points" => $totalPoints,
            "status_text" => $statusText,
            "status_color" => $statusColor,
            "history" => array_slice($activityHistory, 0, 20) // Top 20 activities
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
