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
$sheet->setCellValue('D1', 'Tempat Lahir');
$sheet->setCellValue('E1', 'Tanggal Lahir (Format: YYYY-MM-DD)');
$sheet->setCellValue('F1', 'Alamat');

// Set sample data rows
$sheet->setCellValue('A2', 'Afifah Jelita');
$sheet->setCellValue('B2', '52627025');
$sheet->setCellValue('C2', '10B');
$sheet->setCellValue('D2', 'Lubuklinggau');
$sheet->setCellValue('E2', '');
$sheet->setCellValue('F2', 'Komplek jabon lestari, jalan jabon 2 Desa jati');

$sheet->setCellValue('A3', 'Najmi Lavina');
$sheet->setCellValue('B3', '52627040');
$sheet->setCellValue('C3', '10B');
$sheet->setCellValue('D3', 'Bengkulu');
$sheet->setCellValue('E3', '');
$sheet->setCellValue('F3', 'JL. HUKUM NO. 18 UNSWAGATI GRIYA JATI');

$sheet->setCellValue('A4', 'Maliihah Iffatunnisa');
$sheet->setCellValue('B4', '52627018');
$sheet->setCellValue('C4', '10C');
$sheet->setCellValue('D4', 'Cirebon');
$sheet->setCellValue('E4', '');
$sheet->setCellValue('F4', 'P Grenjeng Rt/Rw 02/06, Harjamukti, Harjamukti');

$sheet->setCellValue('A5', 'Ruhaellah');
$sheet->setCellValue('B5', '52627027');
$sheet->setCellValue('C5', '10C');
$sheet->setCellValue('D5', 'Cirebon');
$sheet->setCellValue('E5', '');
$sheet->setCellValue('F5', 'BLOK KECITRAAN DESA, SURANENGGALA');

// Auto size columns for better readability
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Force download headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_siswa.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
