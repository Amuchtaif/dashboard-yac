<?php
// logic/inventory/import_process.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    redirect('views/inventory/items.php');
}

ini_set('auto_detect_line_endings', true);

$file = $_FILES['csv_file']['tmp_name'];
$content = file_get_contents($file);

if ($content === false) {
    redirect("views/inventory/items.php?error=" . urlencode("Gagal membaca file."));
}

// =====================
// NORMALIZE ENCODING
// =====================
$encoding = mb_detect_encoding($content, 'UTF-8, UTF-16LE, UTF-16BE, ISO-8859-1, Windows-1252', true);
if ($encoding && $encoding !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);
}

// Remove BOM
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

// Normalisasi line ending
$content = str_replace(["\r\n", "\r"], "\n", $content);

// =====================
// HANDLE STREAM
// =====================
$handle = fopen('php://temp', 'r+');
fwrite($handle, $content);
rewind($handle);

// =====================
// DETECT DELIMITER (REAL PARSING)
// =====================
$testComma = fgetcsv($handle, 0, ',');
rewind($handle);
$testSemicolon = fgetcsv($handle, 0, ';');
rewind($handle);

$delimiter = (count($testSemicolon) > count($testComma)) ? ';' : ',';

// =====================
// READ HEADER
// =====================
$header = fgetcsv($handle, 0, $delimiter);

// fallback delimiter
if (!$header || count($header) < 2) {
    rewind($handle);
    $delimiter = ($delimiter === ',') ? ';' : ',';
    $header = fgetcsv($handle, 0, $delimiter);
}

// final check
if (!$header || count($header) < 2) {
    fclose($handle);
    redirect("views/inventory/items.php?error=" . urlencode("Header CSV tidak terbaca."));
}

// DEBUG HEADER (aktifkan kalau perlu)
// error_log("HEADER => " . json_encode($header));

// =====================
// DB INIT
// =====================
$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$rowNum = 1;
$skippedInfo = [];

try {
    $conn->beginTransaction();

    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $rowNum++;

        // =====================
        // CLEAN DATA
        // =====================
        if (is_array($data)) {
            $data = array_map(function ($v) {
                return trim(str_replace(["\r", "\n"], '', $v));
            }, $data);
        }

        // =====================
        // SKIP EMPTY
        // =====================
        if (!$data || (count($data) === 1 && $data[0] === '')) {
            continue;
        }

        // =====================
        // FALLBACK PARSE (INI KUNCI)
        // =====================
        if (count($data) === 1) {
            $rawLine = $data[0];

            // coba parse ulang
            $parsed = str_getcsv($rawLine, $delimiter);

            if (count($parsed) > 1) {
                $data = $parsed;
            }
        }

        // DEBUG (aktifkan kalau perlu)
        // error_log("ROW $rowNum => " . json_encode($data) . " | COUNT=" . count($data));

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
        $name = trim($data[2] ?? '');
        $fullLocName = trim($data[3] ?? '');
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
    fclose($handle);

    if ($successCount > 0) {
        redirect("views/inventory/items.php?success=" . urlencode("Berhasil impor $successCount data."));
    } else {
        $msg = !empty($skippedInfo) ? $skippedInfo[0] : "Tidak ada data valid.";
        redirect("views/inventory/items.php?error=" . urlencode($msg));
    }

} catch (Exception $e) {
    if ($conn->inTransaction())
        $conn->rollBack();
    if (isset($handle))
        fclose($handle);

    redirect("views/inventory/items.php?error=" . urlencode("Error: " . $e->getMessage()));
}
?>