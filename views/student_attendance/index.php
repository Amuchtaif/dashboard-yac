<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Absensi Siswa";
$db = new Database();
$conn = $db->getConnection();

// --- Filters ---
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'daily';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';

// Resolve Day Name
$day_map = [
    'Sunday' => 'Ahad', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$english_day = date('l', strtotime($date));
$idn_day = $day_map[$english_day];

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Logged-in User Info for Role-based Scoping ---
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

// --- Data Master --
if (!$is_admin && $user_level >= 5 && empty($mapped_education_unit_ids)) {
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
} else if (!$is_admin && !empty($mapped_education_unit_ids)) {
    $units = $conn->query("SELECT id, name FROM education_units WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ") ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels WHERE education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ") ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch academic years for date range helpers
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_ay = null;
foreach ($academic_years as $ay) {
    if ($ay['is_active']) {
        $active_ay = $ay;
        break;
    }
}

// Default date range is active academic year's start/end dates
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ($active_ay ? $active_ay['start_date'] : date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : ($active_ay ? $active_ay['end_date'] : date('Y-m-t'));

// Fetch students for the filtered class
$students = [];
$subjects = [];
$recap_map = [];
$attendance_map = [];

if ($grade_id) {
    // Get students in this class for the active academic year
    $active_year = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk,
            s.foto
        FROM students s
        JOIN student_class_history sch ON s.id = sch.student_id
        WHERE sch.class_id = :grade_id AND sch.academic_year_id = :year_id AND s.status = 'Aktif'
        ORDER BY s.nama_siswa ASC
    ");
    $stmt->execute([':grade_id' => $grade_id, ':year_id' => $active_year]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $is_teacher_only = (!$is_admin && $user_level >= 5 && empty($mapped_education_unit_ids));
    
    if ($view_mode === 'daily') {
        // Get attendance records for these students on this date across all subjects
        $where_att = "WHERE cj.date = :date AND cs.grade_level_id = :grade_id";
        $params_att = [':date' => $date, ':grade_id' => $grade_id];

        if ($is_teacher_only) {
            $where_att .= " AND cs.employee_id = :current_user_id";
            $params_att[':current_user_id'] = $_SESSION['user_id'];
        }

        $att_stmt = $conn->prepare("
            SELECT 
                sa.student_id,
                sa.status,
                sub.name as subject_name,
                lp.start_time
            FROM student_attendances sa
            JOIN class_journals cj ON sa.class_journal_id = cj.id
            JOIN class_schedules cs ON cj.class_schedule_id = cs.id
            JOIN subjects sub ON cs.subject_id = sub.id
            JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
            $where_att
            ORDER BY lp.start_time ASC
        ");
        $att_stmt->execute($params_att);
        $attendance_raw = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attendance_raw as $row) {
            $attendance_map[$row['student_id']][] = [
                'subject' => $row['subject_name'],
                'status' => $row['status'],
                'time' => $row['start_time']
            ];
        }
    } else {
        // Recap per Mapel mode
        // Fetch subjects scheduled for this grade
        $where_sub = "WHERE cs.grade_level_id = :grade_id";
        $params_sub = [':grade_id' => $grade_id];
        if ($is_teacher_only) {
            $where_sub .= " AND cs.employee_id = :current_user_id";
            $params_sub[':current_user_id'] = $_SESSION['user_id'];
        }
        
        $sub_stmt = $conn->prepare("
            SELECT DISTINCT sub.id, sub.name
            FROM class_schedules cs
            JOIN subjects sub ON cs.subject_id = sub.id
            $where_sub
            ORDER BY sub.name ASC
        ");
        $sub_stmt->execute($params_sub);
        $subjects = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch aggregate attendance counts for each student and subject
        $where_recap = "WHERE cs.grade_level_id = :grade_id AND cj.date BETWEEN :start_date AND :end_date";
        $params_recap = [
            ':grade_id' => $grade_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ];
        if ($is_teacher_only) {
            $where_recap .= " AND cs.employee_id = :current_user_id";
            $params_recap[':current_user_id'] = $_SESSION['user_id'];
        }

        $recap_stmt = $conn->prepare("
            SELECT 
                sa.student_id,
                cs.subject_id,
                SUM(CASE WHEN LOWER(sa.status) = 'present' THEN 1 ELSE 0 END) as count_present,
                SUM(CASE WHEN LOWER(sa.status) = 'sick' THEN 1 ELSE 0 END) as count_sick,
                SUM(CASE WHEN LOWER(sa.status) = 'permit' THEN 1 ELSE 0 END) as count_permit,
                SUM(CASE WHEN LOWER(sa.status) = 'absent' THEN 1 ELSE 0 END) as count_absent,
                SUM(CASE WHEN LOWER(sa.status) = 'late' THEN 1 ELSE 0 END) as count_late,
                COUNT(sa.id) as total_attendance
            FROM student_attendances sa
            JOIN class_journals cj ON sa.class_journal_id = cj.id
            JOIN class_schedules cs ON cj.class_schedule_id = cs.id
            $where_recap
            GROUP BY sa.student_id, cs.subject_id
        ");
        $recap_stmt->execute($params_recap);
        $recap_raw = $recap_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($recap_raw as $row) {
            $recap_map[$row['student_id']][$row['subject_id']] = [
                'present' => $row['count_present'],
                'sick' => $row['count_sick'],
                'permit' => $row['count_permit'],
                'absent' => $row['count_absent'],
                'late' => $row['count_late'],
                'total' => $row['total_attendance']
            ];
        }
    }
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Absensi Siswa</h1>
            <p class="mt-2 text-sm text-slate-500">Lihat status kehadiran masing-masing siswa berdasarkan jadwal mata pelajaran.</p>
        </div>
    </div>

    <!-- Mode Switcher Tabs -->
    <div class="flex border-b border-slate-200 mt-6 no-print">
        <button type="button" onclick="setMode('daily')" class="py-2.5 px-6 font-semibold text-sm border-b-2 transition-all <?php echo $view_mode === 'daily' ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
            Absensi Harian
        </button>
        <button type="button" onclick="setMode('recap')" class="py-2.5 px-6 font-semibold text-sm border-b-2 transition-all <?php echo $view_mode === 'recap' ? 'border-cyan-600 text-cyan-600' : 'border-transparent text-slate-500 hover:text-slate-700'; ?>">
            Rekap per Mapel (Format Raport)
        </button>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm no-print" method="GET">
        <input type="hidden" name="view_mode" id="view_mode" value="<?php echo htmlspecialchars($view_mode); ?>">
        
        <div class="grid grid-cols-1 sm:grid-cols-2 <?php echo $view_mode === 'daily' ? 'md:grid-cols-4' : 'md:grid-cols-3 lg:grid-cols-6'; ?> gap-4 items-end">
            <?php if ($view_mode === 'daily'): ?>
                <!-- Date -->
                <div class="space-y-1.5">
                    <label for="date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal</label>
                    <div class="relative">
                        <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" 
                            onchange="this.form.submit()"
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px]">
                    </div>
                </div>
            <?php else: ?>
                <!-- Start Date -->
                <div class="space-y-1.5">
                    <label for="start_date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Mulai</label>
                    <div class="relative">
                        <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                            onchange="this.form.submit()"
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px]">
                    </div>
                </div>

                <!-- End Date -->
                <div class="space-y-1.5">
                    <label for="end_date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal Akhir</label>
                    <div class="relative">
                        <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                            onchange="this.form.submit()"
                            class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px]">
                    </div>
                </div>

                <!-- Semester Helper -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Pilih Semester</label>
                    <select onchange="updateDateRange(this)" class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px] select-custom">
                        <option value="">-- Kustom Tanggal --</option>
                        <?php foreach ($academic_years as $ay): ?>
                            <?php
                            $selected = ($start_date === $ay['start_date'] && $end_date === $ay['end_date']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $ay['start_date'] . '|' . $ay['end_date']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester']); ?> <?php echo $ay['is_active'] ? '(Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <!-- Unit Filter -->
            <div class="relative space-y-1.5" id="filter-unit-container">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Unit</label>
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full h-[45px]">
                    <span id="filter-unit-text" class="truncate">
                        <?php
                        $unit_name = "Semua Unit";
                        foreach ($units as $u) {
                            if ($u['id'] == $unit_id) $unit_name = $u['name'];
                        }
                        echo htmlspecialchars($unit_name);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Semua Unit')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Unit</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Grade Filter -->
            <div class="relative space-y-1.5" id="filter-grade-container">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kelas</label>
                <input type="hidden" name="grade_id" id="filter-grade-input" value="<?php echo $grade_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-grade')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full h-[45px]">
                    <span id="filter-grade-text" class="truncate">
                        <?php
                        $grade_name = "Semua Kelas";
                        foreach ($grades as $g) {
                            if ($g['id'] == $grade_id) $grade_name = $g['name'];
                        }
                        echo htmlspecialchars($grade_name);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-grade-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-grade-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('grade', '', 'Semua Kelas')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Kelas</li>
                        <?php foreach ($grades as $g): ?>
                            <li onclick="selectFilterOption('grade', '<?php echo $g['id']; ?>', '<?php echo htmlspecialchars(addslashes($g['name']), ENT_QUOTES); ?>')"
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                class="grade-option cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div>
                <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-red-600 transition-all w-full h-[45px] shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reset
                </a>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function selectFilterOption(type, value, text) {
        document.getElementById('filter-' + type + '-input').value = value;
        document.getElementById('filterForm').submit();
    }
    function setMode(mode) {
        document.getElementById('view_mode').value = mode;
        document.getElementById('filterForm').submit();
    }
    function updateDateRange(select) {
        if (select.value) {
            const [start, end] = select.value.split('|');
            document.getElementById('start_date').value = start;
            document.getElementById('end_date').value = end;
            document.getElementById('filterForm').submit();
        }
    }
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="py-3.5 pl-4 pr-3 text-left w-16 sm:pl-6 text-center">No.</th>
                        <th class="px-3 py-3.5 text-left min-w-[220px]">Siswa</th>
                        <?php if ($view_mode === 'daily'): ?>
                            <th class="px-3 py-3.5 text-left">Status Kehadiran per Mata Pelajaran</th>
                        <?php else: ?>
                            <th class="px-3 py-3.5 text-left">Rekap Absensi per Mata Pelajaran</th>
                            <th class="px-3 py-3.5 text-center w-28 no-print">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($grade_id)): ?>
                        <tr>
                            <td colspan="<?php echo $view_mode === 'daily' ? '3' : '4'; ?>" class="py-10 text-center text-sm text-slate-500 italic">Silakan pilih unit dan kelas terlebih dahulu.</td>
                        </tr>
                    <?php elseif (empty($students)): ?>
                        <tr>
                            <td colspan="<?php echo $view_mode === 'daily' ? '3' : '4'; ?>" class="py-10 text-center text-sm text-slate-500 italic">Tidak ada siswa aktif di kelas ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $index => $s): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-400 sm:pl-6 text-center">
                                    <?php echo $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <?php
                                            $avatarPath = "https://ui-avatars.com/api/?name=" . urlencode($s['nama_siswa']) . "&background=random&color=fff&bold=true";
                                            if (!empty($s['foto']) && file_exists("../../uploads/students/" . $s['foto'])) {
                                                $avatarPath = "../../uploads/students/" . $s['foto'];
                                            }
                                            ?>
                                            <img class="h-10 w-10 rounded-full object-cover border border-slate-100" src="<?php echo $avatarPath; ?>" alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($s['nama_siswa']); ?></div>
                                            <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight">NIS: <?php echo htmlspecialchars($s['nomor_induk'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                
                                <?php if ($view_mode === 'daily'): ?>
                                    <td class="px-3 py-4 text-sm">
                                        <div class="flex flex-wrap gap-2">
                                            <?php if (isset($attendance_map[$s['id']])): ?>
                                                <?php foreach ($attendance_map[$s['id']] as $att): ?>
                                                    <?php
                                                    $statusClass = 'bg-slate-100 text-slate-600';
                                                    $st = strtolower($att['status']);
                                                    if ($st == 'present') $statusClass = 'bg-green-100 text-green-700';
                                                    elseif ($st == 'absent') $statusClass = 'bg-red-100 text-red-700';
                                                    elseif ($st == 'sick') $statusClass = 'bg-blue-100 text-blue-700';
                                                    elseif ($st == 'permit') $statusClass = 'bg-yellow-100 text-yellow-700';
                                                    elseif ($st == 'late') $statusClass = 'bg-orange-100 text-orange-700';
                                                    
                                                    $statusTextMap = [
                                                        'present' => 'H', 'absent' => 'A', 'sick' => 'S', 'permit' => 'I', 'late' => 'T'
                                                    ];
                                                    $text = $statusTextMap[$st] ?? $att['status'];
                                                    ?>
                                                    <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-100 bg-white shadow-sm" title="<?php echo htmlspecialchars($att['subject'] . ' (' . date('H:i', strtotime($att['time'])) . ')'); ?>">
                                                        <span class="text-[10px] font-bold text-slate-400 uppercase"><?php echo htmlspecialchars($att['subject']); ?>:</span>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black <?php echo $statusClass; ?>">
                                                            <?php echo $text; ?>
                                                        </span>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs italic">Belum ada data presensi hari ini.</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td class="px-3 py-4 text-sm">
                                        <?php if (empty($subjects)): ?>
                                            <span class="text-slate-400 text-xs italic">Belum ada jadwal mata pelajaran untuk kelas ini.</span>
                                        <?php else: ?>
                                            <div class="overflow-x-auto rounded-xl border border-slate-100 shadow-sm max-w-full">
                                                <table class="min-w-full divide-y divide-slate-100 text-xs text-left">
                                                    <thead class="bg-slate-50/75 text-slate-500 font-bold uppercase tracking-wider">
                                                        <tr>
                                                            <th class="px-3 py-2">Mata Pelajaran</th>
                                                            <th class="px-2 py-2 text-center text-green-600 bg-green-50/30">H</th>
                                                            <th class="px-2 py-2 text-center text-blue-600 bg-blue-50/30">S</th>
                                                            <th class="px-2 py-2 text-center text-yellow-600 bg-yellow-50/30">I</th>
                                                            <th class="px-2 py-2 text-center text-red-600 bg-red-50/30">A</th>
                                                            <th class="px-2 py-2 text-center text-orange-600 bg-orange-50/30">T</th>
                                                            <th class="px-3 py-2 text-center">Kehadiran</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-slate-100 bg-white">
                                                        <?php foreach ($subjects as $sub): ?>
                                                            <?php
                                                            $sub_id = $sub['id'];
                                                            $stats = isset($recap_map[$s['id']][$sub_id]) ? $recap_map[$s['id']][$sub_id] : [
                                                                'present' => 0, 'sick' => 0, 'permit' => 0, 'absent' => 0, 'late' => 0, 'total' => 0
                                                            ];
                                                            $present_total = $stats['present'] + $stats['late'];
                                                            $pct = $stats['total'] > 0 ? round(($present_total / $stats['total']) * 100) : 0;
                                                            
                                                            $pctClass = 'text-green-600 font-bold';
                                                            if ($pct < 75) $pctClass = 'text-red-600 font-bold';
                                                            elseif ($pct < 90) $pctClass = 'text-yellow-600 font-bold';
                                                            ?>
                                                            <tr class="hover:bg-slate-50/50">
                                                                <td class="px-3 py-1.5 font-medium text-slate-700"><?php echo htmlspecialchars($sub['name']); ?></td>
                                                                <td class="px-2 py-1.5 text-center font-bold text-green-600 bg-green-50/10"><?php echo $stats['present']; ?></td>
                                                                <td class="px-2 py-1.5 text-center font-bold text-blue-600 bg-blue-50/10"><?php echo $stats['sick']; ?></td>
                                                                <td class="px-2 py-1.5 text-center font-bold text-yellow-600 bg-yellow-50/10"><?php echo $stats['permit']; ?></td>
                                                                <td class="px-2 py-1.5 text-center font-bold text-red-600 bg-red-50/10"><?php echo $stats['absent']; ?></td>
                                                                <td class="px-2 py-1.5 text-center font-bold text-orange-600 bg-orange-50/10"><?php echo $stats['late']; ?></td>
                                                                <td class="px-3 py-1.5 text-center <?php echo $pctClass; ?>"><?php echo $pct; ?>%</td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center no-print">
                                        <a href="print_raport.php?student_id=<?php echo $s['id']; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                                           target="_blank" 
                                           class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-cyan-600 hover:text-white bg-white hover:bg-cyan-600 border border-cyan-200 hover:border-cyan-600 rounded-lg shadow-sm transition-all active:scale-95">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                            </svg>
                                            Cetak Raport
                                        </a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
