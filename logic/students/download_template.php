<?php
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header values
$sheet->setCellValue('A1', 'Nama Siswa');
$sheet->setCellValue('B1', 'NISN');
$sheet->setCellValue('C1', 'Kelas');

// Set sample data rows
$sheet->setCellValue('A2', 'Ahmad Santoso');
$sheet->setCellValue('B2', '1234567890');
$sheet->setCellValue('C2', '10-A');

$sheet->setCellValue('A3', 'Budi Hartono');
$sheet->setCellValue('B3', '0987654321');
$sheet->setCellValue('C3', '11-IPA-1');

$sheet->setCellValue('A4', 'Siti Aminah');
$sheet->setCellValue('B4', '1122334455');
$sheet->setCellValue('C4', 'TK-A');

// Auto size columns for better readability
foreach (range('A', 'C') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Force download headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_siswa.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
