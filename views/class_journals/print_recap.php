<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$type = isset($_GET['type']) ? $_GET['type'] : 'monthly'; // 'monthly' or 'semester'
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

// Fetch filter names for header
$unit_name = "Semua Unit";
if ($unit_id) {
    $u_stmt = $conn->prepare("SELECT name FROM education_units WHERE id = :id");
    $u_stmt->execute([':id' => $unit_id]);
    $unit_name = $u_stmt->fetchColumn() ?: "Semua Unit";
}

$grade_name = "Semua Kelas";
if ($grade_id) {
    $g_stmt = $conn->prepare("SELECT name FROM grade_levels WHERE id = :id");
    $g_stmt->execute([':id' => $grade_id]);
    $grade_name = $g_stmt->fetchColumn() ?: "Semua Kelas";
}

$teacher_name = "Semua Guru";
if ($employee_id) {
    $e_stmt = $conn->prepare("SELECT full_name FROM employees WHERE id = :id");
    $e_stmt->execute([':id' => $employee_id]);
    $teacher_name = $e_stmt->fetchColumn() ?: "Semua Guru";
}

$subject_name = "Semua Mapel";
if ($subject_id) {
    $s_stmt = $conn->prepare("SELECT name FROM subjects WHERE id = :id");
    $s_stmt->execute([':id' => $subject_id]);
    $subject_name = $s_stmt->fetchColumn() ?: "Semua Mapel";
}

$months_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

if ($type === 'semester') {
    // Academic year filter
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
    $period_title = "Semester " . ($selected_ay ? ($selected_ay['name'] . ' (' . $selected_ay['semester'] . ')') : '');
} else {
    // Monthly filter
    $month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
    $parts = explode('-', $month);
    $year_str = $parts[0] ?? date('Y');
    $month_str = $parts[1] ?? date('m');
    $month_name = $months_id[$month_str] ?? $month_str;

    $start_date = "$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
    $period_title = "Bulan $month_name $year_str";
}

// Build query
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

// 1. Detailed Journals
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

