<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$columnsToken = [
    'status' => "ALTER TABLE academic_years ADD COLUMN status ENUM('Active', 'Inactive') DEFAULT 'Inactive' AFTER name",
    'start_date' => "ALTER TABLE academic_years ADD COLUMN start_date DATE AFTER status",
    'end_date' => "ALTER TABLE academic_years ADD COLUMN end_date DATE AFTER start_date",
    'updated_at' => "ALTER TABLE academic_years ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
];

foreach ($columnsToken as $col => $sql) {
    try {
        $check = $conn->query("SHOW COLUMNS FROM academic_years LIKE '$col'");
        if ($check->rowCount() == 0) {
            $conn->exec($sql);
            echo "Column '$col' added.\n";
        } else {
            echo "Column '$col' already exists.\n";
        }
    } catch (Exception $e) {
        echo "Error adding '$col': " . $e->getMessage() . "\n";
    }
}

echo "\nFinal Columns:\n";
$stmt = $conn->query("DESCRIBE academic_years");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
?>