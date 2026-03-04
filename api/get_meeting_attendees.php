<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

$meeting_id = isset($_GET['meeting_id']) ? intval($_GET['meeting_id']) : null;

if (!$meeting_id) {
    echo json_encode(["success" => false, "message" => "Meeting ID required"]);
    exit();
}

try {
    // Fetch ALL participants with their attendance status
    $sql = "SELECT e.id, e.full_name, e.email, mp.status, mp.attendance_time as attended_at
            FROM meeting_participants mp
            JOIN employees e ON mp.employee_id = e.id
            WHERE mp.meeting_id = ?
            ORDER BY 
                CASE mp.status 
                    WHEN 'present' THEN 0 
                    ELSE 1 
                END,
                mp.attendance_time DESC";
            
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("i", $meeting_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $participants = [];
    $present_count = 0;
    $total_count = 0;
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
        $total_count++;
        if ($row['status'] === 'present') {
            $present_count++;
        }
    }
    
    echo json_encode([
        "success" => true,
        "data" => $participants,
        "summary" => [
            "total" => $total_count,
            "present" => $present_count,
            "absent" => $total_count - $present_count
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}

$mysqli->close();
?>
