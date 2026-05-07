<?php
// logic/inventory/export_csv.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// Fetch All Locations once for breadcrumbs
$locStmt = $conn->query("SELECT id, name, parent_id FROM inventory_locations");
$locs = $locStmt->fetchAll(PDO::FETCH_ASSOC);
$locMap = [];
foreach ($locs as $l) {
    $locMap[$l['id']] = $l;
}

function getBreadcrumb($map, $locId) {
    if (!isset($map[$locId])) return "-";
    $path = [];
    $curr = $locId;
    while ($curr != null) {
        $path[] = $map[$curr]['name'];
        $curr = $map[$curr]['parent_id'];
    }
    return implode(" > ", array_reverse($path));
}

// Fetch Items
$query = "
    SELECT i.*, l.name as leaf_location 
    FROM inventory_items i
    LEFT JOIN inventory_locations l ON i.location_id = l.id
    ORDER BY l.name ASC, i.name ASC
";
$stmt = $conn->query($query);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data_inventaris_' . date('Y-m-d_His') . '.csv');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, ['No', 'Kode Barang', 'Nama Barang', 'Lokasi (Lengkap)', 'Qty', 'Satuan', 'Kondisi', 'Sumber Dana', 'Tanggal Pembelian', 'Deskripsi']);

$no = 1;
foreach ($items as $row) {
    fputcsv($output, [
        $no++,
        $row['item_code'],
        $itName = $row['name'],
        getBreadcrumb($locMap, $row['location_id']),
        $row['qty'],
        $row['item_unit'] ?: 'Pcs',
        $row['item_condition'],
        $row['funding_source'],
        $row['purchase_date'],
        $row['description']
    ]);
}

fclose($output);
exit;
?>
