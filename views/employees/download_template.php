<?php
// views/employees/download_template.php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

// Create Spreadsheet
$spreadsheet = new Spreadsheet();

// -------------------------------------------------------------
// SHEET 1: TEMPLATE IMPORT
// -------------------------------------------------------------
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Template Impor');

// Design Palette: Cyan-600 (#0891B2) for Main Theme Headers
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
        'startColor' => ['rgb' => '0891B2'],
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

// Set Columns
$headers = [
    'No', 
    'NIK (Wajib, Unik)', 
    'Nama Lengkap (Wajib)', 
    'Email (Wajib, Unik)', 
    'No. Telepon (Wajib)', 
    'Alamat (Wajib)', 
    'Jenis Kelamin (L/P - Wajib)',
    'ID Bidang (Wajib)', 
    'ID Unit (Boleh Kosong)', 
    'ID Jabatan (Wajib)', 
    'ID Jadwal Kerja (Boleh Kosong)', 
    'Password (Wajib)'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet1->setCellValue($col . '1', $header);
    $col++;
}
$highestColumn = $sheet1->getHighestColumn();
$sheet1->getStyle('A1:' . $highestColumn . '1')->applyFromArray($headerStyle);
$sheet1->getRowDimension('1')->setRowHeight(30);

// Insert Sample Rows
$samples = [
    [
        'no' => 1,
        'nik' => '1234567890123456',
        'name' => 'Ahmad Dani',
        'email' => 'ahmaddani@example.com',
        'phone' => '081234567890',
        'address' => 'Jl. Merdeka No. 10, Jakarta',
        'gender' => 'L',
        'division_id' => 1,
        'unit_id' => 1,
        'position_id' => 2,
        'schedule_id' => 1,
        'password' => 'DaniSandi123'
    ],
    [
        'no' => 2,
        'nik' => '1234567890123457',
        'name' => 'Siti Aminah',
        'email' => 'sitiaminah@example.com',
        'phone' => '081234567891',
        'address' => 'Jl. Sudirman No. 25, Bandung',
        'gender' => 'P',
        'division_id' => 2,
        'unit_id' => '',
        'position_id' => 3,
        'schedule_id' => '',
        'password' => 'SitiSandi456'
    ],
];

