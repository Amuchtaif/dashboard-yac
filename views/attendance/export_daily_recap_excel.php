<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE Clause (Base Employees)
$where_emp = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params_emp = [':target_date' => $target_date];

if ($search) {
    $where_emp .= " AND e.full_name LIKE :search ";
    $params_emp[':search'] = "%$search%";
}
if ($division_id) {
    $where_emp .= " AND e.division_id = :division_id ";
    $params_emp[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_emp .= " AND e.unit_id = :unit_id ";
    $params_emp[':unit_id'] = $unit_id;
}

// 1. Query Belum Absen
$query_absent = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where_emp
    AND e.id NOT IN (SELECT user_id FROM attendances WHERE date = :target_date)
    ORDER BY e.full_name ASC
";

$stmt_absent = $conn->prepare($query_absent);
foreach ($params_emp as $key => $val) {
    $stmt_absent->bindValue($key, $val);
}
$stmt_absent->execute();
$absent_employees = $stmt_absent->fetchAll(PDO::FETCH_ASSOC);

// 2. Query Telat
$query_late = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name,
        a.time_in as check_in_time,
        a.status as attendance_status
    FROM attendances a
    JOIN employees e ON a.user_id = e.id
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where_emp
    AND a.date = :target_date
    AND a.status IN ('Telat', 'Late')
    ORDER BY a.time_in ASC
";

$stmt_late = $conn->prepare($query_late);
foreach ($params_emp as $key => $val) {
    $stmt_late->bindValue($key, $val);
}
$stmt_late->execute();
$late_employees = $stmt_late->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Absensi Harian');

// Enable Gridlines
$sheet->setShowGridlines(true);

// Document Title Block
$sheet->setCellValue('A1', 'REKAPITULASI HARIAN ABSENSI PEGAWAI');
$sheet->mergeCells('A1:D1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$formattedDate = date('d F Y', strtotime($target_date));
$sheet->setCellValue('A2', 'Tanggal: ' . $formattedDate);
$sheet->mergeCells('A2:D2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Styles Definition
$titleSectionStyle = [
    'font' => [
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => '0F172A']
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_CENTER
    ]
];

$absentHeaderStyle = [
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
        'startColor' => ['rgb' => 'E11D48'], // Rose-600 to signify absent
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'FDA4AF'], // Rose-300 border
        ],
    ],
];

$lateHeaderStyle = [
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
        'startColor' => ['rgb' => 'D97706'], // Amber-600 to signify late
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'FCD34D'], // Amber-300 border
        ],
    ],
];

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'], // Slate-300 border
        ],
    ],
];

// --- SECTION 1: PEGAWAI BELUM ABSEN ---
$currRow = 5;
$sheet->setCellValue('A' . $currRow, '1. PEGAWAI BELUM ABSEN (Total: ' . count($absent_employees) . ')');
$sheet->mergeCells("A$currRow:D$currRow");
$sheet->getStyle("A$currRow")->applyFromArray($titleSectionStyle);
$sheet->getRowDimension($currRow)->setRowHeight(24);

$currRow++;
$sheet->setCellValue('A' . $currRow, 'No');
$sheet->setCellValue('B' . $currRow, 'Nama Pegawai');
$sheet->setCellValue('C' . $currRow, 'Unit Kerja');
$sheet->setCellValue('D' . $currRow, 'Bidang');
$sheet->getStyle("A$currRow:D$currRow")->applyFromArray($absentHeaderStyle);
$sheet->getRowDimension($currRow)->setRowHeight(22);

$startDataRow1 = $currRow + 1;
if (count($absent_employees) > 0) {
    foreach ($absent_employees as $index => $row) {
        $currRow++;
        $sheet->setCellValue('A' . $currRow, $index + 1 . '.');
        $sheet->setCellValue('B' . $currRow, $row['full_name']);
        $sheet->setCellValue('C' . $currRow, $row['unit_name'] ?: '-');
        $sheet->setCellValue('D' . $currRow, $row['division_name'] ?: '-');
        $sheet->getRowDimension($currRow)->setRowHeight(18);
    }
    $sheet->getStyle("A$startDataRow1:D$currRow")->applyFromArray($dataStyle);
    $sheet->getStyle("A$startDataRow1:A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    $currRow++;
    $sheet->setCellValue('A' . $currRow, 'Semua pegawai sudah melakukan absensi.');
    $sheet->mergeCells("A$currRow:D$currRow");
    $sheet->getStyle("A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$currRow")->getFont()->setItalic(true);
    $sheet->getStyle("A$currRow:D$currRow")->applyFromArray($dataStyle);
    $sheet->getRowDimension($currRow)->setRowHeight(20);
}

// Spacing
$currRow += 3;

// --- SECTION 2: PEGAWAI TERLAMBAT ---
$sheet->setCellValue('A' . $currRow, '2. PEGAWAI TERLAMBAT / TELAT (Total: ' . count($late_employees) . ')');
$sheet->mergeCells("A$currRow:D$currRow");
$sheet->getStyle("A$currRow")->applyFromArray($titleSectionStyle);
$sheet->getRowDimension($currRow)->setRowHeight(24);

$currRow++;
$sheet->setCellValue('A' . $currRow, 'No');
$sheet->setCellValue('B' . $currRow, 'Nama Pegawai');
$sheet->setCellValue('C' . $currRow, 'Unit Kerja');
$sheet->setCellValue('D' . $currRow, 'Waktu Absen');
$sheet->getStyle("A$currRow:D$currRow")->applyFromArray($lateHeaderStyle);
$sheet->getRowDimension($currRow)->setRowHeight(22);

$startDataRow2 = $currRow + 1;
if (count($late_employees) > 0) {
    foreach ($late_employees as $index => $row) {
        $currRow++;
        $sheet->setCellValue('A' . $currRow, $index + 1 . '.');
        $sheet->setCellValue('B' . $currRow, $row['full_name']);
        $sheet->setCellValue('C' . $currRow, $row['unit_name'] ?: '-');
        $sheet->setCellValue('D' . $currRow, date('H:i:s', strtotime($row['check_in_time'])));
        $sheet->getRowDimension($currRow)->setRowHeight(18);
    }
    $sheet->getStyle("A$startDataRow2:D$currRow")->applyFromArray($dataStyle);
    $sheet->getStyle("A$startDataRow2:A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D$startDataRow2:D$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("D$startDataRow2:D$currRow")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E11D48')); // Rose-600 for emphasis on check-in time
} else {
    $currRow++;
    $sheet->setCellValue('A' . $currRow, 'Tidak ada pegawai yang terlambat.');
    $sheet->mergeCells("A$currRow:D$currRow");
    $sheet->getStyle("A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$currRow")->getFont()->setItalic(true);
    $sheet->getStyle("A$currRow:D$currRow")->applyFromArray($dataStyle);
    $sheet->getRowDimension($currRow)->setRowHeight(20);
}

// Auto fit column width
foreach (range('A', 'D') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="rekap_absen_harian_' . $target_date . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
