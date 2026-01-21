-- SQL to add semester and unique index
ALTER TABLE academic_years ADD COLUMN semester ENUM('Ganjil', 'Genap') NOT NULL DEFAULT 'Ganjil' AFTER name;
ALTER TABLE academic_years ADD UNIQUE INDEX uid_name_semester (name, semester);

-- PHP Snippet for Exclusive Active Toggle (logic/academic_years/set_active.php)
<?php
// 1. Deactivate all existing academic years
$conn->query("UPDATE academic_years SET status = 'Inactive'");

// 2. Activate the specific one
$stmt = $conn->prepare("UPDATE academic_years SET status = 'Active' WHERE id = ?");
$stmt->execute([$id]);
?>
