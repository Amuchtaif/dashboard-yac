<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS meal_attendances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        meal_type ENUM('Pagi', 'Siang', 'Malam') NOT NULL,
        date DATE NOT NULL,
        check_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (student_id),
        INDEX (date),
        INDEX (meal_type)
    )");
    echo "Table meal_attendances created or exists.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
