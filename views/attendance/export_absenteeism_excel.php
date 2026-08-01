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

// --- Filter & Search Logic ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE Clause (Base Employees)
$where_emp = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params_emp = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

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

$query_never_attended = "
    SELECT 
        e.id, 
        e.nik,
        e.full_name, 
        e.email,
        u.name as unit_name, 
        d.name as division_name
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where_emp
    AND e.id NOT IN (
        SELECT DISTINCT user_id 
        FROM attendances 
        WHERE date >= :start_date AND date <= :end_date
    )
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query_never_attended);
foreach ($params_emp as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Ketidakhadiran Pegawai');

// Enable Gridlines
$sheet->setShowGridlines(true);

// Document Title Block
$sheet->setCellValue('A1', 'LAPORAN PEGAWAI TIDAK PERNAH HADIR');
$sheet->mergeCells('A1:F1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$formatted_start = date('d-m-Y', strtotime($start_date));
$formatted_end = date('d-m-Y', strtotime($end_date));
$subtitle = "Periode: " . $formatted_start . " s/d " . $formatted_end . " | Tanggal Cetak: " . date('d-m-Y H:i');
$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:F2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
$sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Table Headers
$currRow = 4;
$sheet->setCellValue('A' . $currRow, 'No');
$sheet->setCellValue('B' . $currRow, 'NIK');
$sheet->setCellValue('C' . $currRow, 'Nama Pegawai');
$sheet->setCellValue('D' . $currRow, 'Email');
$sheet->setCellValue('E' . $currRow, 'Bidang / Divisi');
$sheet->setCellValue('F' . $currRow, 'Unit Kerja');

// Header style (Rose-600 to signify absent/never attended)
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E11D48'] // Rose-600
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'FDA4AF'] // Rose-300 border
        ]
    ]
];

$sheet->getStyle("A$currRow:F$currRow")->applyFromArray($headerStyle);
$sheet->getRowDimension($currRow)->setRowHeight(26);

// Data Rows
$startDataRow = $currRow + 1;
$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CBD5E1'] // Slate-300 border
        ]
    ]
];

if (count($employees) > 0) {
    foreach ($employees as $index => $row) {
        $currRow++;
        $sheet->setCellValue('A' . $currRow, $index + 1 . '.');
        $sheet->setCellValueExplicit('B' . $currRow, $row['nik'] ?: '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C' . $currRow, $row['full_name']);
        $sheet->setCellValue('D' . $currRow, $row['email'] ?: '-');
        $sheet->setCellValue('E' . $currRow, $row['division_name'] ?: '-');
        $sheet->setCellValue('F' . $currRow, $row['unit_name'] ?: '-');
        
        $sheet->getRowDimension($currRow)->setRowHeight(20);
        
        // Zebra striping
        if ($index % 2 === 1) {
            $sheet->getStyle("A$currRow:F$currRow")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F2'); // Rose-50 (extremely light red)
        }
    }
    
    // Apply borders and alignments
    $sheet->getStyle("A$startDataRow:F$currRow")->applyFromArray($dataStyle);
    $sheet->getStyle("A$startDataRow:A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("B$startDataRow:B$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
} else {
    $currRow++;
    $sheet->setCellValue('A' . $currRow, 'Semua pegawai memiliki catatan kehadiran dalam rentang tanggal ini.');
    $sheet->mergeCells("A$currRow:F$currRow");
    $sheet->getStyle("A$currRow")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A$currRow")->getFont()->setItalic(true);
    $sheet->getStyle("A$currRow:F$currRow")->applyFromArray($dataStyle);
    $sheet->getRowDimension($currRow)->setRowHeight(24);
}

// Auto fit column width
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set headers for download
$filename = "rekap_ketidakhadiran_pegawai_" . $start_date . "_to_" . $end_date . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
