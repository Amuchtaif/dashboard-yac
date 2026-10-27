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

// --- Filters ---
$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'summary';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : '';

// Active Academic Year
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
$selected_year_id = !empty($ay_id) ? $ay_id : $active_year_id;

$ay_name = "Tahun Ajaran";
foreach ($academic_years as $ay) {
    if ($ay['id'] == $selected_year_id) {
        $ay_name = $ay['name'] . ' (' . $ay['semester'] . ')';
        break;
    }
}

// Day Map
$day_map = [
    'Sunday' => 'Ahad', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$english_day = date('l', strtotime($date));
$idn_day = $day_map[$english_day] ?? $english_day;

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// Query schedules
$sql = "
    SELECT 
        cs.id as schedule_id,
        cs.employee_id,
        cs.grade_level_id,
        cs.subject_id,
        e.full_name as teacher_name,
        e.nik as teacher_nik,
        gl.name as class_name,
        eu.name as unit_name,
        s.name as subject_name,
        lp.period_number as start_period,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        COALESCE(lp_end.period_number, lp.period_number) as end_period,
        cj.id as journal_id,
        cj.topic as journal_topic,
        cj.notes as journal_notes,
        cj.created_at as journal_created_at,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id) as total_students,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as count_present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as count_sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as count_permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as count_absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as count_late,
        att.time_in as teacher_time_in,
        att.status as teacher_att_status
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN education_units eu ON gl.education_unit_id = eu.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    LEFT JOIN class_journals cj ON cs.id = cj.class_schedule_id AND cj.date = :date
    LEFT JOIN attendances att ON att.user_id = cs.employee_id AND att.date = :date
    WHERE cs.day = :day
      AND cs.academic_year_id = :ay_id
      AND cs.is_active = 1
      AND (:date BETWEEN cs.valid_from AND COALESCE(cs.valid_until, '9999-12-31'))
";

$params = [
    ':date' => $date,
    ':day' => $english_day,
    ':ay_id' => $selected_year_id
];

