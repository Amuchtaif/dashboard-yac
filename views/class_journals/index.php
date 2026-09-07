<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data & Rekap Jurnal Kelas";
$db = new Database();
$conn = $db->getConnection();

// --- Active Tab ---
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'daily'; // 'daily', 'monthly', 'semester'

// --- Filters ---
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Logged-in User Info for Role-based Scoping ---
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

// Check if user has a teaching schedule
$sched_stmt = $conn->prepare("SELECT COUNT(*) FROM class_schedules WHERE employee_id = :user_id");
$sched_stmt->execute([':user_id' => $_SESSION['user_id']]);
$has_schedule = ($sched_stmt->fetchColumn() > 0);

$is_guru = ($is_guru_position || $has_schedule);

if ($is_guru) {
    $employee_id = $_SESSION['user_id'];
}

// --- Fetch Master Data ---
if ($is_guru) {
    $grades_query = "
        SELECT DISTINCT gl.id, gl.name, gl.education_unit_id 
        FROM grade_levels gl
        WHERE gl.teacher_id = :current_user_id
           OR gl.id IN (SELECT grade_level_id FROM class_schedules WHERE employee_id = :current_user_id)
        ORDER BY gl.name ASC
    ";
    $grades_stmt = $conn->prepare($grades_query);
    $grades_stmt->execute([':current_user_id' => $_SESSION['user_id']]);
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $unit_ids = array_unique(array_column($grades, 'education_unit_id'));
    if (!empty($unit_ids)) {
        $units = $conn->query("SELECT id, name FROM education_units WHERE id IN (" . implode(',', $unit_ids) . ") ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $units = [];
    }
    
    $employees = $conn->query("SELECT id, full_name FROM employees WHERE id = " . (int)$_SESSION['user_id'])->fetchAll(PDO::FETCH_ASSOC);
} else {
    $units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $employees = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);

$active_ay = null;
foreach ($academic_years as $ay) {
    if ($ay['is_active']) {
        $active_ay = $ay;
        break;
    }
}
$academic_year_id = isset($_GET['academic_year_id']) ? $_GET['academic_year_id'] : ($active_ay ? $active_ay['id'] : ($academic_years[0]['id'] ?? ''));

// Determine Date Range based on Tab
if ($tab === 'monthly') {
    $start_date = "$month-01";
    $end_date = date('Y-m-t', strtotime($start_date));
} elseif ($tab === 'semester') {
    $selected_ay = null;
    foreach ($academic_years as $ay) {
        if ($ay['id'] == $academic_year_id) {
            $selected_ay = $ay;
            break;
        }
    }
    $start_date = $selected_ay ? $selected_ay['start_date'] : date('Y-01-01');
    $end_date = $selected_ay ? $selected_ay['end_date'] : date('Y-12-31');
} else {
    // daily
    $start_date = $date;
    $end_date = $date;
}

// --- Query Construction ---
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

// Fetch Journal Records
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
    ORDER BY cj.date DESC, lp.start_time ASC
";

$stmt = $conn->prepare($sql_journals);
$stmt->execute($params);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For Monthly & Semester Tabs: Compute Aggregate Summary per Mapel & Kelas
$summaries = [];
$monthly_breakdown = [];
if ($tab !== 'daily') {
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

    // Monthly breakdown for semester
    if ($tab === 'semester') {
        $sql_m_breakdown = "
            SELECT 
                DATE_FORMAT(cj.date, '%Y-%m') as m_key,
                DATE_FORMAT(cj.date, '%M %Y') as m_label,
                COUNT(cj.id) as count_journals
            FROM class_journals cj
            JOIN class_schedules cs ON cj.class_schedule_id = cs.id
            JOIN grade_levels gl ON cs.grade_level_id = gl.id
            $where_sql
            GROUP BY DATE_FORMAT(cj.date, '%Y-%m')
            ORDER BY m_key ASC
        ";
        $stmt_mb = $conn->prepare($sql_m_breakdown);
        $stmt_mb->execute($params);
        $monthly_breakdown = $stmt_mb->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Stats Calculation
$total_journals = count($journals);
$tot_present = array_sum(array_column($journals, 'count_present'));
$tot_sick = array_sum(array_column($journals, 'count_sick'));
$tot_permit = array_sum(array_column($journals, 'count_permit'));
$tot_absent = array_sum(array_column($journals, 'count_absent'));
$tot_late = array_sum(array_column($journals, 'count_late'));
$tot_all_att = $tot_present + $tot_sick + $tot_permit + $tot_absent + $tot_late;
$att_percentage = $tot_all_att > 0 ? round(($tot_present / $tot_all_att) * 100, 1) : 0;

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Header Title & Tabs -->
    <div class="sm:flex sm:items-center sm:justify-between border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Jurnal Kelas</h1>
            <p class="mt-1 text-sm text-slate-500">Monitoring dan rekapan harian, bulanan, serta semesteran kegiatan KBM.</p>
        </div>
        
        <!-- Tab Switches -->
        <div class="mt-4 sm:mt-0 flex items-center bg-slate-100 p-1.5 rounded-xl border border-slate-200 shadow-inner">
            <a href="?tab=daily&date=<?php echo htmlspecialchars($date); ?>&unit_id=<?php echo $unit_id; ?>&grade_id=<?php echo $grade_id; ?>&employee_id=<?php echo $employee_id; ?>&subject_id=<?php echo $subject_id; ?>" 
               class="px-4 py-2 text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-2 <?php echo $tab === 'daily' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-calendar-day"></i>
                Harian
            </a>
            <a href="?tab=monthly&month=<?php echo htmlspecialchars($month); ?>&unit_id=<?php echo $unit_id; ?>&grade_id=<?php echo $grade_id; ?>&employee_id=<?php echo $employee_id; ?>&subject_id=<?php echo $subject_id; ?>" 
               class="px-4 py-2 text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-2 <?php echo $tab === 'monthly' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-calendar-week"></i>
                Rekap Bulanan
            </a>
            <a href="?tab=semester&academic_year_id=<?php echo htmlspecialchars($academic_year_id); ?>&unit_id=<?php echo $unit_id; ?>&grade_id=<?php echo $grade_id; ?>&employee_id=<?php echo $employee_id; ?>&subject_id=<?php echo $subject_id; ?>" 
               class="px-4 py-2 text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-2 <?php echo $tab === 'semester' ? 'bg-white text-cyan-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'; ?>">
                <i class="fa-solid fa-graduation-cap"></i>
                Rekap Semesteran
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-6 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm" method="GET">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
            
            <!-- Date/Month/Academic Year Filter -->
            <?php if ($tab === 'daily'): ?>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tanggal</label>
                    <input type="date" id="input-date" name="date" value="<?php echo htmlspecialchars($date); ?>" 
                        onchange="this.form.submit()"
                        class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-slate-50 h-[42px]">
                </div>
            <?php elseif ($tab === 'monthly'): ?>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pilih Bulan</label>
                    <input type="month" id="input-month" name="month" value="<?php echo htmlspecialchars($month); ?>" 
                        onchange="this.form.submit()"
                        class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-slate-50 h-[42px]">
                </div>
            <?php elseif ($tab === 'semester'): ?>
                <div class="space-y-1">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tahun Ajaran / Semester</label>
                    <select name="academic_year_id" onchange="this.form.submit()" class="block w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 bg-slate-50 h-[42px]">
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo $academic_year_id == $ay['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['name'] . ' (' . $ay['semester'] . ')' . ($ay['is_active'] ? ' - Aktif' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Education Unit -->
            <div class="space-y-1 relative" id="container-unit_id">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jenjang</label>
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-unit_id" class="block truncate">
                        <?php 
                        $unitTitle = "Semua Jenjang";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <i id="arrow-unit_id" class="fa-solid fa-chevron-down h-3.5 w-3.5 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari jenjang..." class="block w-full rounded-md border-slate-200 py-1 pl-2 text-xs focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Semua Jenjang')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Jenjang</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Grade Level Dropdown -->
            <div class="space-y-1 relative" id="container-grade_id">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Kelas</label>
                <input type="hidden" name="grade_id" id="input-grade_id" value="<?php echo $grade_id; ?>">
                <button type="button" onclick="toggleFormDropdown('grade_id')"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-grade_id" class="block truncate">
                        <?php 
                        $gradeTitle = "Semua Kelas";
                        foreach($grades as $g) if($g['id'] == $grade_id) $gradeTitle = $g['name'];
                        echo htmlspecialchars($gradeTitle);
                        ?>
                    </span>
                    <i id="arrow-grade_id" class="fa-solid fa-chevron-down h-3.5 w-3.5 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-grade_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-grade_id" onkeyup="filterDropdownSearch('grade_id')" placeholder="Cari kelas..." class="block w-full rounded-md border-slate-200 py-1 pl-2 text-xs focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-grade_id">
                        <li onclick="selectFilterOption('grade_id', '', 'Semua Kelas')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Kelas</li>
                        <?php foreach ($grades as $g): ?>
                            <li onclick="selectFilterOption('grade_id', '<?php echo $g['id']; ?>', '<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>')" 
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                class="grade-option relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Subject Dropdown -->
            <div class="space-y-1 relative" id="container-subject_id">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mata Pelajaran</label>
                <input type="hidden" name="subject_id" id="input-subject_id" value="<?php echo $subject_id; ?>">
                <button type="button" onclick="toggleFormDropdown('subject_id')"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-subject_id" class="block truncate">
                        <?php 
                        $subTitle = "Semua Mapel";
                        foreach($subjects as $sb) if((string)$sb['id'] === (string)$subject_id) $subTitle = $sb['name'];
                        echo htmlspecialchars($subTitle);
                        ?>
                    </span>
                    <i id="arrow-subject_id" class="fa-solid fa-chevron-down h-3.5 w-3.5 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-subject_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-subject_id" onkeyup="filterDropdownSearch('subject_id')" placeholder="Cari mapel..." class="block w-full rounded-md border-slate-200 py-1 pl-2 text-xs focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-subject_id">
                        <li onclick="selectFilterOption('subject_id', '', 'Semua Mapel')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Mapel</li>
                        <?php foreach ($subjects as $sb): ?>
                            <li onclick="selectFilterOption('subject_id', '<?php echo $sb['id']; ?>', '<?php echo htmlspecialchars($sb['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($sb['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <?php if ($is_admin): ?>
            <!-- Teacher Dropdown -->
            <div class="space-y-1 relative" id="container-employee_id">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Guru Pengampu</label>
                <input type="hidden" name="employee_id" id="input-employee_id" value="<?php echo $employee_id; ?>">
                <button type="button" onclick="toggleFormDropdown('employee_id')"
                    class="flex w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-employee_id" class="block truncate">
                        <?php 
                        $empTitle = "Semua Guru";
                        foreach($employees as $emp) if((string)$emp['id'] === (string)$employee_id) $empTitle = $emp['full_name'];
                        echo htmlspecialchars($empTitle);
                        ?>
                    </span>
                    <i id="arrow-employee_id" class="fa-solid fa-chevron-down h-3.5 w-3.5 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-employee_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm right-0">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-employee_id" onkeyup="filterDropdownSearch('employee_id')" placeholder="Cari guru..." class="block w-full rounded-md border-slate-200 py-1 pl-2 text-xs focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-employee_id">
                        <li onclick="selectFilterOption('employee_id', '', 'Semua Guru')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Guru</li>
                        <?php foreach ($employees as $emp): ?>
                            <li onclick="selectFilterOption('employee_id', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons: Reset & Export & Print -->
            <div class="flex items-center gap-2 h-[42px] mt-auto">
                <a href="?tab=<?php echo $tab; ?>" class="inline-flex flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm hover:bg-slate-50 transition-all h-[42px]" title="Reset Filter">
                    <i class="fa-solid fa-rotate mr-1 text-slate-400"></i> Reset
                </a>
            </div>
        </div>

        <button type="submit" class="hidden">Filter</button>
    </form>

    <!-- Top Action Bar (Export & Print) -->
    <div class="mt-6 flex flex-wrap items-center justify-between gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200">
        <div class="text-xs text-slate-600 font-medium flex items-center gap-2">
            <i class="fa-solid fa-circle-info text-cyan-600"></i>
            <span>Menampilkan data untuk mode <strong><?php echo ucfirst($tab); ?></strong> (Total: <strong><?php echo $total_journals; ?></strong> Jurnal)</span>
        </div>

        <div class="flex items-center gap-3">
            <!-- Export Excel -->
            <?php 
            $export_url = "export_excel.php?type=" . $tab . 
                "&date=" . urlencode($date) . 
                "&month=" . urlencode($month) . 
                "&academic_year_id=" . urlencode($academic_year_id) . 
                "&unit_id=" . urlencode($unit_id) . 
                "&grade_id=" . urlencode($grade_id) . 
                "&employee_id=" . urlencode($employee_id) . 
                "&subject_id=" . urlencode($subject_id);
            
            $print_url = ($tab === 'daily') 
                ? "#" 
                : "print_recap.php?type=" . $tab . 
                  "&month=" . urlencode($month) . 
                  "&academic_year_id=" . urlencode($academic_year_id) . 
                  "&unit_id=" . urlencode($unit_id) . 
                  "&grade_id=" . urlencode($grade_id) . 
                  "&employee_id=" . urlencode($employee_id) . 
                  "&subject_id=" . urlencode($subject_id);
            ?>

            <a href="<?php echo $export_url; ?>" 
               class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-emerald-700 transition-all">
                <i class="fa-solid fa-file-excel text-sm"></i>
                Export Excel
            </a>

            <?php if ($tab !== 'daily'): ?>
            <a href="<?php echo $print_url; ?>" target="_blank"
               class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-slate-800 transition-all">
                <i class="fa-solid fa-print text-sm"></i>
                Cetak Laporan
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats Cards (For Monthly & Semester Tabs) -->
    <?php if ($tab !== 'daily'): ?>
    <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Total Jurnal -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Jurnal</p>
                <p class="text-2xl font-black text-slate-900 mt-1"><?php echo $total_journals; ?></p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
                <i class="fa-solid fa-book-open text-lg"></i>
            </div>
        </div>

        <!-- Total Kehadiran Siswa -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Hadir</p>
                <p class="text-2xl font-black text-green-600 mt-1"><?php echo (int)$tot_present; ?></p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-green-50 flex items-center justify-center text-green-600">
                <i class="fa-solid fa-user-check text-lg"></i>
            </div>
        </div>

        <!-- Total Sakit / Izin -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Sakit / Izin</p>
                <p class="text-2xl font-black text-amber-600 mt-1"><?php echo (int)($tot_sick + $tot_permit); ?></p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                <i class="fa-solid fa-notes-medical text-lg"></i>
            </div>
        </div>

        <!-- Total Alpa -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Alpa</p>
                <p class="text-2xl font-black text-rose-600 mt-1"><?php echo (int)$tot_absent; ?></p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-600">
                <i class="fa-solid fa-user-xmark text-lg"></i>
            </div>
        </div>

        <!-- Persentase Kehadiran -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">% Kehadiran</p>
                <p class="text-2xl font-black text-cyan-600 mt-1"><?php echo $att_percentage; ?>%</p>
            </div>
            <div class="h-10 w-10 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
                <i class="fa-solid fa-chart-pie text-lg"></i>
            </div>
        </div>
    </div>

    <!-- Summary per Mapel / Kelas Table -->
    <?php if (!empty($summaries)): ?>
    <div class="mt-8 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-600"></i>
                Rekapitulasi Pertemuan per Kelas & Mata Pelajaran
            </h3>
            <span class="text-xs font-semibold text-slate-500"><?php echo count($summaries); ?> Kombinasi Kelas & Mapel</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-100/70 font-bold text-slate-600 uppercase tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Kelas</th>
                        <th class="py-3 px-4">Mata Pelajaran</th>
                        <th class="py-3 px-4">Guru Pengampu</th>
                        <th class="py-3 px-4 text-center">Jumlah Pertemuan</th>
                        <th class="py-3 px-4 text-center">Hadir</th>
                        <th class="py-3 px-4 text-center">Sakit</th>
                        <th class="py-3 px-4 text-center">Izin</th>
                        <th class="py-3 px-4 text-center">Alpa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php foreach ($summaries as $sum): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-bold text-slate-900"><?php echo htmlspecialchars($sum['class_name']); ?></td>
                            <td class="py-3 px-4 font-semibold text-cyan-700"><?php echo htmlspecialchars($sum['subject_name']); ?></td>
                            <td class="py-3 px-4 text-slate-700"><?php echo htmlspecialchars($sum['teacher_name']); ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-bold text-slate-800">
                                    <?php echo $sum['total_meetings']; ?> Pertemuan
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-green-600"><?php echo (int)$sum['sum_present']; ?></td>
                            <td class="py-3 px-4 text-center font-bold text-amber-600"><?php echo (int)$sum['sum_sick']; ?></td>
                            <td class="py-3 px-4 text-center font-bold text-blue-600"><?php echo (int)$sum['sum_permit']; ?></td>
                            <td class="py-3 px-4 text-center font-bold text-rose-600"><?php echo (int)$sum['sum_absent']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Monthly Breakdown Grid (Semester Tab Only) -->
    <?php if ($tab === 'semester' && !empty($monthly_breakdown)): ?>
    <div class="mt-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2 mb-4">
            <i class="fa-solid fa-chart-column text-cyan-600"></i>
            Perkembangan Jurnal Terisi per Bulan dalam Semester
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-3">
            <?php foreach ($monthly_breakdown as $mb): ?>
                <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-center">
                    <p class="text-[11px] font-bold text-slate-500"><?php echo $mb['m_label']; ?></p>
                    <p class="text-xl font-extrabold text-cyan-700 mt-1"><?php echo $mb['count_journals']; ?></p>
                    <p class="text-[10px] text-slate-400">Jurnal</p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Table of Detailed Journals -->
    <div class="mt-8 flex flex-col">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-rectangle-list text-cyan-600"></i>
                Detail Daftar Jurnal Pembelajaran
            </h3>
            <span class="text-xs text-slate-500 font-medium">Total: <strong><?php echo count($journals); ?></strong> Record</span>
        </div>

        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-2xl bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">Waktu / Tanggal</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kelas / Mapel</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Guru</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Materi / Catatan</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Kehadiran</th>
                        <th class="px-3 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($journals)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-sm text-slate-500">
                                <div class="flex flex-col items-center">
                                    <i class="fa-solid fa-folder-open text-3xl text-slate-300 mb-2"></i>
                                    <p>Belum ada data jurnal kelas untuk periode/filter ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($journals as $j): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6 align-top">
                                <div class="font-medium text-slate-900"><?php echo date('d M Y', strtotime($j['date'])); ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?php echo date('H:i', strtotime($j['start_time'])); ?> - <?php echo date('H:i', strtotime($j['end_time'])); ?></div>
                            </td>
                            <td class="px-3 py-4 text-sm align-top">
                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($j['class_name']); ?></div>
                                <div class="text-cyan-600 mt-1"><?php echo htmlspecialchars($j['subject_name']); ?></div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 align-top">
                                <?php echo htmlspecialchars($j['teacher_name']); ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-top max-w-xs">
                                <?php if ($j['topic']): ?>
                                    <div class="font-medium text-slate-800 mb-1">Materi: <?php echo htmlspecialchars($j['topic']); ?></div>
                                <?php endif; ?>
                                <?php if ($j['notes']): ?>
                                    <div class="italic text-slate-500 text-xs">"<?php echo htmlspecialchars($j['notes']); ?>"</div>
                                <?php endif; ?>
                                <?php if (!$j['topic'] && !$j['notes']) echo '<span class="text-slate-400">-</span>'; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center align-top">
                                <div class="inline-flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-full border border-slate-200 text-xs">
                                    <span class="font-bold text-green-600" title="Hadir"><?php echo $j['count_present']; ?> H</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="font-bold text-amber-600" title="Sakit"><?php echo $j['count_sick']; ?> S</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="font-bold text-blue-600" title="Izin"><?php echo $j['count_permit']; ?> I</span>
                                    <span class="text-slate-300">|</span>
                                    <span class="font-bold text-rose-600" title="Alpa"><?php echo $j['count_absent']; ?> A</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-right align-top">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" 
                                        onclick="openViewModal(<?php echo htmlspecialchars(json_encode([
                                            'date' => date('d M Y', strtotime($j['date'])),
                                            'time' => date('H:i', strtotime($j['start_time'])) . ' - ' . date('H:i', strtotime($j['end_time'])),
                                            'class' => $j['class_name'],
                                            'subject' => $j['subject_name'],
                                            'teacher' => $j['teacher_name'],
                                            'topic' => $j['topic'] ?? '-',
                                            'notes' => preg_replace('/\r|\n/', ' ', $j['notes'] ?? '-'),
                                            'present' => $j['count_present'],
                                            'absent' => $j['count_absent'],
                                            'sick' => $j['count_sick'],
                                            'permit' => $j['count_permit'],
                                            'late' => $j['count_late']
                                        ]), ENT_QUOTES); ?>)"
                                        class="inline-flex items-center p-1.5 text-cyan-600 hover:text-cyan-900 bg-cyan-50 hover:bg-cyan-100 rounded-lg transition-colors" title="Lihat Jurnal">
                                        <i class="fa-solid fa-eye h-4 w-4"></i>
                                    </button>
                                    <a href="print.php?id=<?php echo $j['id']; ?>" target="_blank"
                                        class="inline-flex items-center p-1.5 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" title="Cetak Jurnal Ini">
                                        <i class="fa-solid fa-print h-4 w-4"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleFormDropdown(id) {
    const menu = document.getElementById('menu-' + id);
    const arrow = document.getElementById('arrow-' + id);
    const allMenus = document.querySelectorAll('[id^="menu-"]');
    const allArrows = document.querySelectorAll('[id^="arrow-"]');
    
    allMenus.forEach(m => { if(m !== menu) m.classList.add('hidden'); });
    allArrows.forEach(a => { if(a !== arrow) a.classList.remove('rotate-180'); });

    if (menu) menu.classList.toggle('hidden');
    if (arrow) arrow.classList.toggle('rotate-180');
    
    if (menu && !menu.classList.contains('hidden') && document.getElementById('search-' + id)) {
        document.getElementById('search-' + id).focus();
    }
}

