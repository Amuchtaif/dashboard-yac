<?php
// api/get_meetings.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

// Parameters
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$date = isset($_GET['date']) ? $_GET['date'] : null; // Filter by specific date
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

try {
    $meetings = [];
    $params = [];
    $types = "";

    // Base query
    // If user_id is provided, show meetings where user is participant OR creator
    if ($user_id) {
        $sql = "SELECT m.*, d.name as division_name, 
                (SELECT status FROM meeting_participants mp WHERE mp.meeting_id = m.id AND mp.employee_id = ? LIMIT 1) as my_status
                FROM meetings m 
                LEFT JOIN divisions d ON m.division_id = d.id
                WHERE (m.created_by = ? 
                   OR m.id IN (SELECT meeting_id FROM meeting_participants WHERE employee_id = ?))";
        $params[] = $user_id;
        $params[] = $user_id;
        $params[] = $user_id;
        $types .= "iii";
    } else {
        // If no user_id, show all (public/admin view)
        $sql = "SELECT m.*, d.name as division_name FROM meetings m 
                LEFT JOIN divisions d ON m.division_id = d.id WHERE 1=1";
    }

    // Date Filter
    if ($date) {
        $sql .= " AND m.meeting_date = ?";
        $params[] = $date;
        $types .= "s";
    }

    // Ordering: Upcoming first, then past
    $sql .= " ORDER BY m.meeting_date DESC, m.start_time ASC";

    // Pagination
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // Convert time to easier format if needed, or keep raw
        $row['start_time_formatted'] = substr($row['start_time'], 0, 5);
        $row['end_time_formatted'] = substr($row['end_time'], 0, 5);
        $meetings[] = $row;
    }

    echo json_encode([
        "success" => true,
        "count" => count($meetings),
        "data" => $meetings
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