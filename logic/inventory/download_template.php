<?php
// logic/inventory/download_template.php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Import');

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

// Columns headers
$headers = [
    'No', 
    'Kode Barang (Kosongkan jika ingin auto-generate)', 
    'Nama Barang', 
    'Lokasi (Wajib Ada di Sistem)', 
    'Qty', 
    'Satuan', 
    'Kondisi (Baik/Rusak Ringan/Rusak Berat)', 
    'Sumber Dana',
    'Tanggal Pembelian (YYYY-MM-DD)',
    'Deskripsi'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    $col++;
}

$highestColumn = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(28);

// Sample Rows
$sampleData = [
    ['1', '', 'Meja Kantor 120cm', 'Kantor Bidik', '5', 'Unit', 'Baik', 'Dana BOS 2024', '2024-01-15', 'Meja kayu jati minimalis'],
    ['2', '', 'Kursi Lipat Chitose', 'Ma\'had Aly', '10', 'Pcs', 'Rusak Ringan', 'Yayasan', '2023-08-10', 'Warna hitam, butuh baut tambahan']
];

$rowNumber = 2;
foreach ($sampleData as $rowData) {
    $col = 'A';
    foreach ($rowData as $val) {
        $sheet->setCellValue($col . $rowNumber, $val);
        $col++;
    }
    $sheet->getRowDimension($rowNumber)->setRowHeight(20);
    $rowNumber++;
}

// Auto fit column width
foreach (range('A', $highestColumn) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_import_inventaris.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
