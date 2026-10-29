<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Rekap Presensi Guru";
$db = new Database();
$conn = $db->getConnection();

// --- Filters & Tab ---
$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$tab = isset($_GET['tab']) && in_array($_GET['tab'], ['summary', 'schedules', 'already_attended', 'not_attended']) ? $_GET['tab'] : 'summary';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$teacher_id = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : ''; // 'all', 'complete', 'incomplete', 'no_attendance', 'no_journal', 'empty'
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : '';

// --- Fetch Academic Years ---
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
if (!$active_year_id && !empty($academic_years)) {
    $active_year_id = $academic_years[0]['id'];
}
$selected_year_id = !empty($ay_id) ? $ay_id : $active_year_id;

// Resolve Day Name in Indonesian and English
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

// Indonesian Date Formatter Helper
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

// --- Fetch Filter Dropdowns ---
if ($is_teacher_only) {
    $units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->prepare("
        SELECT DISTINCT gl.id, gl.name, gl.education_unit_id 
        FROM grade_levels gl
        WHERE gl.teacher_id = :uid 
           OR gl.id IN (SELECT grade_level_id FROM class_schedules WHERE employee_id = :uid AND academic_year_id = :ay_id)
        ORDER BY gl.name ASC
    ");
    $grades->execute([':uid' => $_SESSION['user_id'], ':ay_id' => $selected_year_id]);
    $grades = $grades->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $conn->query("SELECT id, full_name, nik FROM employees WHERE id = " . (int)$_SESSION['user_id'])->fetchAll(PDO::FETCH_ASSOC);
} elseif (!$is_admin && !empty($mapped_education_unit_ids)) {
    $unit_in = implode(',', $mapped_education_unit_ids);
    $units = $conn->query("SELECT id, name FROM education_units WHERE id IN ($unit_in) ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels WHERE education_unit_id IN ($unit_in) ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $conn->query("
        SELECT DISTINCT e.id, e.full_name, e.nik 
        FROM employees e 
        JOIN class_schedules cs ON cs.employee_id = e.id 
        JOIN grade_levels gl ON cs.grade_level_id = gl.id 
        WHERE gl.education_unit_id IN ($unit_in) AND e.status = 'active'
        ORDER BY e.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $conn->query("
        SELECT DISTINCT e.id, e.full_name, e.nik 
        FROM employees e 
        JOIN class_schedules cs ON cs.employee_id = e.id 
        WHERE e.status = 'active'
        ORDER BY e.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// --- Query Base Schedules for Target Date ---
$sql = "
    SELECT 
        cs.id as schedule_id,
        cs.employee_id,
        cs.grade_level_id,
        cs.subject_id,
        e.full_name as teacher_name,
        e.nik as teacher_nik,
        e.profile_photo as teacher_photo,
        e.phone_number as teacher_phone,
        gl.name as class_name,
        eu.id as education_unit_id,
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
        cj.updated_at as journal_updated_at,
        -- Student attendance counts
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id) as total_students,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as count_present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as count_sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as count_permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as count_absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as count_late,
        -- Teacher daily attendance (check-in at school)
        att.time_in as teacher_time_in,
        att.time_out as teacher_time_out,
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
$all_raw_schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Process Schedule Records & Compute Overall KPI Counts ---
$total_schedules_all = count($all_raw_schedules);
$unique_teachers_all = [];
$total_completed_all = 0;
$total_no_att_all = 0;
$total_no_journal_all = 0;

$processed_schedules = [];
$teacher_summaries = [];

foreach ($all_raw_schedules as $s) {
    $has_journal = !empty($s['journal_id']) && (!empty($s['journal_topic']) || !empty($s['journal_notes']));
    $has_attendance = !empty($s['total_students']) && $s['total_students'] > 0;
    
    // Status Code: complete, partial_att, partial_journal, empty
    if ($has_journal && $has_attendance) {
        $status_code = 'complete';
        $status_label = 'Lengkap';
        $status_color = 'emerald';
        $total_completed_all++;
    } elseif ($has_attendance && !$has_journal) {
        $status_code = 'only_attendance';
        $status_label = 'Hanya Absen';
        $status_color = 'amber';
        $total_no_journal_all++;
    } elseif ($has_journal && !$has_attendance) {
        $status_code = 'only_journal';
        $status_label = 'Hanya Jurnal';
        $status_color = 'sky';
        $total_no_att_all++;
    } else {
        $status_code = 'empty';
        $status_label = 'Belum Input';
        $status_color = 'rose';
        $total_no_att_all++;
        $total_no_journal_all++;
    }

    $s['has_journal'] = $has_journal;
    $s['has_attendance'] = $has_attendance;
    $s['status_code'] = $status_code;
    $s['status_label'] = $status_label;
    $s['status_color'] = $status_color;

    // Filter check by status_filter
    $include_in_view = true;
    if ($status_filter === 'complete' && $status_code !== 'complete') {
        $include_in_view = false;
    } elseif ($status_filter === 'incomplete' && $status_code === 'complete') {
        $include_in_view = false;
    } elseif ($status_filter === 'no_attendance' && $has_attendance) {
        $include_in_view = false;
    } elseif ($status_filter === 'no_journal' && $has_journal) {
        $include_in_view = false;
    } elseif ($status_filter === 'empty' && $status_code !== 'empty') {
        $include_in_view = false;
    }

    if ($include_in_view) {
        $processed_schedules[] = $s;
    }

    // Teacher grouping
    $emp_id = $s['employee_id'];
    $unique_teachers_all[$emp_id] = true;

    if (!isset($teacher_summaries[$emp_id])) {
        // Avatar URL
        if (!empty($s['teacher_photo']) && file_exists(BASE_PATH . '/uploads/profile_photos/' . $s['teacher_photo'])) {
            $avatar_url = BASE_URL . '/uploads/profile_photos/' . $s['teacher_photo'];
        } else {
            $avatar_url = 'https://ui-avatars.com/api/?name=' . urlencode($s['teacher_name']) . '&background=2B3990&color=fff&size=128';
        }

        $teacher_summaries[$emp_id] = [
            'employee_id' => $emp_id,
            'teacher_name' => $s['teacher_name'],
            'teacher_nik' => $s['teacher_nik'],
            'teacher_phone' => $s['teacher_phone'] ?? '',
            'avatar_url' => $avatar_url,
            'units' => [],
            'teacher_time_in' => $s['teacher_time_in'],
            'teacher_time_out' => $s['teacher_time_out'],
            'teacher_att_status' => $s['teacher_att_status'],
            'total_sessions' => 0,
            'sessions_with_attendance' => 0,
            'sessions_with_journal' => 0,
            'sessions_completed' => 0,
            'sessions' => []
        ];
    }

    $teacher_summaries[$emp_id]['total_sessions']++;
    if ($has_journal) $teacher_summaries[$emp_id]['sessions_with_journal']++;
    if ($has_attendance) $teacher_summaries[$emp_id]['sessions_with_attendance']++;
    if ($has_journal && $has_attendance) $teacher_summaries[$emp_id]['sessions_completed']++;

    if (!in_array($s['unit_name'], $teacher_summaries[$emp_id]['units'])) {
        $teacher_summaries[$emp_id]['units'][] = $s['unit_name'];
    }

    $teacher_summaries[$emp_id]['sessions'][] = $s;
}

// Filter teacher summaries if status_filter is active
$filtered_teacher_summaries = [];
foreach ($teacher_summaries as $emp_id => $t_data) {
    $t_complete = ($t_data['sessions_completed'] === $t_data['total_sessions'] && $t_data['total_sessions'] > 0);
    $t_none = ($t_data['sessions_completed'] === 0 && $t_data['sessions_with_attendance'] === 0 && $t_data['sessions_with_journal'] === 0);
    $t_partial = (!$t_complete && !$t_none);

    $keep_teacher = true;
    if ($status_filter === 'complete' && !$t_complete) {
        $keep_teacher = false;
    } elseif ($status_filter === 'incomplete' && $t_complete) {
        $keep_teacher = false;
    } elseif ($status_filter === 'no_attendance' && ($t_data['sessions_with_attendance'] === $t_data['total_sessions'])) {
        $keep_teacher = false;
    } elseif ($status_filter === 'no_journal' && ($t_data['sessions_with_journal'] === $t_data['total_sessions'])) {
        $keep_teacher = false;
    } elseif ($status_filter === 'empty' && !$t_none) {
        $keep_teacher = false;
    }

    if ($keep_teacher) {
        $filtered_teacher_summaries[$emp_id] = $t_data;
    }
}

// Separate collections for Tab 3 (Sudah Absensi) and Tab 4 (Belum Absensi)
$teachers_attended = [];
$teachers_not_attended = [];
$total_pending_sessions = 0;

foreach ($filtered_teacher_summaries as $emp_id => $t_data) {
    if ($t_data['sessions_with_attendance'] > 0) {
        $teachers_attended[$emp_id] = $t_data;
    }
    if ($t_data['sessions_with_attendance'] < $t_data['total_sessions']) {
        $teachers_not_attended[$emp_id] = $t_data;
        $total_pending_sessions += ($t_data['total_sessions'] - $t_data['sessions_with_attendance']);
    }
}

if (!function_exists('formatWaNumber')) {
    function formatWaNumber($phone) {
        if (empty($phone)) return '';
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($clean, '0') === 0) {
            $clean = '62' . substr($clean, 1);
        }
        return $clean;
    }
}

// Calculate Global Metrics
$count_unique_teachers = count($unique_teachers_all);
$compliance_rate = $total_schedules_all > 0 ? round(($total_completed_all / $total_schedules_all) * 100) : 0;

// Teacher Attendance Status Metrics (Across all scheduled teachers)
$count_teachers_attended = 0;       // Guru yang sudah absen (minimal 1 sesi)
$count_teachers_zero_attended = 0;  // Guru yang tidak sama sekali absen kelas (0 sesi)
$count_teachers_complete_att = 0;   // Guru yang sudah absen lengkap semua sesi
$count_teachers_partial_att = 0;    // Guru yang baru sebagian sesi

foreach ($teacher_summaries as $emp_id => $t_data) {
    if ($t_data['sessions_with_attendance'] > 0) {
        $count_teachers_attended++;
        if ($t_data['sessions_with_attendance'] === $t_data['total_sessions']) {
            $count_teachers_complete_att++;
        } else {
            $count_teachers_partial_att++;
        }
    } else {
        $count_teachers_zero_attended++;
    }
}

// Sub-filter for Tab 4 ('all', 'zero', 'partial')
$filter_att = isset($_GET['filter_att']) && in_array($_GET['filter_att'], ['all', 'zero', 'partial']) ? $_GET['filter_att'] : 'all';

// Export URL
$query_params = $_GET;
$export_qs = http_build_query($query_params);
$export_url = "export_excel.php?" . $export_qs;
$export_progress_url = "export_progress_excel.php?" . $export_qs;
$print_url = "print.php?" . $export_qs;

include '../layouts/header.php';
?>

<div class="w-full pb-12">

    <!-- Header Block -->
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-500">Manajemen Akademik</span>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Rekap Presensi Guru</span>
                </li>
            </ol>
        </nav>

        <!-- Title & Action Buttons -->
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Rekap Presensi Guru</h1>
                <p class="text-slate-500 text-sm mt-1">
                    Monitoring input absensi siswa dan jurnal kelas bagi guru yang terjadwal mengajar.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo htmlspecialchars($export_progress_url); ?>"
                   class="inline-flex items-center justify-center rounded-lg border border-emerald-600 bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors"
                   title="Download Excel rekap daftar nama guru yang sudah & belum absen beserta rincian sesi">
                    <i class="fa-solid fa-file-excel mr-2"></i>
                    Export Progres Guru
                </a>

                <a href="<?php echo htmlspecialchars($export_url); ?>"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors"
                   title="Export seluruh rincian sesi ke Excel">
                    <i class="fa-solid fa-file-excel mr-2 text-slate-500"></i>
                    Export Detail Sesi
                </a>

                <a href="<?php echo htmlspecialchars($print_url); ?>" target="_blank"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-print mr-2 text-slate-500"></i>
                    Cetak Rekap
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3.5 mb-6">
        <!-- 1. Guru Terjadwal -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'summary'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-slate-300 hover:shadow-lg hover:shadow-slate-200/60 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-slate-800/[0.05] group-hover:text-slate-800/[0.12] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-slate-800 transition-colors">Guru Terjadwal</span>
                <span class="p-2 rounded-lg bg-slate-100 text-slate-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-slate-900 group-hover:text-slate-950 transition-colors"><?php echo number_format($count_unique_teachers); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Total guru hari ini</div>
            </div>
        </a>

        <!-- 2. Guru Sudah Absen -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'already_attended'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-emerald-600/[0.06] group-hover:text-emerald-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-emerald-700 transition-colors">Guru Sudah Absen</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-user-check"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-emerald-600"><?php echo number_format($count_teachers_attended); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">
                    <?php echo $count_teachers_complete_att; ?> selesai, <?php echo $count_teachers_partial_att; ?> sebagian
                </div>
            </div>
        </a>

        <!-- 3. Tidak Sama Sekali Absen Kelas -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'not_attended', 'filter_att' => 'zero'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-rose-300 hover:shadow-lg hover:shadow-rose-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-rose-600/[0.06] group-hover:text-rose-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-user-xmark"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-rose-700 transition-colors">Tidak Absen Sama Sekali</span>
                <span class="p-2 rounded-lg bg-rose-50 text-rose-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-user-xmark"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-rose-600"><?php echo number_format($count_teachers_zero_attended); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">0 sesi diabsen hari ini</div>
            </div>
        </a>

        <!-- 4. Tingkat Kepatuhan Sesi -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-cyan-300 hover:shadow-lg hover:shadow-cyan-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-cyan-600/[0.06] group-hover:text-cyan-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-cyan-700 transition-colors">Kepatuhan Sesi</span>
                <span class="p-2 rounded-lg bg-cyan-50 text-cyan-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-slate-900 group-hover:text-cyan-800 transition-colors"><?php echo $compliance_rate; ?>%</div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full mt-2 overflow-hidden">
                    <div class="bg-cyan-600 h-full rounded-full transition-all duration-500 group-hover:bg-cyan-500" style="width: <?php echo $compliance_rate; ?>%;"></div>
                </div>
            </div>
        </a>

        <!-- 5. Total Sesi / Jam -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-blue-300 hover:shadow-lg hover:shadow-blue-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-blue-600/[0.06] group-hover:text-blue-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-blue-700 transition-colors">Total Sesi</span>
                <span class="p-2 rounded-lg bg-blue-50 text-blue-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-clock"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-slate-900 group-hover:text-blue-900 transition-colors"><?php echo number_format($total_schedules_all); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Slot pelajaran</div>
            </div>
        </a>

        <!-- 6. Sesi Lengkap -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules', 'status' => 'complete'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-emerald-300 hover:shadow-lg hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-emerald-600/[0.06] group-hover:text-emerald-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-emerald-700 transition-colors">Sesi Lengkap</span>
                <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-emerald-600"><?php echo number_format($total_completed_all); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Absen & Jurnal OK</div>
            </div>
        </a>

        <!-- 7. Sesi Belum Absen -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules', 'status' => 'no_attendance'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-amber-300 hover:shadow-lg hover:shadow-amber-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-amber-600/[0.06] group-hover:text-amber-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-clipboard-user"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-amber-700 transition-colors">Sesi Belum Absen</span>
                <span class="p-2 rounded-lg bg-amber-50 text-amber-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-clipboard-user"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-amber-600"><?php echo number_format($total_no_att_all); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Siswa belum diabsen</div>
            </div>
        </a>

        <!-- 8. Sesi Belum Jurnal -->
        <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules', 'status' => 'no_journal'])); ?>"
           class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between hover:border-rose-300 hover:shadow-lg hover:shadow-rose-500/10 hover:-translate-y-1 transition-all duration-300 ease-out cursor-pointer">
            <div class="absolute -right-3 -bottom-3 text-7xl text-rose-600/[0.06] group-hover:text-rose-600/[0.16] transform group-hover:scale-115 group-hover:-rotate-6 group-hover:-translate-x-1 group-hover:-translate-y-1 transition-all duration-300 ease-out pointer-events-none select-none">
                <i class="fa-solid fa-book"></i>
            </div>
            <div class="relative z-10 flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 group-hover:text-rose-700 transition-colors">Sesi Belum Jurnal</span>
                <span class="p-2 rounded-lg bg-rose-50 text-rose-600 text-xs transition-transform duration-300 group-hover:scale-110">
                    <i class="fa-solid fa-book"></i>
                </span>
            </div>
            <div class="relative z-10 mt-3">
                <div class="text-2xl font-bold text-rose-600"><?php echo number_format($total_no_journal_all); ?></div>
                <div class="text-[11px] text-slate-400 mt-0.5">Materi belum diisi</div>
            </div>
        </a>
    </div>

    <!-- Filter Toolbar -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-6">
        <form id="filterForm" method="GET" action="index.php">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                <!-- Date Picker & Shortcuts -->
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label for="dateInput" class="block text-xs font-semibold text-slate-600">
                            Tanggal Pantau
                        </label>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="setDateAndSubmit('<?php echo date('Y-m-d'); ?>')"
                                    class="text-[11px] px-2 py-0.5 rounded <?php echo $date === date('Y-m-d') ? 'bg-cyan-600 text-white font-medium' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?> transition-colors">
                                Hari Ini
                            </button>
                            <button type="button" onclick="setDateAndSubmit('<?php echo date('Y-m-d', strtotime('-1 day')); ?>')"
                                    class="text-[11px] px-2 py-0.5 rounded <?php echo $date === date('Y-m-d', strtotime('-1 day')) ? 'bg-cyan-600 text-white font-medium' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?> transition-colors">
                                Kemarin
                            </button>
                        </div>
                    </div>
                    <input type="date" name="date" id="dateInput" value="<?php echo htmlspecialchars($date); ?>"
                           onchange="document.getElementById('filterForm').submit()"
                           class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                </div>

                <!-- Academic Year Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Tahun Ajaran</label>
                    <select name="ay_id" onchange="document.getElementById('filterForm').submit()"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $selected_year_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['name'] . ' (' . $ay['semester'] . ')'); ?>
                                <?php echo $ay['is_active'] ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Education Unit Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Unit Pendidikan</label>
                    <select name="unit_id" onchange="document.getElementById('filterForm').submit()"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                        <option value="">Semua Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Status Input</label>
                    <select name="status" onchange="document.getElementById('filterForm').submit()"
                            class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="complete" <?php echo $status_filter === 'complete' ? 'selected' : ''; ?>>Lengkap (Absen & Jurnal)</option>
                        <option value="incomplete" <?php echo $status_filter === 'incomplete' ? 'selected' : ''; ?>>Belum Lengkap</option>
                        <option value="no_attendance" <?php echo $status_filter === 'no_attendance' ? 'selected' : ''; ?>>Belum Absen Siswa</option>
                        <option value="no_journal" <?php echo $status_filter === 'no_journal' ? 'selected' : ''; ?>>Belum Isi Jurnal</option>
                        <option value="empty" <?php echo $status_filter === 'empty' ? 'selected' : ''; ?>>Belum Keduanya</option>
                    </select>
                </div>

                <!-- Search Input & Reset Button -->
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Cari guru / mapel..."
                               class="block w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3 py-2 text-xs text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm h-9">
                        <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    </div>

                    <a href="index.php?date=<?php echo htmlspecialchars($date); ?>&tab=<?php echo htmlspecialchars($tab); ?>"
                       title="Reset Filter"
                       class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-300 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 transition-colors shadow-sm flex-shrink-0">
                        <i class="fa-solid fa-rotate-left text-xs"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-200 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <nav class="flex space-x-6 -mb-px overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Tabs">
            <!-- 1. Rekap Per Guru -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'summary'])); ?>"
               class="<?php echo $tab === 'summary' ? 'border-cyan-600 text-cyan-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'; ?> whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2">
                <span>Rekap Per Guru</span>
                <span class="<?php echo $tab === 'summary' ? 'bg-cyan-50 text-cyan-700 font-semibold' : 'bg-slate-100 text-slate-600'; ?> py-0.5 px-2 rounded-full text-xs">
                    <?php echo count($filtered_teacher_summaries); ?>
                </span>
            </a>

            <!-- 2. Rincian Sesi Mengajar -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules'])); ?>"
               class="<?php echo $tab === 'schedules' ? 'border-cyan-600 text-cyan-600 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'; ?> whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2">
                <span>Rincian Sesi Mengajar</span>
                <span class="<?php echo $tab === 'schedules' ? 'bg-cyan-50 text-cyan-700 font-semibold' : 'bg-slate-100 text-slate-600'; ?> py-0.5 px-2 rounded-full text-xs">
                    <?php echo count($processed_schedules); ?>
                </span>
            </a>

            <!-- 3. Guru Sudah Absensi Kelas -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'already_attended'])); ?>"
               class="<?php echo $tab === 'already_attended' ? 'border-emerald-600 text-emerald-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'; ?> whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2">
                <span>Sudah Absensi Kelas</span>
                <span class="<?php echo $tab === 'already_attended' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'bg-slate-100 text-slate-600'; ?> py-0.5 px-2 rounded-full text-xs">
                    <?php echo count($teachers_attended); ?>
                </span>
            </a>

            <!-- 4. Guru Belum Mengisi Absensi -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'not_attended'])); ?>"
               class="<?php echo $tab === 'not_attended' ? 'border-rose-600 text-rose-700 font-semibold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 font-medium'; ?> whitespace-nowrap py-3 px-1 border-b-2 text-sm flex items-center gap-2">
                <span>Belum Absensi Kelas</span>
                <span class="<?php echo $tab === 'not_attended' ? 'bg-rose-50 text-rose-700 font-semibold' : 'bg-slate-100 text-slate-600'; ?> py-0.5 px-2 rounded-full text-xs">
                    <?php echo count($teachers_not_attended); ?>
                </span>
            </a>
        </nav>

        <div class="text-xs text-slate-500 pb-2 sm:pb-0 whitespace-nowrap">
            <span>Tanggal: <strong class="text-slate-800 font-medium"><?php echo htmlspecialchars($formatted_date_indo); ?></strong></span>
        </div>
    </div>

    <!-- TAB 1: REKAP PER GURU -->
    <?php if ($tab === 'summary'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-200">
                        <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4">Guru Pengajar</th>
                            <th class="py-3.5 px-4">Unit / Sekolah</th>
                            <th class="py-3.5 px-4 text-center">Presensi Pegawai</th>
                            <th class="py-3.5 px-4 text-center">Total Jadwal</th>
                            <th class="py-3.5 px-4 text-center">Absen Siswa</th>
                            <th class="py-3.5 px-4 text-center">Jurnal Kelas</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 pr-6 pl-4 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($filtered_teacher_summaries)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-1">
                                        <i class="fa-solid fa-inbox text-slate-300 text-2xl mb-1"></i>
                                        <p class="font-semibold text-slate-700">Tidak ada jadwal mengajar</p>
                                        <p class="text-[11px] text-slate-400">Tidak ditemukan jadwal guru pada tanggal dan filter yang dipilih.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($filtered_teacher_summaries as $emp_id => $t): ?>
                            <?php
                            $is_all_complete = ($t['sessions_completed'] === $t['total_sessions'] && $t['total_sessions'] > 0);
                            $is_none_complete = ($t['sessions_completed'] === 0 && $t['sessions_with_attendance'] === 0 && $t['sessions_with_journal'] === 0);
                            
                            $att_pct = $t['total_sessions'] > 0 ? round(($t['sessions_with_attendance'] / $t['total_sessions']) * 100) : 0;
                            $journal_pct = $t['total_sessions'] > 0 ? round(($t['sessions_with_journal'] / $t['total_sessions']) * 100) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-8 w-8 rounded-full object-cover border border-slate-200 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-900 block truncate">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: '-'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Kehadiran Pegawai -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] text-slate-400 bg-slate-100">
                                            Belum Presensi
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total Sesi Hari Ini -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap font-medium text-slate-800">
                                    <?php echo $t['total_sessions']; ?> Sesi
                                </td>

                                <!-- Progress Input Absen -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[80px]">
                                        <span class="font-medium <?php echo $t['sessions_with_attendance'] === $t['total_sessions'] ? 'text-emerald-700' : 'text-slate-700'; ?>">
                                            <?php echo $t['sessions_with_attendance']; ?> / <?php echo $t['total_sessions']; ?>
                                        </span>
                                        <div class="w-16 bg-slate-100 h-1 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $att_pct === 100 ? 'bg-emerald-500' : ($att_pct > 0 ? 'bg-amber-400' : 'bg-slate-200'); ?>"
                                                 style="width: <?php echo $att_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Progress Input Jurnal -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[80px]">
                                        <span class="font-medium <?php echo $t['sessions_with_journal'] === $t['total_sessions'] ? 'text-emerald-700' : 'text-slate-700'; ?>">
                                            <?php echo $t['sessions_with_journal']; ?> / <?php echo $t['total_sessions']; ?>
                                        </span>
                                        <div class="w-16 bg-slate-100 h-1 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $journal_pct === 100 ? 'bg-emerald-500' : ($journal_pct > 0 ? 'bg-amber-400' : 'bg-slate-200'); ?>"
                                                 style="width: <?php echo $journal_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Keseluruhan -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($is_all_complete): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Lengkap
                                        </span>
                                    <?php elseif ($is_none_complete): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            Belum Input
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            Sebagian
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 pr-6 pl-4 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                                        <i class="fa-solid fa-eye text-slate-500"></i>
                                        <span>Rincian</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 2: RINCIAN SESI MENGAJAR -->
    <?php elseif ($tab === 'schedules'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-200">
                        <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-3 min-w-[130px]">Waktu & Jam</th>
                            <th class="py-3.5 px-3 min-w-[140px]">Unit & Kelas</th>
                            <th class="py-3.5 px-3 min-w-[180px]">Mata Pelajaran</th>
                            <th class="py-3.5 px-3 min-w-[200px]">Guru Pengajar</th>
                            <th class="py-3.5 px-3 text-center min-w-[150px]">Absensi Siswa</th>
                            <th class="py-3.5 px-3 min-w-[200px]">Jurnal Kelas</th>
                            <th class="py-3.5 px-3 text-center min-w-[110px]">Status</th>
                            <th class="py-3.5 pr-6 pl-3 text-right min-w-[70px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($processed_schedules)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-1">
                                        <i class="fa-solid fa-calendar-xmark text-slate-300 text-2xl mb-1"></i>
                                        <p class="font-semibold text-slate-700">Tidak ada sesi pelajaran</p>
                                        <p class="text-[11px] text-slate-400">Tidak ditemukan jadwal untuk hari dan kriteria filter ini.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($processed_schedules as $s): ?>
                            <?php
                            $start_p = $s['start_period'];
                            $end_p = $s['end_period'];
                            $period_label = ($start_p == $end_p) ? "Jam ke-{$start_p}" : "Jam ke-{$start_p} - {$end_p}";
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <!-- Waktu Pelajaran -->
                                <td class="py-3.5 pl-6 pr-3 whitespace-nowrap">
                                    <div class="font-medium text-slate-900">
                                        <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?>
                                    </div>
                                    <span class="text-[10px] text-slate-500">
                                        <?php echo htmlspecialchars($period_label); ?>
                                    </span>
                                </td>

                                <!-- Unit & Kelas -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <div class="font-medium text-slate-900"><?php echo htmlspecialchars($s['class_name']); ?></div>
                                    <span class="text-[10px] text-slate-500"><?php echo htmlspecialchars($s['unit_name']); ?></span>
                                </td>

                                <!-- Mata Pelajaran -->
                                <td class="py-3.5 px-3">
                                    <span class="font-medium text-slate-800 block">
                                        <?php echo htmlspecialchars($s['subject_name']); ?>
                                    </span>
                                </td>

                                <!-- Guru Pengajar -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="font-medium text-slate-900 block"><?php echo htmlspecialchars($s['teacher_name']); ?></span>
                                    <?php if (!empty($s['teacher_time_in'])): ?>
                                        <span class="text-[10px] text-emerald-700">
                                            Masuk <?php echo date('H:i', strtotime($s['teacher_time_in'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-400">Belum presensi</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Input Absen Siswa -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <?php if ($s['has_attendance']): ?>
                                        <div>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Sudah Absen (<?php echo $s['total_students']; ?>)
                                            </span>
                                            <div class="text-[10px] text-slate-500 mt-1">
                                                H:<?php echo (int)$s['count_present']; ?> | S:<?php echo (int)$s['count_sick']; ?> | I:<?php echo (int)$s['count_permit']; ?> | A:<?php echo (int)$s['count_absent']; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            Belum Diabsen
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Input Jurnal Kelas -->
                                <td class="py-3.5 px-3">
                                    <?php if ($s['has_journal']): ?>
                                        <div class="space-y-0.5">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                Sudah Jurnal
                                            </span>
                                            <p class="text-xs text-slate-700 line-clamp-1" title="<?php echo htmlspecialchars($s['journal_topic']); ?>">
                                                <?php echo htmlspecialchars($s['journal_topic'] ?: '-'); ?>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            Belum Diisi
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <?php if ($s['status_code'] === 'complete'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Lengkap
                                        </span>
                                    <?php elseif ($s['status_code'] === 'only_attendance'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            Hanya Absen
                                        </span>
                                    <?php elseif ($s['status_code'] === 'only_journal'): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-sky-50 text-sky-700 border border-sky-200">
                                            Hanya Jurnal
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            Belum Input
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Detail Action -->
                                <td class="py-3.5 pr-6 pl-3 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openScheduleDetailModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-colors shadow-sm"
                                            title="Lihat Detail Sesi">
                                        <i class="fa-solid fa-eye text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 3: GURU SUDAH ABSENSI KELAS -->
    <?php elseif ($tab === 'already_attended'): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                <span class="text-slate-600">Daftar guru yang telah menginput absensi kelas pada jadwal hari ini.</span>
                <div class="flex items-center gap-2">
                    <a href="<?php echo htmlspecialchars($export_progress_url); ?>" 
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium text-xs shadow-sm"
                       title="Download file Excel rekap progres guru">
                        <i class="fa-solid fa-file-excel text-emerald-600"></i>
                        <span>Export Excel</span>
                    </a>
                    <span class="font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                        Total: <?php echo count($teachers_attended); ?> Guru (<?php echo $count_teachers_complete_att; ?> Selesai, <?php echo $count_teachers_partial_att; ?> Sebagian)
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-200">
                        <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4 min-w-[200px]">Guru Pengajar</th>
                            <th class="py-3.5 px-4 min-w-[120px]">Unit</th>
                            <th class="py-3.5 px-4 text-center min-w-[120px]">Presensi Pegawai</th>
                            <th class="py-3.5 px-4 text-center min-w-[130px]">Progres Absensi</th>
                            <th class="py-3.5 px-4 min-w-[300px]">Sesi yang Sudah Diabsen</th>
                            <th class="py-3.5 px-4 text-center min-w-[110px]">Status</th>
                            <th class="py-3.5 pr-6 pl-4 text-right min-w-[70px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($teachers_attended)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-1">
                                        <i class="fa-solid fa-user-slash text-slate-300 text-2xl mb-1"></i>
                                        <p class="font-semibold text-slate-700">Belum ada absensi kelas</p>
                                        <p class="text-[11px] text-slate-400">Belum ada guru yang menginput absensi kelas untuk kriteria ini.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($teachers_attended as $emp_id => $t): ?>
                            <?php
                            $is_all_complete = ($t['sessions_with_attendance'] === $t['total_sessions'] && $t['total_sessions'] > 0);
                            $att_pct = $t['total_sessions'] > 0 ? round(($t['sessions_with_attendance'] / $t['total_sessions']) * 100) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-8 w-8 rounded-full object-cover border border-slate-200 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-900 block truncate">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: '-'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Presensi Pegawai -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Belum Presensi</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Progres Absen -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[90px]">
                                        <span class="font-medium text-slate-800">
                                            <?php echo $t['sessions_with_attendance']; ?> / <?php echo $t['total_sessions']; ?> Sesi
                                        </span>
                                        <div class="w-16 bg-slate-100 h-1 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $att_pct === 100 ? 'bg-emerald-500' : 'bg-amber-400'; ?>"
                                                 style="width: <?php echo $att_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Sesi yang SUDAH Diabsen -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1 max-w-md">
                                        <?php foreach ($t['sessions'] as $s): ?>
                                            <?php if ($s['has_attendance']): ?>
                                                <div class="p-2 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs gap-2">
                                                    <div>
                                                        <div class="font-medium text-slate-800">
                                                            <?php echo htmlspecialchars($s['class_name']); ?> • <?php echo htmlspecialchars($s['subject_name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400">
                                                            <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?> (<?php echo htmlspecialchars($s['unit_name']); ?>)
                                                        </div>
                                                    </div>
                                                    <div class="text-right flex-shrink-0 text-[10px] font-medium text-emerald-700">
                                                        H:<?php echo (int)$s['count_present']; ?> S:<?php echo (int)$s['count_sick']; ?> I:<?php echo (int)$s['count_permit']; ?> A:<?php echo (int)$s['count_absent']; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($is_all_complete): ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            Sebagian
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 pr-6 pl-4 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors shadow-sm">
                                        <i class="fa-solid fa-eye text-slate-500"></i>
                                        <span>Rincian</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 4: GURU BELUM MENGISI ABSENSI -->
    <?php elseif ($tab === 'not_attended'): ?>
        <?php
        $display_teachers_not_attended = [];
        foreach ($teachers_not_attended as $emp_id => $t) {
            $is_none = ($t['sessions_with_attendance'] === 0);
            if ($filter_att === 'zero' && !$is_none) continue;
            if ($filter_att === 'partial' && $is_none) continue;
            $display_teachers_not_attended[$emp_id] = $t;
        }
        ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50 flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs">
                <div class="flex items-center flex-wrap gap-2">
                    <span class="text-slate-500 font-medium mr-1">Filter Kategori:</span>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_att' => 'all'])); ?>"
                       class="px-2.5 py-1 rounded-lg text-xs font-medium transition-colors <?php echo $filter_att === 'all' ? 'bg-slate-800 text-white shadow-sm' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-100'; ?>">
                        Semua Belum Lengkap (<?php echo count($teachers_not_attended); ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_att' => 'zero'])); ?>"
                       class="px-2.5 py-1 rounded-lg text-xs font-medium transition-colors <?php echo $filter_att === 'zero' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100'; ?>">
                        Tidak Absen Sama Sekali (<?php echo $count_teachers_zero_attended; ?>)
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['filter_att' => 'partial'])); ?>"
                       class="px-2.5 py-1 rounded-lg text-xs font-medium transition-colors <?php echo $filter_att === 'partial' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 border border-amber-200 text-amber-700 hover:bg-amber-100'; ?>">
                        Sebagian Diabsen (<?php echo max(0, count($teachers_not_attended) - $count_teachers_zero_attended); ?>)
                    </a>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?php echo htmlspecialchars($export_progress_url); ?>" 
                       class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 font-medium text-xs shadow-sm"
                       title="Download file Excel rekap progres guru">
                        <i class="fa-solid fa-file-excel text-emerald-600"></i>
                        <span>Export Excel</span>
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-50/80 border-b border-slate-200">
                        <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4 min-w-[200px]">Guru Pengajar</th>
                            <th class="py-3.5 px-4 min-w-[120px]">Unit</th>
                            <th class="py-3.5 px-4 text-center min-w-[120px]">Presensi Masuk</th>
                            <th class="py-3.5 px-4 text-center min-w-[120px]">Sesi Tertunda</th>
                            <th class="py-3.5 px-4 min-w-[320px]">Kelas yang Belum Diabsen</th>
                            <th class="py-3.5 pr-6 pl-4 text-right min-w-[140px]">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($display_teachers_not_attended)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-1">
                                        <i class="fa-solid fa-circle-check text-emerald-500 text-2xl mb-1"></i>
                                        <p class="font-semibold text-slate-800">
                                            <?php if ($filter_att === 'zero'): ?>
                                                Tidak Ada Guru Belum Absen Sama Sekali
                                            <?php elseif ($filter_att === 'partial'): ?>
                                                Tidak Ada Guru dengan Status Absen Sebagian
                                            <?php else: ?>
                                                Semua Absensi Selesai
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            <?php if ($filter_att === 'zero'): ?>
                                                Seluruh guru yang terjadwal telah menginput setidaknya 1 sesi absensi kelas.
                                            <?php elseif ($filter_att === 'partial'): ?>
                                                Tidak ditemukan guru yang baru menginput sebagian sesi jadwal.
                                            <?php else: ?>
                                                Seluruh guru yang mengajar hari ini telah menginput absensi kelas.
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($display_teachers_not_attended as $emp_id => $t): ?>
                            <?php
                            $pending_count = $t['total_sessions'] - $t['sessions_with_attendance'];
                            $is_none = ($t['sessions_with_attendance'] === 0);

                            // Collect pending classes for WhatsApp message
                            $pending_classes = [];
                            foreach ($t['sessions'] as $s) {
                                if (!$s['has_attendance']) {
                                    $timeStr = date('H:i', strtotime($s['start_time'])) . '-' . date('H:i', strtotime($s['end_time']));
                                    $pending_classes[] = $s['class_name'] . ' (' . $s['subject_name'] . ', ' . $timeStr . ')';
                                }
                            }
                            $wa_num = formatWaNumber($t['teacher_phone'] ?? '');
                            $wa_msg = "Assalamu'alaikum Warahmatullahi Wabarakatuh, Ustadz/Ustadzah {$t['teacher_name']}.\n\nMengingatkan untuk pengisian absensi kelas pada SIM YAC hari ini ({$formatted_date_indo}) untuk mata pelajaran:\n• " . implode("\n• ", $pending_classes) . "\n\nMohon kesediaannya untuk segera menginput. Terima kasih. Jazakumullahu khairan.";
                            $wa_url = !empty($wa_num) ? "https://wa.me/{$wa_num}?text=" . rawurlencode($wa_msg) : "";
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-8 w-8 rounded-full object-cover border border-slate-200 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-medium text-slate-900 block truncate">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: '-'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Kehadiran Pegawai di Sekolah -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-clock text-[10px]"></i>
                                            <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">Belum Hadir</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Sesi Tertunda -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $is_none ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200'; ?>">
                                        <?php echo $pending_count; ?> dari <?php echo $t['total_sessions']; ?> Sesi
                                    </span>
                                </td>

                                <!-- List Jadwal Kelas yang BELUM Diabsen -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1 max-w-md">
                                        <?php foreach ($t['sessions'] as $s): ?>
                                            <?php if (!$s['has_attendance']): ?>
                                                <div class="p-2 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs gap-2">
                                                    <div>
                                                        <div class="font-medium text-slate-900">
                                                            <?php echo htmlspecialchars($s['class_name']); ?> • <?php echo htmlspecialchars($s['subject_name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-400">
                                                            <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?> (<?php echo htmlspecialchars($s['unit_name']); ?>)
                                                        </div>
                                                    </div>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200 flex-shrink-0">
                                                        Belum
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Action / Reminder via WhatsApp & Modal -->
                                <td class="py-3.5 pr-6 pl-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if (!empty($wa_url)): ?>
                                            <a href="<?php echo htmlspecialchars($wa_url); ?>" target="_blank"
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 transition-colors shadow-sm"
                                               title="Kirim pesan WhatsApp pengingat">
                                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                                <span>Ingatkan</span>
                                            </a>
                                        <?php endif; ?>

                                        <button type="button"
                                                onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-300 bg-white text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-colors shadow-sm"
                                                title="Lihat Detail Sesi">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Modal 1: Detail Guru & Rincian Sesi Mengajar Hari Ini -->
<div id="teacherDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 transition-opacity" onclick="closeTeacherDetailModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Box -->
        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 bg-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img id="mTeacherAvatar" src="" class="h-10 w-10 rounded-full object-cover border border-slate-200">
                    <div>
                        <h3 class="text-base font-bold text-slate-900" id="mTeacherName">-</h3>
                        <p class="text-xs text-slate-500" id="mTeacherNik">NIK: -</p>
                    </div>
                </div>
                <button type="button" onclick="closeTeacherDetailModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Check-in status card -->
                <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-lg border border-slate-200 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Presensi Pegawai</span>
                        <span class="font-medium text-slate-800" id="mTeacherPresensi">-</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Kepatuhan</span>
                        <span class="font-semibold text-cyan-700" id="mTeacherProgress">-</span>
                    </div>
                </div>

                <h4 class="text-xs font-semibold text-slate-700 uppercase tracking-wider">Jadwal Mengajar Hari Ini</h4>

                <div id="mTeacherSessionsList" class="space-y-2.5">
                    <!-- Session cards injected via JS -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeTeacherDetailModal()"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Detail Sesi Spesifik (Topik, Catatan, Absensi) -->
<div id="scheduleDetailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 transition-opacity" onclick="closeScheduleDetailModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Box -->
        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-200 bg-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900" id="mSchedSubject">-</h3>
                    <p class="text-xs text-slate-500" id="mSchedClassTime">-</p>
                </div>
                <button type="button" onclick="closeScheduleDetailModal()" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 text-xs">
                <!-- Guru Info -->
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <div class="h-8 w-8 rounded-lg bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <div>
                        <span class="font-medium text-slate-900 block" id="mSchedTeacherName">-</span>
                        <span class="text-slate-500 text-[11px]" id="mSchedUnit">-</span>
                    </div>
                </div>

                <!-- Jurnal Info -->
                <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Jurnal Kelas</span>
                        <span id="mSchedJournalBadge" class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-200 text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block">Topik / Materi:</span>
                        <p class="text-xs font-medium text-slate-900" id="mSchedTopic">-</p>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block">Catatan Tambahan:</span>
                        <p class="text-xs text-slate-600" id="mSchedNotes">-</p>
                    </div>
                    <div class="text-[10px] text-slate-400 pt-1" id="mSchedTimestamp"></div>
                </div>

                <!-- Absensi Siswa Info -->
                <div class="p-4 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Absensi Siswa</span>
                        <span id="mSchedAttBadge" class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-200 text-slate-700">-</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 text-center pt-1" id="mSchedAttGrid">
                        <div class="p-2 bg-white rounded border border-slate-200">
                            <span class="text-[10px] text-emerald-700 block font-medium">Hadir</span>
                            <span class="text-sm font-bold text-slate-900" id="mCountHadir">0</span>
                        </div>
                        <div class="p-2 bg-white rounded border border-slate-200">
                            <span class="text-[10px] text-amber-700 block font-medium">Sakit</span>
                            <span class="text-sm font-bold text-slate-900" id="mCountSakit">0</span>
                        </div>
                        <div class="p-2 bg-white rounded border border-slate-200">
                            <span class="text-[10px] text-blue-700 block font-medium">Izin</span>
                            <span class="text-sm font-bold text-slate-900" id="mCountIzin">0</span>
                        </div>
                        <div class="p-2 bg-white rounded border border-slate-200">
                            <span class="text-[10px] text-rose-700 block font-medium">Alpa</span>
                            <span class="text-sm font-bold text-slate-900" id="mCountAlpa">0</span>
                        </div>
                        <div class="p-2 bg-white rounded border border-slate-200">
                            <span class="text-[10px] text-purple-700 block font-medium">Telat</span>
                            <span class="text-sm font-bold text-slate-900" id="mCountTelat">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeScheduleDetailModal()"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
function setDateAndSubmit(d) {
    document.getElementById('dateInput').value = d;
    document.getElementById('filterForm').submit();
}

// Teacher Detail Modal Handlers
function openTeacherDetailModal(teacher) {
    document.getElementById('mTeacherAvatar').src = teacher.avatar_url;
    document.getElementById('mTeacherName').innerText = teacher.teacher_name;
    document.getElementById('mTeacherNik').innerText = teacher.teacher_nik ? 'NIK: ' + teacher.teacher_nik : 'NIK: -';

    const presensiStr = teacher.teacher_time_in ? 
        'Hadir pukul ' + teacher.teacher_time_in.substring(0, 5) + (teacher.teacher_att_status ? ' (' + teacher.teacher_att_status + ')' : '') :
        'Belum melakukan presensi hari ini';
    document.getElementById('mTeacherPresensi').innerText = presensiStr;

    document.getElementById('mTeacherProgress').innerText = 
        teacher.sessions_completed + ' dari ' + teacher.total_sessions + ' Sesi Lengkap';

    const container = document.getElementById('mTeacherSessionsList');
    container.innerHTML = '';

    teacher.sessions.forEach((s) => {
        const hasJournal = s.has_journal;
        const hasAtt = s.has_attendance;

        let statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200">Belum Input</span>';
        if (hasJournal && hasAtt) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">Lengkap</span>';
        } else if (hasAtt && !hasJournal) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">Hanya Absen</span>';
        } else if (hasJournal && !hasAtt) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-sky-50 text-sky-700 border border-sky-200">Hanya Jurnal</span>';
        }

        const timeStr = s.start_time.substring(0, 5) + ' - ' + s.end_time.substring(0, 5);

        const card = document.createElement('div');
        card.className = "p-3 bg-white rounded-lg border border-slate-200 space-y-1.5 shadow-sm";
        card.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-xs text-slate-900">${s.class_name}</span>
                    <span class="text-[10px] text-slate-400">(${s.unit_name})</span>
                    <span class="text-[10px] text-slate-500 font-mono">${timeStr}</span>
                </div>
                ${statusBadge}
            </div>

            <div class="text-xs text-slate-700 font-medium">${s.subject_name}</div>

            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <span class="text-slate-400 block text-[10px]">Absensi Siswa:</span>
                    ${hasAtt ? 
                        `<span class="font-medium text-emerald-700">Sudah (H:${s.count_present || 0}, S:${s.count_sick || 0}, I:${s.count_permit || 0}, A:${s.count_absent || 0})</span>` :
                        `<span class="text-rose-600 font-medium">Belum Diabsen</span>`
                    }
                </div>
                <div class="p-2 rounded bg-slate-50 border border-slate-200">
                    <span class="text-slate-400 block text-[10px]">Jurnal Materi:</span>
                    ${hasJournal ?
                        `<span class="font-medium text-emerald-700 line-clamp-1" title="${s.journal_topic || ''}">${s.journal_topic || 'Sudah diisi'}</span>` :
                        `<span class="text-rose-600 font-medium">Belum Diisi</span>`
                    }
                </div>
            </div>
        `;
        container.appendChild(card);
    });

    document.getElementById('teacherDetailModal').classList.remove('hidden');
}

function closeTeacherDetailModal() {
    document.getElementById('teacherDetailModal').classList.add('hidden');
}

// Single Schedule Detail Modal Handlers
function openScheduleDetailModal(s) {
    document.getElementById('mSchedSubject').innerText = s.subject_name;
    document.getElementById('mSchedClassTime').innerText = 
        s.class_name + ' (' + s.unit_name + ') • ' + s.start_time.substring(0, 5) + ' - ' + s.end_time.substring(0, 5);

    document.getElementById('mSchedTeacherName').innerText = s.teacher_name;
    document.getElementById('mSchedUnit').innerText = 
        'Presensi: ' + (s.teacher_time_in ? 'Masuk ' + s.teacher_time_in.substring(0, 5) : 'Belum Presensi');

    // Jurnal
    if (s.has_journal) {
        document.getElementById('mSchedJournalBadge').className = "px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200";
        document.getElementById('mSchedJournalBadge').innerText = "Sudah Diisi";
        document.getElementById('mSchedTopic').innerText = s.journal_topic || '-';
        document.getElementById('mSchedNotes').innerText = s.journal_notes || '(Tidak ada catatan tambahan)';
        document.getElementById('mSchedTimestamp').innerText = s.journal_created_at ? 'Diisi pada: ' + s.journal_created_at : '';
    } else {
        document.getElementById('mSchedJournalBadge').className = "px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200";
        document.getElementById('mSchedJournalBadge').innerText = "Belum Diisi";
        document.getElementById('mSchedTopic').innerText = '-';
        document.getElementById('mSchedNotes').innerText = '-';
        document.getElementById('mSchedTimestamp').innerText = '';
    }

    // Absen
    if (s.has_attendance) {
        document.getElementById('mSchedAttBadge').className = "px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200";
        document.getElementById('mSchedAttBadge').innerText = "Sudah Diabsen (" + s.total_students + " Siswa)";
        document.getElementById('mCountHadir').innerText = s.count_present || 0;
        document.getElementById('mCountSakit').innerText = s.count_sick || 0;
        document.getElementById('mCountIzin').innerText = s.count_permit || 0;
        document.getElementById('mCountAlpa').innerText = s.count_absent || 0;
        document.getElementById('mCountTelat').innerText = s.count_late || 0;
        document.getElementById('mSchedAttGrid').style.opacity = '1';
    } else {
        document.getElementById('mSchedAttBadge').className = "px-2 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200";
        document.getElementById('mSchedAttBadge').innerText = "Belum Diabsen";
        document.getElementById('mCountHadir').innerText = '0';
        document.getElementById('mCountSakit').innerText = '0';
        document.getElementById('mCountIzin').innerText = '0';
        document.getElementById('mCountAlpa').innerText = '0';
        document.getElementById('mCountTelat').innerText = '0';
        document.getElementById('mSchedAttGrid').style.opacity = '0.5';
    }

    document.getElementById('scheduleDetailModal').classList.remove('hidden');
}

function closeScheduleDetailModal() {
    document.getElementById('scheduleDetailModal').classList.add('hidden');
}
</script>

<?php include '../layouts/footer.php'; ?>

