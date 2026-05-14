<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS daily_student_attendances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    grade_level_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('H', 'S', 'I', 'A', 'T') DEFAULT 'H' COMMENT 'H: Hadir, S: Sakit, I: Izin, A: Alpha, T: Terlambat',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $conn->exec($sql);
    echo "Table daily_student_attendances created successfully.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
