<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Jadwal Pelajaran";

$db = new Database();
$conn = $db->getConnection();

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 50, 100]) ? (int) $_GET['limit'] : 10;
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

if (!$is_admin) {
    $where_clauses[] = "cs.employee_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
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

// --- Data Master untuk Filter ---
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

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
                <svg class="-ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244 2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Hapus Terpilih (<span id="selectedCount">0</span>)
            </button>
            <a href="import.php?<?php echo http_build_query($_GET); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                Import Jadwal
            </a>
            <a href="form.php?<?php echo http_build_query($_GET); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jadwal
            </a>
        </div>
    </div>

    <!-- Import Errors Notice -->
    <?php if (isset($_SESSION['import_errors']) && !empty($_SESSION['import_errors'])): ?>
        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-bold text-red-800 flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    Detail Kesalahan Import
                </h3>
                <button onclick="this.parentElement.parentElement.remove()" class="text-red-400 hover:text-red-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
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
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
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
                    <svg id="arrow-ay_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                    <svg id="arrow-unit_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                        foreach($grades as $g) if($g['id'] == $grade_id) $gradeTitle = $g['name'];
                        echo htmlspecialchars($gradeTitle);
                        ?>
                    </span>
                    <svg id="arrow-grade_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-grade_id" class="hidden absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5">
                        <input type="text" id="search-grade_id" onkeyup="filterDropdownSearch('grade_id')" placeholder="Cari kelas..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
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

            <!-- Custom Day Dropdown -->
            <div class="relative" id="container-day">
                <input type="hidden" name="day" id="input-day" value="<?php echo $day; ?>">
                <button type="button" onclick="toggleFormDropdown('day')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                    <span id="text-day" class="block truncate">
                        <?php echo $day ? ($indo_days[$day] ?? $day) : "Semua Hari"; ?>
                    </span>
                    <svg id="arrow-day" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
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
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
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
                                        title="Edit"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg></a>
                                    <button
                                        onclick="openDeleteModal('../../logic/class_schedules/delete.php?id=<?php echo $sch['id']; ?>&<?php echo http_build_query($_GET); ?>')"
                                        class="hover:text-red-600" title="Hapus"><svg class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-8 flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-xl shadow-sm border">
            <div class="flex flex-1 justify-between sm:hidden">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                        class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                        class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
                <?php endif; ?>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <p class="text-sm text-slate-700">
                        Showing <span class="font-medium"><?php echo $offset + 1; ?></span> to
                        <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span> of
                        <span class="font-medium"><?php echo $total_rows; ?></span> results
                    </p>
                    
                    <div class="flex items-center gap-2">
                        <label class="text-xs text-slate-500">Tampilkan:</label>
                        <div class="relative w-24" id="container-limit">
                            <button type="button" onclick="toggleFormDropdown('limit')"
                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-3 py-1.5 text-xs text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500 transition-all">
                                <span id="text-limit" class="block truncate"><?php echo $limit; ?></span>
                                <svg id="arrow-limit" class="h-3 w-3 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div id="menu-limit" class="hidden absolute bottom-full left-0 z-30 mb-1 w-full rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-xs">
                                <ul id="list-limit">
                                    <?php foreach ([10, 50, 100] as $l): ?>
                                        <li onclick="selectFilterOption('limit', '<?php echo $l; ?>', '<?php echo $l; ?>')" 
                                            class="cursor-pointer select-none py-2 px-3 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo $limit == $l ? 'bg-cyan-50 text-cyan-700 font-bold' : ''; ?>">
                                            <?php echo $l; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01.02 1.06L8.832 10l3.978 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $i == $page ? 'z-10 bg-cyan-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600' : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.19 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="bulkDeleteModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="bulkDeleteModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
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
        const selectAllCb = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
            countSpan.textContent = checkboxes.length;
        } else {
            btn.classList.add('hidden');
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
</script>

<?php include '../layouts/footer.php'; ?>