<?php
// logic/inventory/import_process.php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    redirect('views/inventory/items.php');
}

$file = $_FILES['csv_file']['tmp_name'];
$originalName = $_FILES['csv_file']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

if (!is_uploaded_file($file)) {
    redirect("views/inventory/items.php?error=" . urlencode("Gagal membaca file unggahan."));
}

// =====================
// DB INIT
// =====================
$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$rowNum = 1;
$skippedInfo = [];

try {
    // =====================
    // LOAD WITH SPREADSHEET
    // =====================
    if ($extension === 'csv') {
        $reader = IOFactory::createReader('Csv');
        // Auto-detect delimiter: check for semicolon or comma
        $reader->setDelimiter(',');
        $firstLine = fgets(fopen($file, 'r'));
        if ($firstLine !== false && strpos($firstLine, ';') !== false && strpos($firstLine, ',') === false) {
            $reader->setDelimiter(';');
        }
        $spreadsheet = $reader->load($file);
    } else {
        // XLSX or XLS
        $spreadsheet = IOFactory::load($file);
    }

    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray(null, true, true, false);

    if (empty($rows) || count($rows) <= 1) {
        redirect("views/inventory/items.php?error=" . urlencode("File kosong atau tidak memiliki data."));
    }

    $conn->beginTransaction();

    foreach ($rows as $index => $data) {
        $rowNum = $index + 1;

        // Skip header row
        if ($rowNum === 1) {
            continue;
        }

        // =====================
        // CLEAN DATA
        // =====================
        if (is_array($data)) {
            $data = array_map(function ($v) {
                return $v !== null ? trim(str_replace(["\r", "\n"], '', (string)$v)) : '';
            }, $data);
        }

        // =====================
        // SKIP EMPTY ROWS
        // =====================
        if (empty($data) || (count($data) === 1 && $data[0] === '')) {
            continue;
        }

        // Check if important fields are completely blank
        $name = trim($data[2] ?? '');
        $fullLocName = trim($data[3] ?? '');
        if ($name === '' && $fullLocName === '') {
            continue; // Skip empty trailing rows
        }

        // =====================
        // VALIDASI MINIMAL
        // =====================
        if (count($data) < 4) {
            $skippedInfo[] = "Baris $rowNum: Format kolom tidak sesuai (" . count($data) . " kolom).";
            continue;
        }

        // =====================
        // MAPPING DATA
        // =====================
        $codeFromCsv = trim($data[1] ?? '');
        $qty = is_numeric(trim($data[4] ?? '')) ? (int) $data[4] : 1;
        $unit = trim($data[5] ?? '') ?: 'Unit';
        $condition = trim($data[6] ?? '') ?: 'Baik';
        $fundingSource = trim($data[7] ?? '');
        $purchaseDateRaw = trim($data[8] ?? '');
        $desc = trim($data[9] ?? '');

        // Handle Purchase Date / Year
        $purchaseDate = null;
        if (!empty($purchaseDateRaw)) {
            if (is_numeric($purchaseDateRaw) && strlen($purchaseDateRaw) === 4) {
                $purchaseDate = $purchaseDateRaw . "-01-01";
            } else {
                $timestamp = strtotime($purchaseDateRaw);
                if ($timestamp) $purchaseDate = date('Y-m-d', $timestamp);
            }
        }

        if ($name === '' || $fullLocName === '') {
            $skippedInfo[] = "Baris $rowNum: Nama atau lokasi kosong.";
            continue;
        }

        // =====================
        // HANDLE LOKASI
        // =====================
        $locParts = explode(' > ', $fullLocName);
        $leafName = trim(end($locParts));

        $stmtLoc = $conn->prepare("SELECT id, name FROM inventory_locations WHERE name = ? LIMIT 1");
        $stmtLoc->execute([$leafName]);
        $loc = $stmtLoc->fetch();

        // fallback ambil kata terakhir
        if (!$loc && strpos($leafName, ' ') !== false) {
            $subParts = explode(' ', $leafName);
            $shortName = trim(end($subParts));
            $stmtLoc->execute([$shortName]);
            $loc = $stmtLoc->fetch();
        }

        if (!$loc) {
            $skippedInfo[] = "Baris $rowNum: Lokasi '$leafName' tidak ditemukan.";
            continue;
        }

        $locId = $loc['id'];
        $locRealName = $loc['name'];

        // =====================
        // INSERT DATA
        // =====================
        $stmtIns = $conn->prepare("
            INSERT INTO inventory_items 
            (name, item_code, location_id, qty, item_unit, item_condition, funding_source, purchase_date, description) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtIns->execute([
            $name,
            $codeFromCsv,
            $locId,
            $qty,
            $unit,
            $condition,
            $fundingSource,
            $purchaseDate,
            $desc
        ]);

        $itemId = $conn->lastInsertId();

        // =====================
        // AUTO GENERATE CODE
        // =====================
        if ($codeFromCsv === '') {
            $newCode = generateItemCodeV2($conn, $locId, $locRealName, $name, $itemId);
            $conn->prepare("UPDATE inventory_items SET item_code = ? WHERE id = ?")
                ->execute([$newCode, $itemId]);
        }

        $successCount++;
    }

    $conn->commit();

    if ($successCount > 0) {
        redirect("views/inventory/items.php?success=" . urlencode("Berhasil impor $successCount data."));
    } else {
        $msg = !empty($skippedInfo) ? $skippedInfo[0] : "Tidak ada data valid.";
        redirect("views/inventory/items.php?error=" . urlencode($msg));
    }

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    redirect("views/inventory/items.php?error=" . urlencode("Error: " . $e->getMessage()));
}
?>