function selectFilterOption(id, value, text) {
    document.getElementById('input-' + id).value = value;
    document.getElementById('text-' + id).innerText = text;
    
    const menu = document.getElementById('menu-' + id);
    if (menu) menu.classList.add('hidden');
    
    const arrow = document.getElementById('arrow-' + id);
    if (arrow) arrow.classList.remove('rotate-180');

    if (id === 'unit_id') {
        document.getElementById('input-grade_id').value = '';
        document.getElementById('text-grade_id').innerText = 'Semua Kelas';
    }

    document.getElementById('filterForm').submit();
}

function filterDropdownSearch(id) {
    const input = document.getElementById('search-' + id);
    const filter = input.value.toLowerCase();
    const list = document.getElementById('list-' + id);
    const li = list.getElementsByTagName('li');
    const unitId = document.getElementById('input-unit_id') ? document.getElementById('input-unit_id').value : '';

    for (let i = 0; i < li.length; i++) {
        const txtValue = li[i].textContent || li[i].innerText;
        const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;

        if (id === 'grade_id') {
            const itemUnit = li[i].getAttribute('data-unit');
            const matchesUnit = (!unitId || itemUnit === unitId || !itemUnit);
            li[i].style.display = (matchesSearch && matchesUnit) ? "" : "none";
        } else {
            li[i].style.display = matchesSearch ? "" : "none";
        }
    }
}

