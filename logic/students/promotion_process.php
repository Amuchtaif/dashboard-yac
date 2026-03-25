<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('views/students/promotion.php?success=Operasi+berhasil');
}

$db = new Database();
$conn = $db->getConnection();

// Validate Inputs
$target_class_id = $_POST['target_class_id'] ?? '';
$target_year_id = $_POST['target_year_id'] ?? ''; // Assuming ID for now
$student_ids = $_POST['student_ids'] ?? [];

if (empty($target_class_id) || empty($target_year_id) || empty($student_ids)) {
    // Error: Missing required fields
    redirect('views/students/promotion.php?error=' . urlencode('Harap pilih Kelas Tujuan, Tahun Ajaran Tujuan, dan minimal satu Siswa.'));
}

try {
    $conn->beginTransaction();

    // Prepare Insert Statement
    $sql = "INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, created_at, updated_at) 
            VALUES (:student_id, :class_id, :academic_year_id, 'Active', NOW(), NOW())";

    $stmt = $conn->prepare($sql);

    $count = 0;
    foreach ($student_ids as $student_id) {
        // Optional: Check if already exists for this target year/class to prevent duplicates?
        // For now, straightforward insert as per request.

        $stmt->execute([
            ':student_id' => $student_id,
            ':class_id' => $target_class_id,
            ':academic_year_id' => $target_year_id
        ]);
        $count++;
    }

    $conn->commit();

    redirect('views/students/promotion.php?success=' . urlencode("Berhasil menaikkan kelas $count siswa."));

} catch (Exception $e) {
    $conn->rollBack();
    redirect('views/students/promotion.php?error=' . urlencode('Terjadi kesalahan database: ' . $e->getMessage()));
}
