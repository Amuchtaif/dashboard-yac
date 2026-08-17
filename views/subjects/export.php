<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic (Mirrors Index) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE :search OR code LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch All Matching Data (No Limit)
$query = "SELECT * FROM subjects $where_sql ORDER BY name ASC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Mata Pelajaran');

// Header style
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
        'startColor' => ['rgb' => '0891B2'], // Cyan-600 to match theme
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

// Title Block
$sheet->setCellValue('A1', 'DATA MATA PELAJARAN');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getRowDimension('1')->setRowHeight(24);

if ($search) {
    $sheet->setCellValue('A2', "Filter pencarian: '$search'");
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
}

$startRow = $search ? 4 : 3;

// Set columns headers
$headers = ['No', 'Kode Mapel', 'Nama Mata Pelajaran', 'Kategori', 'Deskripsi'];
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . $startRow, $header);
    $col++;
}

$highestColumn = 'E';
$sheet->getStyle('A' . $startRow . ':' . $highestColumn . $startRow)->applyFromArray($headerStyle);
$sheet->getRowDimension((string)$startRow)->setRowHeight(28);

// Populate Data
$rowNumber = $startRow + 1;
$no = 1;

foreach ($subjects as $sub) {
    $sheet->setCellValue('A' . $rowNumber, $no++);
    $sheet->setCellValue('B' . $rowNumber, $sub['code']);
    $sheet->setCellValue('C' . $rowNumber, $sub['name']);
    $sheet->setCellValue('D' . $rowNumber, $sub['category'] ?: 'Umum');
    $sheet->setCellValue('E' . $rowNumber, $sub['description'] ?: '-');

    $sheet->getRowDimension((string)$rowNumber)->setRowHeight(20);
    $rowNumber++;
}

// Apply border and alignment to data rows
$highestRow = $sheet->getHighestRow();
if ($highestRow >= $startRow + 1) {
    $sheet->getStyle('A' . ($startRow + 1) . ':' . $highestColumn . $highestRow)->applyFromArray($dataStyle);
    $sheet->getStyle('A' . ($startRow + 1) . ':A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('B' . ($startRow + 1) . ':B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('D' . ($startRow + 1) . ':D' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto fit column width
foreach (range('A', $highestColumn) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="data_mata_pelajaran_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
