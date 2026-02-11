<?php
require_once 'config/app.php';
require_once 'config/database.php';

echo "Setting up Tahfidz Tables...\n";

$db = new Database();
$conn = $db->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS `tahfidz_memorization` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `session` varchar(20) DEFAULT 'Pagi',
  `surah_start` varchar(100) DEFAULT NULL,
  `ayat_start` int(11) DEFAULT 0,
  `surah_end` varchar(100) DEFAULT NULL,
  `ayat_end` int(11) DEFAULT 0,
  `juz` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Lancar',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $conn->exec($sql);
    echo "Table 'tahfidz_memorization' created or already exists.\n";
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}

// Add any other setup needed
echo "Setup Complete.";
?>
