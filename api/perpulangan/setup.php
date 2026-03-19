<?php
// api/perpulangan/setup.php
require_once __DIR__ . '/../../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Add category column if not exists
    $checkQuery = $conn->query("SHOW COLUMNS FROM boarding_permits LIKE 'category'");
    if ($checkQuery->rowCount() == 0) {
        $conn->exec("ALTER TABLE boarding_permits ADD COLUMN category ENUM('Izin', 'Sakit', 'Libur') NOT NULL DEFAULT 'Izin' AFTER id");
        echo "Column 'category' added successfully.\n";
    } else {
        echo "Column 'category' already exists.\n";
    }

    // Check boarding_returns table
    $conn->exec("CREATE TABLE IF NOT EXISTS `boarding_returns` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `student_id` INT(11) NOT NULL,
        `return_date` DATE NOT NULL,
        `status` ENUM('Sudah Kembali', 'Belum Kembali') DEFAULT 'Belum Kembali',
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `student_id` (`student_id`),
        CONSTRAINT `fk_return_student_new` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    echo "Table 'boarding_returns' ensured.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
