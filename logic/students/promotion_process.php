<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('views/students/promotion.php');
}

$db = new Database();
$conn = $db->getConnection();

$action_type = $_POST['action_type'] ?? 'promote';
$student_ids = $_POST['student_ids'] ?? [];

if (empty($student_ids)) {
    redirect('views/students/promotion.php?error=' . urlencode('Harap pilih minimal satu Siswa.'));
}

if ($action_type === 'promote') {
    $target_class_id = $_POST['target_class_id'] ?? '';
    $target_year_id = $_POST['target_year_id'] ?? '';

    if (empty($target_class_id) || empty($target_year_id)) {
        redirect('views/students/promotion.php?error=' . urlencode('Harap pilih Kelas Tujuan, Tahun Ajaran Tujuan, dan minimal satu Siswa.'));
    }

    try {
        $conn->beginTransaction();

        // Prepare Insert Statement
        $sql = "INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, joined_at) 
                VALUES (:student_id, :class_id, :academic_year_id, 'ACTIVE', NOW())";

        $stmt = $conn->prepare($sql);

        $count = 0;
        foreach ($student_ids as $student_id) {
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
} elseif ($action_type === 'graduate') {
    $source_class_id = $_POST['source_class_id'] ?? '';
    $source_year_id = $_POST['source_year_id'] ?? '';

    if (empty($source_class_id) || empty($source_year_id)) {
        redirect('views/students/promotion.php?error=' . urlencode('Data kelas asal atau tahun ajaran asal tidak lengkap.'));
    }

    try {
        $conn->beginTransaction();

        // Update student status to 'Lulus'
        $stmtStudent = $conn->prepare("UPDATE students SET status = 'Lulus' WHERE id = :student_id");

        // Update class history status to 'GRADUATED'
        $stmtHistory = $conn->prepare("UPDATE student_class_history 
                                       SET status = 'GRADUATED', left_at = NOW() 
                                       WHERE student_id = :student_id 
                                         AND class_id = :class_id 
                                         AND academic_year_id = :academic_year_id");

        $count = 0;
        foreach ($student_ids as $student_id) {
            // Update student status
            $stmtStudent->execute([':student_id' => $student_id]);

            // Update student class history
            $stmtHistory->execute([
                ':student_id' => $student_id,
                ':class_id' => $source_class_id,
                ':academic_year_id' => $source_year_id
            ]);
            
            $count++;
        }

        $conn->commit();

        redirect('views/students/promotion.php?success=' . urlencode("Berhasil meluluskan $count siswa."));

    } catch (Exception $e) {
        $conn->rollBack();
        redirect('views/students/promotion.php?error=' . urlencode('Terjadi kesalahan database: ' . $e->getMessage()));
    }
} else {
    redirect('views/students/promotion.php?error=' . urlencode('Tindakan tidak valid.'));
}
