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

// Export URL
$query_params = $_GET;
$export_qs = http_build_query($query_params);
$export_url = "export_excel.php?" . $export_qs;
$print_url = "print.php?" . $export_qs;

include '../layouts/header.php';
?>

<div class="w-full pb-14">

    <!-- Top Breadcrumbs & Page Header -->
    <div class="mb-6">
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">
                        <i class="fa-solid fa-house mr-1"></i> Beranda
                    </a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-500 font-medium">Manajemen Akademik</span>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-[#2B3990] font-semibold">Rekap Presensi Guru</span>
                </li>
            </ol>
        </nav>

        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-gradient-to-tr from-[#2B3990] to-indigo-600 flex items-center justify-center text-white shadow-md shadow-indigo-100 flex-shrink-0">
                        <i class="fa-solid fa-user-check text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
                            Rekap Presensi Mengajar Guru
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                                <?php echo htmlspecialchars($idn_day); ?>
                            </span>
                        </h1>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Monitoring input absensi siswa dan jurnal kelas bagi guru yang terjadwal mengajar per hari ini.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <a href="<?php echo htmlspecialchars($export_url); ?>"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition-all shadow-sm shadow-emerald-600/20">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Export Excel</span>
                </a>

                <a href="<?php echo htmlspecialchars($print_url); ?>" target="_blank"
                   class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white text-slate-700 hover:bg-slate-50 border border-slate-200 active:scale-95 transition-all shadow-sm">
                    <i class="fa-solid fa-print text-slate-500"></i>
                    <span>Cetak Rekap</span>
                </a>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
        <!-- 1. Total Guru Mengajar -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-indigo-200 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Guru Terjadwal</span>
                <span class="h-8 w-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-chalkboard-user"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold text-slate-900"><?php echo number_format($count_unique_teachers); ?></span>
                <span class="text-[11px] text-slate-400">Guru</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-1">Mengajar hari ini</span>
        </div>

        <!-- 2. Total Sesi / Jam Pelajaran -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-blue-200 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Total Sesi</span>
                <span class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold text-slate-900"><?php echo number_format($total_schedules_all); ?></span>
                <span class="text-[11px] text-slate-400">Jam/Sesi</span>
            </div>
            <span class="text-[10px] text-slate-400 mt-1">Slot mata pelajaran</span>
        </div>

        <!-- 3. Selesai Lengkap (Absen & Jurnal) -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-emerald-200 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider">Sudah Lengkap</span>
                <span class="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-circle-check"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold text-emerald-700"><?php echo number_format($total_completed_all); ?></span>
                <span class="text-[11px] text-emerald-600/70">Sesi</span>
            </div>
            <span class="text-[10px] text-emerald-600/80 mt-1 font-medium">Absen & Jurnal OK</span>
        </div>

        <!-- 4. Belum Input Absen -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-amber-200 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-amber-700 uppercase tracking-wider">Belum Absen</span>
                <span class="h-8 w-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-user-xmark"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold text-amber-700"><?php echo number_format($total_no_att_all); ?></span>
                <span class="text-[11px] text-amber-600/70">Sesi</span>
            </div>
            <span class="text-[10px] text-amber-600/80 mt-1 font-medium">Siswa belum diabsen</span>
        </div>

        <!-- 5. Belum Input Jurnal -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm hover:border-rose-200 transition-all flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-rose-700 uppercase tracking-wider">Belum Jurnal</span>
                <span class="h-8 w-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-book-bookmark"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-bold text-rose-700"><?php echo number_format($total_no_journal_all); ?></span>
                <span class="text-[11px] text-rose-600/70">Sesi</span>
            </div>
            <span class="text-[10px] text-rose-600/80 mt-1 font-medium">Materi belum diisi</span>
        </div>

        <!-- 6. Persentase Kepatuhan -->
        <div class="bg-gradient-to-br from-[#2B3990] to-indigo-800 text-white p-4 rounded-2xl shadow-md shadow-indigo-900/10 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-semibold text-white/80 uppercase tracking-wider">Kepatuhan</span>
                <span class="h-8 w-8 rounded-lg bg-white/10 text-white flex items-center justify-center text-xs backdrop-blur-sm">
                    <i class="fa-solid fa-chart-pie"></i>
                </span>
            </div>
            <div class="mt-2.5 flex items-baseline gap-1">
                <span class="text-2xl font-black tracking-tight text-white"><?php echo $compliance_rate; ?>%</span>
                <span class="text-[11px] text-white/70">Selesai</span>
            </div>
            <div class="w-full bg-white/20 h-1.5 rounded-full mt-1.5 overflow-hidden">
                <div class="bg-emerald-400 h-full rounded-full transition-all duration-500" style="width: <?php echo $compliance_rate; ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-5 mb-6">
        <form id="filterForm" method="GET" action="index.php" class="space-y-4">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3.5 items-end">
                <!-- Date Picker & Shortcuts -->
                <div class="lg:col-span-2 space-y-1.5">
                    <div class="flex items-center justify-between">
                        <label for="date" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">
                            <i class="fa-regular fa-calendar mr-1 text-[#2B3990]"></i> Tanggal Pantau
                        </label>
                        <div class="flex items-center gap-1.5">
                            <button type="button" onclick="setDateAndSubmit('<?php echo date('Y-m-d'); ?>')"
                                    class="text-[11px] font-medium px-2 py-0.5 rounded-md <?php echo $date === date('Y-m-d') ? 'bg-[#2B3990] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?> transition-all">
                                Hari Ini
                            </button>
                            <button type="button" onclick="setDateAndSubmit('<?php echo date('Y-m-d', strtotime('-1 day')); ?>')"
                                    class="text-[11px] font-medium px-2 py-0.5 rounded-md <?php echo $date === date('Y-m-d', strtotime('-1 day')) ? 'bg-[#2B3990] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?> transition-all">
                                Kemarin
                            </button>
                        </div>
                    </div>
                    <input type="date" name="date" id="dateInput" value="<?php echo htmlspecialchars($date); ?>"
                           onchange="document.getElementById('filterForm').submit()"
                           class="w-full rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 px-3.5 py-2.5 h-[42px] transition-all">
                </div>

                <!-- Academic Year Filter -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">Tahun Ajaran</label>
                    <select name="ay_id" onchange="document.getElementById('filterForm').submit()"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 px-3 py-2.5 h-[42px] transition-all">
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $ay['id'] == $selected_year_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['name'] . ' (' . $ay['semester'] . ')'); ?>
                                <?php echo $ay['is_active'] ? ' ★' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Education Unit Filter -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">Unit Pendidikan</label>
                    <select name="unit_id" onchange="document.getElementById('filterForm').submit()"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 px-3 py-2.5 h-[42px] transition-all">
                        <option value="">Semua Unit</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">Status Input</label>
                    <select name="status" onchange="document.getElementById('filterForm').submit()"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 px-3 py-2.5 h-[42px] transition-all">
                        <option value="" <?php echo $status_filter === '' ? 'selected' : ''; ?>>Semua Status</option>
                        <option value="complete" <?php echo $status_filter === 'complete' ? 'selected' : ''; ?>>✓ Lengkap (Absen & Jurnal)</option>
                        <option value="incomplete" <?php echo $status_filter === 'incomplete' ? 'selected' : ''; ?>>! Belum Lengkap</option>
                        <option value="no_attendance" <?php echo $status_filter === 'no_attendance' ? 'selected' : ''; ?>>✗ Belum Absen Siswa</option>
                        <option value="no_journal" <?php echo $status_filter === 'no_journal' ? 'selected' : ''; ?>>✗ Belum Isi Jurnal</option>
                        <option value="empty" <?php echo $status_filter === 'empty' ? 'selected' : ''; ?>>∅ Belum Input Keduanya</option>
                    </select>
                </div>

                <!-- Search Input & Reset Button -->
                <div class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Cari guru/mapel/kelas..."
                               class="w-full rounded-xl border border-slate-200 bg-slate-50/70 text-slate-800 text-sm focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 pl-9 pr-3.5 py-2.5 h-[42px] transition-all">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    </div>

                    <a href="index.php?date=<?php echo htmlspecialchars($date); ?>&tab=<?php echo htmlspecialchars($tab); ?>"
                       title="Reset Filter"
                       class="h-[42px] w-[42px] rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all flex-shrink-0">
                        <i class="fa-solid fa-rotate-left text-xs"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tab Switching Header -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-4">
        <div class="inline-flex p-1 bg-slate-200/80 rounded-xl flex-wrap gap-1">
            <!-- 1. Rekap Per Guru -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'summary'])); ?>"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $tab === 'summary' ? 'bg-white text-[#2B3990] shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-users-viewfinder"></i>
                <span>Rekap Per Guru</span>
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] <?php echo $tab === 'summary' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-300 text-slate-700'; ?>">
                    <?php echo count($filtered_teacher_summaries); ?>
                </span>
            </a>

            <!-- 2. Rincian Sesi Mengajar -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'schedules'])); ?>"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $tab === 'schedules' ? 'bg-white text-[#2B3990] shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-table-list"></i>
                <span>Rincian Sesi Mengajar</span>
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] <?php echo $tab === 'schedules' ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-300 text-slate-700'; ?>">
                    <?php echo count($processed_schedules); ?>
                </span>
            </a>

            <!-- 3. Guru Sudah Absensi Kelas -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'already_attended'])); ?>"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $tab === 'already_attended' ? 'bg-white text-emerald-800 shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-user-check text-emerald-600"></i>
                <span>Sudah Absensi Kelas</span>
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] <?php echo $tab === 'already_attended' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-300 text-slate-700'; ?> font-bold">
                    <?php echo count($teachers_attended); ?>
                </span>
            </a>

            <!-- 4. Guru Belum Mengisi Absensi -->
            <a href="?<?php echo http_build_query(array_merge($_GET, ['tab' => 'not_attended'])); ?>"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold transition-all <?php echo $tab === 'not_attended' ? 'bg-white text-rose-800 shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-user-xmark text-rose-600"></i>
                <span>Belum Mengisi Absensi</span>
                <span class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] <?php echo $tab === 'not_attended' ? 'bg-rose-100 text-rose-800' : 'bg-slate-300 text-slate-700'; ?> font-bold">
                    <?php echo count($teachers_not_attended); ?>
                </span>
            </a>
        </div>

        <div class="text-xs text-slate-500 flex items-center gap-2">
            <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Menampilkan data: <strong class="text-slate-800"><?php echo htmlspecialchars($formatted_date_indo); ?></strong></span>
        </div>
    </div>

    <!-- TAB 1: REKAP PER GURU -->
    <?php if ($tab === 'summary'): ?>
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4">Guru Pengajar</th>
                            <th class="py-3.5 px-4">Unit / Sekolah</th>
                            <th class="py-3.5 px-4 text-center">Presensi Pegawai</th>
                            <th class="py-3.5 px-4 text-center">Jadwal Hari Ini</th>
                            <th class="py-3.5 px-4 text-center">Input Absen Siswa</th>
                            <th class="py-3.5 px-4 text-center">Input Jurnal Kelas</th>
                            <th class="py-3.5 px-4 text-center">Status Akhir</th>
                            <th class="py-3.5 pr-6 pl-4 text-right">Rincian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($filtered_teacher_summaries)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl">
                                            <i class="fa-solid fa-inbox"></i>
                                        </div>
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
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-9 w-9 rounded-xl object-cover ring-2 ring-slate-100 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-bold text-slate-900 block truncate group-hover:text-[#2B3990] transition-colors">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-[11px] text-slate-400 font-mono">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: 'NIK: -'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200/60">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Kehadiran Pegawai (Fingerprint/App) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                                <i class="fa-solid fa-clock text-[9px]"></i>
                                                <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                            </span>
                                            <span class="text-[9px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($t['teacher_att_status'] ?? 'Hadir'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500">
                                            Belum Presensi
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Total Sesi Hari Ini -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap font-bold text-slate-800">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs">
                                        <?php echo $t['total_sessions']; ?> Sesi
                                    </span>
                                </td>

                                <!-- Progress Input Absen -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[90px]">
                                        <span class="font-bold <?php echo $t['sessions_with_attendance'] === $t['total_sessions'] ? 'text-emerald-700' : 'text-slate-700'; ?>">
                                            <?php echo $t['sessions_with_attendance']; ?> / <?php echo $t['total_sessions']; ?> Sesi
                                        </span>
                                        <div class="w-20 bg-slate-100 h-1.5 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $att_pct === 100 ? 'bg-emerald-500' : ($att_pct > 0 ? 'bg-amber-400' : 'bg-slate-200'); ?>"
                                                 style="width: <?php echo $att_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Progress Input Jurnal -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[90px]">
                                        <span class="font-bold <?php echo $t['sessions_with_journal'] === $t['total_sessions'] ? 'text-emerald-700' : 'text-slate-700'; ?>">
                                            <?php echo $t['sessions_with_journal']; ?> / <?php echo $t['total_sessions']; ?> Sesi
                                        </span>
                                        <div class="w-20 bg-slate-100 h-1.5 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $journal_pct === 100 ? 'bg-emerald-500' : ($journal_pct > 0 ? 'bg-amber-400' : 'bg-slate-200'); ?>"
                                                 style="width: <?php echo $journal_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Keseluruhan -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($is_all_complete): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            Lengkap
                                        </span>
                                    <?php elseif ($is_none_complete): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-circle-xmark text-[10px]"></i>
                                            Belum Mengisi
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                            Sebagian
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 pr-6 pl-4 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-[#2B3990] hover:text-white transition-all shadow-sm">
                                        <i class="fa-solid fa-eye text-[11px]"></i>
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
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-3 min-w-[130px]">Waktu & Jam</th>
                            <th class="py-3.5 px-3 min-w-[140px]">Unit & Kelas</th>
                            <th class="py-3.5 px-3 min-w-[180px]">Mata Pelajaran</th>
                            <th class="py-3.5 px-3 min-w-[200px]">Guru Pengajar</th>
                            <th class="py-3.5 px-3 text-center min-w-[160px]">Input Absen Siswa</th>
                            <th class="py-3.5 px-3 text-center min-w-[220px]">Input Jurnal Kelas</th>
                            <th class="py-3.5 px-3 text-center min-w-[110px]">Status</th>
                            <th class="py-3.5 pr-6 pl-3 text-right min-w-[80px]">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($processed_schedules)): ?>
                            <tr>
                                <td colspan="8" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl">
                                            <i class="fa-solid fa-calendar-xmark"></i>
                                        </div>
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
                            $period_label = ($start_p == $end_p) ? "Jam ke-{$start_p}" : "Jam ke {$start_p} - {$end_p}";
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <!-- Waktu Pelajaran -->
                                <td class="py-3.5 pl-6 pr-3 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">
                                        <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?>
                                    </div>
                                    <span class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md font-medium inline-block mt-0.5">
                                        <?php echo htmlspecialchars($period_label); ?>
                                    </span>
                                </td>

                                <!-- Unit & Kelas -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($s['class_name']); ?></div>
                                    <span class="text-[10px] text-slate-500"><?php echo htmlspecialchars($s['unit_name']); ?></span>
                                </td>

                                <!-- Mata Pelajaran -->
                                <td class="py-3.5 px-3">
                                    <span class="font-semibold text-slate-800 block">
                                        <?php echo htmlspecialchars($s['subject_name']); ?>
                                    </span>
                                </td>

                                <!-- Guru Pengajar -->
                                <td class="py-3.5 px-3 whitespace-nowrap">
                                    <span class="font-semibold text-slate-900 block"><?php echo htmlspecialchars($s['teacher_name']); ?></span>
                                    <?php if (!empty($s['teacher_time_in'])): ?>
                                        <span class="text-[10px] text-emerald-700 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-clock text-[8px]"></i> Masuk <?php echo date('H:i', strtotime($s['teacher_time_in'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-400">Belum presensi</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Input Absen Siswa -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <?php if ($s['has_attendance']): ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <i class="fa-solid fa-check text-[9px]"></i> Sudah Absen (<?php echo $s['total_students']; ?>)
                                            </span>
                                            <div class="flex items-center gap-1.5 mt-1 text-[10px] font-semibold">
                                                <span class="text-emerald-700" title="Hadir">H: <?php echo (int)$s['count_present']; ?></span>
                                                <span class="text-amber-700" title="Sakit">S: <?php echo (int)$s['count_sick']; ?></span>
                                                <span class="text-blue-700" title="Izin">I: <?php echo (int)$s['count_permit']; ?></span>
                                                <span class="text-rose-700" title="Alpa">A: <?php echo (int)$s['count_absent']; ?></span>
                                                <?php if ((int)$s['count_late'] > 0): ?>
                                                    <span class="text-purple-700" title="Terlambat">T: <?php echo (int)$s['count_late']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200/60">
                                            <i class="fa-solid fa-xmark text-[9px]"></i> Belum Diabsen
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Input Jurnal Kelas -->
                                <td class="py-3.5 px-3">
                                    <?php if ($s['has_journal']): ?>
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                    Sudah Jurnal
                                                </span>
                                                <?php if (!empty($s['journal_created_at'])): ?>
                                                    <span class="text-[10px] text-slate-400">
                                                        <?php echo date('H:i', strtotime($s['journal_created_at'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-xs font-semibold text-slate-800 line-clamp-1" title="<?php echo htmlspecialchars($s['journal_topic']); ?>">
                                                <?php echo htmlspecialchars($s['journal_topic'] ?: 'Topik tanpa judul'); ?>
                                            </p>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200/60">
                                                <i class="fa-solid fa-xmark text-[9px]"></i> Belum Isi Jurnal
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-3 text-center whitespace-nowrap">
                                    <?php if ($s['status_code'] === 'complete'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            Lengkap
                                        </span>
                                    <?php elseif ($s['status_code'] === 'only_attendance'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            Hanya Absen
                                        </span>
                                    <?php elseif ($s['status_code'] === 'only_journal'): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-sky-100 text-sky-800 border border-sky-200">
                                            Hanya Jurnal
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-200">
                                            Belum Input
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Detail Action -->
                                <td class="py-3.5 pr-6 pl-3 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openScheduleDetailModal(<?php echo json_encode($s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-slate-100 text-slate-600 hover:bg-[#2B3990] hover:text-white transition-all shadow-sm"
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
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Header bar for Tab 3 -->
            <div class="p-4 bg-emerald-50/70 border-b border-emerald-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-sm">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-emerald-950 uppercase tracking-wide">Daftar Guru yang Sudah Absensi Kelas</h3>
                        <p class="text-[11px] text-emerald-700">Guru yang telah menyelesaikan sebagian atau seluruh absensi siswa pada jadwal hari ini.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-600 text-white shadow-sm">
                        Total: <?php echo count($teachers_attended); ?> Guru
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4 min-w-[220px]">Guru Pengajar</th>
                            <th class="py-3.5 px-4 min-w-[130px]">Unit</th>
                            <th class="py-3.5 px-4 text-center min-w-[120px]">Presensi Masuk</th>
                            <th class="py-3.5 px-4 text-center min-w-[140px]">Progres Absensi</th>
                            <th class="py-3.5 px-4 min-w-[320px]">Sesi & Kelas yang Sudah Diabsen</th>
                            <th class="py-3.5 px-4 text-center min-w-[130px]">Status</th>
                            <th class="py-3.5 pr-6 pl-4 text-right min-w-[90px]">Rincian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($teachers_attended)): ?>
                            <tr>
                                <td colspan="7" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <div class="h-12 w-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl">
                                            <i class="fa-solid fa-user-slash"></i>
                                        </div>
                                        <p class="font-semibold text-slate-700">Belum ada absensi kelas</p>
                                        <p class="text-[11px] text-slate-400">Belum ada guru yang menginput absensi kelas untuk tanggal dan kriteria filter ini.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($teachers_attended as $emp_id => $t): ?>
                            <?php
                            $is_all_complete = ($t['sessions_with_attendance'] === $t['total_sessions'] && $t['total_sessions'] > 0);
                            $att_pct = $t['total_sessions'] > 0 ? round(($t['sessions_with_attendance'] / $t['total_sessions']) * 100) : 0;
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-9 w-9 rounded-xl object-cover ring-2 ring-slate-100 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-bold text-slate-900 block truncate group-hover:text-[#2B3990] transition-colors">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-[11px] text-slate-400 font-mono">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: 'NIK: -'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200/60">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Presensi Pegawai -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                                <i class="fa-solid fa-clock text-[9px]"></i>
                                                <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                            </span>
                                            <span class="text-[9px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($t['teacher_att_status'] ?? 'Hadir'); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-100 text-slate-500">
                                            Belum Presensi
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Progres Absen -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center min-w-[100px]">
                                        <span class="font-bold text-emerald-700">
                                            <?php echo $t['sessions_with_attendance']; ?> dari <?php echo $t['total_sessions']; ?> Sesi
                                        </span>
                                        <div class="w-24 bg-slate-100 h-1.5 rounded-full mt-1 overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $att_pct === 100 ? 'bg-emerald-500' : 'bg-amber-400'; ?>"
                                                 style="width: <?php echo $att_pct; ?>%;"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Sesi yang SUDAH Diabsen -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1.5 max-w-md">
                                        <?php foreach ($t['sessions'] as $s): ?>
                                            <?php if ($s['has_attendance']): ?>
                                                <div class="p-2 bg-emerald-50/60 rounded-xl border border-emerald-100/80 flex items-center justify-between text-xs gap-2">
                                                    <div>
                                                        <div class="font-bold text-slate-800">
                                                            <?php echo htmlspecialchars($s['class_name']); ?> • <?php echo htmlspecialchars($s['subject_name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-500">
                                                            <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?> (<?php echo htmlspecialchars($s['unit_name']); ?>)
                                                        </div>
                                                    </div>
                                                    <div class="text-right flex-shrink-0">
                                                        <div class="text-[10px] font-bold text-emerald-800">
                                                            H:<?php echo (int)$s['count_present']; ?> | S:<?php echo (int)$s['count_sick']; ?> | I:<?php echo (int)$s['count_permit']; ?> | A:<?php echo (int)$s['count_absent']; ?>
                                                        </div>
                                                        <span class="text-[9px] text-slate-400">Total: <?php echo $s['total_students']; ?> Santri</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($is_all_complete): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i>
                                            Semua Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                            <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                                            Selesai Sebagian
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action Buttons -->
                                <td class="py-3.5 pr-6 pl-4 text-right whitespace-nowrap">
                                    <button type="button"
                                            onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-[#2B3990] hover:text-white transition-all shadow-sm">
                                        <i class="fa-solid fa-eye text-[11px]"></i>
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
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <!-- Header bar for Tab 4 -->
            <div class="p-4 bg-rose-50/70 border-b border-rose-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-xl bg-rose-600 text-white flex items-center justify-center text-sm shadow-sm shadow-rose-200">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-rose-950 uppercase tracking-wide">Daftar Guru yang Belum Mengisi Absensi Kelas</h3>
                        <p class="text-[11px] text-rose-700">
                            Terdapat <strong class="underline"><?php echo count($teachers_not_attended); ?> Guru</strong> dengan total <strong class="underline"><?php echo $total_pending_sessions; ?> Sesi Pelajaran</strong> yang belum diinput absensi siswanya hari ini.
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-600 text-white shadow-sm">
                        <?php echo count($teachers_not_attended); ?> Guru Belum Lengkap
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3.5 pl-6 pr-4 min-w-[220px]">Guru Pengajar</th>
                            <th class="py-3.5 px-4 min-w-[130px]">Unit</th>
                            <th class="py-3.5 px-4 text-center min-w-[130px]">Presensi Pegawai</th>
                            <th class="py-3.5 px-4 text-center min-w-[140px]">Sesi Tertunda</th>
                            <th class="py-3.5 px-4 min-w-[340px]">Jadwal Kelas yang Belum Diabsen</th>
                            <th class="py-3.5 pr-6 pl-4 text-right min-w-[160px]">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs">
                        <?php if (empty($teachers_not_attended)): ?>
                            <tr>
                                <td colspan="6" class="py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <div class="h-12 w-12 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-xl">
                                            <i class="fa-solid fa-circle-check"></i>
                                        </div>
                                        <p class="font-semibold text-slate-800">Alhamdulillah, Semua Absensi Selesai!</p>
                                        <p class="text-[11px] text-slate-500">Seluruh guru yang mengajar hari ini telah menyelesaikan input absensi kelas.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($teachers_not_attended as $emp_id => $t): ?>
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
                            <tr class="hover:bg-slate-50/80 transition-colors group">
                                <!-- Guru Name & Avatar -->
                                <td class="py-3.5 pl-6 pr-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($t['avatar_url']); ?>"
                                             alt="<?php echo htmlspecialchars($t['teacher_name']); ?>"
                                             class="h-9 w-9 rounded-xl object-cover ring-2 ring-slate-100 flex-shrink-0">
                                        <div class="min-w-0">
                                            <span class="font-bold text-slate-900 block truncate group-hover:text-[#2B3990] transition-colors">
                                                <?php echo htmlspecialchars($t['teacher_name']); ?>
                                            </span>
                                            <span class="text-[11px] text-slate-400 font-mono">
                                                <?php echo htmlspecialchars($t['teacher_nik'] ?: 'NIK: -'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Units -->
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($t['units'] as $u_name): ?>
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200/60">
                                                <?php echo htmlspecialchars($u_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Kehadiran Pegawai di Sekolah -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($t['teacher_time_in'])): ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                                <i class="fa-solid fa-clock text-[9px]"></i>
                                                Hadir <?php echo date('H:i', strtotime($t['teacher_time_in'])); ?>
                                            </span>
                                            <span class="text-[9px] text-slate-400 mt-0.5">Ada di Sekolah</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="inline-flex flex-col items-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-rose-50 text-rose-700 border border-rose-200/60">
                                                Belum Hadir
                                            </span>
                                            <span class="text-[9px] text-slate-400 mt-0.5">Presensi Kosong</span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Sesi Tertunda -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex flex-col items-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold <?php echo $is_none ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-amber-100 text-amber-800 border border-amber-200'; ?>">
                                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                                            <?php echo $pending_count; ?> dari <?php echo $t['total_sessions']; ?> Sesi
                                        </span>
                                        <span class="text-[10px] text-slate-400 mt-0.5">
                                            <?php echo $is_none ? 'Belum Sama Sekali' : 'Kurang Sebagian'; ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- List Jadwal Kelas yang BELUM Diabsen -->
                                <td class="py-3.5 px-4">
                                    <div class="space-y-1.5 max-w-md">
                                        <?php foreach ($t['sessions'] as $s): ?>
                                            <?php if (!$s['has_attendance']): ?>
                                                <div class="p-2 bg-rose-50/60 rounded-xl border border-rose-100 flex items-center justify-between text-xs gap-2">
                                                    <div>
                                                        <div class="font-bold text-slate-900">
                                                            <?php echo htmlspecialchars($s['class_name']); ?> • <?php echo htmlspecialchars($s['subject_name']); ?>
                                                        </div>
                                                        <div class="text-[10px] text-slate-500 font-mono">
                                                            <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?> (<?php echo htmlspecialchars($s['unit_name']); ?>)
                                                        </div>
                                                    </div>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-700 flex-shrink-0">
                                                        Belum Diabsen
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
                                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700 active:scale-95 transition-all shadow-sm"
                                               title="Kirim pesan WhatsApp pengingat absensi">
                                                <i class="fa-brands fa-whatsapp text-sm"></i>
                                                <span>Ingatkan WA</span>
                                            </a>
                                        <?php endif; ?>

                                        <button type="button"
                                                onclick='openTeacherDetailModal(<?php echo json_encode($t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-[#2B3990] hover:text-white transition-all shadow-sm"
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
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeTeacherDetailModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Box -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-[#2B3990] to-indigo-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img id="mTeacherAvatar" src="" class="h-11 w-11 rounded-xl object-cover ring-2 ring-white/20">
                    <div>
                        <h3 class="text-base font-bold leading-6" id="mTeacherName">-</h3>
                        <p class="text-xs text-white/80" id="mTeacherNik">NIK: -</p>
                    </div>
                </div>
                <button type="button" onclick="closeTeacherDetailModal()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <!-- Check-in status card -->
                <div class="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-200/80">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Presensi Pegawai Hari Ini</span>
                        <span class="text-xs font-semibold text-slate-800" id="mTeacherPresensi">-</span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block text-right">Kepatuhan Hari Ini</span>
                        <span class="text-xs font-bold text-indigo-700" id="mTeacherProgress">-</span>
                    </div>
                </div>

                <h4 class="text-xs font-bold text-slate-600 uppercase tracking-wider">Daftar Jadwal Mengajar Hari Ini</h4>

                <div id="mTeacherSessionsList" class="space-y-3">
                    <!-- Session cards injected via JS -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeTeacherDetailModal()"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 transition-colors">
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
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeScheduleDetailModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal Box -->
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-slate-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-[#2B3990] text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold" id="mSchedSubject">-</h3>
                    <p class="text-xs text-white/80" id="mSchedClassTime">-</p>
                </div>
                <button type="button" onclick="closeScheduleDetailModal()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10 transition-colors">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <!-- Guru Info -->
                <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-200/80">
                    <div class="h-9 w-9 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-chalkboard-user"></i>
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-900 block" id="mSchedTeacherName">-</span>
                        <span class="text-[11px] text-slate-500" id="mSchedUnit">-</span>
                    </div>
                </div>

                <!-- Jurnal Info -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Jurnal Kelas</span>
                        <span id="mSchedJournalBadge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700">-</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block">Topik / Materi:</span>
                        <p class="text-sm font-semibold text-slate-900" id="mSchedTopic">-</p>
                    </div>
                    <div>
                        <span class="text-[11px] text-slate-400 block">Catatan Tambahan:</span>
                        <p class="text-xs text-slate-600 italic" id="mSchedNotes">-</p>
                    </div>
                    <div class="text-[10px] text-slate-400 pt-1" id="mSchedTimestamp"></div>
                </div>

                <!-- Absensi Siswa Info -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Absensi Siswa</span>
                        <span id="mSchedAttBadge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-200 text-slate-700">-</span>
                    </div>
                    <div class="grid grid-cols-5 gap-2 text-center pt-1" id="mSchedAttGrid">
                        <div class="p-2 bg-emerald-50 rounded-lg border border-emerald-100">
                            <span class="text-[10px] text-emerald-600 block font-bold">Hadir</span>
                            <span class="text-sm font-black text-emerald-800" id="mCountHadir">0</span>
                        </div>
                        <div class="p-2 bg-amber-50 rounded-lg border border-amber-100">
                            <span class="text-[10px] text-amber-600 block font-bold">Sakit</span>
                            <span class="text-sm font-black text-amber-800" id="mCountSakit">0</span>
                        </div>
                        <div class="p-2 bg-blue-50 rounded-lg border border-blue-100">
                            <span class="text-[10px] text-blue-600 block font-bold">Izin</span>
                            <span class="text-sm font-black text-blue-800" id="mCountIzin">0</span>
                        </div>
                        <div class="p-2 bg-rose-50 rounded-lg border border-rose-100">
                            <span class="text-[10px] text-rose-600 block font-bold">Alpa</span>
                            <span class="text-sm font-black text-rose-800" id="mCountAlpa">0</span>
                        </div>
                        <div class="p-2 bg-purple-50 rounded-lg border border-purple-100">
                            <span class="text-[10px] text-purple-600 block font-bold">Telat</span>
                            <span class="text-sm font-black text-purple-800" id="mCountTelat">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeScheduleDetailModal()"
                        class="px-4 py-2 text-xs font-semibold rounded-xl bg-slate-200 text-slate-700 hover:bg-slate-300 transition-colors">
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

    teacher.sessions.forEach((s, idx) => {
        const hasJournal = s.has_journal;
        const hasAtt = s.has_attendance;

        let statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Belum Input</span>';
        if (hasJournal && hasAtt) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Lengkap</span>';
        } else if (hasAtt && !hasJournal) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Hanya Absen</span>';
        } else if (hasJournal && !hasAtt) {
            statusBadge = '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700">Hanya Jurnal</span>';
        }

        const timeStr = s.start_time.substring(0, 5) + ' - ' + s.end_time.substring(0, 5);

        const card = document.createElement('div');
        card.className = "p-3.5 bg-white rounded-xl border border-slate-200 space-y-2 hover:border-indigo-200 transition-colors shadow-sm";
        card.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-xs text-slate-900">${s.class_name}</span>
                    <span class="text-[10px] text-slate-400 font-medium">(${s.unit_name})</span>
                    <span class="text-[10px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded font-mono font-medium">${timeStr}</span>
                </div>
                ${statusBadge}
            </div>

            <div class="text-xs font-semibold text-slate-800">${s.subject_name}</div>

            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
                <div class="p-2 rounded-lg ${hasAtt ? 'bg-emerald-50/70 border border-emerald-100' : 'bg-slate-50 border border-slate-100'}">
                    <span class="text-slate-400 block text-[10px]">Absensi Siswa:</span>
                    ${hasAtt ? 
                        `<span class="font-bold text-emerald-700">Sudah (H:${s.count_present || 0}, S:${s.count_sick || 0}, I:${s.count_permit || 0}, A:${s.count_absent || 0})</span>` :
                        `<span class="text-rose-600 font-semibold">Belum Diabsen</span>`
                    }
                </div>
                <div class="p-2 rounded-lg ${hasJournal ? 'bg-emerald-50/70 border border-emerald-100' : 'bg-slate-50 border border-slate-100'}">
                    <span class="text-slate-400 block text-[10px]">Jurnal Materi:</span>
                    ${hasJournal ?
                        `<span class="font-bold text-emerald-700 line-clamp-1" title="${s.journal_topic || ''}">${s.journal_topic || 'Sudah diisi'}</span>` :
                        `<span class="text-rose-600 font-semibold">Belum Diisi</span>`
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
        document.getElementById('mSchedJournalBadge').className = "px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800";
        document.getElementById('mSchedJournalBadge').innerText = "Sudah Diisi";
        document.getElementById('mSchedTopic').innerText = s.journal_topic || '-';
        document.getElementById('mSchedNotes').innerText = s.journal_notes || '(Tidak ada catatan tambahan)';
        document.getElementById('mSchedTimestamp').innerText = s.journal_created_at ? 'Diisi pada: ' + s.journal_created_at : '';
    } else {
        document.getElementById('mSchedJournalBadge').className = "px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800";
        document.getElementById('mSchedJournalBadge').innerText = "Belum Diisi";
        document.getElementById('mSchedTopic').innerText = '-';
        document.getElementById('mSchedNotes').innerText = '-';
        document.getElementById('mSchedTimestamp').innerText = '';
    }

    // Absen
    if (s.has_attendance) {
        document.getElementById('mSchedAttBadge').className = "px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800";
        document.getElementById('mSchedAttBadge').innerText = "Sudah Diabsen (" + s.total_students + " Siswa)";
        document.getElementById('mCountHadir').innerText = s.count_present || 0;
        document.getElementById('mCountSakit').innerText = s.count_sick || 0;
        document.getElementById('mCountIzin').innerText = s.count_permit || 0;
        document.getElementById('mCountAlpa').innerText = s.count_absent || 0;
        document.getElementById('mCountTelat').innerText = s.count_late || 0;
        document.getElementById('mSchedAttGrid').style.opacity = '1';
    } else {
        document.getElementById('mSchedAttBadge').className = "px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800";
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