// 2. Summary Aggregate per Mapel & Kelas
$sql_summary = "
    SELECT 
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        COUNT(cj.id) as total_meetings,
        SUM((SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present')) as sum_present,
        SUM((SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick')) as sum_sick,
        SUM((SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit')) as sum_permit,
        SUM((SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent')) as sum_absent,
        SUM((SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late')) as sum_late
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN employees e ON cj.teacher_id = e.id
    $where_sql
    GROUP BY cs.grade_level_id, cs.subject_id, cj.teacher_id
    ORDER BY gl.name ASC, s.name ASC
";
$stmt_sum = $conn->prepare($sql_summary);
$stmt_sum->execute($params);
$summaries = $stmt_sum->fetchAll(PDO::FETCH_ASSOC);

// Total Stats
$total_journals = count($journals);
$tot_present = array_sum(array_column($journals, 'count_present'));
$tot_sick = array_sum(array_column($journals, 'count_sick'));
$tot_permit = array_sum(array_column($journals, 'count_permit'));
$tot_absent = array_sum(array_column($journals, 'count_absent'));
$tot_late = array_sum(array_column($journals, 'count_late'));
$tot_all_att = $tot_present + $tot_sick + $tot_permit + $tot_absent + $tot_late;

$days_id = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Rekap Jurnal Kelas - <?php echo htmlspecialchars($period_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { 
                print-color-adjust: exact; 
                -webkit-print-color-adjust: exact; 
                font-size: 10pt;
            }
            .no-print { display: none !important; }
            @page {
                margin: 0.8cm;
            }
        }
        body {
            font-family: Arial, sans-serif;
            color: #000;
        }
        .header-box { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 5px; }
        .data-table th { background-color: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body class="bg-white p-6 max-w-5xl mx-auto" onload="window.print()">
    <div class="no-print mb-4 flex justify-end">
        <button onclick="window.print()" class="bg-cyan-700 text-white px-4 py-2 rounded shadow hover:bg-cyan-800 font-semibold text-sm">
            <i class="fa-solid fa-print mr-1"></i> Cetak PDF/Print
        </button>
        <button onclick="window.close()" class="bg-slate-200 text-slate-800 px-4 py-2 rounded shadow ml-2 hover:bg-slate-300 font-semibold text-sm">
            Tutup
        </button>
    </div>

    <!-- Kop Surat -->
    <div class="header-box flex items-center justify-between pb-4">
        <div class="w-24 flex justify-center">
            <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="h-16 object-contain">
        </div>
        <div class="flex-1 text-center pr-24">
            <h1 class="text-xl font-bold uppercase tracking-wider">REKAPITULASI JURNAL KELAS <?php echo ($type === 'semester') ? 'SEMESTERAN' : 'BULANAN'; ?></h1>
            <h2 class="text-lg font-bold mt-0.5 text-slate-800"><?php echo htmlspecialchars($unit_name); ?></h2>
            <p class="text-xs text-slate-600">Periode: <?php echo htmlspecialchars($period_title); ?></p>
        </div>
    </div>

    <!-- Metadata Filter -->
    <div class="grid grid-cols-2 gap-4 mb-5 text-xs">
        <div>
            <table class="w-full">
                <tr><td class="w-28 font-bold py-0.5">Unit Pendidikan</td><td class="w-4">:</td><td><?php echo htmlspecialchars($unit_name); ?></td></tr>
                <tr><td class="font-bold py-0.5">Kelas</td><td>:</td><td><?php echo htmlspecialchars($grade_name); ?></td></tr>
            </table>
        </div>
        <div>
            <table class="w-full">
                <tr><td class="w-28 font-bold py-0.5">Mata Pelajaran</td><td class="w-4">:</td><td><?php echo htmlspecialchars($subject_name); ?></td></tr>
                <tr><td class="font-bold py-0.5">Guru Pengajar</td><td>:</td><td><?php echo htmlspecialchars($teacher_name); ?></td></tr>
            </table>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="mb-5">
        <h3 class="font-bold text-xs uppercase tracking-wide mb-2 text-slate-800">A. Ringkasan Kehadiran & Pertemuan</h3>
        <table class="data-table w-full text-center text-xs">
            <thead>
                <tr>
                    <th>Total Jurnal</th>
                    <th>Hadir</th>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Alpa</th>
                    <th>Telat</th>
                    <th>Persentase Kehadiran</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="font-bold"><?php echo $total_journals; ?> Kali</td>
                    <td class="text-green-700 font-bold"><?php echo (int)$tot_present; ?></td>
                    <td class="text-yellow-700 font-bold"><?php echo (int)$tot_sick; ?></td>
                    <td class="text-blue-700 font-bold"><?php echo (int)$tot_permit; ?></td>
                    <td class="text-red-700 font-bold"><?php echo (int)$tot_absent; ?></td>
                    <td class="text-orange-700 font-bold"><?php echo (int)$tot_late; ?></td>
                    <td class="font-bold">
                        <?php echo $tot_all_att > 0 ? round(($tot_present / $tot_all_att) * 100, 1) . '%' : '-'; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Summary per Mapel/Kelas -->
    <?php if (!empty($summaries)): ?>
    <div class="mb-6">
        <h3 class="font-bold text-xs uppercase tracking-wide mb-2 text-slate-800">B. Rekapitulasi per Mata Pelajaran & Kelas</h3>
        <table class="data-table w-full text-xs">
            <thead>
                <tr>
                    <th class="w-8 text-center">No</th>
                    <th class="text-left">Kelas</th>
                    <th class="text-left">Mata Pelajaran</th>
                    <th class="text-left">Guru Pengampu</th>
                    <th class="w-24 text-center">Pertemuan</th>
                    <th class="w-16 text-center">Hadir</th>
                    <th class="w-16 text-center">Sakit</th>
                    <th class="w-16 text-center">Izin</th>
                    <th class="w-16 text-center">Alpa</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($summaries as $sum): ?>
                <tr>
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td class="font-semibold"><?php echo htmlspecialchars($sum['class_name']); ?></td>
                    <td><?php echo htmlspecialchars($sum['subject_name']); ?></td>
                    <td><?php echo htmlspecialchars($sum['teacher_name']); ?></td>
                    <td class="text-center font-bold"><?php echo $sum['total_meetings']; ?></td>
                    <td class="text-center text-green-800"><?php echo (int)$sum['sum_present']; ?></td>
                    <td class="text-center text-yellow-800"><?php echo (int)$sum['sum_sick']; ?></td>
                    <td class="text-center text-blue-800"><?php echo (int)$sum['sum_permit']; ?></td>
                    <td class="text-center text-red-800"><?php echo (int)$sum['sum_absent']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Detailed List of Journals -->
    <div class="mb-6">
        <h3 class="font-bold text-xs uppercase tracking-wide mb-2 text-slate-800">C. Detail Rincian Aktivitas Jurnal Kelas</h3>
        <table class="data-table w-full text-xs">
            <thead>
                <tr>
                    <th class="w-8 text-center">No</th>
                    <th class="w-24 text-center">Tanggal / Jam</th>
                    <th class="w-32 text-left">Kelas / Mapel</th>
                    <th class="w-32 text-left">Guru</th>
                    <th class="text-left">Materi Pembelajaran</th>
                    <th class="text-left">Catatan Tambahan</th>
                    <th class="w-28 text-center">Kehadiran (H/S/I/A/T)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($journals)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 italic text-slate-500">Tidak ada record jurnal pada periode ini.</td>
                </tr>
                <?php else: ?>
                <?php $no = 1; foreach ($journals as $j): 
                    $d_parts = explode('-', $j['date']);
                    $date_id = $d_parts[2] . ' ' . ($months_id[$d_parts[1]] ?? $d_parts[1]) . ' ' . $d_parts[0];
                    $day_name = $days_id[$j['day']] ?? $j['day'];
                ?>
                <tr class="align-top">
                    <td class="text-center"><?php echo $no++; ?></td>
                    <td>
                        <div class="font-semibold"><?php echo $date_id; ?></div>
                        <div class="text-[10px] text-slate-600"><?php echo date('H:i', strtotime($j['start_time'])); ?> - <?php echo date('H:i', strtotime($j['end_time'])); ?></div>
                    </td>
                    <td>
                        <div class="font-bold"><?php echo htmlspecialchars($j['class_name']); ?></div>
                        <div class="text-cyan-800"><?php echo htmlspecialchars($j['subject_name']); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($j['teacher_name']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($j['topic'] ?: '-')); ?></td>
                    <td class="italic text-slate-700"><?php echo nl2br(htmlspecialchars($j['notes'] ?: '-')); ?></td>
                    <td class="text-center font-mono">
                        <span class="text-green-700 font-bold"><?php echo $j['count_present']; ?></span> /
                        <span class="text-yellow-700 font-bold"><?php echo $j['count_sick']; ?></span> /
                        <span class="text-blue-700 font-bold"><?php echo $j['count_permit']; ?></span> /
                        <span class="text-red-700 font-bold"><?php echo $j['count_absent']; ?></span> /
                        <span class="text-orange-700 font-bold"><?php echo $j['count_late']; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Signature Footer -->
    <div class="mt-8 flex justify-between text-xs">
        <div class="text-center w-48">
            <p class="mb-14">Mengetahui,<br><strong>Kepala Sekolah / Unit</strong></p>
            <p class="font-bold underline">( .................................... )</p>
        </div>
        <div class="text-center w-48">
            <p class="mb-14">Dicetak Pada: <?php echo date('d') . ' ' . $months_id[date('m')] . ' ' . date('Y'); ?><br><strong>Guru / Pengelola Akademik</strong></p>
            <p class="font-bold underline"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Petugas Akademik'); ?></p>
        </div>
    </div>
</body>
</html>
