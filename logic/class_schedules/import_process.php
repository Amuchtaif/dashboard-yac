<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php");
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php?error=Upload failed or no file selected");
    exit;
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === false) {
    header("Location: " . BASE_URL . "/views/class_schedules/import.php?error=Could not open file");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$errorCount = 0;
$errors = [];
$rowNumber = 0;

try {
    $conn->beginTransaction();

    // 1. Get raw content and clean it
    $content = file_get_contents($file);

    // Remove UTF-8 BOM if present
    $bom = pack('H*', 'EFBBBF');
    $content = preg_replace("/^$bom/", '', $content);

    // 2. Normalize and split into lines
    $content = str_replace("\r\n", "\n", $content);
    $content = str_replace("\r", "\n", $content);
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function ($l) {
        return trim($l) !== ''; });

    if (empty($lines)) {
        throw new Exception("File kosong atau tidak terbaca.");
    }

    // 3. Detect Delimiter from the first line (header)
    $headerLine = trim($lines[0]);
    // If entire line is wrapped in quotes like "A,B,C,D", unwrap it first for detection
    if (str_starts_with($headerLine, '"') && str_ends_with($headerLine, '"')) {
        $headerLine = substr($headerLine, 1, -1);
        $headerLine = str_replace('""', '"', $headerLine);
    }

    $delimiters = [',', ';', "\t"];
    $delimiter = ',';
    $maxCount = -1;

    foreach ($delimiters as $d) {
        $count = substr_count($headerLine, $d);
        if ($count > $maxCount) {
            $maxCount = $count;
            $delimiter = $d;
        }
    }

    // 4. Process each row
    $startIndex = 1; // Skip header
    for ($i = $startIndex; $i < count($lines); $i++) {
        $rowNumber = $i + 1;
        $line = trim($lines[$i]);
        if (empty($line))
            continue;

        // CRITICAL FIX: If Excel wrapped the whole line in quotes: "Day,Unit,Grade..."
        if (str_starts_with($line, '"') && str_ends_with($line, '"')) {
            // Check if it's a "faked" CSV line where the whole thing is one quoted string
            // We strip leading/trailing quote and unescape double-double quotes
            $testLine = substr($line, 1, -1);
            $testLine = str_replace('""', '"', $testLine);
            $data = str_getcsv($testLine, $delimiter);
        } else {
            $data = str_getcsv($line, $delimiter);
        }

        if (count($data) < 7) {
            $errorCount++;
            $preview = htmlspecialchars(substr($line, 0, 50));
            $errors[] = "Baris $rowNumber: Kolom tidak lengkap (Hanya " . count($data) . " kolom). Isi: [$preview]. Pastikan file disimpan sebagai CSV (Comma Delimited).";
            continue;
        }

        $day = trim($data[0]);
        $unit_name = trim($data[1], " \t\n\r\0\x0B\"");
        $grade_name = trim($data[2], " \t\n\r\0\x0B\"");
        $subject_name = trim($data[3], " \t\n\r\0\x0B\"");
        $teacher_name = trim($data[4], " \t\n\r\0\x0B\"");
        $start_period = trim($data[5], " \t\n\r\0\x0B\"");
        $end_period = trim($data[6] ?? $data[5], " \t\n\r\0\x0B\"");
        $ay_name = trim($data[7] ?? '', " \t\n\r\0\x0B\"");

        // 1. Resolve Academic Year
        $ay_id = null;
        if ($ay_name) {
            $stmt = $conn->prepare("SELECT id FROM academic_years WHERE name = ? LIMIT 1");
            $stmt->execute([$ay_name]);
            $ay_id = $stmt->fetchColumn();
        }
        if (!$ay_id) {
            // Pick active year as fallback
            $ay_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
        }
        if (!$ay_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Tahun Akademik '$ay_name' tidak ditemukan/aktif.";
            continue;
        }

        // 2. Resolve Unit
        $stmt = $conn->prepare("SELECT id FROM education_units WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $unit_name . '%']);
        $unit_id = $stmt->fetchColumn();
        if (!$unit_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Unit '$unit_name' tidak ditemukan.";
            continue;
        }

        // 3. Resolve Grade Level
        $stmt = $conn->prepare("SELECT id FROM grade_levels WHERE name LIKE ? AND education_unit_id = ? LIMIT 1");
        $stmt->execute(['%' . $grade_name . '%', $unit_id]);
        $grade_id = $stmt->fetchColumn();
        if (!$grade_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Kelas '$grade_name' tidak ditemukan di unit '$unit_name'.";
            continue;
        }

        // 4. Resolve Subject
        $stmt = $conn->prepare("SELECT id FROM subjects WHERE name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $subject_name . '%']);
        $subject_id = $stmt->fetchColumn();
        if (!$subject_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Mata Pelajaran '$subject_name' tidak ditemukan.";
            continue;
        }

        // 5. Resolve Teacher (Improved: Partial Match/LIKE)
        $stmt = $conn->prepare("SELECT id FROM employees WHERE full_name LIKE ? AND status = 'active' ORDER BY LENGTH(full_name) ASC LIMIT 1");
        $stmt->execute(['%' . $teacher_name . '%']);
        $teacher_id = $stmt->fetchColumn();
        if (!$teacher_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Guru '$teacher_name' tidak ditemukan.";
            continue;
        }

        // 6. Resolve Periods
        $stmt = $conn->prepare("SELECT id FROM lesson_periods WHERE education_unit_id = ? AND period_number = ? LIMIT 1");
        $stmt->execute([$unit_id, $start_period]);
        $lp_id = $stmt->fetchColumn();

        $stmt->execute([$unit_id, $end_period]);
        $lp_end_id = $stmt->fetchColumn();

        if (!$lp_id) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Jam pelajaran ke-$start_period tidak ditemukan untuk unit '$unit_name'.";
            continue;
        }

        // --- NEW: Calculate day_of_week ---
        $day_map = [
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
            'Sunday' => 7
        ];
        $day_of_week = $day_map[$day] ?? 0;

        // 7. Duplicate Check (to avoid Integrity constraint violation)
        $stmtCheck = $conn->prepare("
            SELECT id FROM class_schedules 
            WHERE academic_year_id = :ay 
              AND grade_level_id = :grade 
              AND day_of_week = :dow 
              AND lesson_period_id = :lp 
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':ay' => $ay_id,
            ':grade' => $grade_id,
            ':dow' => $day_of_week,
            ':lp' => $lp_id
        ]);

        if ($stmtCheck->fetch()) {
            $errorCount++;
            $errors[] = "Baris $rowNumber: Jadwal sudah ada untuk " . htmlspecialchars("$grade_name di hari $day jam ke-$start_period") . ". Baris ini dilewati.";
            continue;
        }

        // 8. Insert
        $stmt = $conn->prepare("
            INSERT INTO class_schedules (
                academic_year_id, employee_id, subject_id, grade_level_id, 
                lesson_period_id, end_lesson_period_id, day, day_of_week
            ) VALUES (
                :ay, :emp, :sub, :grade, :lp, :lp_end, :day, :dow
            )
        ");
        $stmt->execute([
            ':ay' => $ay_id,
            ':emp' => $teacher_id,
            ':sub' => $subject_id,
            ':grade' => $grade_id,
            ':lp' => $lp_id,
            ':lp_end' => $lp_end_id ?: $lp_id,
            ':day' => $day,
            ':dow' => $day_of_week
        ]);

        $successCount++;
    }

    $conn->commit();
    // fclose($handle); // Removed as we use string processing now


    $msg = "Import Berhasil! $successCount jadwal ditambahkan.";
    if ($errorCount > 0) {
        $msg .= " Terdapat $errorCount baris bermasalah.";
        // Store errors in session to show them?
        $_SESSION['import_errors'] = $errors;
    }

    header("Location: " . BASE_URL . "/views/class_schedules/index.php?success=" . urlencode($msg));
    exit;

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    fclose($handle);
    header("Location: " . BASE_URL . "/views/class_schedules/import.php?error=" . urlencode('Error Sistem: ' . $e->getMessage()));
    exit;
}
