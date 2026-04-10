<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once '../config/database.php';
require_once '../config/permission.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->user_id) && !isset($data->email)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID or Email required"]);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    $query = "
        SELECT 
            e.id, 
            e.full_name, 
            e.email, 
            e.phone_number,
            e.address,
            e.status,
            e.created_at as joined_at,
            e.schedule_id as personal_schedule_id,
            e.division_id,
            e.unit_id,
            d.name as division_name, 
            d.schedule_id as division_schedule_id,
            u.name as unit_name,
            u.schedule_id as unit_schedule_id,
            p.name as position_name
        FROM employees e
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE ";

    if (isset($data->user_id)) {
        $query .= "e.id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $data->user_id, PDO::PARAM_INT);
    } else {
        $query .= "e.email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':email', $data->email, PDO::PARAM_STR);
    }

    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // --- Determine Effective Schedule ---
        // Priority: 1. Personal, 2. Unit, 3. Division, 4. Default(1)
        $schedule_id = null;
        if (!empty($user['personal_schedule_id'])) {
            $schedule_id = $user['personal_schedule_id'];
        } elseif (!empty($user['unit_schedule_id'])) {
            $schedule_id = $user['unit_schedule_id'];
        } elseif (!empty($user['division_schedule_id'])) {
            $schedule_id = $user['division_schedule_id'];
        } else {
            $schedule_id = 1; // Default
        }

        // Fetch Today's Schedule Detail
        $today_shift = null;
        if ($schedule_id) {
            $day_name = date('l'); // Today, e.g. "Monday"
            $stmtSched = $conn->prepare("SELECT start_time, end_time, is_day_off FROM work_schedule_details WHERE schedule_id = ? AND day_name = ?");
            $stmtSched->execute([$schedule_id, $day_name]);
            $today_shift = $stmtSched->fetch(PDO::FETCH_ASSOC);
        }

        // Clean up internal ID fields from response
        $responseUser = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'phone_number' => $user['phone_number'],
            'address' => $user['address'],
            'alamat' => $user['address'],
            'status' => $user['status'],
            'joined_at' => $user['joined_at'],
            'division_name' => $user['division_name'],
            'unit_name' => $user['unit_name'],
            'position_name' => $user['position_name'],
            'today_schedule' => $today_shift ? (function($shift, $conn, $user) {
                $start_time = $shift['start_time'];
                $end_time = $shift['end_time'];
                
                // Logic Ramadan Override
                $stmtRamadan = $conn->query("SELECT is_active FROM ramadan_settings WHERE id = 1 LIMIT 1");
                $ramadan = $stmtRamadan->fetch(PDO::FETCH_ASSOC);
                if ($ramadan && (int)$ramadan['is_active'] === 1) {
                    if (!empty($user['unit_id'])) {
                        $day_l = date('l');
                        $stmtOverride = $conn->prepare("SELECT start_time, end_time FROM ramadan_overrides 
                                                      WHERE FIND_IN_SET(?, days) 
                                                      AND FIND_IN_SET(?, unit_ids)
                                                      ORDER BY id DESC LIMIT 1");
                        $stmtOverride->execute([$day_l, $user['unit_id']]);
                        $override = $stmtOverride->fetch(PDO::FETCH_ASSOC);
                        
                        if ($override) {
                            if (!empty($override['start_time'])) {
                                $start_time = $override['start_time'];
                            }
                            if (!empty($override['end_time'])) {
                                $end_time = $override['end_time'];
                            }
                        }
                    }
                }

                return [
                    'day' => date('l'),
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_day_off' => (bool) $shift['is_day_off']
                ];
            })($today_shift, $conn, $user) : null,
            'is_koordinator' => (stripos($user['position_name'], 'Koordinator Tahfidz') !== false) ? 1 : 0,

            // Dynamic Access for Education Menu (Teacher Check)
            'can_access_education' => (function($userId, $conn) {
                $stmtTeacher = $conn->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = ? LIMIT 1");
                $stmtTeacher->execute([$userId]);
                $isTeacher = (int)$stmtTeacher->fetchColumn() > 0;
                return (hasPermission($userId, 'access_education') || $isTeacher) ? 1 : 0;
            })($user['id'], $conn),

            'can_manage_news' => hasPermission($user['id'], 'manage_news') ? 1 : 0,
            'can_access_kesantrian' => hasPermission($user['id'], 'can_access_kesantrian') ? 1 : 0,
            'can_access_kabid' => hasPermission($user['id'], 'can_access_kabid') ? 1 : 0
        ];

        echo json_encode([
            "status" => "success",
            "data" => $responseUser
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "System error: " . $e->getMessage()]);
}
