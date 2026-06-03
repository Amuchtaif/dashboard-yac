<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();

// --- Filter Logic (Same as index.php) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$db = new Database();
$conn = $db->getConnection();

// Build Where Clause
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(nama_siswa LIKE :search OR nomor_induk LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "eu.id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($class_id) {
    $where_clauses[] = "gl.id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($status) {
    $where_clauses[] = "s.status = :status";
    $params[':status'] = $status;
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Active Academic Year
$active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year = $active_year_stmt->fetch(PDO::FETCH_ASSOC);
$active_year_id = $active_year ? $active_year['id'] : 1; // Default to 1 if none found

// Fetch All Matching Students (No Pagination)
$query = "
    SELECT 
        s.nama_siswa, 
        s.nomor_induk, 
        s.status, 
        s.tahun_ajaran,
        eu.name AS unit_name,
        gl.name AS class_name
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE ($where_sql)
    ORDER BY 
        CASE 
            WHEN eu.name LIKE '%PG%' THEN 1
            WHEN eu.name LIKE '%TK%' THEN 2
            WHEN eu.name LIKE '%SD%' THEN 3
            WHEN eu.name LIKE '%MTs%' THEN 4
            WHEN eu.name LIKE '%MA%' THEN 5
            WHEN eu.name LIKE '%Mahad%' THEN 6
            ELSE 7 
        END ASC, 
        gl.name ASC, 
        s.nama_siswa ASC
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':active_year_id', $active_year_id, PDO::PARAM_INT); // Bind Active Year
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Siswa');

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

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E5E7EB'],
        ],
    ],
];

// Set columns headers
$headers = ['No', 'Nama Siswa', 'NISN', 'Unit', 'Kelas', 'Tahun Ajaran', 'Status'];
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
foreach ($students as $row) {
    $sheet->setCellValue('A' . $rowNumber, $no++);
    $sheet->setCellValue('B' . $rowNumber, ucwords(strtolower($row['nama_siswa'])));
    // Treat NISN explicitly as text to prevent format loss
    $sheet->setCellValueExplicit('C' . $rowNumber, $row['nomor_induk'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('D' . $rowNumber, $row['unit_name'] ?? '-');
    $sheet->setCellValue('E' . $rowNumber, $row['class_name'] ?? '-');
    $sheet->setCellValue('F' . $rowNumber, $row['tahun_ajaran']);
    $sheet->setCellValue('G' . $rowNumber, $row['status']);
    
    $sheet->getRowDimension($rowNumber)->setRowHeight(20);
    $rowNumber++;
}

// Apply styling to data rows
$highestRow = $sheet->getHighestRow();
if ($highestRow > 1) {
    $sheet->getStyle('A2:' . $highestColumn . $highestRow)->applyFromArray($dataStyle);
    $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('C2:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F2:G' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto fit column width
foreach (range('A', $highestColumn) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Set excel headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="data_siswa_' . date('Y-m-d_H-i') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
