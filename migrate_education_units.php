<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "CREATE TABLE IF NOT EXISTS education_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    student_count INT DEFAULT 0,
    teacher_count INT DEFAULT 0,
    icon VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $conn->exec($sql);
    echo "Table 'education_units' created successfully.";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>