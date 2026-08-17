<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Absensi Kelas";
$db = new Database();
$conn = $db->getConnection();

// --- Filters ---
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : '';

// --- Fetch Academic Years ---
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
if (!$active_year_id && !empty($academic_years)) {
    $active_year_id = $academic_years[0]['id'];
}
$selected_year_id = !empty($ay_id) ? $ay_id : $active_year_id;

// Resolve Day Name
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
           OR gl.id IN (SELECT grade_level_id FROM class_schedules WHERE employee_id = :current_user_id AND academic_year_id = :selected_year_id)
        ORDER BY gl.name ASC
    ";
    $grades_stmt = $conn->prepare($grades_query);
    $grades_stmt->execute([':current_user_id' => $_SESSION['user_id'], ':selected_year_id' => $selected_year_id]);
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

// --- Fetch Schedules & Attendance ---
$sql = "
    SELECT 
        cs.id as schedule_id,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        cj.id as journal_id,
        cj.topic,
        cj.notes,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as late
    FROM class_schedules cs
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cs.employee_id = e.id
    LEFT JOIN class_journals cj ON cs.id = cj.class_schedule_id AND cj.date = :date
    WHERE cs.day = :day 
      AND cs.academic_year_id = :selected_year_id
      AND (:date BETWEEN cs.valid_from AND COALESCE(cs.valid_until, '9999-12-31'))
";

$params = [':date' => $date, ':day' => $english_day, ':selected_year_id' => $selected_year_id];

$is_teacher_only = (!$is_admin && $user_level >= 5 && empty($mapped_education_unit_ids));

if ($is_teacher_only) {
    $sql .= " AND cs.employee_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
}

if ($unit_id) {
    $sql .= " AND gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($grade_id) {
    $sql .= " AND cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}

$sql .= " ORDER BY lp.start_time ASC, gl.name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Absensi Kelas</h1>
            <p class="mt-2 text-sm text-slate-500">Pantau rekapitulasi kehadiran siswa per kelas dan mata pelajaran.</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 flex items-center space-x-2">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-800">
                Tahun Ajaran: <?php
                foreach ($academic_years as $ay) {
                    if ($ay['id'] == $selected_year_id) {
                        echo htmlspecialchars($ay['name'] . ' (' . $ay['semester'] . ')');
                        if ($ay['is_active']) echo ' - Aktif';
                    }
                }
                ?>
            </span>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm" method="GET">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <!-- Date -->
            <div class="space-y-1.5">
                <label for="date"
                    class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal</label>
                <div class="relative">
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>"
                        onchange="this.form.submit()"
                        class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px]">
                </div>
            </div>

            <!-- Academic Year Filter -->
            <div class="relative space-y-1.5" id="filter-ay-container">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tahun Ajaran</label>
                <input type="hidden" name="ay_id" id="filter-ay-input" value="<?php echo htmlspecialchars($selected_year_id); ?>">
                <button type="button" onclick="toggleDropdown('filter-ay')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full h-[45px]">
                    <span id="filter-ay-text" class="truncate">
                        <?php
                        $ay_name = "Tahun Ajaran Aktif";
                        foreach ($academic_years as $ay) {
                            if ($ay['id'] == $selected_year_id) {
                                $ay_name = $ay['name'] . ' (' . $ay['semester'] . ')';
                            }
                        }
                        echo htmlspecialchars($ay_name);
                        ?>
                    </span>
                    <i id="filter-ay-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-ay-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <?php foreach ($academic_years as $ay): ?>
                            <li onclick="selectFilterOption('ay', '<?php echo $ay['id']; ?>', '<?php echo htmlspecialchars(addslashes($ay['name'] . ' (' . $ay['semester'] . ')'), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($ay['name'] . ' (' . $ay['semester'] . ')'); ?>
                                <?php if ($ay['is_active']): ?>
                                    <span class="ml-1 px-1.5 py-0.5 text-[10px] bg-emerald-100 text-emerald-700 rounded-full font-bold">Aktif</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

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
                            if ($u['id'] == $unit_id)
                                $unit_name = $u['name'];
                        }
                        echo htmlspecialchars($unit_name);
                        ?>
                    </span>
                    <i id="filter-unit-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
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
                            if ($g['id'] == $grade_id)
                                $grade_name = $g['name'];
                        }
                        echo htmlspecialchars($grade_name);
                        ?>
                    </span>
                    <i id="filter-grade-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
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
                <a href="index.php"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-red-600 transition-all w-full h-[45px] shadow-sm">
                    <i class="fa-solid fa-xmark w-4 h-4"></i>
                    Reset
                </a>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
        function selectFilterOption(type, value, text) {
            document.getElementById('filter-' + type + '-input').value = value;
            // If it's unit, we might want to reset grade, but usually we just submit and let PHP handle it
            document.getElementById('filterForm').submit();
        }

        function filterGrades() {
            // This is now partially handled by the server on submit, 
            // but if we wanted to do it client side before submit, we'd loop through .grade-option
            const unitId = document.getElementById('filter-unit-input').value;
            const items = document.querySelectorAll('.grade-option');
            items.forEach(item => {
                const optUnitId = item.getAttribute('data-unit');
                if (!unitId || !optUnitId || optUnitId === unitId) {
                    item.style.display = "";
                } else {
                    item.style.display = "none";
                }
            });
        }
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="py-3.5 pl-4 pr-3 text-left min-w-[120px] sm:pl-6">Waktu</th>
                        <th class="px-3 py-3.5 text-left min-w-[150px]">Kelas</th>
                        <th class="px-3 py-3.5 text-left min-w-[200px]">Mata Pelajaran</th>
                        <th class="px-3 py-3.5 text-left min-w-[200px]">Guru</th>
                        <th class="px-3 py-3.5 text-center min-w-[100px]">Status</th>
                        <th class="px-3 py-3.5 text-center min-w-[150px]">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">Tidak ada jadwal pelajaran pada
                                hari ini.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $s): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo date('H:i', strtotime($s['start_time'])); ?> -
                                <?php echo date('H:i', strtotime($s['end_time'])); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($s['class_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($s['subject_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($s['teacher_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                <?php if ($s['journal_id']): ?>
                                    <span
                                        class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Sudah</span>
                                <?php else: ?>
                                    <span
                                        class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-center">
                                <?php if ($s['journal_id']): ?>
                                    <div class="flex justify-center space-x-2 text-xs">
                                        <span class="text-green-600 font-bold" title="Hadir">H:
                                            <?php echo $s['present']; ?></span>
                                        <span class="text-red-600 font-bold" title="Alpha">A: <?php echo $s['absent']; ?></span>
                                        <span class="text-yellow-600 font-bold" title="Sakit">S:
                                            <?php echo $s['sick']; ?></span>
                                        <span class="text-blue-600 font-bold" title="Izin">I: <?php echo $s['permit']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>