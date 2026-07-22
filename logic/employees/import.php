<?php
// logic/employees/import.php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

check_login();
check_permission('manage_employees');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['import_errors'] = ["Gagal mengunggah file. Silakan coba lagi."];
        header("Location: ../../views/employees/import.php");
        exit;
    }

    $file_path = $file['tmp_name'];
    
    try {
        $spreadsheet = IOFactory::load($file_path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
    } catch (\Exception $e) {
        $_SESSION['import_errors'] = ["Gagal membaca format file: " . $e->getMessage()];
        header("Location: ../../views/employees/import.php");
        exit;
    }

    if (count($rows) <= 1) {
        $_SESSION['import_errors'] = ["File Excel/CSV kosong atau hanya berisi baris header."];
        header("Location: ../../views/employees/import.php");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    // 1. Fetch reference data for existence validation
    $divisions = $conn->query("SELECT id FROM divisions")->fetchAll(PDO::FETCH_COLUMN);
    $units = $conn->query("SELECT id FROM units")->fetchAll(PDO::FETCH_COLUMN);
    $positions = $conn->query("SELECT id FROM positions")->fetchAll(PDO::FETCH_COLUMN);
    $schedules = $conn->query("SELECT id FROM work_schedules")->fetchAll(PDO::FETCH_COLUMN);

    // 2. Fetch existing unique keys to avoid DB roundtrips per row
    $existing_niks = $conn->query("SELECT nik FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    $existing_nik_map = array_fill_keys($existing_niks, true);

    $existing_emails = $conn->query("SELECT email FROM employees")->fetchAll(PDO::FETCH_COLUMN);
    $existing_email_map = array_fill_keys($existing_emails, true);

    $errors = [];
    $to_import = [];
    $row_index = 0;

    // Track duplicates within the uploaded file itself
    $file_niks = [];
    $file_emails = [];

    // Columns index mapping:
    // A: No, B: NIK, C: Nama Lengkap, D: Email, E: No. Telepon, F: Alamat, G: Gender, H: ID Bidang, I: ID Unit, J: ID Jabatan, K: ID Jadwal Kerja, L: Password
    foreach ($rows as $index => $row) {
        $row_index++;
        if ($row_index === 1) {
            // Validate headers or just skip header row
            continue;
        }

        // Clean values
        $nik = isset($row['B']) ? trim((string)$row['B']) : '';
        $full_name = isset($row['C']) ? trim((string)$row['C']) : '';
        $email = isset($row['D']) ? trim((string)$row['D']) : '';
        $phone = isset($row['E']) ? trim((string)$row['E']) : '';
        $address = isset($row['F']) ? trim((string)$row['F']) : '';
        $gender_raw = isset($row['G']) ? trim((string)$row['G']) : '';
        $division_id = isset($row['H']) && trim((string)$row['H']) !== '' ? (int)$row['H'] : null;
        $unit_id = isset($row['I']) && trim((string)$row['I']) !== '' ? (int)$row['I'] : null;
        $position_id = isset($row['J']) && trim((string)$row['J']) !== '' ? (int)$row['J'] : null;
        $schedule_id = isset($row['K']) && trim((string)$row['K']) !== '' ? (int)$row['K'] : null;
        $password = isset($row['L']) ? trim((string)$row['L']) : '';

        // Skip completely empty rows
        if (empty($nik) && empty($full_name) && empty($email) && empty($phone) && empty($address) && empty($gender_raw) && empty($password)) {
            continue;
        }

        $row_errors = [];

        // Required Field Validations
        if (empty($nik)) {
            $row_errors[] = "NIK wajib diisi.";
        }
        if (empty($full_name)) {
            $row_errors[] = "Nama Lengkap wajib diisi.";
        }
        if (empty($email)) {
            $row_errors[] = "Email wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $row_errors[] = "Format Email tidak valid.";
        }
        if (empty($phone)) {
            $row_errors[] = "No. Telepon wajib diisi.";
        }
        if (empty($address)) {
            $row_errors[] = "Alamat wajib diisi.";
        }
        
        // Gender validation and mapping
        $gender = null;
        if (empty($gender_raw)) {
            $row_errors[] = "Jenis Kelamin wajib diisi.";
        } else {
            $gender_lower = strtolower($gender_raw);
            if (in_array($gender_lower, ['l', 'laki-laki', 'laki laki', 'male', 'm'])) {
                $gender = 'Male';
            } elseif (in_array($gender_lower, ['p', 'perempuan', 'female', 'f'])) {
                $gender = 'Female';
            } else {
                $row_errors[] = "Jenis Kelamin tidak valid (Harus Male/Female atau Laki-laki/Perempuan).";
            }
        }

        if ($division_id === null) {
            $row_errors[] = "ID Bidang wajib diisi.";
        }
        if ($position_id === null) {
            $row_errors[] = "ID Jabatan wajib diisi.";
        }
        if (empty($password)) {
            $row_errors[] = "Password wajib diisi.";
        }

        // Integrity Constraints & Database ID Verification
        if ($division_id !== null && !in_array($division_id, $divisions)) {
            $row_errors[] = "ID Bidang ($division_id) tidak terdaftar di sistem.";
        }
        if ($unit_id !== null && !in_array($unit_id, $units)) {
            $row_errors[] = "ID Unit ($unit_id) tidak terdaftar di sistem.";
        }
        if ($position_id !== null && !in_array($position_id, $positions)) {
            $row_errors[] = "ID Jabatan ($position_id) tidak terdaftar di sistem.";
        }
        if ($schedule_id !== null && !in_array($schedule_id, $schedules)) {
            $row_errors[] = "ID Jadwal Kerja ($schedule_id) tidak terdaftar di sistem.";
        }

        // NIK Uniqueness (within database and within file)
        if (!empty($nik)) {
            if (isset($existing_nik_map[$nik])) {
                $row_errors[] = "NIK '$nik' sudah terdaftar di sistem.";
            }
            if (isset($file_niks[$nik])) {
                $row_errors[] = "NIK '$nik' ganda dalam file import (Baris " . $file_niks[$nik] . " & Baris " . $row_index . ").";
            } else {
                $file_niks[$nik] = $row_index;
            }
        }

        // Email Uniqueness (within database and within file)
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if (isset($existing_email_map[$email])) {
                $row_errors[] = "Email '$email' sudah terdaftar di sistem.";
            }
            if (isset($file_emails[$email])) {
                $row_errors[] = "Email '$email' ganda dalam file import (Baris " . $file_emails[$email] . " & Baris " . $row_index . ").";
            } else {
                $file_emails[$email] = $row_index;
            }
        }

        if (count($row_errors) > 0) {
            $errors[] = "Baris $row_index: " . implode(" ", $row_errors);
        } else {
            $to_import[] = [
                'nik' => $nik,
                'full_name' => $full_name,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'gender' => $gender,
                'division_id' => $division_id,
                'unit_id' => $unit_id,
                'position_id' => $position_id,
                'schedule_id' => $schedule_id,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ];
        }
    }

    if (count($errors) > 0) {
        $_SESSION['import_errors'] = $errors;
        header("Location: ../../views/employees/import.php?error=Impor dibatalkan karena kesalahan data.");
        exit;
    }

    // Database insertion wrapped in transaction
    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("
            INSERT INTO employees 
            (nik, full_name, email, phone_number, address, gender, password, division_id, unit_id, position_id, schedule_id) 
            VALUES 
            (:nik, :name, :email, :phone, :address, :gender, :pass, :div, :unit, :pos, :sched)
        ");

        foreach ($to_import as $emp) {
            $stmt->execute([
                ':nik' => $emp['nik'],
                ':name' => $emp['full_name'],
                ':email' => $emp['email'],
                ':phone' => $emp['phone'],
                ':address' => $emp['address'],
                ':gender' => $emp['gender'],
                ':pass' => $emp['password'],
                ':div' => $emp['division_id'],
                ':unit' => $emp['unit_id'],
                ':pos' => $emp['position_id'],
                ':sched' => $emp['schedule_id'],
            ]);
        }

        $conn->commit();
        $count = count($to_import);

        Logger::activity(
            'Pegawai',
            'IMPORT',
            "Mengimpor $count data pegawai baru",
            [
                'table' => 'employees',
                'new_data' => ['import_count' => $count]
            ]
        );

        header("Location: ../../views/employees/index.php?success=Berhasil mengimpor $count data pegawai.");
        exit;
    } catch (\PDOException $e) {
        $conn->rollBack();
        $_SESSION['import_errors'] = ["Kesalahan Database saat menyimpan: " . $e->getMessage()];
        header("Location: ../../views/employees/import.php?error=Gagal menyimpan ke database.");
        exit;
    }
} else {
    header("Location: ../../views/employees/import.php");
    exit;
}
?>
