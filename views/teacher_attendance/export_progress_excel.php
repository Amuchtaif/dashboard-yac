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
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'summary';

// Active Academic Year
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
if (!$active_year_id && !empty($academic_years)) {
    $active_year_id = $academic_years[0]['id'];
}
$selected_year_id = !empty($ay_id) ? $ay_id : $active_year_id;

$ay_name = "Tahun Ajaran";
foreach ($academic_years as $ay) {
    if ($ay['id'] == $selected_year_id) {
        $ay_name = $ay['name'] . ' (' . $ay['semester'] . ')';
        break;
    }
}

// Day Map in Indonesian
$day_map = [
    'Sunday' => 'Ahad',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$english_day = date('l', strtotime($date));
$idn_day = $day_map[$english_day] ?? $english_day;

$bulan_indo = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$time_ts = strtotime($date);
$formatted_date_indo = $idn_day . ', ' . date('d', $time_ts) . ' ' . $bulan_indo[(int)date('m', $time_ts)] . ' ' . date('Y', $time_ts);

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Role-based Scoping ---
$user_stmt = $conn->prepare("
    SELECT e.unit_id, p.level, u.name as unit_name
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.id = :user_id LIMIT 1
");
$user_stmt->execute([':user_id' => $_SESSION['user_id']]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_level = $user_data ? (int)$user_data['level'] : 5;
$user_unit_name = $user_data ? $user_data['unit_name'] : '';

$mapped_education_unit_ids = [];
if (!empty($user_unit_name)) {
    $clean_unit_name = str_replace(["'", " "], ["", ""], strtolower($user_unit_name));
    $edu_stmt = $conn->query("SELECT id, name FROM education_units");
    while ($edu_row = $edu_stmt->fetch(PDO::FETCH_ASSOC)) {
        $clean_edu_name = str_replace(["'", " "], ["", ""], strtolower($edu_row['name']));
        if (strpos($clean_unit_name, $clean_edu_name) !== false || strpos($clean_edu_name, $clean_unit_name) !== false) {
            $mapped_education_unit_ids[] = (int)$edu_row['id'];
        }
    }
}

$is_teacher_only = (!$is_admin && $user_level >= 5 && empty($mapped_education_unit_ids));

// Query Base Schedules for Target Date
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

if ($is_teacher_only) {
    $sql .= " AND cs.employee_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
} elseif (!$is_admin && !empty($mapped_education_unit_ids)) {
    $sql .= " AND gl.education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
}

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

// --- Group by Teacher ---
$teacher_summaries = [];

foreach ($raw_schedules as $s) {
    $has_attendance = !empty($s['total_students']) && $s['total_students'] > 0;
    $emp_id = $s['employee_id'];

    if (!isset($teacher_summaries[$emp_id])) {
        $teacher_summaries[$emp_id] = [
            'employee_id' => $emp_id,
            'teacher_name' => $s['teacher_name'],
            'teacher_nik' => $s['teacher_nik'],
            'units' => [],
            'teacher_time_in' => $s['teacher_time_in'],
            'total_sessions' => 0,
            'sessions_with_attendance' => 0,
            'sessions_attended' => [],
            'sessions_pending' => []
        ];
    }

    $teacher_summaries[$emp_id]['total_sessions']++;

    if (!in_array($s['unit_name'], $teacher_summaries[$emp_id]['units'])) {
        $teacher_summaries[$emp_id]['units'][] = $s['unit_name'];
    }

    $time_str = date('H:i', strtotime($s['start_time'])) . ' - ' . date('H:i', strtotime($s['end_time']));
    $period_label = ($s['start_period'] == $s['end_period']) ? "Jam ke-{$s['start_period']}" : "Jam ke-{$s['start_period']} - {$s['end_period']}";

    if ($has_attendance) {
        $teacher_summaries[$emp_id]['sessions_with_attendance']++;
        $student_stat = "H:" . (int)$s['count_present'] . " S:" . (int)$s['count_sick'] . " I:" . (int)$s['count_permit'] . " A:" . (int)$s['count_absent'];
        if ((int)$s['count_late'] > 0) {
            $student_stat .= " T:" . (int)$s['count_late'];
        }
        $teacher_summaries[$emp_id]['sessions_attended'][] = [
            'class_name' => $s['class_name'],
            'subject_name' => $s['subject_name'],
            'unit_name' => $s['unit_name'],
            'time_str' => $time_str,
            'period_label' => $period_label,
            'total_students' => (int)$s['total_students'],
            'student_stat' => $student_stat
        ];
    } else {
        $teacher_summaries[$emp_id]['sessions_pending'][] = [
            'class_name' => $s['class_name'],
            'subject_name' => $s['subject_name'],
            'unit_name' => $s['unit_name'],
            'time_str' => $time_str,
            'period_label' => $period_label
        ];
    }
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();

// Shared Style definitions
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0891B2']], // Cyan-600
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
];

$dataStyle = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
    'font' => ['size' => 9],
];

// Helper function to populate a teacher progress sheet
function populateTeacherProgressSheet($sheet, $title, $mainHeading, $teachers, $headerStyle, $dataStyle, $formatted_date_indo, $ay_name) {
    $sheet->setTitle($title);

    // Title Block
    $sheet->setCellValue('A1', $mainHeading);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0F172A'));
    $sheet->setCellValue('A2', 'Tanggal: ' . $formatted_date_indo . ' | Tahun Ajaran: ' . $ay_name);
    $sheet->getStyle('A2')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

    // Headers
    $headers = [
        'No',
        'Nama Guru',
        'Unit',
        'Status Presensi',
        'Progress Absensi per Sesi',
        'Sesi yang Sudah Diabsen',
        'Sesi yang Belum Diabsen'
    ];

    $col = 'A';
    foreach ($headers as $h) {
        $sheet->setCellValue($col . '4', $h);
        $col++;
    }
    $sheet->getStyle('A4:G4')->applyFromArray($headerStyle);
    $sheet->getRowDimension('4')->setRowHeight(26);

    $rowNum = 5;
    $no = 1;

    if (empty($teachers)) {
        $sheet->setCellValue('A5', 'Tidak ada data guru untuk kriteria ini.');
        $sheet->mergeCells('A5:G5');
        $sheet->getStyle('A5:G5')->applyFromArray($dataStyle);
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $rowNum = 6;
    } else {
        foreach ($teachers as $t) {
            $isComplete = ($t['sessions_with_attendance'] === $t['total_sessions'] && $t['total_sessions'] > 0);
            $isNone = ($t['sessions_with_attendance'] === 0);

            if ($isComplete) {
                $statusText = 'Sudah Semua (Lengkap)';
            } elseif ($isNone) {
                $statusText = 'Belum Absen';
            } else {
                $pending = $t['total_sessions'] - $t['sessions_with_attendance'];
                $statusText = "Sebagian ({$pending} Sesi Belum)";
            }

            $pct = $t['total_sessions'] > 0 ? round(($t['sessions_with_attendance'] / $t['total_sessions']) * 100) : 0;
            $progressText = $t['sessions_with_attendance'] . ' / ' . $t['total_sessions'] . ' Sesi (' . $pct . '%)';

            // Sesi yang sudah
            if (!empty($t['sessions_attended'])) {
                $linesSudah = [];
                foreach ($t['sessions_attended'] as $idx => $s) {
                    $linesSudah[] = ($idx + 1) . ". " . $s['class_name'] . " - " . $s['subject_name'] . " (" . $s['time_str'] . " / " . $s['period_label'] . ") [" . $s['student_stat'] . "]";
                }
                $sesiSudahStr = implode("\n", $linesSudah);
            } else {
                $sesiSudahStr = "Belum ada sesi yang diabsen";
            }

            // Sesi yang belum
            if (!empty($t['sessions_pending'])) {
                $linesBelum = [];
                foreach ($t['sessions_pending'] as $idx => $s) {
                    $linesBelum[] = ($idx + 1) . ". " . $s['class_name'] . " - " . $s['subject_name'] . " (" . $s['time_str'] . " / " . $s['period_label'] . ", " . $s['unit_name'] . ")";
                }
                $sesiBelumStr = implode("\n", $linesBelum);
            } else {
                $sesiBelumStr = "Semua sesi telah diabsen (Lengkap)";
            }

            $sheet->setCellValue('A' . $rowNum, $no++);
            $sheet->setCellValue('B' . $rowNum, $t['teacher_name']);
            $sheet->setCellValue('C' . $rowNum, implode(', ', $t['units']));
            $sheet->setCellValue('D' . $rowNum, $statusText);
            $sheet->setCellValue('E' . $rowNum, $progressText);
            $sheet->setCellValue('F' . $rowNum, $sesiSudahStr);
            $sheet->setCellValue('G' . $rowNum, $sesiBelumStr);

            $sheet->getStyle('A' . $rowNum . ':G' . $rowNum)->applyFromArray($dataStyle);
            $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $rowNum . ':E' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('F' . $rowNum . ':G' . $rowNum)->getAlignment()->setWrapText(true);

            // Subtle highlight on status
            if ($isComplete) {
                $sheet->getStyle('D' . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('047857')); // Emerald
            } elseif ($isNone) {
                $sheet->getStyle('D' . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('B91C1C')); // Rose
            } else {
                $sheet->getStyle('D' . $rowNum)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('B45309')); // Amber
            }

            $rowNum++;
        }
    }

    // Set Column Dimensions
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(18);
    $sheet->getColumnDimension('D')->setWidth(26);
    $sheet->getColumnDimension('E')->setWidth(26);
    $sheet->getColumnDimension('F')->setWidth(56);
    $sheet->getColumnDimension('G')->setWidth(52);
}

// -------------------------------------------------------------
// SHEET 1: REKAP SEMUA GURU (SUDAH & BELUM ABSEN)
// -------------------------------------------------------------
$sheet1 = $spreadsheet->getActiveSheet();
populateTeacherProgressSheet(
    $sheet1,
    'Semua Guru',
    'REKAP PROGRES ABSENSI MENGAJAR GURU (SEMUA GURU TERJADWAL)',
    $teacher_summaries,
    $headerStyle,
    $dataStyle,
    $formatted_date_indo,
    $ay_name
);

// -------------------------------------------------------------
// SHEET 2: GURU BELUM LENGKAP ABSEN
// -------------------------------------------------------------
$teachers_pending = array_filter($teacher_summaries, function($t) {
    return $t['sessions_with_attendance'] < $t['total_sessions'];
});

$headerStyleRose = $headerStyle;
$headerStyleRose['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E11D48']]; // Rose-600

