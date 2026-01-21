<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$cols = $conn->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_COLUMN);
echo "Current columns: " . implode(", ", $cols) . "\n";

$required = [
    'status' => "ALTER TABLE academic_years ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Inactive' AFTER name",
    'start_date' => "ALTER TABLE academic_years ADD COLUMN start_date DATE AFTER status",
    'end_date' => "ALTER TABLE academic_years ADD COLUMN end_date DATE AFTER start_date",
    'updated_at' => "ALTER TABLE academic_years ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($required as $col => $sql) {
    if (!in_array($col, $cols)) {
        try {
            $conn->exec($sql);
            echo "Added column $col\n";
        } catch (Exception $e) {
            echo "Failed to add $col: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Column $col already exists\n";
    }
}

// Final check
$final_cols = $conn->query("SHOW COLUMNS FROM academic_years")->fetchAll(PDO::FETCH_COLUMN);
echo "Final columns: " . implode(", ", $final_cols) . "\n";
?>