$rowNum = 2;
foreach ($samples as $s) {
    $sheet1->setCellValue('A' . $rowNum, $s['no']);
    $sheet1->setCellValueExplicit('B' . $rowNum, $s['nik'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet1->setCellValue('C' . $rowNum, $s['name']);
    $sheet1->setCellValue('D' . $rowNum, $s['email']);
    $sheet1->setCellValueExplicit('E' . $rowNum, $s['phone'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet1->setCellValue('F' . $rowNum, $s['address']);
    $sheet1->setCellValue('G' . $rowNum, $s['gender']);
    $sheet1->setCellValue('H' . $rowNum, $s['division_id']);
    $sheet1->setCellValue('I' . $rowNum, $s['unit_id']);
    $sheet1->setCellValue('J' . $rowNum, $s['position_id']);
    $sheet1->setCellValue('K' . $rowNum, $s['schedule_id']);
    $sheet1->setCellValue('L' . $rowNum, $s['password']);
    
    $sheet1->getRowDimension($rowNum)->setRowHeight(20);
    $rowNum++;
}

// Auto fit column width for Sheet 1
foreach (range('A', $highestColumn) as $columnID) {
    $sheet1->getColumnDimension($columnID)->setAutoSize(true);
}
$sheet1->getStyle('A2:L' . ($rowNum - 1))->applyFromArray($dataStyle);


// -------------------------------------------------------------
// SHEET 2: REFERENSI ID
// -------------------------------------------------------------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Referensi ID');

// Fetch DB options
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, name, division_id FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions = $conn->query("SELECT id, name FROM positions ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $conn->query("SELECT id, name FROM work_schedules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Header Style for Referensi (Cyan-600 but slightly different layout)
$refHeaderStyle = [
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
        'startColor' => ['rgb' => '0891B2'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB'],
        ],
    ],
];

// Write divisions table (Col A - B)
$sheet2->mergeCells('A1:B1');
$sheet2->setCellValue('A1', 'DAFTAR BIDANG');
$sheet2->setCellValue('A2', 'ID Bidang');
$sheet2->setCellValue('B2', 'Nama Bidang');
$sheet2->getStyle('A1:B2')->applyFromArray($refHeaderStyle);

$rDiv = 3;
foreach ($divisions as $d) {
    $sheet2->setCellValue('A' . $rDiv, $d['id']);
    $sheet2->setCellValue('B' . $rDiv, $d['name']);
    $sheet2->getRowDimension($rDiv)->setRowHeight(18);
    $rDiv++;
}
$sheet2->getStyle('A3:B' . ($rDiv - 1))->applyFromArray($dataStyle);
$sheet2->getStyle('A3:A' . ($rDiv - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Write units table (Col D - F)
$sheet2->mergeCells('D1:F1');
$sheet2->setCellValue('D1', 'DAFTAR UNIT ORGANISASI');
$sheet2->setCellValue('D2', 'ID Unit');
$sheet2->setCellValue('E2', 'Nama Unit');
$sheet2->setCellValue('F2', 'ID Bidang');
$sheet2->getStyle('D1:F2')->applyFromArray($refHeaderStyle);

$rUnit = 3;
foreach ($units as $u) {
    $sheet2->setCellValue('D' . $rUnit, $u['id']);
    $sheet2->setCellValue('E' . $rUnit, $u['name']);
    $sheet2->setCellValue('F' . $rUnit, $u['division_id']);
    $sheet2->getRowDimension($rUnit)->setRowHeight(18);
    $rUnit++;
}
$sheet2->getStyle('D3:F' . ($rUnit - 1))->applyFromArray($dataStyle);
$sheet2->getStyle('D3:D' . ($rUnit - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet2->getStyle('F3:F' . ($rUnit - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Write positions table (Col H - I)
$sheet2->mergeCells('H1:I1');
$sheet2->setCellValue('H1', 'DAFTAR JABATAN');
$sheet2->setCellValue('H2', 'ID Jabatan');
$sheet2->setCellValue('I2', 'Nama Jabatan');
$sheet2->getStyle('H1:I2')->applyFromArray($refHeaderStyle);

$rPos = 3;
foreach ($positions as $p) {
    $sheet2->setCellValue('H' . $rPos, $p['id']);
    $sheet2->setCellValue('I' . $rPos, $p['name']);
    $sheet2->getRowDimension($rPos)->setRowHeight(18);
    $rPos++;
}
$sheet2->getStyle('H3:I' . ($rPos - 1))->applyFromArray($dataStyle);
$sheet2->getStyle('H3:H' . ($rPos - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Write schedules table (Col K - L)
$sheet2->mergeCells('K1:L1');
$sheet2->setCellValue('K1', 'DAFTAR JADWAL KERJA');
$sheet2->setCellValue('K2', 'ID Jadwal');
$sheet2->setCellValue('L2', 'Nama Jadwal');
$sheet2->getStyle('K1:L2')->applyFromArray($refHeaderStyle);

$rSched = 3;
foreach ($schedules as $s) {
    $sheet2->setCellValue('K' . $rSched, $s['id']);
    $sheet2->setCellValue('L' . $rSched, $s['name']);
    $sheet2->getRowDimension($rSched)->setRowHeight(18);
    $rSched++;
}
$sheet2->getStyle('K3:L' . ($rSched - 1))->applyFromArray($dataStyle);
$sheet2->getStyle('K3:K' . ($rSched - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Auto fit column width for Sheet 2
foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'] as $colID) {
    $sheet2->getColumnDimension($colID)->setAutoSize(true);
}

// Reset active sheet to Sheet 1 so the user opens on the template page
$spreadsheet->setActiveSheetIndex(0);

// Excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="template_impor_pegawai.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