if ($unit_id) {
    $sql .= " AND gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}
if ($grade_id) {
    $sql .= " AND cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}
if ($teacher_id) {
    $sql .= " AND cs.employee_id = :teacher_id";
    $params[':teacher_id'] = $teacher_id;
}
if ($search) {
    $sql .= " AND (e.full_name LIKE :search OR s.name LIKE :search OR gl.name LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY lp.start_time ASC, gl.name ASC, e.full_name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$raw_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process & Group Data
$schedules_list = [];
$teacher_summaries = [];

foreach ($raw_schedules as $s) {
    $has_journal = !empty($s['journal_id']) && (!empty($s['journal_topic']) || !empty($s['journal_notes']));
    $has_attendance = !empty($s['total_students']) && $s['total_students'] > 0;

    if ($has_journal && $has_attendance) {
        $status_label = 'Lengkap';
    } elseif ($has_attendance && !$has_journal) {
        $status_label = 'Hanya Absen';
    } elseif ($has_journal && !$has_attendance) {
        $status_label = 'Hanya Jurnal';
    } else {
        $status_label = 'Belum Input';
    }

    $s['has_journal'] = $has_journal;
    $s['has_attendance'] = $has_attendance;
    $s['status_label'] = $status_label;

    $schedules_list[] = $s;

    // Grouping
    $emp_id = $s['employee_id'];
    if (!isset($teacher_summaries[$emp_id])) {
        $teacher_summaries[$emp_id] = [
            'teacher_name' => $s['teacher_name'],
            'teacher_nik' => $s['teacher_nik'],
            'units' => [],
            'teacher_time_in' => $s['teacher_time_in'],
            'teacher_att_status' => $s['teacher_att_status'],
            'total_sessions' => 0,
            'sessions_with_attendance' => 0,
            'sessions_with_journal' => 0,
            'sessions_completed' => 0
        ];
    }

    $teacher_summaries[$emp_id]['total_sessions']++;
    if ($has_journal) $teacher_summaries[$emp_id]['sessions_with_journal']++;
    if ($has_attendance) $teacher_summaries[$emp_id]['sessions_with_attendance']++;
    if ($has_journal && $has_attendance) $teacher_summaries[$emp_id]['sessions_completed']++;

    if (!in_array($s['unit_name'], $teacher_summaries[$emp_id]['units'])) {
        $teacher_summaries[$emp_id]['units'][] = $s['unit_name'];
    }
}

// Filter by tab if already_attended or not_attended
if ($tab === 'already_attended') {
    $teacher_summaries = array_filter($teacher_summaries, function($t) {
        return $t['sessions_with_attendance'] > 0;
    });
    $sheet1_title = 'Guru Sudah Absen';
    $main_heading = 'REKAPITULASI GURU SUDAH ABSENSI KELAS';
    $export_filename = "guru_sudah_absen_" . $date . ".xlsx";
} elseif ($tab === 'not_attended') {
    $teacher_summaries = array_filter($teacher_summaries, function($t) {
        return $t['sessions_with_attendance'] < $t['total_sessions'];
    });
    $sheet1_title = 'Guru Belum Absen';
    $main_heading = 'REKAPITULASI GURU BELUM MENGISI ABSENSI';
    $export_filename = "guru_belum_absen_" . $date . ".xlsx";
} else {
    $sheet1_title = 'Rekap Per Guru';
    $main_heading = 'REKAPITULASI PRESENSI MENGAJAR GURU';
    $export_filename = "rekap_presensi_guru_" . $date . ".xlsx";
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();

// Style definitions
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2B3990']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    'font' => ['size' => 9],
];

// -------------------------------------------------------------
// SHEET 1: REKAP PER GURU / SESUAI TAB
// -------------------------------------------------------------
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle($sheet1_title);

$sheet1->setCellValue('A1', $main_heading);
$sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet1->setCellValue('A2', 'Hari, Tanggal: ' . $idn_day . ', ' . $date . ' | Tahun Ajaran: ' . $ay_name);
$sheet1->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

$headers1 = ['No', 'NIK', 'Nama Guru', 'Unit', 'Presensi Masuk', 'Total Sesi', 'Selesai Absen', 'Selesai Jurnal', 'Status Kepatuhan'];
$col = 'A';
foreach ($headers1 as $h) {
    $sheet1->setCellValue($col . '4', $h);
    $col++;
}
$sheet1->getStyle('A4:I4')->applyFromArray($headerStyle);
$sheet1->getRowDimension('4')->setRowHeight(26);

$rowNum = 5;
$no = 1;
foreach ($teacher_summaries as $t) {
    $presensiStr = !empty($t['teacher_time_in']) ? date('H:i', strtotime($t['teacher_time_in'])) : 'Belum';
    $isComplete = ($t['sessions_completed'] === $t['total_sessions'] && $t['total_sessions'] > 0);
    $isNone = ($t['sessions_completed'] === 0 && $t['sessions_with_attendance'] === 0 && $t['sessions_with_journal'] === 0);
    $statusText = $isComplete ? 'Lengkap' : ($isNone ? 'Belum Mengisi' : 'Sebagian');

    $sheet1->setCellValue('A' . $rowNum, $no++);
    $sheet1->setCellValue('B' . $rowNum, $t['teacher_nik'] ?: '-');
    $sheet1->setCellValue('C' . $rowNum, $t['teacher_name']);
    $sheet1->setCellValue('D' . $rowNum, implode(', ', $t['units']));
    $sheet1->setCellValue('E' . $rowNum, $presensiStr);
    $sheet1->setCellValue('F' . $rowNum, $t['total_sessions']);
    $sheet1->setCellValue('G' . $rowNum, $t['sessions_with_attendance'] . ' / ' . $t['total_sessions']);
    $sheet1->setCellValue('H' . $rowNum, $t['sessions_with_journal'] . ' / ' . $t['total_sessions']);
    $sheet1->setCellValue('I' . $rowNum, $statusText);

    $sheet1->getStyle('A' . $rowNum . ':I' . $rowNum)->applyFromArray($dataStyle);
    $sheet1->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('E' . $rowNum . ':I' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum++;
}

foreach (range('A', 'I') as $c) {
    $sheet1->getColumnDimension($c)->setAutoSize(true);
}

// -------------------------------------------------------------
// SHEET 2: RINCIAN SESI MENGAJAR
// -------------------------------------------------------------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Rincian Sesi');

$sheet2->setCellValue('A1', 'RINCIAN SESI MENGAJAR GURU PER KELAS');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(13);
$sheet2->setCellValue('A2', 'Hari, Tanggal: ' . $idn_day . ', ' . $date . ' | Tahun Ajaran: ' . $ay_name);
$sheet2->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

$headers2 = [
    'No', 'Waktu', 'Jam Ke', 'Unit', 'Kelas', 'Mata Pelajaran', 'Guru Pengajar', 
    'Presensi Guru', 'Status Absensi Siswa', 'Total', 'H', 'S', 'I', 'A', 'T', 
    'Status Jurnal', 'Materi / Topik Pembelajaran', 'Catatan Jurnal', 'Jam Input'
];

$col = 'A';
foreach ($headers2 as $h) {
    $sheet2->setCellValue($col . '4', $h);
    $col++;
}
$sheet2->getStyle('A4:S4')->applyFromArray($headerStyle);
$sheet2->getRowDimension('4')->setRowHeight(26);

$rowNum = 5;
$no = 1;
foreach ($schedules_list as $s) {
    $waktuStr = date('H:i', strtotime($s['start_time'])) . ' - ' . date('H:i', strtotime($s['end_time']));
    $jamKeStr = ($s['start_period'] == $s['end_period']) ? (string)$s['start_period'] : ($s['start_period'] . ' - ' . $s['end_period']);
    $presensiStr = !empty($s['teacher_time_in']) ? date('H:i', strtotime($s['teacher_time_in'])) : 'Belum';
    $absenStatus = $s['has_attendance'] ? 'Sudah' : 'Belum';
    $jurnalStatus = $s['has_journal'] ? 'Sudah' : 'Belum';
    $jamInput = !empty($s['journal_created_at']) ? date('H:i', strtotime($s['journal_created_at'])) : '-';

    $sheet2->setCellValue('A' . $rowNum, $no++);
    $sheet2->setCellValue('B' . $rowNum, $waktuStr);
    $sheet2->setCellValue('C' . $rowNum, $jamKeStr);
    $sheet2->setCellValue('D' . $rowNum, $s['unit_name']);
    $sheet2->setCellValue('E' . $rowNum, $s['class_name']);
    $sheet2->setCellValue('F' . $rowNum, $s['subject_name']);
    $sheet2->setCellValue('G' . $rowNum, $s['teacher_name']);
    $sheet2->setCellValue('H' . $rowNum, $presensiStr);
    $sheet2->setCellValue('I' . $rowNum, $absenStatus);
    $sheet2->setCellValue('J' . $rowNum, (int)$s['total_students']);
    $sheet2->setCellValue('K' . $rowNum, (int)$s['count_present']);
    $sheet2->setCellValue('L' . $rowNum, (int)$s['count_sick']);
    $sheet2->setCellValue('M' . $rowNum, (int)$s['count_permit']);
    $sheet2->setCellValue('N' . $rowNum, (int)$s['count_absent']);
    $sheet2->setCellValue('O' . $rowNum, (int)$s['count_late']);
    $sheet2->setCellValue('P' . $rowNum, $jurnalStatus);
    $sheet2->setCellValue('Q' . $rowNum, $s['journal_topic'] ?: '-');
    $sheet2->setCellValue('R' . $rowNum, $s['journal_notes'] ?: '-');
    $sheet2->setCellValue('S' . $rowNum, $jamInput);

    $sheet2->getStyle('A' . $rowNum . ':S' . $rowNum)->applyFromArray($dataStyle);
    $sheet2->getStyle('A' . $rowNum . ':C' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('H' . $rowNum . ':P' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('S' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $rowNum++;
}

foreach (range('A', 'S') as $c) {
    $sheet2->getColumnDimension($c)->setAutoSize(true);
}

// Reset active sheet to sheet 1
$spreadsheet->setActiveSheetIndex(0);

// Set Headers for Download
$filename = $export_filename;
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
