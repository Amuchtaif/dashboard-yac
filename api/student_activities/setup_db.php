<?php
// api/student_activities/setup_db.php
// Script to create tables for Student Activities (Amaliyah)

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

    // 1. Run migration SQL file
    $sql_file = __DIR__ . '/../../database/migrations/2026_07_01_create_student_activities_tables.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Migration file not found at: $sql_file");
    }

    $sql = file_get_contents($sql_file);
    $conn->exec($sql);
    $messages[] = "Created student activities tables (activity_types, student_activities, activity_files).";

    // 2. Add can_manage_amaliyah to positions table if it does not exist
    $checkColumn = $conn->query("SHOW COLUMNS FROM `positions` LIKE 'can_manage_amaliyah'");
    if ($checkColumn->rowCount() === 0) {
        $conn->exec("ALTER TABLE `positions` ADD COLUMN `can_manage_amaliyah` TINYINT(1) DEFAULT 0 AFTER `can_access_kesantrian`");
        $messages[] = "Added column 'can_manage_amaliyah' to table 'positions'.";
    } else {
        $messages[] = "Column 'can_manage_amaliyah' already exists in 'positions'.";
    }

    // 3. Seed initial activity types if table is empty
    $checkEmpty = $conn->query("SELECT COUNT(*) FROM `activity_types` WHERE `deleted_at` IS NULL");
    if ((int)$checkEmpty->fetchColumn() === 0) {
        $initial_types = [
            ['Shalat Dhuha', 'shalat-dhuha', 'personal', 'Mengerjakan shalat sunnah dhuha harian', 'sun', '#eab308', 5, 1],
            ['Puasa Sunnah', 'puasa-sunnah', 'personal', 'Mengerjakan puasa sunnah senin-kamis atau ayyamul bidh', 'calendar', '#3b82f6', 15, 2],
            ['Dzikir Pagi', 'dzikir-pagi', 'personal', 'Membaca dzikir pagi Al-Matsurat', 'book-open', '#10b981', 5, 3],
            ['Dzikir Petang', 'dzikir-petang', 'personal', 'Membaca dzikir petang Al-Matsurat', 'moon', '#6366f1', 5, 4],
            ['Kajian', 'kajian', 'event', 'Mengikuti kajian keagamaan', 'users', '#a855f7', 10, 5],
            ['Kerja Bakti', 'kerja-bakti', 'event', 'Mengikuti kegiatan bersih-bersih lingkungan', 'trash-2', '#f97316', 10, 6],
            ['Bakti Sosial', 'bakti-sosial', 'event', 'Mengikuti kegiatan sosial kemasyarakatan', 'heart', '#ec4899', 15, 7],
            ['Menjadi Imam', 'menjadi-imam', 'personal', 'Menjadi imam shalat berjamaah', 'user-check', '#06b6d4', 10, 8]
        ];

        $stmt = $conn->prepare("INSERT INTO `activity_types` (`name`, `slug`, `type`, `description`, `icon`, `color`, `point`, `sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($initial_types as $type) {
            $stmt->execute($type);
        }
        $messages[] = "Seed initial activity types completed.";
    }

    echo json_encode([
        "success" => true,
        "messages" => $messages,
        "detail" => "Student activities database setup completed successfully."
    ]);

} catch(Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
