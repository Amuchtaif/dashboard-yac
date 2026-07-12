<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/students/import.php");
    exit;
}

require_once '../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/students/import.php");
    exit;
}

$file_key = isset($_FILES['import_file']) ? 'import_file' : 'csv_file';
if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . BASE_URL . "/views/students/import.php?error=Gagal mengunggah file atau tidak ada file yang dipilih");
    exit;
}

$file = $_FILES[$file_key]['tmp_name'];

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$errorCount = 0;
$errors = [];
$rowNumber = 0;

$academic_year_id = $_POST['academic_year_id'] ?? null;
$unit_id = $_POST['unit_id'] ?? null;

if (empty($academic_year_id) || empty($unit_id)) {
    header("Location: " . BASE_URL . "/views/students/import.php?error=" . urlencode("Harap pilih Tahun Ajaran dan Unit Pendidikan"));
    exit;
}

try {
    $conn->beginTransaction();

    // Load file using PhpSpreadsheet
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // Format expected: Nama Siswa, NISN, Kelas
    // Loop starts at 1 to skip header row
    for ($i = 1; $i < count($rows); $i++) {
        $rowNumber = $i + 1;
        $data = $rows[$i];

        // Skip completely empty rows
        if (empty($data) || (!isset($data[0]) && !isset($data[1]))) {
            continue;
        }

        $nama_siswa = isset($data[0]) ? trim($data[0]) : '';
        $nisn = isset($data[1]) ? trim($data[1]) : '';
        $kelas_nama = isset($data[2]) ? trim($data[2]) : '';
        $tempat_lahir = isset($data[3]) && trim($data[3]) !== '' ? trim($data[3]) : null;
        $tanggal_lahir_raw = isset($data[4]) && trim($data[4]) !== '' ? trim($data[4]) : null;
        $alamat = isset($data[5]) && trim($data[5]) !== '' ? trim($data[5]) : null;

        // If the row is partially filled but missing main columns
        if (empty($nama_siswa) && empty($nisn)) {
            continue;
        }

        $nama_siswa = ucwords(strtolower($nama_siswa));

        if (empty($nama_siswa) || empty($nisn)) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Nama atau NISN kosong.";
            continue;
        }

        // Parse and clean Date
        $tanggal_lahir = null;
        if (!empty($tanggal_lahir_raw)) {
            if (is_numeric($tanggal_lahir_raw)) {
                // It is a serial Excel date
                try {
                    $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($tanggal_lahir_raw);
                    $tanggal_lahir = $dt->format('Y-m-d');
                } catch (Exception $e) {
                    $tanggal_lahir = null;
                }
            } else {
                // Check if YYYY-MM-DD
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_lahir_raw)) {
                    $tanggal_lahir = $tanggal_lahir_raw;
                } else {
                    // Try DD-MM-YYYY or DD/MM/YYYY
                    $date_parsed = str_replace('/', '-', $tanggal_lahir_raw);
                    $parts = explode('-', $date_parsed);
                    if (count($parts) === 3) {
                        if (strlen($parts[0]) === 4) {
                            $tanggal_lahir = $parts[0] . '-' . sprintf('%02d', $parts[1]) . '-' . sprintf('%02d', $parts[2]);
                        } else {
                            $tanggal_lahir = $parts[2] . '-' . sprintf('%02d', $parts[1]) . '-' . sprintf('%02d', $parts[0]);
                        }
                    }
                }
            }
        }

        // --- Step A: Student UPSERT ---
        // Check if student exists
        $stmtCheck = $conn->prepare("SELECT id FROM students WHERE nomor_induk = :nisn");
        $stmtCheck->execute([':nisn' => $nisn]);
        $existingStudent = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existingStudent) {
            $student_id = $existingStudent['id'];
            // Update Name, Tempat Lahir, Tanggal Lahir, Alamat if provided
            $stmtUpdate = $conn->prepare("
                UPDATE students 
                SET nama_siswa = :nama,
                    tempat_lahir = COALESCE(:tempat, tempat_lahir),
                    tanggal_lahir = COALESCE(:tanggal, tanggal_lahir),
                    alamat = COALESCE(:alamat, alamat)
                WHERE id = :id
            ");
            $stmtUpdate->execute([
                ':nama' => $nama_siswa, 
                ':tempat' => $tempat_lahir,
                ':tanggal' => $tanggal_lahir,
                ':alamat' => $alamat,
                ':id' => $student_id
            ]);
        } else {
            // Insert New
            $stmtInsert = $conn->prepare("
                INSERT INTO students (nama_siswa, nomor_induk, tempat_lahir, tanggal_lahir, alamat, status, created_at) 
                VALUES (:nama, :nisn, :tempat, :tanggal, :alamat, 'Aktif', NOW())
            ");
            $stmtInsert->execute([
                ':nama' => $nama_siswa, 
                ':nisn' => $nisn,
                ':tempat' => $tempat_lahir,
                ':tanggal' => $tanggal_lahir,
                ':alamat' => $alamat
            ]);
            $student_id = $conn->lastInsertId();
        }

        // --- Step B: Class Logic ---
        $class_id = null;
        if (!empty($kelas_nama)) {
            // Check if class exists in the selected Unit
            $stmtClass = $conn->prepare("SELECT id FROM grade_levels WHERE name = :name AND education_unit_id = :unit_id LIMIT 1");
            $stmtClass->execute([':name' => $kelas_nama, ':unit_id' => $unit_id]);
            $existingClass = $stmtClass->fetch(PDO::FETCH_ASSOC);

            if ($existingClass) {
                $class_id = $existingClass['id'];
            } else {
                // Create Class under the selected Unit
                $stmtNewClass = $conn->prepare("INSERT INTO grade_levels (name, level, education_unit_id, created_at) VALUES (:name, 0, :unit_id, NOW())");
                $stmtNewClass->execute([':name' => $kelas_nama, ':unit_id' => $unit_id]);
                $class_id = $conn->lastInsertId();
            }
        }

        // --- Step C: Assignment ---
        if ($class_id) {
            // Check existing placement for this year
            $stmtHistCheck = $conn->prepare("SELECT id FROM student_class_history WHERE student_id = :sid AND academic_year_id = :yid");
            $stmtHistCheck->execute([':sid' => $student_id, ':yid' => $academic_year_id]);
            $hist = $stmtHistCheck->fetch(PDO::FETCH_ASSOC);

            if ($hist) {
                // Update class if different
                $stmtHistUpdate = $conn->prepare("UPDATE student_class_history SET class_id = :cid WHERE id = :hid");
                $stmtHistUpdate->execute([':cid' => $class_id, ':hid' => $hist['id']]);
            } else {
                // Insert with joined_at
                $stmtHistInsert = $conn->prepare("INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, joined_at, created_at) VALUES (:sid, :cid, :yid, 'ACTIVE', CURDATE(), NOW())");
                $stmtHistInsert->execute([':sid' => $student_id, ':cid' => $class_id, ':yid' => $academic_year_id]);
            }
        }

        $successCount++;
    }

    $conn->commit();

    $msg = "Impor Berhasil! Memproses: $successCount siswa.";
    if ($errorCount > 0) {
        $msg .= " Dengan $errorCount kesalahan.";
    }

    header("Location: " . BASE_URL . "/views/students/index.php?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log($e->getMessage());
    header("Location: " . BASE_URL . "/views/students/import.php?error=" . urlencode('Kesalahan Sistem: ' . $e->getMessage()));
    exit;
}
