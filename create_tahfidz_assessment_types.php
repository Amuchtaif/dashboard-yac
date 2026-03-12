<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$sql = "
CREATE TABLE IF NOT EXISTS tahfidz_assessment_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

$conn->exec($sql);

// Seed with default types if empty
$stmt = $conn->query("SELECT COUNT(*) FROM tahfidz_assessment_types");
if ($stmt->fetchColumn() == 0) {
    $defaults = [
        ['Ziyadah', 'Hafalan baru materi harian'],
        ['Murojaah', 'Mengulang hafalan yang sudah pernah disetor'],
        ['Sema\'an 1 Juz', 'Ujian hafalan 1 juz sekali duduk'],
        ['Ujian Semester', 'Penilaian berkala di akhir semester']
    ];
    
    $insert = $conn->prepare("INSERT INTO tahfidz_assessment_types (name, description) VALUES (?, ?)");
    foreach ($defaults as $d) {
        $insert->execute($d);
    }
    echo "Table created and seeded with default types.";
} else {
    echo "Table already exists and has data.";
}
