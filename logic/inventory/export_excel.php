<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Inventaris');

// Header style (Cyan-600)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0891B2'], // Cyan-600 matching Dashboard YAC theme
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E5E7EB'],
        ],
    ],
];

// Set columns headers
$headers = ['No', 'Kode Barang', 'Nama Barang', 'Lokasi (Lengkap)', 'Qty', 'Satuan', 'Kondisi', 'Sumber Dana', 'Tanggal Pembelian', 'Deskripsi'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}
$highestColumn = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(28);

// Populate Data
$rowNumber = 2;
$no = 1;
foreach ($items as $row) {
    $sheet->setCellValue('A' . $rowNumber, $no++);
    $sheet->setCellValueExplicit('B' . $rowNumber, $row['item_code'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $rowNumber, $row['name']);
    $sheet->setCellValue('D' . $rowNumber, getBreadcrumb($locMap, $row['location_id']));
    $sheet->setCellValue('E' . $rowNumber, $row['qty']);
    $sheet->setCellValue('F' . $rowNumber, $row['item_unit'] ?: 'Pcs');
    $sheet->setCellValue('G' . $rowNumber, $row['item_condition']);
    $sheet->setCellValue('H' . $rowNumber, $row['funding_source']);
    $sheet->setCellValue('I' . $rowNumber, $row['purchase_date']);
    $sheet->setCellValue('J' . $rowNumber, $row['description']);
    
    $sheet->getRowDimension($rowNumber)->setRowHeight(20);
    $rowNumber++;
}

// Apply styling to data rows
$highestRow = $sheet->getHighestRow();
if ($highestRow > 1) {
    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray($dataStyle);
    $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B2:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('E2:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('I2:I' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto fit column width
foreach (range('A', $highestColumn) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="data_inventaris_' . date('Y-m-d_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
