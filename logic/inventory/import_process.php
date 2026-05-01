<?php
// logic/inventory/import_process.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['csv_file'])) {
    redirect('views/inventory/items.php');
}

$file = $_FILES['csv_file']['tmp_name'];
$handle = fopen($file, "r");

// Detect delimiter (comma or semicolon)
$firstLine = fgets($handle);
$commaCount = substr_count($firstLine, ',');
$semicolonCount = substr_count($firstLine, ';');
$delimiter = ($semicolonCount > $commaCount) ? ';' : ',';

// Reset file pointer and skip header
rewind($handle);
fgetcsv($handle, 1000, $delimiter); 

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$rowNum = 0;

try {
    $conn->beginTransaction();

    while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($data))) continue;

        // Expected format: No, Code, Name, Full Location, Qty, Unit, Condition, Desc
        if (count($data) < 4) continue;

        $codeFromCsv = isset($data[1]) ? trim($data[1]) : '';
        $name = isset($data[2]) ? trim($data[2]) : '';
        $fullLocName = isset($data[3]) ? trim($data[3]) : '';
        $qty = isset($data[4]) ? (int)trim($data[4]) : 0;
        $unit = (isset($data[5]) && trim($data[5])) ? trim($data[5]) : 'Pcs';
        $condition = (isset($data[6]) && trim($data[6])) ? trim($data[6]) : 'Baik';
        $desc = isset($data[7]) ? trim($data[7]) : '';

        if (empty($name) || empty($fullLocName)) continue;

        // Find location
        $locParts = explode(' > ', $fullLocName);
        $leafName = trim(end($locParts));

        $stmtLoc = $conn->prepare("SELECT id, name FROM inventory_locations WHERE name = ? LIMIT 1");
        $stmtLoc->execute([$leafName]);
        $loc = $stmtLoc->fetch();

        if (!$loc) continue;

        $locId = $loc['id'];
        $locRealName = $loc['name'];

        // Insert Item
        $stmtIns = $conn->prepare("INSERT INTO inventory_items (name, item_code, location_id, qty, item_unit, item_condition, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$name, $codeFromCsv, $locId, $qty, $unit, $condition, $desc]);
        $itemId = $conn->lastInsertId();

        // If code was empty, generate it
        if (empty($codeFromCsv)) {
            $newCode = generateItemCodeV2($conn, $locId, $locRealName, $name, $itemId);
            $conn->prepare("UPDATE inventory_items SET item_code = ? WHERE id = ?")->execute([$newCode, $itemId]);
        }

        $successCount++;
    }

    $conn->commit();
    fclose($handle);
    redirect("views/inventory/items.php?success=" . urlencode("Berhasil mengimpor $successCount data barang."));

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    fclose($handle);
    redirect("views/inventory/items.php?error=" . urlencode("Gagal impor: " . $e->getMessage()));
}
?>
