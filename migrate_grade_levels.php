<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Create grade_levels table
    $sql1 = "CREATE TABLE IF NOT EXISTS grade_levels (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql1);
    echo "Table 'grade_levels' created successfully.<br>";
} catch (PDOException $e) {
    echo "Error creating table 'grade_levels': " . $e->getMessage() . "<br>";
}

try {
    // Add category column to education_units
    $sql2 = "ALTER TABLE education_units ADD COLUMN category VARCHAR(255) NULL AFTER name";
    $conn->exec($sql2);
    echo "Column 'category' added to 'education_units' successfully.<br>";
} catch (PDOException $e) {
    echo "Error adding 'category' to 'education_units': " . $e->getMessage() . "<br>";
}
?>