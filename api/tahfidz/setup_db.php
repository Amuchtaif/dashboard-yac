<?php
// setup_tahfidz_db.php
// Script to create tables for Tahfidz features

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../../config/database.php';

$messages = [];

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Table for Tahfidz Students (if not using existing students table, 
    // but assuming we link to existing students table or create a specific one if needed.
    // Let's use existing 'students' table for student base data, and add a linking table if needed.
    // For now, we will just use student_id in attendance tables.

    // 2. Table for Tahfidz Student Attendance (Absensi Tahfidz)
    $sql = "CREATE TABLE IF NOT EXISTS `tahfidz_attendance` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `student_id` INT(11) NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('Hadir', 'Sakit', 'Izin', 'Alpha') DEFAULT 'Hadir',
        `session` VARCHAR(50) DEFAULT 'Pagi', -- Pagi, Sore
        `teacher_id` INT(11) DEFAULT NULL, -- ID of the teacher who took attendance
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        -- FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    $messages[] = "Table 'tahfidz_attendance' created or already exists.";

    // 3. Table for Teacher Attendance (Absensi Pengampu)
    // Assuming teachers are in 'employees' table
    $sql = "CREATE TABLE IF NOT EXISTS `tahfidz_teacher_attendance` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `teacher_id` INT(11) NOT NULL,
        `date` DATE NOT NULL,
        `status` ENUM('Hadir', 'Sakit', 'Izin', 'Alpha') DEFAULT 'Hadir',
        `check_in_time` TIME DEFAULT NULL,
        `check_out_time` TIME DEFAULT NULL,
        `notes` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        -- FOREIGN KEY (`teacher_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    $messages[] = "Table 'tahfidz_teacher_attendance' created or already exists.";

    // 4. Table for Setoran Tahfidz (Memorization)
    $sql = "CREATE TABLE IF NOT EXISTS `tahfidz_memorization` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `student_id` INT(11) NOT NULL,
        `date` DATE NOT NULL,
        `surah_start` VARCHAR(100),
        `ayat_start` INT(11),
        `surah_end` VARCHAR(100),
        `ayat_end` INT(11),
        `juz` INT(11) DEFAULT NULL,
        `status` ENUM('Lancar', 'Kurang Lancar', 'Ulang', 'Ziyadah', 'Murajaah') DEFAULT 'Lancar',
        `notes` TEXT,
        `teacher_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    $messages[] = "Table 'tahfidz_memorization' created or already exists.";

    // 5. Table for Penilaian Tahfidz (Assessment)
    $sql = "CREATE TABLE IF NOT EXISTS `tahfidz_assessments` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `student_id` INT(11) NOT NULL,
        `assessment_date` DATE NOT NULL,
        `category` VARCHAR(50) DEFAULT 'Bulanan', -- Harian, Bulanan, Ujian
        `tajweed_score` INT(11) DEFAULT 0,
        `fluency_score` INT(11) DEFAULT 0,
        `makhraj_score` INT(11) DEFAULT 0,
        `total_score` INT(11) DEFAULT 0,
        `comments` TEXT,
        `teacher_id` INT(11) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    $messages[] = "Table 'tahfidz_assessments' created or already exists.";
    
    echo json_encode([
        "success" => true,
        "messages" => $messages,
        "detail" => "All Tahfidz tables setup completed successfully."
    ]);

} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
