<?php
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Drop old columns from work_schedules
    // Check if columns exist before dropping (to prevent errors on rerun)
    $columns = $conn->query("SHOW COLUMNS FROM work_schedules")->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('start_time', $columns)) {
        $conn->exec("ALTER TABLE work_schedules DROP COLUMN start_time");
        echo "Dropped start_time.\n";
    }
    if (in_array('end_time', $columns)) {
        $conn->exec("ALTER TABLE work_schedules DROP COLUMN end_time");
        echo "Dropped end_time.\n";
    }

    // 2. Create detail table
    $sql = "CREATE TABLE IF NOT EXISTS work_schedule_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        schedule_id INT NOT NULL,
        day_name ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        start_time TIME DEFAULT NULL,
        end_time TIME DEFAULT NULL,
        is_day_off TINYINT(1) DEFAULT 0,
        FOREIGN KEY (schedule_id) REFERENCES work_schedules(id) ON DELETE CASCADE,
        UNIQUE KEY unique_day_schedule (schedule_id, day_name)
    )";
    $conn->exec($sql);
    echo "Created work_schedule_details table.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>