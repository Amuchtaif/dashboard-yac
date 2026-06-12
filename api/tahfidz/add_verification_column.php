<?php
// api/tahfidz/add_verification_column.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/db_mysqli.php';

if (!isset($mysqli)) {
    die(json_encode(["success" => false, "message" => "Connection failed to establish"]));
}


try {
    // Check if column exists
    $check = $mysqli->query("SHOW COLUMNS FROM `tahfidz_teacher_attendance` LIKE 'is_verified'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE `tahfidz_teacher_attendance` ADD COLUMN `is_verified` TINYINT(1) DEFAULT 0 COMMENT '0=Pending, 1=Verified/Rejected'";
        if ($mysqli->query($sql)) {
            echo json_encode(["success" => true, "message" => "Column is_verified added successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Error adding column: " . $mysqli->error]);
        }
    } else {
        echo json_encode(["success" => true, "message" => "Column is_verified already exists"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Exception: " . $e->getMessage()]);
}
?>
