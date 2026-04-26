<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : null;
$position_level = isset($_GET['position_level']) ? (int)$_GET['position_level'] : 5;
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : null;
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "User ID diperlukan."));
    exit;
}

try {
    $query = "SELECT wr.*, e.full_name as employee_name, p.name as position_name, u.name as unit_name, d.name as division_name 
              FROM work_reports wr
              JOIN employees e ON wr.user_id = e.id
              LEFT JOIN positions p ON e.position_id = p.id
              LEFT JOIN units u ON e.unit_id = u.id
              LEFT JOIN divisions d ON e.division_id = d.id";

    $where_clauses = [];
    $params = [];

    if ($position_level == 1) {
        // Mudir: Lihat semua
    } elseif ($position_level == 2) {
        // Kepala Bidang: Lihat semua staf di Divisi yang sama
        if ($division_id) {
            $where_clauses[] = "e.division_id = :division_id";
            $params[':division_id'] = $division_id;
        } else {
            // Jika division_id tidak ada, batasi ke diri sendiri (aman)
            $where_clauses[] = "wr.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
    } elseif ($position_level == 3) {
        // Kepala Unit: Lihat semua staf di Unit yang sama
        if ($unit_id) {
            $where_clauses[] = "e.unit_id = :unit_id";
            $params[':unit_id'] = $unit_id;
        } else {
            $where_clauses[] = "wr.user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
    } else {
        // Staf biasa: Hanya lihat laporan sendiri
        $where_clauses[] = "wr.user_id = :user_id";
        $params[':user_id'] = $user_id;
    }

    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $query .= " ORDER BY wr.report_date DESC, wr.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();

    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(array("success" => true, "data" => $reports));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Database error: " . $e->getMessage()));
}
?>
