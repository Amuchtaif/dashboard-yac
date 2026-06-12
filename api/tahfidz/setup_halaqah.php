<?php
// api/tahfidz/setup_halaqah.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Create halaqah_groups table
    $sql1 = "CREATE TABLE IF NOT EXISTS `halaqah_groups` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_name` VARCHAR(255) NOT NULL,
        `teacher_id` INT(11) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `teacher_id` (`teacher_id`),
        CONSTRAINT `fk_halaqah_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $conn->exec($sql1);
    
    // 2. Create halaqah_members table
    $sql2 = "CREATE TABLE IF NOT EXISTS `halaqah_members` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_id` INT(11) NOT NULL,
        `student_id` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_group_student` (`group_id`, `student_id`),
        KEY `group_id` (`group_id`),
        KEY `student_id` (`student_id`),
        CONSTRAINT `fk_halaqah_group` FOREIGN KEY (`group_id`) REFERENCES `halaqah_groups` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_halaqah_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $conn->exec($sql2);

    echo json_encode(["status" => "success", "message" => "Halaqah tables created successfully"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
