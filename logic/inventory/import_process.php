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
$header = fgetcsv($handle, 1000, ","); // Skip header

$db = new Database();
$conn = $db->getConnection();

$successCount = 0;
$rowNum = 0;

try {
    $conn->beginTransaction();

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $rowNum++;
        
        // Expected format (same as export): 
        // No, Code, Name, Full Location, Qty, Unit, Condition, Desc
        // 0   1     2     3               4    5     6          7
        if (count($data) < 4) continue;

        $codeFromCsv = trim($data[1]);
        $name = trim($data[2]);
        $fullLocName = trim($data[3]);
        $qty = (int)trim($data[4]);
        $unit = trim($data[5]) ?: 'Pcs';
        $condition = trim($data[6]) ?: 'Baik';
        $desc = trim($data[7]);

        if (empty($name) || empty($fullLocName)) continue;

        // Find location by full name or leaf name
        // We split by ' > ' if it's there
        $locParts = explode(' > ', $fullLocName);
        $leafName = end($locParts);

        $stmtLoc = $conn->prepare("SELECT id, name FROM inventory_locations WHERE name = ? LIMIT 1");
        $stmtLoc->execute([$leafName]);
        $loc = $stmtLoc->fetch();

        if (!$loc) {
            // If location not found, we skip or put in Unassigned
            // For now, let's skip
            continue;
        }

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