// Close on click outside
window.addEventListener('click', function (e) {
    document.querySelectorAll('[id^="container-"]').forEach(container => {
        if (!container.contains(e.target)) {
            const id = container.id.replace('container-', '');
            const menu = document.getElementById('menu-' + id);
            if(menu) menu.classList.add('hidden');
            const arrow = document.getElementById('arrow-' + id);
            if(arrow) arrow.classList.remove('rotate-180');
        }
    });
});
</script>

<!-- View Modal -->
<div id="viewModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeViewModal()"></div>

        <!-- Modal panel -->
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-file-arrow-down h-6 w-6 text-cyan-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                        <h3 class="text-lg font-semibold leading-6 text-slate-900" id="modal-title">Detail Jurnal Kelas</h3>
                        <div class="mt-4 space-y-4">
                            <!-- Info Grid -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Tanggal & Waktu</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-datetime"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Kelas / Mapel</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-class-subject"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Guru</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-teacher"></p>
                                </div>
                            </div>
                            
                            <hr class="border-slate-100">

                            <!-- Content -->
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Materi</p>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-700" id="v-topic"></div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Catatan Tambahan</p>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-700 italic" id="v-notes"></div>
                            </div>
                            
                            <hr class="border-slate-100">

                            <!-- Attendance Stats -->
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Rekap Kehadiran Siswa</p>
                                <div class="grid grid-cols-5 gap-2 text-center text-xs">
                                    <div class="bg-green-50 rounded-lg p-2 border border-green-100">
                                        <div class="font-bold text-green-700 text-lg" id="v-present">0</div>
                                        <div class="text-green-600 mt-0.5">Hadir</div>
                                    </div>
                                    <div class="bg-red-50 rounded-lg p-2 border border-red-100">
                                        <div class="font-bold text-red-700 text-lg" id="v-absent">0</div>
                                        <div class="text-red-600 mt-0.5">Alpa</div>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-2 border border-yellow-100">
                                        <div class="font-bold text-yellow-700 text-lg" id="v-sick">0</div>
                                        <div class="text-yellow-600 mt-0.5">Sakit</div>
                                    </div>
                                    <div class="bg-blue-50 rounded-lg p-2 border border-blue-100">
                                        <div class="font-bold text-blue-700 text-lg" id="v-permit">0</div>
                                        <div class="text-blue-600 mt-0.5">Izin</div>
                                    </div>
                                    <div class="bg-orange-50 rounded-lg p-2 border border-orange-100">
                                        <div class="font-bold text-orange-700 text-lg" id="v-late">0</div>
                                        <div class="text-orange-600 mt-0.5">Telat</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <button type="button" onclick="closeViewModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openViewModal(data) {
        document.getElementById('v-datetime').innerText = data.date + ' (' + data.time + ')';
        document.getElementById('v-class-subject').innerText = data.class + ' - ' + data.subject;
        document.getElementById('v-teacher').innerText = data.teacher;
        document.getElementById('v-topic').innerText = data.topic;
        document.getElementById('v-notes').innerText = data.notes;
        
        document.getElementById('v-present').innerText = data.present;
        document.getElementById('v-absent').innerText = data.absent;
        document.getElementById('v-sick').innerText = data.sick;
        document.getElementById('v-permit').innerText = data.permit;
        document.getElementById('v-late').innerText = data.late;
        
        const modal = document.getElementById('viewModal');
        modal.classList.remove('hidden');
    }

    function closeViewModal() {
        const modal = document.getElementById('viewModal');
        modal.classList.add('hidden');
    }
</script>

<?php include '../layouts/footer.php'; ?>
