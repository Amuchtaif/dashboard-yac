<?php
// api/calendar/import.php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

check_login();

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? ($_POST['action'] ?? 'preview');

switch ($action) {
    case 'preview':
        handlePreview($conn);
        break;
    case 'confirm':
        handleConfirm($conn, $user_id);
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
        break;
}

function handlePreview($conn) {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau terjadi kesalahan upload']);
        return;
    }

    $fileTmp = $_FILES['file']['tmp_name'];
    $fileName = $_FILES['file']['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    $rowsData = [];

    if (in_array($fileExt, ['xlsx', 'xls']) && class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($fileTmp);
            $worksheet = $spreadsheet->getActiveSheet();
            $rawRows = $worksheet->toArray();
            if (count($rawRows) > 1) {
                // Header is first row
                for ($i = 1; $i < count($rawRows); $i++) {
                    $r = $rawRows[$i];
                    if (empty(array_filter($r))) continue; // skip empty rows
                    $rowsData[] = [
                        'title' => trim($r[0] ?? ''),
                        'start_date' => trim($r[1] ?? ''),
                        'end_date' => trim($r[2] ?? ''),
                        'start_time' => trim($r[3] ?? ''),
                        'end_time' => trim($r[4] ?? ''),
                        'category' => trim($r[5] ?? ''),
                        'source_type' => trim($r[6] ?? ''),
                        'unit_name' => trim($r[7] ?? ''),
                        'location' => trim($r[8] ?? ''),
                        'description' => trim($r[9] ?? '')
                    ];
                }
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gagal membaca file Excel: ' . $e->getMessage()]);
            return;
        }
    } else {
        // Handle CSV
        if (($handle = fopen($fileTmp, "r")) !== false) {
            // Check UTF-8 BOM
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }
            $header = fgetcsv($handle, 1000, ",");
            while (($r = fgetcsv($handle, 1000, ",")) !== false) {
                if (empty(array_filter($r))) continue;
                $rowsData[] = [
                    'title' => trim($r[0] ?? ''),
                    'start_date' => trim($r[1] ?? ''),
                    'end_date' => trim($r[2] ?? ''),
                    'start_time' => trim($r[3] ?? ''),
                    'end_time' => trim($r[4] ?? ''),
                    'category' => trim($r[5] ?? ''),
                    'source_type' => trim($r[6] ?? ''),
                    'unit_name' => trim($r[7] ?? ''),
                    'location' => trim($r[8] ?? ''),
                    'description' => trim($r[9] ?? '')
                ];
            }
            fclose($handle);
        }
    }

    if (empty($rowsData)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File kosong atau tidak berisi data']);
        return;
    }

    // Fetch existing units map (name -> id)
    $unitsMap = [];
    $unitStmt = $conn->query("SELECT id, name FROM education_units");
    while ($u = $unitStmt->fetch(PDO::FETCH_ASSOC)) {
        $unitsMap[strtolower(trim($u['name']))] = $u['id'];
    }

    // Fetch active academic year
    $ayStmt = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    $active_ay_id = $ayStmt->fetchColumn() ?: null;

    $parsedRows = [];
    $validCount = 0;
    $duplicateCount = 0;
    $errorCount = 0;

    foreach ($rowsData as $idx => $row) {
        $rowNum = $idx + 2; // header is row 1
        $errors = [];
        $status = 'valid';

        // Title check
        if (empty($row['title'])) {
            $errors[] = "Nama Kegiatan wajib diisi";
        }

        // Start Date check & normalization
        $start_date = parseDate($row['start_date']);
        if (!$start_date) {
            $errors[] = "Tanggal Mulai tidak valid";
        }

        // End Date check & normalization
        $end_date = !empty($row['end_date']) ? parseDate($row['end_date']) : $start_date;
        if ($start_date && $end_date && $end_date < $start_date) {
            $errors[] = "Tanggal Selesai tidak boleh sebelum Tanggal Mulai";
        }

        // Source Type normalization
        $src_type = strtolower(str_replace(' ', '_', $row['source_type']));
        if (!in_array($src_type, ['yayasan', 'bidang_pendidikan', 'unit'])) {
            $src_type = 'bidang_pendidikan';
        }

        // Unit resolving
        $unit_id = null;
        if (!empty($row['unit_name'])) {
            $cleanUnit = strtolower(trim($row['unit_name']));
            if (isset($unitsMap[$cleanUnit])) {
                $unit_id = $unitsMap[$cleanUnit];
            } else {
                // Try fuzzy match
                foreach ($unitsMap as $uname => $uid) {
                    if (strpos($uname, $cleanUnit) !== false || strpos($cleanUnit, $uname) !== false) {
                        $unit_id = $uid;
                        break;
                    }
                }
            }
        }

        // Category default
        $category = !empty($row['category']) ? $row['category'] : 'Kegiatan Bidang Pendidikan';

        // Check Duplicates in DB
        if (empty($errors) && $start_date) {
            $dupStmt = $conn->prepare("SELECT id FROM academic_calendar WHERE title = ? AND start_date = ? LIMIT 1");
            $dupStmt->execute([$row['title'], $start_date]);
            if ($dupStmt->fetchColumn()) {
                $status = 'duplicate';
                $errors[] = "Data duplikat terdeteksi (sudah ada di database)";
                $duplicateCount++;
            }
        }

        if (!empty($errors) && $status !== 'duplicate') {
            $status = 'error';
            $errorCount++;
        } elseif ($status === 'valid') {
            $validCount++;
        }

        $parsedRows[] = [
            'row_num' => $rowNum,
            'title' => $row['title'],
            'start_date' => $start_date ?: $row['start_date'],
            'end_date' => $end_date ?: $row['end_date'],
            'start_time' => $row['start_time'] ?: null,
            'end_time' => $row['end_time'] ?: null,
            'category' => $category,
            'source_type' => $src_type,
            'unit_id' => $unit_id,
            'unit_name' => $row['unit_name'],
            'location' => $row['location'],
            'description' => $row['description'],
            'academic_year_id' => $active_ay_id,
            'status' => $status,
            'errors' => $errors
        ];
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'total' => count($parsedRows),
            'valid' => $validCount,
            'duplicate' => $duplicateCount,
            'error' => $errorCount
        ],
        'rows' => $parsedRows
    ]);
}

