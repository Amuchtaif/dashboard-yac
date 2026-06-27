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
$today_day = (int)date('d');
if ($today_day >= 26) {
    $default_start = date('Y-m-26');
    $default_end = date('Y-m-25', strtotime('+1 month'));
} else {
    $default_start = date('Y-m-26', strtotime('-1 month'));
    $default_end = date('Y-m-25');
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end;
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE Clause
$where = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($search) {
    $where .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where .= " AND e.unit_id = :unit_id ";
    $params[':unit_id'] = $unit_id;
}

// Query Rekapitulasi (No Pagination for Export)
$query = "
    SELECT 
        e.id, 
        e.nik,
        e.full_name, 
        e.email,
        u.name as unit_name, 
        d.name as division_name, 
        (SELECT COUNT(id) FROM attendances WHERE user_id = e.id AND date BETWEEN :start_date AND :end_date) as total_attendance
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':start_date', $start_date);
$stmt->bindValue(':end_date', $end_date);
foreach ($params as $key => $val) {
    if ($key !== ':start_date' && $key !== ':end_date') {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Absensi Pegawai');

// Enable Gridlines
$sheet->setShowGridlines(true);

// Document Title Block
$sheet->setCellValue('A1', 'REKAPITULASI TOTAL KEHADIRAN PEGAWAI');
$sheet->mergeCells('A1:G1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$formatted_start = date('d F Y', strtotime($start_date));
$formatted_end = date('d F Y', strtotime($end_date));
$sheet->setCellValue('A2', 'Periode: ' . $formatted_start . ' s/d ' . $formatted_end);
$sheet->mergeCells('A2:G2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table Headers
$currRow = 4;
$sheet->setCellValue('A' . $currRow, 'No');
$sheet->setCellValue('B' . $currRow, 'NIK');
$sheet->setCellValue('C' . $currRow, 'Nama Pegawai');
$sheet->setCellValue('D' . $currRow, 'Email');
$sheet->setCellValue('E' . $currRow, 'Unit Kerja');
$sheet->setCellValue('F' . $currRow, 'Bidang');
$sheet->setCellValue('G' . $currRow, 'Total Kehadiran');

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
            'color' => ['rgb' => '22D3EE'], // Cyan-400 border
        ],
    ],
];

$sheet->getStyle("A$currRow:G$currRow")->applyFromArray($headerStyle);
$sheet->getRowDimension($currRow)->setRowHeight(24);

// Data Rows
$startDataRow = $currRow + 1;
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'], // Slate-300 border
        ],
    ],
];

if (count($summary) > 0) {
    foreach ($summary as $index => $row) {
        $currRow++;
        $sheet->setCellValue('A' . $currRow, $index + 1);
        $sheet->setCellValue('B' . $currRow, $row['nik'] ?: '-');
        $sheet->setCellValue('C' . $currRow, $row['full_name']);
        $sheet->setCellValue('D' . $currRow, $row['email']);
        $sheet->setCellValue('E' . $currRow, $row['unit_name'] ?: '-');
        $sheet->setCellValue('F' . $currRow, $row['division_name'] ?: '-');
        $sheet->setCellValue('G' . $currRow, $row['total_attendance'] . ' Hari');
        
        $sheet->getRowDimension($currRow)->setRowHeight(20);
    }
    
    // Apply styling to data rows
    $sheet->getStyle("A$startDataRow:G$currRow")->applyFromArray($dataStyle);
    
    // Alignments
    $sheet->getStyle("A$startDataRow:A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B$startDataRow:B$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("G$startDataRow:G$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    $currRow++;
    $sheet->setCellValue('A' . $currRow, 'Tidak ada data kehadiran.');
    $sheet->mergeCells("A$currRow:G$currRow");
    $sheet->getStyle("A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$currRow")->getFont()->setItalic(true);
    $sheet->getStyle("A$currRow:G$currRow")->applyFromArray($dataStyle);
    $sheet->getRowDimension($currRow)->setRowHeight(24);
}

// Auto fit column width
foreach (range('A', 'G') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="rekap_absensi_' . $start_date . '_to_' . $end_date . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
