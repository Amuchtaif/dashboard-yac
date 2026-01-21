<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // 1. Add semester column
    $check = $conn->query("SHOW COLUMNS FROM academic_years LIKE 'semester'");
    if ($check->rowCount() == 0) {
        $conn->exec("ALTER TABLE academic_years ADD COLUMN semester ENUM('Ganjil', 'Genap') NOT NULL DEFAULT 'Ganjil' AFTER name");
        echo "Column 'semester' added.\n";
    } else {
        echo "Column 'semester' already exists.\n";
    }

    // 2. Add Unique Index
    try {
        $conn->exec("ALTER TABLE academic_years ADD UNIQUE INDEX uid_name_semester (name, semester)");
        echo "Unique Index 'uid_name_semester' added.\n";
    } catch (Exception $e) {
        echo "Unique Index probably already exists: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
?>