function handleConfirm($conn, $user_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $rows = $input['rows'] ?? [];

        if (empty($rows)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada data untuk diimport']);
            return;
        }

        $insertedCount = 0;
        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO academic_calendar 
            (title, description, start_date, end_date, start_time, end_time, location, category, source_type, unit_id, academic_year_id, semester, visibility, status, color, is_holiday, created_by, updated_by) 
            VALUES 
            (:title, :description, :start_date, :end_date, :start_time, :end_time, :location, :category, :source_type, :unit_id, :academic_year_id, :semester, :visibility, :status, :color, :is_holiday, :created_by, :updated_by)");

        foreach ($rows as $r) {
            // Only import valid rows (skip duplicates & errors)
            if ($r['status'] !== 'valid') continue;

            $is_holiday = in_array($r['category'], ['Libur Nasional', 'Libur Sekolah', 'Cuti Bersama']) ? 1 : 0;
            $color = '#3b82f6';
            if ($r['category'] === 'Libur Nasional') $color = '#ef4444';
            elseif ($r['category'] === 'Libur Sekolah') $color = '#16a34a';
            elseif ($r['category'] === 'Rapat') $color = '#7c3aed';

            $stmt->execute([
                ':title' => $r['title'],
                ':description' => $r['description'] ?? '',
                ':start_date' => $r['start_date'],
                ':end_date' => !empty($r['end_date']) ? $r['end_date'] : $r['start_date'],
                ':start_time' => !empty($r['start_time']) ? $r['start_time'] : null,
                ':end_time' => !empty($r['end_time']) ? $r['end_time'] : null,
                ':location' => $r['location'] ?? '',
                ':category' => $r['category'] ?? 'Kegiatan Bidang Pendidikan',
                ':source_type' => $r['source_type'] ?? 'bidang_pendidikan',
                ':unit_id' => !empty($r['unit_id']) ? $r['unit_id'] : null,
                ':academic_year_id' => !empty($r['academic_year_id']) ? $r['academic_year_id'] : null,
                ':semester' => 'Ganjil',
                ':visibility' => 'public',
                ':status' => 'scheduled',
                ':color' => $color,
                ':is_holiday' => $is_holiday,
                ':created_by' => $user_id,
                ':updated_by' => $user_id
            ]);
            $insertedCount++;
        }

        $conn->commit();

        if (class_exists('Logger')) {
            Logger::log('info', 'activity', 'Kalender Akademik', 'Import Agenda', "Mengimport {$insertedCount} agenda ke Kalender Akademik", [
                'user_id' => $user_id,
                'count' => $insertedCount
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => "Berhasil mengimport {$insertedCount} data agenda ke Kalender Akademik"
        ]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengimport data: ' . $e->getMessage()]);
    }
}

function parseDate($dateStr) {
    if (empty($dateStr)) return null;
    $dateStr = trim($dateStr);
    
    // Check YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
        return $dateStr;
    }
    // Check DD/MM/YYYY
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $dateStr, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    // Try strtotime
    $ts = strtotime($dateStr);
    if ($ts && $ts > 0) {
        return date('Y-m-d', $ts);
    }
    return null;
}
