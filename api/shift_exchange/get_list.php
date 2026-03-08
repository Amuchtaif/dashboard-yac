<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['user_id'] ?? null;
$type = $_GET['type'] ?? 'incoming'; // incoming | outgoing

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID is required"]);
    exit;
}

try {
    $whereClause = ($type === 'incoming') ? "se.substitute_id = :uid" : "se.requester_id = :uid";
    
    $query = "
        SELECT 
            se.id,
            se.requester_id,
            se.substitute_id,
            se.exchange_date,
            se.reason,
            se.status,
            se.created_at,
            e_req.full_name as requester_name,
            e_req.profile_photo as requester_photo,
            ws_req.name as requester_shift_name,
            e_sub.full_name as substitute_name,
            e_sub.profile_photo as substitute_photo,
            ws_sub.name as substitute_shift_name
        FROM shift_exchanges se
        JOIN employees e_req ON se.requester_id = e_req.id
        LEFT JOIN work_schedules ws_req ON e_req.schedule_id = ws_req.id
        JOIN employees e_sub ON se.substitute_id = e_sub.id
        LEFT JOIN work_schedules ws_sub ON e_sub.schedule_id = ws_sub.id
        WHERE $whereClause
        ORDER BY se.exchange_date DESC, se.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':uid', $user_id);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    foreach ($results as $row) {
        $data[] = [
            "id" => (int)$row['id'],
            "date" => $row['exchange_date'],
            "formatted_date" => date('d M Y', strtotime($row['exchange_date'])),
            "requester_id" => (int)$row['requester_id'],
            "requester_name" => $row['requester_name'],
            "requester_photo" => $row['requester_photo'],
            "from_shift" => $row['requester_shift_name'] ?? 'Shift Default',
            "substitute_id" => (int)$row['substitute_id'],
            "substitute_name" => $row['substitute_name'],
            "to_shift" => $row['substitute_shift_name'] ?? 'Shift Default',
            "reason" => $row['reason'],
            "status" => $row['status'],
            "created_at" => $row['created_at']
        ];
    }

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
