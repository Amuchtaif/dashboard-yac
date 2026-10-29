<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

// --- Filters ---
$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['summary', 'schedules']) ? $_GET['tab'] : 'summary';
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

$bulan_indo = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
$time_ts = strtotime($date);
$formatted_date_indo = $idn_day . ', ' . date('d', $time_ts) . ' ' . $bulan_indo[(int)date('m', $time_ts)] . ' ' . date('Y', $time_ts);

// Filter names
$unit_name = "Semua Unit";
if ($unit_id) {
    $u_stmt = $conn->prepare("SELECT name FROM education_units WHERE id = :id");
    $u_stmt->execute([':id' => $unit_id]);
    $unit_name = $u_stmt->fetchColumn() ?: "Semua Unit";
}

// Base SQL
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

// Process
$total_sessions = count($raw_schedules);
$total_complete = 0;
$total_no_att = 0;
$total_no_journal = 0;

$processed_schedules = [];
$teacher_summaries = [];

foreach ($raw_schedules as $s) {
    $has_journal = !empty($s['journal_id']) && (!empty($s['journal_topic']) || !empty($s['journal_notes']));
    $has_attendance = !empty($s['total_students']) && $s['total_students'] > 0;

    if ($has_journal && $has_attendance) {
        $status_label = 'Lengkap';
        $total_complete++;
    } elseif ($has_attendance && !$has_journal) {
        $status_label = 'Hanya Absen';
        $total_no_journal++;
    } elseif ($has_journal && !$has_attendance) {
        $status_label = 'Hanya Jurnal';
        $total_no_att++;
    } else {
        $status_label = 'Belum Input';
        $total_no_att++;
        $total_no_journal++;
    }

    $s['has_journal'] = $has_journal;
    $s['has_attendance'] = $has_attendance;
    $s['status_label'] = $status_label;
    $processed_schedules[] = $s;

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

// Compute overall teacher metrics before tab filtering
$total_teachers_all = count($teacher_summaries);
$total_teachers_attended_all = 0;
$total_teachers_zero_all = 0;
foreach ($teacher_summaries as $t) {
    if ($t['sessions_with_attendance'] > 0) {
        $total_teachers_attended_all++;
    } else {
        $total_teachers_zero_all++;
    }
}

// Tab filtering for Print
$report_title = "LAPORAN REKAPITULASI PRESENSI MENGAJAR GURU";
if ($tab === 'already_attended') {
    $teacher_summaries = array_filter($teacher_summaries, function($t) {
        return $t['sessions_with_attendance'] > 0;
    });
    $report_title = "LAPORAN GURU YANG SUDAH ABSENSI KELAS";
} elseif ($tab === 'not_attended') {
    $teacher_summaries = array_filter($teacher_summaries, function($t) {
        return $t['sessions_with_attendance'] < $t['total_sessions'];
    });
    $report_title = "LAPORAN GURU YANG BELUM MENGISI ABSENSI KELAS";
}

$total_teachers = count($teacher_summaries);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($report_title); ?> - <?php echo htmlspecialchars($date); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
        }
        @media print {
            body {
                background-color: #ffffff;
                color: #000000;
            }
            .no-print {
                display: none !important;
            }
            .print-page {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            @page {
                size: A4 landscape;
                margin: 1.2cm;
            }
        }
    </style>
</head>
<body class="py-8">

    <!-- Action Bar (Hidden on print) -->
    <div class="no-print max-w-6xl mx-auto mb-6 px-4 flex items-center justify-between">
        <a href="index.php?<?php echo http_build_query($_GET); ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold bg-white text-slate-700 hover:bg-slate-50 border border-slate-300 transition-all shadow-sm">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
        </a>

        <div class="flex items-center gap-2">
            <button onclick="window.print()"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl text-xs font-semibold bg-[#2B3990] text-white hover:bg-indigo-800 transition-all shadow-md">
                <i class="fa-solid fa-print"></i> Cetak Dokumen
            </button>
        </div>
    </div>

    <!-- Printable Paper Sheet -->
    <div class="print-page max-w-6xl mx-auto bg-white p-8 rounded-2xl border border-slate-200/90 shadow-md">
        
        <!-- Header Yayasan / Kop -->
        <div class="border-b-2 border-slate-900 pb-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight uppercase text-slate-900">YAYASAN ASSUNNAH CIREBON</h2>
                    <h1 class="text-lg font-black text-[#2B3990] uppercase tracking-wide"><?php echo htmlspecialchars($report_title); ?></h1>
                    <p class="text-xs text-slate-500 mt-0.5">Monitoring Pengisian Absensi Siswa & Jurnal Pembelajaran di Kelas</p>
                </div>
                <div class="text-right text-xs space-y-1">
                    <div><strong>Hari, Tanggal:</strong> <?php echo htmlspecialchars($formatted_date_indo); ?></div>
                    <div><strong>Tahun Ajaran:</strong> <?php echo htmlspecialchars($ay_name); ?></div>
                    <div><strong>Unit:</strong> <?php echo htmlspecialchars($unit_name); ?></div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics Cards -->
        <div class="grid grid-cols-6 gap-3 mb-6 text-xs">
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-center">
                <span class="text-slate-500 font-semibold block text-[10px] uppercase">Guru Terjadwal</span>
                <span class="text-lg font-bold text-slate-900"><?php echo $total_teachers_all; ?> Guru</span>
            </div>
            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-center">
                <span class="text-emerald-700 font-semibold block text-[10px] uppercase">Guru Sudah Absen</span>
                <span class="text-lg font-bold text-emerald-800"><?php echo $total_teachers_attended_all; ?> Guru</span>
            </div>
            <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-center">
                <span class="text-rose-700 font-semibold block text-[10px] uppercase">Tidak Absen Sama Sekali</span>
                <span class="text-lg font-bold text-rose-800"><?php echo $total_teachers_zero_all; ?> Guru</span>
            </div>
            <div class="p-3 bg-blue-50 rounded-xl border border-blue-200 text-center">
                <span class="text-blue-700 font-semibold block text-[10px] uppercase">Total Sesi</span>
                <span class="text-lg font-bold text-blue-900"><?php echo $total_sessions; ?> Sesi</span>
            </div>
            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-center">
                <span class="text-emerald-700 font-semibold block text-[10px] uppercase">Sesi Lengkap</span>
                <span class="text-lg font-bold text-emerald-800"><?php echo $total_complete; ?> Sesi</span>
            </div>
            <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-center">
                <span class="text-amber-700 font-semibold block text-[10px] uppercase">Sesi Belum Lengkap</span>
                <span class="text-lg font-bold text-amber-800"><?php echo ($total_sessions - $total_complete); ?> Sesi</span>
            </div>
        </div>

        <!-- Section 1: Rekap Per Guru -->
        <div class="mb-8">
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2.5">
                1. Ringkasan Kepatuhan Per Guru
            </h3>
            <table class="w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-300 text-[10px] font-bold text-slate-700 uppercase">
                        <th class="py-2 px-2 border-r border-slate-300 text-center w-8">No</th>
                        <th class="py-2 px-3 border-r border-slate-300">Nama Guru</th>
                        <th class="py-2 px-3 border-r border-slate-300">Unit</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Presensi Masuk</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Jadwal</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Absen Siswa</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Jurnal Kelas</th>
                        <th class="py-2 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($teacher_summaries)): ?>
                        <tr><td colspan="8" class="text-center py-6 text-slate-400">Tidak ada data jadwal untuk hari ini.</td></tr>
                    <?php endif; ?>
                    <?php 
                    $no = 1; 
                    foreach ($teacher_summaries as $t): 
                        $presensiStr = !empty($t['teacher_time_in']) ? date('H:i', strtotime($t['teacher_time_in'])) : 'Belum';
                        $isComplete = ($t['sessions_completed'] === $t['total_sessions'] && $t['total_sessions'] > 0);
                        $isNone = ($t['sessions_completed'] === 0 && $t['sessions_with_attendance'] === 0 && $t['sessions_with_journal'] === 0);
                        $statusText = $isComplete ? 'Lengkap' : ($isNone ? 'Belum Mengisi' : 'Sebagian');
                    ?>
                        <tr>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200"><?php echo $no++; ?></td>
                            <td class="py-1.5 px-3 font-semibold border-r border-slate-200"><?php echo htmlspecialchars($t['teacher_name']); ?></td>
                            <td class="py-1.5 px-3 border-r border-slate-200"><?php echo htmlspecialchars(implode(', ', $t['units'])); ?></td>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200 font-mono"><?php echo $presensiStr; ?></td>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200 font-bold"><?php echo $t['total_sessions']; ?></td>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200"><?php echo $t['sessions_with_attendance'] . ' / ' . $t['total_sessions']; ?></td>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200"><?php echo $t['sessions_with_journal'] . ' / ' . $t['total_sessions']; ?></td>
                            <td class="py-1.5 px-3 text-center font-bold">
                                <?php if ($isComplete): ?>
                                    <span class="text-emerald-700">Lengkap</span>
                                <?php elseif ($isNone): ?>
                                    <span class="text-rose-700">Belum Mengisi</span>
                                <?php else: ?>
                                    <span class="text-amber-700">Sebagian</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Section 2: Rincian Jadwal Mengajar -->
        <div>
            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider mb-2.5">
                2. Rincian Sesi Mengajar Hari Ini
            </h3>
            <table class="w-full text-left text-xs border border-slate-300">
                <thead>
                    <tr class="bg-slate-100 border-b border-slate-300 text-[10px] font-bold text-slate-700 uppercase">
                        <th class="py-2 px-2 border-r border-slate-300 text-center w-8">No</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Waktu</th>
                        <th class="py-2 px-2 border-r border-slate-300">Kelas</th>
                        <th class="py-2 px-3 border-r border-slate-300">Mata Pelajaran</th>
                        <th class="py-2 px-3 border-r border-slate-300">Guru Pengajar</th>
                        <th class="py-2 px-2 border-r border-slate-300 text-center">Absensi Siswa</th>
                        <th class="py-2 px-3 border-r border-slate-300">Materi / Topik Jurnal</th>
                        <th class="py-2 px-2 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($processed_schedules)): ?>
                        <tr><td colspan="8" class="text-center py-6 text-slate-400">Tidak ada sesi pelajaran.</td></tr>
                    <?php endif; ?>
                    <?php 
                    $no = 1;
                    foreach ($processed_schedules as $s): 
                        $timeStr = date('H:i', strtotime($s['start_time'])) . '-' . date('H:i', strtotime($s['end_time']));
                    ?>
                        <tr>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200"><?php echo $no++; ?></td>
                            <td class="py-1.5 px-2 text-center font-mono border-r border-slate-200 whitespace-nowrap"><?php echo $timeStr; ?></td>
                            <td class="py-1.5 px-2 font-semibold border-r border-slate-200"><?php echo htmlspecialchars($s['class_name']); ?> (<?php echo htmlspecialchars($s['unit_name']); ?>)</td>
                            <td class="py-1.5 px-3 border-r border-slate-200"><?php echo htmlspecialchars($s['subject_name']); ?></td>
                            <td class="py-1.5 px-3 border-r border-slate-200"><?php echo htmlspecialchars($s['teacher_name']); ?></td>
                            <td class="py-1.5 px-2 text-center border-r border-slate-200 whitespace-nowrap">
                                <?php if ($s['has_attendance']): ?>
                                    <span class="text-emerald-700 font-bold">Sudah (H:<?php echo (int)$s['count_present']; ?>, S:<?php echo (int)$s['count_sick']; ?>, I:<?php echo (int)$s['count_permit']; ?>, A:<?php echo (int)$s['count_absent']; ?>)</span>
                                <?php else: ?>
                                    <span class="text-rose-600 font-semibold">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3 border-r border-slate-200">
                                <?php if ($s['has_journal']): ?>
                                    <span class="font-medium text-slate-800"><?php echo htmlspecialchars($s['journal_topic'] ?: 'Sudah diisi'); ?></span>
                                <?php else: ?>
                                    <span class="text-rose-600 italic">Belum diisi</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-2 text-center font-bold">
                                <?php echo htmlspecialchars($s['status_label']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>
</html>
