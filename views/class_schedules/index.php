<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Jadwal Pelajaran";

$db = new Database();
$conn = $db->getConnection();

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// --- Logika Filter ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$day = isset($_GET['day']) ? $_GET['day'] : '';
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : '';

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Active Academic Year ---
$active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year_id = $active_year_stmt->fetchColumn();

if (empty($ay_id)) {
    $selected_year_id = $active_year_id;
} else {
    $selected_year_id = $ay_id;
}

$where_clauses = [];
$params = [];

if ($selected_year_id) {
    $where_clauses[] = "cs.academic_year_id = :selected_year_id";
    $params[':selected_year_id'] = $selected_year_id;
}

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

if (!$is_admin && $user_level > 2) {
    if (!empty($mapped_education_unit_ids)) {
        // Scoped to their unit
        $where_clauses[] = "gl.education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
    } else {
        // Teachers only see schedules they are assigned to
        $where_clauses[] = "cs.employee_id = :current_user_id";
        $params[':current_user_id'] = $_SESSION['user_id'];
    }
}

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR s.name LIKE :search OR gl.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($grade_id) {
    $where_clauses[] = "cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}

if ($day) {
    $where_clauses[] = "cs.day = :day";
    $params[':day'] = $day;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$current_filters = $_GET;
unset($current_filters['success'], $current_filters['error'], $current_filters['id']);
$pagination_params = $current_filters;
unset($pagination_params['page']);
$pag_qs = http_build_query($pagination_params);

// --- Data Master untuk Filter ---
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT id, name, education_unit_id, is_active FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Map Hari ke Bahasa Indonesia
$indo_days = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Ahad'
];

// Total baris
$count_query = "
    SELECT COUNT(*) 
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    $where_sql
";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Ambil data dengan Join tambahan untuk Unit
$query = "
    SELECT cs.*, e.full_name as teacher_name, s.name as subject_name, gl.name as class_name, ay.name as ay_name, 
           lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time, 
           lp.period_number as start_period, COALESCE(lp_end.period_number, lp.period_number) as end_period
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN academic_years ay ON cs.academic_year_id = ay.id
    LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    $where_sql
    ORDER BY ay.name DESC, FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), lp.start_time ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Jadwal Pelajaran</h1>
            <p class="mt-2 text-sm text-slate-500">Atur siapa mengajar apa, di mana, dan kapan.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex items-center gap-3">
            <button type="button" id="bulkDeleteBtn" onclick="confirmBulkDelete()"
                class="hidden inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-all">
                <i class="fa-solid fa-trash -ml-1 mr-2 h-4 w-4 text-white"></i>
                Hapus Terpilih (<span id="selectedCount">0</span>)
            </button>
            <button type="button" id="bulkEditTeacherBtn" onclick="openBulkEditTeacherModal()"
                class="hidden inline-flex items-center justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition-all">
                <i class="fa-solid fa-pen-to-square -ml-1 mr-2 h-4 w-4 text-white"></i>
                Ubah Guru (<span id="selectedEditCount">0</span>)
            </button>
            <a href="import.php?<?php echo http_build_query($_GET); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-download -ml-1 mr-2 h-4 w-4 text-slate-500"></i>
                Import Jadwal
            </a>
            <a href="form.php?<?php echo http_build_query($_GET); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Jadwal
            </a>
        </div>
    </div>

    <!-- Import Errors Notice -->
    <?php if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-bold text-red-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation h-4 w-4"></i>
                    Detail Kesalahan Import
                </h3>
                <button onclick="this.parentElement.parentElement.remove()" class="text-red-400 hover:text-red-600">
                    <i class="fa-solid fa-xmark h-4 w-4"></i>
                </button>
            </div>
            <ul class="list-disc list-inside text-xs text-red-700 space-y-1 max-h-40 overflow-y-auto">
                <?php foreach ($_SESSION['import_errors'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php unset($_SESSION['import_errors']); ?>
    <?php endif; ?>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm" method="GET">
        <input type="hidden" name="limit" id="input-limit" value="<?php echo $limit; ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 items-end">
            <!-- Search -->
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="block w-full rounded-lg border-slate-200 pl-10 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600 py-2.5"
                    placeholder="Cari guru, mapel...">
            </div>

            <!-- Custom Academic Year Dropdown -->
            <div class="relative" id="container-ay_id">
                <input type="hidden" name="ay_id" id="input-ay_id" value="<?php echo $selected_year_id; ?>">
                <button type="button" onclick="toggleFormDropdown('ay_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-ay_id" class="block truncate">
                        <?php 
                        $ayTitle = "Pilih Tahun Ajaran";
                        foreach($academic_years as $ay) {
                            if($ay['id'] == $selected_year_id) {
                                $ayTitle = $ay['name'] . ' - ' . $ay['semester'] . ($ay['is_active'] == 1 ? ' (Aktif)' : '');
                                break;
                            }
                        }
                        echo htmlspecialchars($ayTitle);
                        ?>
                    </span>
                    <i id="arrow-ay_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-ay_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <ul id="list-ay_id">
                        <?php foreach ($academic_years as $ay): ?>
                            <li onclick="selectFilterOption('ay_id', '<?php echo $ay['id']; ?>', '<?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester'] . ($ay['is_active'] == 1 ? ' (Aktif)' : ''), ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester'] . ($ay['is_active'] == 1 ? ' (Aktif)' : '')); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Education Unit Dropdown -->
            <div class="relative" id="container-unit_id">
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-unit_id" class="block truncate">
                        <?php 
                        $unitTitle = "Semua Unit";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <i id="arrow-unit_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari unit..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Semua Unit')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Unit</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Grade Level Dropdown -->
            <div class="relative" id="container-grade_id">
                <input type="hidden" name="grade_id" id="input-grade_id" value="<?php echo $grade_id; ?>">
                <button type="button" onclick="toggleFormDropdown('grade_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-grade_id" class="block truncate">
                        <?php 
                        $gradeTitle = "Semua Kelas";
                        foreach($grades as $g) if($g['id'] == $grade_id) $gradeTitle = $g['name'] . ($g['is_active'] ? '' : ' (Non-aktif)');
                        echo htmlspecialchars($gradeTitle);
                        ?>
                    </span>
                    <i id="arrow-grade_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-grade_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                        <input type="text" id="search-grade_id" onkeyup="filterDropdownSearch('grade_id')" placeholder="Cari kelas..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-grade_id">
                        <li onclick="selectFilterOption('grade_id', '', 'Semua Kelas')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Kelas</li>
                        <?php foreach ($grades as $g): ?>
                            <li onclick="selectFilterOption('grade_id', '<?php echo $g['id']; ?>', '<?php echo htmlspecialchars($g['name'] . ($g['is_active'] ? '' : ' (Non-aktif)'), ENT_QUOTES); ?>')" 
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                class="grade-option relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($g['name'] . ($g['is_active'] ? '' : ' (Non-aktif)')); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Day Dropdown -->
            <div class="relative" id="container-day">
                <input type="hidden" name="day" id="input-day" value="<?php echo $day; ?>">
                <button type="button" onclick="toggleFormDropdown('day')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-day" class="block truncate">
                        <?php echo $day ? ($indo_days[$day] ?? $day) : "Semua Hari"; ?>
                    </span>
                    <i id="arrow-day" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                </button>
                <div id="menu-day" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <ul id="list-day">
                        <li onclick="selectFilterOption('day', '', 'Semua Hari')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Hari</li>
                        <?php foreach ($indo_days as $eng => $idn): ?>
                            <li onclick="selectFilterOption('day', '<?php echo $eng; ?>', '<?php echo $idn; ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo $idn; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <div>
                <a href="index.php" 
                    class="flex items-center justify-center w-full px-4 py-2.5 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 shadow-sm transition-all active:scale-95">
                    <i class="fa-solid fa-xmark w-4 h-4 mr-2"></i>
                    Hapus Filter
                </a>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        const allArrows = document.querySelectorAll('[id^="arrow-"]');
        
        allMenus.forEach(m => { if(m !== menu) m.classList.add('hidden'); });
        allArrows.forEach(a => { if(a !== arrow) a.classList.remove('rotate-180'); });

        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden') && document.getElementById('search-' + id)) {
            document.getElementById('search-' + id).focus();
        }
    }

    function selectFilterOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).innerText = text;
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

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
        const unitId = document.getElementById('input-unit_id').value;

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

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left w-12 sm:pl-6">
                            <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)" class="custom-checkbox">
                        </th>
                        <th
                            class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-12">
                            No.</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Tahun Akademik</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Hari & Waktu</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Mata Pelajaran</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Kelas</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Guru</th>
                        <th
                            class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="8" class="py-10 text-center text-sm text-slate-500">Belum ada data jadwal.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $index => $sch): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6">
                                <input type="checkbox" value="<?php echo $sch['id']; ?>" class="row-checkbox custom-checkbox" onchange="updateBulkDeleteBtn()">
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 font-medium">
                                <?php echo htmlspecialchars($sch['ay_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="font-bold text-slate-900">
                                    <?php echo isset($indo_days[$sch['day']]) ? $indo_days[$sch['day']] : htmlspecialchars($sch['day'] ?: '-'); ?>
                                </span>
                                <div class="text-xs text-slate-500 mt-1">
                                    <?php 
                                    if ($sch['start_period'] == $sch['end_period']) {
                                        echo "Jam " . $sch['start_period'] . " &bull; ";
                                    } else {
                                        echo "Jam " . $sch['start_period'] . "-" . $sch['end_period'] . " &bull; ";
                                    }
                                    ?>
                                    <?php echo date('H:i', strtotime($sch['start_time'])); ?> -
                                    <?php echo date('H:i', strtotime($sch['end_time'])); ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-cyan-600">
                                <?php echo htmlspecialchars($sch['subject_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($sch['class_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($sch['teacher_name']); ?>
                                </div>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-3 text-gray-400">
                                    <a href="form.php?id=<?php echo $sch['id']; ?>&<?php echo http_build_query($_GET); ?>" class="hover:text-cyan-600"
                                        title="Edit"><i class="fa-solid fa-pen-to-square w-5 h-5"></i></a>
                                    <button
                                        onclick="openDeleteModal('../../logic/class_schedules/delete.php?id=<?php echo $sch['id']; ?>&<?php echo http_build_query($_GET); ?>')"
                                        class="hover:text-red-600" title="Hapus"><i class="fa-solid fa-trash w-5 h-5"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                    <!-- Mobile Pagination Info -->
                    <div class="flex sm:hidden flex-col items-center gap-2">
                        <p class="text-xs text-slate-500">
                            Menampilkan <span class="font-bold text-slate-900"><?php echo $offset + 1; ?></span> - <span
                                class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span>
                            dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                        </p>
                        <div class="flex gap-2">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                    class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                    class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Desktop/Tablet Pagination Info -->
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <select onchange="window.location.href='?page=1&'+(this.value ? 'limit='+this.value : '')+'<?php echo $pag_qs ? '&' . str_replace('limit=' . $limit, '', $pag_qs) : ''; ?>'.replace('&&', '&')"
                                class="block rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-slate-900 ring-1 ring-inset ring-slate-100 focus:ring-2 focus:ring-cyan-600 sm:text-xs">
                                <?php foreach ([10, 20, 50, 100] as $val): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                        <?php echo $val; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="text-xs text-slate-500">
                                Menampilkan <span class="font-bold text-slate-900"><?php echo $offset + 1; ?></span> - <span
                                    class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span>
                                dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span> data
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden"
                                aria-label="Pagination">
                                <!-- Prev -->
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                        class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                        <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $range = 1;
                                for ($i = 1; $i <= $total_pages; $i++) {
                                    if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                        ?>
                                        <a href="?page=<?php echo $i; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                            <?php echo $i; ?>
                                        </a>
                                        <?php
                                    } elseif ($i == 2 || $i == $total_pages - 1) {
                                        ?>
                                        <span
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                                        <?php
                                    }
                                }
                                ?>

                                <!-- Next -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                        class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                        <i class="fa-solid fa-chevron-right h-5 w-5"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="bulkDeleteModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="bulkDeleteModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-triangle-exclamation h-6 w-6 text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Hapus Jadwal Terpilih</h3>
                        <div class="mt-2 text-sm text-slate-500">
                            Apakah Anda yakin ingin menghapus <span id="bulkDeleteCount" class="font-bold text-slate-700"></span> jadwal terpilih? Tindakan ini tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <button type="button" onclick="submitBulkDelete()" class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    Hapus Terpilih
                </button>
                <button type="button" onclick="closeBulkDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Bulk Edit Teacher Confirmation Modal -->
<div id="bulkEditTeacherModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="bulkEditTeacherModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="bulkEditTeacherModalPanel" class="relative transform overflow-visible rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <form id="bulkEditTeacherForm" method="POST" action="../../logic/class_schedules/bulk_edit_teacher.php?<?php echo http_build_query($_GET); ?>">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fa-solid fa-pen-to-square h-6 w-6 text-amber-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Ubah Guru Terpilih</h3>
                            <div class="mt-2 text-sm text-slate-500">
                                Anda akan mengubah guru untuk <span id="bulkEditTeacherCount" class="font-bold text-slate-700"></span> jadwal terpilih.
                            </div>
                            
                            <div class="mt-4 relative" id="container-modal_teacher_id">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Pilih Guru Baru</label>
                                <input type="hidden" name="teacher_id" id="input-modal_teacher_id" required>
                                
                                <button type="button" onclick="toggleModalTeacherDropdown()"
                                    class="flex w-full items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all text-left">
                                    <span id="text-modal_teacher_id" class="block truncate text-slate-400">
                                        -- Pilih Guru --
                                    </span>
                                    <i id="arrow-modal_teacher_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform"></i>
                                </button>
                                
                                <div id="menu-modal_teacher_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm border border-slate-100">
                                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                                        <input type="text" id="search-modal_teacher_id" onkeyup="filterModalTeacherDropdown()" placeholder="Cari nama guru..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                    </div>
                                    <ul id="list-modal_teacher_id">
                                        <?php foreach ($teachers as $teacher): ?>
                                            <li onclick="selectModalTeacherOption('<?php echo $teacher['id']; ?>', '<?php echo htmlspecialchars($teacher['full_name'], ENT_QUOTES); ?>')" 
                                                class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors text-left">
                                                <?php echo htmlspecialchars($teacher['full_name']); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="bulkEditHiddenInputs"></div>
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                    <button type="submit" class="inline-flex w-full justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 sm:w-auto transition-all transform active:scale-95">
                        Simpan Perubahan
                    </button>
                    <button type="button" onclick="closeBulkEditTeacherModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="hiddenBulkDeleteForm" method="POST" action="../../logic/class_schedules/bulk_delete.php?<?php echo http_build_query($_GET); ?>" class="hidden">
</form>

<script>
    function openBulkDeleteModal(count) {
        document.getElementById('bulkDeleteCount').textContent = count;
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeBulkDeleteModal() {
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateBulkDeleteBtn();
    }

    function updateBulkDeleteBtn() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const btn = document.getElementById('bulkDeleteBtn');
        const countSpan = document.getElementById('selectedCount');
        const editBtn = document.getElementById('bulkEditTeacherBtn');
        const editCountSpan = document.getElementById('selectedEditCount');
        const selectAllCb = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
            countSpan.textContent = checkboxes.length;
            if (editBtn) {
                editBtn.classList.remove('hidden');
                editCountSpan.textContent = checkboxes.length;
            }
        } else {
            btn.classList.add('hidden');
            if (editBtn) {
                editBtn.classList.add('hidden');
            }
        }

        if (allCheckboxes.length > 0) {
            selectAllCb.checked = (checkboxes.length === allCheckboxes.length);
        }
    }

    function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        openBulkDeleteModal(checkboxes.length);
    }

    function submitBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        const form = document.getElementById('hiddenBulkDeleteForm');
        form.innerHTML = ''; // clear previous elements
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        
        // Hide modal and submit
        closeBulkDeleteModal();
        form.submit();
    }

    function openBulkEditTeacherModal() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;

        document.getElementById('bulkEditTeacherCount').textContent = checkboxes.length;
        
        // Reset modal dropdown value
        document.getElementById('input-modal_teacher_id').value = '';
        const textSpan = document.getElementById('text-modal_teacher_id');
        textSpan.innerText = '-- Pilih Guru --';
        textSpan.classList.add('text-slate-400');
        textSpan.classList.remove('text-slate-700');
        
        // Hide dropdown menu if open
        const menu = document.getElementById('menu-modal_teacher_id');
        if (menu) menu.classList.add('hidden');
        const arrow = document.getElementById('arrow-modal_teacher_id');
        if (arrow) arrow.classList.remove('rotate-180');

        // populate hidden inputs in the form
        const container = document.getElementById('bulkEditHiddenInputs');
        container.innerHTML = '';
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            container.appendChild(input);
        });

        const modal = document.getElementById('bulkEditTeacherModal');
        const backdrop = document.getElementById('bulkEditTeacherModalBackdrop');
        const panel = document.getElementById('bulkEditTeacherModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeBulkEditTeacherModal() {
        const modal = document.getElementById('bulkEditTeacherModal');
        const backdrop = document.getElementById('bulkEditTeacherModalBackdrop');
        const panel = document.getElementById('bulkEditTeacherModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    function toggleModalTeacherDropdown() {
        const menu = document.getElementById('menu-modal_teacher_id');
        const arrow = document.getElementById('arrow-modal_teacher_id');
        
        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden')) {
            document.getElementById('search-modal_teacher_id').focus();
            document.getElementById('search-modal_teacher_id').value = '';
            filterModalTeacherDropdown(); // Reset search filter
        }
    }

    function selectModalTeacherOption(value, text) {
        document.getElementById('input-modal_teacher_id').value = value;
        const textSpan = document.getElementById('text-modal_teacher_id');
        textSpan.innerText = text;
        textSpan.classList.remove('text-slate-400');
        textSpan.classList.add('text-slate-700');
        
        document.getElementById('menu-modal_teacher_id').classList.add('hidden');
        document.getElementById('arrow-modal_teacher_id').classList.remove('rotate-180');
    }

    function filterModalTeacherDropdown() {
        const input = document.getElementById('search-modal_teacher_id');
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-modal_teacher_id');
        const li = list.getElementsByTagName('li');

        for (let i = 0; i < li.length; i++) {
            const txtValue = li[i].textContent || li[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                li[i].style.display = "";
            } else {
                li[i].style.display = "none";
            }
        }
    }
</script>

<?php include '../layouts/footer.php'; ?>