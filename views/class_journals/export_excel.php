<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$type = isset($_GET['type']) ? $_GET['type'] : 'monthly'; // 'daily', 'monthly', 'semester'
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

// Scoping for Guru
$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
$user_stmt = $conn->prepare("
    SELECT p.name as position_name
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    WHERE e.id = :user_id LIMIT 1
");
$user_stmt->execute([':user_id' => $_SESSION['user_id']]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
$position_name = $user_data['position_name'] ?? $_SESSION['position_name'] ?? '';
$is_guru_position = (strpos(strtolower($position_name), 'guru') !== false);

$sched_stmt = $conn->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = :user_id");
$sched_stmt->execute([':user_id' => $_SESSION['user_id']]);
$has_schedule = ($sched_stmt->fetchColumn() > 0);
$is_guru = ($is_guru_position || $has_schedule);

if ($is_guru) {
    $employee_id = $_SESSION['user_id'];
}

$months_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if ($type === 'daily') {
    $start_date = $date;
    $end_date = $date;
    $period_title = "Harian - " . date('d M Y', strtotime($date));
    $filename = "jurnal_kelas_harian_" . $date . ".xlsx";
} elseif ($type === 'semester') {
    $academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
    $active_ay = null;
    foreach ($academic_years as $ay) {
        if ($ay['is_active']) {
            $active_ay = $ay;
            break;
        }
    }
    $academic_year_id = isset($_GET['academic_year_id']) ? $_GET['academic_year_id'] : ($active_ay ? $active_ay['id'] : ($academic_years[0]['id'] ?? ''));

    $selected_ay = null;
    foreach ($academic_years as $ay) {
        if ($ay['id'] == $academic_year_id) {
            $selected_ay = $ay;
            break;
        }
    }

    $start_date = $selected_ay ? $selected_ay['start_date'] : date('Y-01-01');
    $end_date = $selected_ay ? $selected_ay['end_date'] : date('Y-12-31');
    $sem_name = $selected_ay ? str_replace('/', '_', $selected_ay['name']) . '_' . $selected_ay['semester'] : 'semester';
    $period_title = "Semester " . ($selected_ay ? ($selected_ay['name'] . ' (' . $selected_ay['semester'] . ')') : '');
    $filename = "rekap_jurnal_semester_" . strtolower($sem_name) . ".xlsx";
} else {
    // monthly
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    $parts = explode('-', $month);
    $year_str = $parts[0] ?? date('Y');
    $month_str = $parts[1] ?? date('m');
    $month_name = $months_id[$month_str] ?? $month_str;

    $start_date = "$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $period_title = "Bulan $month_name $year_str";
    $filename = "rekap_jurnal_bulanan_" . $month . ".xlsx";
}

// Build SQL
$where_clauses = ["cj.date BETWEEN :start_date AND :end_date"];
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($unit_id) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}
if ($grade_id) {
    $where_clauses[] = "cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}
if ($employee_id) {
    $where_clauses[] = "cj.teacher_id = :employee_id";
    $params[':employee_id'] = $employee_id;
}
if ($subject_id) {
    $where_clauses[] = "cs.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// Fetch Journals
$sql_journals = "
    SELECT 
        cj.id,
        cj.date,
        cs.day,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        cj.topic,
        cj.notes,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as count_present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as count_absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as count_sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as count_permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as count_late,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id) as total_attendance
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cj.teacher_id = e.id
    $where_sql
    ORDER BY cj.date ASC, lp.start_time ASC
";
$stmt = $conn->prepare($sql_journals);
$stmt->execute($params);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Rekap Jurnal');

// Define Styles
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']], // Cyan-600
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
];

$subHeaderStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
];

// Title Block
$sheet->setCellValue('A1', 'REKAPITULASI JURNAL KELAS');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Periode: ' . $period_title);
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

$rowNumber = 4;

// Table Headers
$headers = ['No', 'Tanggal', 'Waktu', 'Kelas', 'Mata Pelajaran', 'Guru Pengajar', 'Materi / Pembelajaran', 'Catatan Tambahan', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Telat', 'Total Siswa'];
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $rowNumber, $h);
    $col++;
}
$sheet->getStyle('A' . $rowNumber . ':N' . $rowNumber)->applyFromArray($headerStyle);
$sheet->getRowDimension((string)$rowNumber)->setRowHeight(26);

$rowNumber++;
$no = 1;

if (empty($journals)) {
    $sheet->setCellValue('A' . $rowNumber, 'Tidak ada data jurnal pada periode ini');
    $sheet->mergeCells('A' . $rowNumber . ':N' . $rowNumber);
    $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $rowNumber++;
} else {
    foreach ($journals as $j) {
        $date_formatted = date('d/m/Y', strtotime($j['date']));
        $time_formatted = date('H:i', strtotime($j['start_time'])) . ' - ' . date('H:i', strtotime($j['end_time']));
        
        $sheet->setCellValue('A' . $rowNumber, $no++);
        $sheet->setCellValue('B' . $rowNumber, $date_formatted);
        $sheet->setCellValue('C' . $rowNumber, $time_formatted);
        $sheet->setCellValue('D' . $rowNumber, $j['class_name']);
        $sheet->setCellValue('E' . $rowNumber, $j['subject_name']);
        $sheet->setCellValue('F' . $rowNumber, $j['teacher_name']);
        $sheet->setCellValue('G' . $rowNumber, $j['topic'] ?: '-');
        $sheet->setCellValue('H' . $rowNumber, $j['notes'] ?: '-');
        $sheet->setCellValue('I' . $rowNumber, (int)$j['count_present']);
        $sheet->setCellValue('J' . $rowNumber, (int)$j['count_sick']);
        $sheet->setCellValue('K' . $rowNumber, (int)$j['count_permit']);
        $sheet->setCellValue('L' . $rowNumber, (int)$j['count_absent']);
        $sheet->setCellValue('M' . $rowNumber, (int)$j['count_late']);
        $sheet->setCellValue('N' . $rowNumber, (int)$j['total_attendance']);
        
        $sheet->getRowDimension((string)$rowNumber)->setRowHeight(-1); // Auto row height
        $rowNumber++;
    }
}

// Apply Styles
$highestRow = $rowNumber - 1;
if ($highestRow >= 5) {
    $sheet->getStyle('A5:N' . $highestRow)->applyFromArray($dataStyle);
    $sheet->getStyle('A5:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('I5:N' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

// Auto fit column width
foreach (range('A', 'N') as $colID) {
    $sheet->getColumnDimension($colID)->setAutoSize(true);
}

// Set Headers for Download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
