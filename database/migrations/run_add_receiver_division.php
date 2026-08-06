<?php
// database/migrations/run_add_receiver_division.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmtCheck = $conn->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'receiver_division_id'
    ");
    $stmtCheck->execute();
    $columnExists = $stmtCheck->fetchColumn();

    if (!$columnExists) {
        $conn->exec("ALTER TABLE `documents` ADD COLUMN `receiver_division_id` INT DEFAULT NULL AFTER `sender`");
        $conn->exec("ALTER TABLE `documents` ADD KEY `idx_receiver_div` (`receiver_division_id`)");
        echo "SUCCESS: Column receiver_division_id added to documents table.\n";
    } else {
        echo "INFO: Column receiver_division_id already exists in documents table.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
