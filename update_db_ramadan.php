<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `ramadan_settings` (
        `id` INT(11) NOT NULL DEFAULT 1,
        `is_active` TINYINT(1) DEFAULT 0,
        `start_date` DATE,
        `end_date` DATE,
        `half_day_end_time` TIME DEFAULT '12:00:00',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB");

    $conn->exec("INSERT IGNORE INTO `ramadan_settings` (`id`, `is_active`, `half_day_end_time`) VALUES (1, 0, '12:00:00')");

    // Check if column exists in units
    $stmt = $conn->query("SHOW COLUMNS FROM units LIKE 'is_ramadan_affected'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE units ADD COLUMN is_ramadan_affected TINYINT(1) DEFAULT 0");
    }

    echo "Database updated successfully.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