$sheet2 = $spreadsheet->createSheet();
populateTeacherProgressSheet(
    $sheet2,
    'Belum Lengkap',
    'DAFTAR GURU YANG BELUM LENGKAP MENGISI ABSENSI KELAS',
    $teachers_pending,
    $headerStyleRose,
    $dataStyle,
    $formatted_date_indo,
    $ay_name
);

// -------------------------------------------------------------
// SHEET 3: GURU SUDAH LENGKAP ABSEN
// -------------------------------------------------------------
$teachers_completed = array_filter($teacher_summaries, function($t) {
    return $t['sessions_with_attendance'] === $t['total_sessions'] && $t['total_sessions'] > 0;
});

$headerStyleEmerald = $headerStyle;
$headerStyleEmerald['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']]; // Emerald-600

$sheet3 = $spreadsheet->createSheet();
populateTeacherProgressSheet(
    $sheet3,
    'Sudah Lengkap',
    'DAFTAR GURU YANG SUDAH LENGKAP MENGISI ABSENSI KELAS',
    $teachers_completed,
    $headerStyleEmerald,
    $dataStyle,
    $formatted_date_indo,
    $ay_name
);

// Select appropriate sheet by tab parameter
if ($tab === 'not_attended') {
    $spreadsheet->setActiveSheetIndex(1); // Belum Lengkap
} elseif ($tab === 'already_attended') {
    $spreadsheet->setActiveSheetIndex(2); // Sudah Lengkap
} else {
    $spreadsheet->setActiveSheetIndex(0); // Semua Guru
}

// Download Headers
$filename = "rekap_progres_absen_guru_" . $date . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
