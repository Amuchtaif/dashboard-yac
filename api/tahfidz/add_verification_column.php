<?php
// api/tahfidz/add_verification_column.php
header("Content-Type: application/json");

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
