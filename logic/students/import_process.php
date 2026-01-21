<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/students/import.php");
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . BASE_URL . "/views/students/import.php?error=Upload failed or no file selected");
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === false) {
    header("Location: " . BASE_URL . "/views/students/import.php?error=Could not open file");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$errorCount = 0;
$errors = [];
$rowNumber = 0;

$active_year_id = 1; // Default Academic Year

try {
    $conn->beginTransaction();

    // Skip Header Row if desired (assuming user downloads template which has header)
    // Let's verify header or just skip first row
    $header = fgetcsv($handle, 1000, ",");
    // Format expected: Nama Siswa, NISN, Kelas

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNumber++;

        // Basic validation: Check column count (at least 3)
        if (count($data) < 3) {
            $errorCount++;
            $errors[] = "Row $rowNumber: Insufficient columns.";
            continue;
        }

        $nama_siswa = trim($data[0]);
        $nisn = trim($data[1]);
        $kelas_nama = trim($data[2]);

        if (empty($nama_siswa) || empty($nisn)) {
            $errorCount++;
            $errors[] = "Row $rowNumber: Name or NISN empty.";
            continue;
        }

        // --- Step A: Student UPSERT ---
        // Check if student exists
        $stmtCheck = $conn->prepare("SELECT id FROM students WHERE nomor_induk = :nisn");
        $stmtCheck->execute([':nisn' => $nisn]);
        $existingStudent = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingStudent) {
            $student_id = $existingStudent['id'];
            // Update Name just in case
            $stmtUpdate = $conn->prepare("UPDATE students SET nama_siswa = :nama, updated_at = NOW() WHERE id = :id");
            $stmtUpdate->execute([':nama' => $nama_siswa, ':id' => $student_id]);
        } else {
            // Insert New
            $stmtInsert = $conn->prepare("INSERT INTO students (nama_siswa, nomor_induk, status, created_at, updated_at) VALUES (:nama, :nisn, 'Aktif', NOW(), NOW())");
            $stmtInsert->execute([':nama' => $nama_siswa, ':nisn' => $nisn]);
            $student_id = $conn->lastInsertId();
        }

        // --- Step B: Class Logic ---
        $class_id = null;
        if (!empty($kelas_nama)) {
            // Check if class exists
            $stmtClass = $conn->prepare("SELECT id FROM grade_levels WHERE name = :name LIMIT 1");
            $stmtClass->execute([':name' => $kelas_nama]);
            $existingClass = $stmtClass->fetch(PDO::FETCH_ASSOC);

            if ($existingClass) {
                $class_id = $existingClass['id'];
            } else {
                // Create Class - "Unassigned" Unit logic?
                // For now, insert with minimal info
                $stmtNewClass = $conn->prepare("INSERT INTO grade_levels (name, level, created_at, updated_at) VALUES (:name, 0, NOW(), NOW())");
                $stmtNewClass->execute([':name' => $kelas_nama]);
                $class_id = $conn->lastInsertId();
            }
        }

        // --- Step C: Assignment ---
        if ($class_id) {
            // Check existing placement for this year
            $stmtHistCheck = $conn->prepare("SELECT id FROM student_class_history WHERE student_id = :sid AND academic_year_id = :yid");
            $stmtHistCheck->execute([':sid' => $student_id, ':yid' => $active_year_id]);
            $hist = $stmtHistCheck->fetch(PDO::FETCH_ASSOC);

            if ($hist) {
                // Update class if different?
                $stmtHistUpdate = $conn->prepare("UPDATE student_class_history SET class_id = :cid, updated_at = NOW() WHERE id = :hid");
                $stmtHistUpdate->execute([':cid' => $class_id, ':hid' => $hist['id']]);
            } else {
                // Insert
                $stmtHistInsert = $conn->prepare("INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, created_at, updated_at) VALUES (:sid, :cid, :yid, 'ACTIVE', NOW(), NOW())");
                $stmtHistInsert->execute([':sid' => $student_id, ':cid' => $class_id, ':yid' => $active_year_id]);
            }
        }

        $successCount++;
    }

    $conn->commit();
    fclose($handle);

    $msg = "Import Success! Processed: $successCount students.";
    if ($errorCount > 0) {
        $msg .= " With $errorCount errors.";
    }

    header("Location: " . BASE_URL . "/views/students/index.php?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fclose($handle);
    error_log($e->getMessage());
    header("Location: " . BASE_URL . "/views/students/import.php?error=" . urlencode('System Error: ' . $e->getMessage()));
    exit;
}
