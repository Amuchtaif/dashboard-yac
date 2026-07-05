<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

// --- Logika Filter ---
$search = $_GET['search'] ?? '';
$unit_id = $_GET['unit_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$subject_id = $_GET['subject_id'] ?? '';

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

$where_clauses = [];
$params = [];

if (!$is_admin) {
    $where_clauses[] = "sa.teacher_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
}

if ($search) {
    $where_clauses[] = "(s.name LIKE :search OR at.name LIKE :search OR e.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($class_id) {
    $where_clauses[] = "sa.grade_level_id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($subject_id) {
    $where_clauses[] = "sa.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch all detailed student scores for filtered assessments
$query = "
    SELECT 
        sa.assessment_date,
        sa.created_at,
        s.name as subject_name, 
        gl.name as class_name, 
        at.name as assessment_type_name,
        e.full_name as teacher_name,
        st.nomor_induk as student_nisn,
        st.nama_siswa as student_name,
        sad.score as student_score
    FROM student_assessment_details sad
    JOIN student_assessments sa ON sad.assessment_id = sa.id
    JOIN students st ON sad.student_id = st.id
    JOIN subjects s ON sa.subject_id = s.id
    JOIN grade_levels gl ON sa.grade_level_id = gl.id
    JOIN assessment_types at ON sa.assessment_type_id = at.id
    LEFT JOIN employees e ON sa.teacher_id = e.id
    $where_sql
    ORDER BY sa.assessment_date DESC, sa.created_at DESC, st.nama_siswa ASC
";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize spreadsheet
$spreadsheet = new Spreadsheet();

// Group by assessment category (assessment_type_name)
$grouped = [];
foreach ($details as $d) {
    $category = trim($d['assessment_type_name']);
    if (empty($category)) {
        $category = 'Lainnya';
    }
    $grouped[$category][] = $d;
}

if (empty($grouped)) {
    // If empty, create one default sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Tidak Ada Data');
    $sheet->setCellValue('A1', 'Tidak ada data penilaian yang cocok dengan filter aktif.');
} else {
    $sheetIndex = 0;
    foreach ($grouped as $category => $items) {
        if ($sheetIndex == 0) {
            $sheet = $spreadsheet->getActiveSheet();
        } else {
            $sheet = $spreadsheet->createSheet();
        }
        
        // Clean sheet title (max 31 chars, no special characters: \ / ? * : [ ])
        $cleanTitle = preg_replace('/[\\\\\/\\?\\*\\:\\[\\]]/', '', $category);
        $cleanTitle = substr($cleanTitle, 0, 30);
        if (empty($cleanTitle)) {
            $cleanTitle = 'Sheet ' . ($sheetIndex + 1);
        }
        $sheet->setTitle($cleanTitle);
        
        // Write title header block inside sheet
        $sheet->setCellValue('A1', 'DAFTAR NILAI AKADEMIK SISWA - ' . strtoupper($category));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        
        // Write filter description
        $filter_desc = "Filter aktif: ";
        if ($search) $filter_desc .= "Pencarian: '$search' | ";
        if ($unit_id) $filter_desc .= "Jenjang ID: $unit_id | ";
        if ($class_id) $filter_desc .= "Kelas ID: $class_id | ";
        if ($subject_id) $filter_desc .= "Mapel ID: $subject_id | ";
        if ($filter_desc === "Filter aktif: ") $filter_desc .= "Semua Data";
        $sheet->setCellValue('A2', rtrim($filter_desc, ' | '));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
        
        // Table Headers
        $headers = ['No', 'Tanggal', 'Mata Pelajaran', 'Kelas', 'Nama Guru', 'NISN', 'Nama Siswa', 'Nilai'];
        $rowNumber = 4;
        foreach ($headers as $index => $header) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($colLetter . $rowNumber, $header);
            $sheet->getStyle($colLetter . $rowNumber)->getFont()->setBold(true);
            // Gray background for header row
            $sheet->getStyle($colLetter . $rowNumber)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF2F2F2');
        }
        
        // Write Data
        $no = 1;
        foreach ($items as $item) {
            $rowNumber++;
            $sheet->setCellValue('A' . $rowNumber, $no++);
            $sheet->setCellValue('B' . $rowNumber, date('d M Y', strtotime($item['assessment_date'])));
            $sheet->setCellValue('C' . $rowNumber, $item['subject_name']);
            $sheet->setCellValue('D' . $rowNumber, $item['class_name'] ?? '-');
            $sheet->setCellValue('E' . $rowNumber, $item['teacher_name'] ?? '-');
            $sheet->setCellValue('F' . $rowNumber, $item['student_nisn'] ?? '-');
            $sheet->setCellValue('G' . $rowNumber, $item['student_name']);
            $sheet->setCellValue('H' . $rowNumber, (float)$item['student_score']);
        }
        
        // Fixed width for column A (No) to prevent auto-sizing with long header title in row 1 & 2
        $sheet->getColumnDimension('A')->setWidth(6);
        
        // Auto-fit remaining columns (B to H)
        foreach (range(2, count($headers)) as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }
        
        $sheetIndex++;
    }
}

// Reset active sheet to first sheet
$spreadsheet->setActiveSheetIndex(0);

// Download Headers
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="daftar_nilai_akademik_siswa.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
