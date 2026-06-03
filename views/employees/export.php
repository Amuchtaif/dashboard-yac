<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
// Include Composer Autoloader
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

// --- Filter Logic (Mirrors Index) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search OR e.phone_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where_clauses[] = "e.division_id = :division_id";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_clauses[] = "e.unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

// Always filter for active employees as requested
$where_clauses[] = "(e.status = 'active' OR e.status IS NULL)";

$where_sql = implode(" AND ", $where_clauses);

// Fetch All Matching Data (No Limit)
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        e.phone_number, 
        e.address, 
        d.name as division_name, 
        u.name as unit_name,
        p.name as position_name,
        e.status
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE $where_sql
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Pegawai');

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
        'startColor' => ['rgb' => '0891B2'], // Cyan-600 color to match layout theme
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
$headers = ['No', 'Nama Lengkap', 'Email', 'Telepon', 'Alamat', 'Bidang', 'Unit', 'Jabatan', 'Status'];
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
foreach ($employees as $emp) {
    $sheet->setCellValue('A' . $rowNumber, $no++);
    $sheet->setCellValue('B' . $rowNumber, $emp['full_name']);
    $sheet->setCellValue('C' . $rowNumber, $emp['email']);
    $sheet->setCellValue('D' . $rowNumber, $emp['phone_number']);
    $sheet->setCellValue('E' . $rowNumber, $emp['address']);
    $sheet->setCellValue('F' . $rowNumber, $emp['division_name']);
    $sheet->setCellValue('G' . $rowNumber, $emp['unit_name']);
    $sheet->setCellValue('H' . $rowNumber, $emp['position_name']);
    $sheet->setCellValue('I' . $rowNumber, $emp['status'] ?: 'Active');
    
    $sheet->getRowDimension($rowNumber)->setRowHeight(20);
    $rowNumber++;
}

// Apply border to all data rows
$highestRow = $sheet->getHighestRow();
if ($highestRow > 1) {
    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray($dataStyle);
    // Align first column center
    $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    // Align status column center
    $sheet->getStyle('I2:I' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto fit column width
foreach (range('A', $highestColumn) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>