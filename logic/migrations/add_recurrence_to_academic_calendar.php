<?php
// logic/migrations/add_recurrence_to_academic_calendar.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo "Running migration: add_recurrence_to_academic_calendar...\n";

    // 1. Check & Add is_recurring
    $stmt = $conn->query("SHOW COLUMNS FROM academic_calendar LIKE 'is_recurring'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE academic_calendar ADD COLUMN is_recurring TINYINT(1) DEFAULT 0 AFTER is_holiday");
        echo " - Added column: is_recurring\n";
    }

    // 2. Check & Add recurrence_type
    $stmt = $conn->query("SHOW COLUMNS FROM academic_calendar LIKE 'recurrence_type'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE academic_calendar ADD COLUMN recurrence_type ENUM('none','daily','weekly','monthly','yearly') DEFAULT 'none' AFTER is_recurring");
        echo " - Added column: recurrence_type\n";
    }

    // 3. Check & Add recurrence_rule
    $stmt = $conn->query("SHOW COLUMNS FROM academic_calendar LIKE 'recurrence_rule'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE academic_calendar ADD COLUMN recurrence_rule TEXT DEFAULT NULL AFTER recurrence_type");
        echo " - Added column: recurrence_rule\n";
    }

    // 4. Check & Add repeat_group_id
    $stmt = $conn->query("SHOW COLUMNS FROM academic_calendar LIKE 'repeat_group_id'");
    if (!$stmt->fetch()) {
        $conn->exec("ALTER TABLE academic_calendar ADD COLUMN repeat_group_id VARCHAR(64) DEFAULT NULL AFTER recurrence_rule");
        $conn->exec("ALTER TABLE academic_calendar ADD INDEX idx_repeat_group_id (repeat_group_id)");
        echo " - Added column: repeat_group_id\n";
    }

    echo "Migration completed successfully.\n";
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
