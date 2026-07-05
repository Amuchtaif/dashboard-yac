<?php
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = ['Hari', 'Unit', 'Kelas', 'Mapel', 'Guru', 'Jam Ke Mulai', 'Jam Ke Selesai', 'Tahun Akademik'];
foreach ($headers as $index => $header) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($colLetter . '1', $header);
}

// Sample Data Row 1
$row1 = ['Monday', 'SDIT', '1A', 'Matematika', 'Ahmad Fauzi', '1', '2', '2025/2026'];
foreach ($row1 as $index => $val) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($colLetter . '2', $val);
}

// Sample Data Row 2
$row2 = ['Tuesday', 'MTs', '7A', 'Bahasa Inggris', 'Siti Aminah', '3', '3', '2025/2026'];
foreach ($row2 as $index => $val) {
    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($colLetter . '3', $val);
}

// Auto size columns for better readability
foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Force download headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_jadwal.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
