<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Siswa";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 50, 100]))
    $limit = 10;

// --- Fetch Active Academic Year ---
$active_year_query = "SELECT id, name FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year = $active_year_stmt->fetch(PDO::FETCH_ASSOC);
$active_year_id = $active_year ? $active_year['id'] : null;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// --- Fetch Filter Data ---
// 1. Fetch Units
$units_stmt = $conn->query("SELECT id, name FROM education_units ORDER BY id ASC");
$units = $units_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Classes (Optional: Filter by Unit if selected)
if ($unit_id) {
    $classes_stmt = $conn->prepare("SELECT id, name, is_active FROM grade_levels WHERE education_unit_id = :uid ORDER BY name ASC");
    $classes_stmt->execute([':uid' => $unit_id]);
} else {
    $classes_stmt = $conn->query("SELECT id, name, is_active FROM grade_levels ORDER BY name ASC");
}
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// Build Where Clause
$where_clauses = ["1=1"]; // Default true
$params = [];

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
        // Map education unit names for s.tingkat filter
        $edu_names_stmt = $conn->prepare("SELECT name FROM education_units WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")");
        $edu_names_stmt->execute();
        $edu_names = $edu_names_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $unit_where_parts = ["gl.education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")"];
        if (!empty($edu_names)) {
            $placeholders = [];
            foreach ($edu_names as $idx => $name) {
                $param_key = ":edu_name_scope_" . $idx;
                $placeholders[] = $param_key;
                $params[$param_key] = $name;
            }
            $unit_where_parts[] = "s.tingkat IN (" . implode(',', $placeholders) . ")";
        }
        $where_clauses[] = "(" . implode(" OR ", $unit_where_parts) . ")";
    } else {
        // Teacher/Staff: only see classes they are wali kelas for OR classes they teach
        $where_clauses[] = "gl.id IN (
            SELECT id FROM grade_levels WHERE teacher_id = :teacher_id_filter
            UNION
            SELECT grade_level_id FROM class_schedules WHERE employee_id = :employee_id_filter
        )";
        $params[':teacher_id_filter'] = $_SESSION['user_id'];
        $params[':employee_id_filter'] = $_SESSION['user_id'];
    }
}

if ($search) {
    $where_clauses[] = "(nama_siswa LIKE :search OR nomor_induk LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "eu.id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($class_id) {
    $where_clauses[] = "gl.id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($status) {
    if ($status === 'Semua') {
        // No status filter applied
    } else {
        $where_clauses[] = "s.status = :status";
        $params[':status'] = $status;
    }
} else {
    // Default to Aktif if no filter is provided
    $where_clauses[] = "s.status = 'Aktif'";
}

$where_sql = implode(" AND ", $where_clauses);

// Total Students with filters
$count_query = "
    SELECT COUNT(*) 
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE ($where_sql)
";

$count_stmt = $conn->prepare($count_query);
// Bind active_year_id for join condition
$params[':active_year_id'] = $active_year_id;
foreach ($params as $key => $val) {
    $count_stmt->bindValue($key, $val);
}
$count_stmt->execute();
$total_students = $count_stmt->fetchColumn();
$total_pages = ceil($total_students / $limit);

// Fetch Students with Limit/Offset
$query = "
    SELECT 
        s.id, 
        s.nama_siswa, 
        s.nomor_induk, 
        s.foto, 
        s.status, 
        s.tahun_ajaran,
        s.tempat_lahir,
        s.tanggal_lahir,
        s.alamat,
        gl.name AS class_name,
        gl.id AS class_id,
        eu.name AS unit_name
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE ($where_sql)
    ORDER BY 
        CASE 
            WHEN eu.name LIKE '%PG%' THEN 1
            WHEN eu.name LIKE '%TK%' THEN 2
            WHEN eu.name LIKE '%SD%' THEN 3
            WHEN eu.name LIKE '%MTs%' THEN 4
            WHEN eu.name LIKE '%MA%' THEN 5
            WHEN eu.name LIKE '%Mahad%' THEN 6
            ELSE 7 
        END ASC, 
        gl.name ASC, 
        s.nama_siswa ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    if ($key != ':limit' && $key != ':offset')
        $stmt->bindValue($key, $val);
}
$stmt->bindValue(':active_year_id', $active_year_id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Data Siswa</span>
                </li>
            </ol>
        </nav>

        <!-- Title & Action Buttons -->
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Data Siswa</h1>
                <p class="text-slate-500 text-sm mt-1">Kelola data siswa, perbarui profil, dan penempatan kelas tahun ajaran aktif.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <button type="submit" form="filter-form" formaction="<?php url('logic/students/export_excel.php'); ?>"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-file-excel mr-2 text-emerald-600"></i>
                    Export Excel
                </button>
                <a href="<?php url('views/students/import.php'); ?>"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-file-arrow-down mr-2 text-slate-500"></i>
                    Import CSV
                </a>
                <a href="<?php url('views/students/create.php'); ?>"
                    class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                    <i class="fa-solid fa-plus mr-1.5"></i>
                    Tambah Siswa
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filter-form"
        class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col lg:flex-row gap-3.5 justify-between items-stretch lg:items-center mb-6"
        method="GET" action="">

        <!-- Search Input -->
        <div class="relative w-full lg:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass text-xs text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                placeholder="Cari nama atau NISN..." onchange="this.form.submit()">
        </div>

        <!-- Filter Dropdowns Group -->
        <div class="flex flex-wrap items-center gap-2 flex-1 lg:justify-end">

            <!-- Unit Filter (Jenjang) -->
            <div class="relative" id="filter-unit-container">
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[130px] cursor-pointer">
                    <span id="filter-unit-text" class="truncate max-w-[150px]">
                        <?php
                        $unit_label = "Jenjang: Semua";
                        foreach ($units as $u) {
                            if ($u['id'] == $unit_id) $unit_label = "Jenjang: " . $u['name'];
                        }
                        echo htmlspecialchars($unit_label);
                        ?>
                    </span>
                    <i id="filter-unit-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-52 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Jenjang: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Jenjang
                        </li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', 'Jenjang: <?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Class Filter (Kelas) -->
            <div class="relative" id="filter-class-container">
                <input type="hidden" name="class_id" id="filter-class-input" value="<?php echo $class_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-class', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[130px] cursor-pointer">
                    <span id="filter-class-text" class="truncate max-w-[150px]">
                        <?php
                        $class_label = "Kelas: Semua";
                        foreach ($classes as $c) {
                            if ($c['id'] == $class_id) $class_label = "Kelas: " . $c['name'];
                        }
                        echo htmlspecialchars($class_label);
                        ?>
                    </span>
                    <i id="filter-class-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-class-menu"
                    class="hidden absolute top-full left-0 mt-1 w-52 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('class', '', 'Kelas: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Kelas
                        </li>
                        <?php foreach ($classes as $c): ?>
                            <li onclick="selectFilterOption('class', '<?php echo $c['id']; ?>', 'Kelas: <?php echo htmlspecialchars(addslashes($c['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="relative" id="filter-status-container">
                <input type="hidden" name="status" id="filter-status-input" value="<?php echo $status; ?>">
                <button type="button" onclick="toggleDropdown('filter-status', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[120px] cursor-pointer">
                    <span id="filter-status-text" class="truncate">
                        <?php
                        if ($status === 'Semua') echo "Status: Semua";
                        elseif ($status) echo "Status: " . str_replace('_', ' ', $status);
                        else echo "Status: Aktif";
                        ?>
                    </span>
                    <i id="filter-status-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-status-menu"
                    class="hidden absolute top-full left-0 mt-1 w-40 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('status', 'Semua', 'Status: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Status
                        </li>
                        <?php foreach (['Aktif', 'Non_aktif', 'Lulus', 'Pindah', 'Dikeluarkan'] as $s): ?>
                            <li onclick="selectFilterOption('status', '<?php echo $s; ?>', 'Status: <?php echo str_replace('_', ' ', $s); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo str_replace('_', ' ', $s); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <a href="index.php"
                class="inline-flex items-center justify-center p-2 rounded-lg border border-slate-300 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm h-9 w-9 cursor-pointer"
                title="Reset Filter">
                <i class="fa-solid fa-rotate-left text-xs"></i>
            </a>

            <!-- Limit Selector (Rows Per Page) -->
            <div class="flex items-center gap-2 ml-auto lg:ml-2">
                <span class="text-xs font-medium text-slate-500">Tampilkan:</span>
                <div class="relative" id="limit-dropdown-container">
                    <button type="button" onclick="toggleDropdown('limit-dropdown', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[90px] cursor-pointer">
                        <span id="limit-text"><?php echo $limit; ?> baris</span>
                        <i id="limit-dropdown-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="limit-dropdown-menu"
                        class="hidden absolute right-0 top-full mt-1 w-28 origin-top-right rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50">
                        <ul class="py-1">
                            <?php foreach ([10, 50, 100] as $opt): ?>
                                <li onclick="selectLimit(<?php echo $opt; ?>)"
                                    class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo $limit == $opt ? 'bg-slate-50 font-semibold text-cyan-700' : ''; ?>">
                                    <?php echo $opt; ?> baris
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <input type="hidden" name="limit" id="limit-input" value="<?php echo $limit; ?>">
                </div>
            </div>
        </div>
    </form>

    <!-- Students Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/80">
                    <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="pl-6 py-3.5 pr-3 text-left w-14">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Nama Siswa</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">TTL</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[180px] max-w-[240px]">Alamat</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[130px]">Jenjang</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[110px]">Thn Ajaran</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[110px]">Kelas</th>
                        <th scope="col" class="px-3 py-3.5 text-center w-28">Status</th>
                        <th scope="col" class="py-3.5 pl-3 pr-6 text-right w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $index => $student): ?>
                            <?php
                            // Avatar logic
                            $avatarPath = null;
                            if (!empty($student['foto']) && file_exists("../../uploads/students/" . $student['foto'])) {
                                $avatarPath = "../../uploads/students/" . $student['foto'];
                            }

                            // Status badge styling
                            $status = $student['status'] ?? 'Aktif';
                            $statusLabel = str_replace('_', ' ', $status);
                            
                            $badgeStyle = 'bg-emerald-50 text-emerald-700 border border-emerald-200/80';
                            $dotColor = 'bg-emerald-500';
                            if ($status === 'Non_aktif') {
                                $badgeStyle = 'bg-slate-100 text-slate-600 border border-slate-200';
                                $dotColor = 'bg-slate-400';
                            } elseif ($status === 'Lulus') {
                                $badgeStyle = 'bg-blue-50 text-blue-700 border border-blue-200/80';
                                $dotColor = 'bg-blue-500';
                            } elseif ($status === 'Pindah') {
                                $badgeStyle = 'bg-amber-50 text-amber-700 border border-amber-200/80';
                                $dotColor = 'bg-amber-500';
                            } elseif ($status === 'Dikeluarkan') {
                                $badgeStyle = 'bg-rose-50 text-rose-700 border border-rose-200/80';
                                $dotColor = 'bg-rose-500';
                            }

                            // TTL logic
                            $tempat = !empty($student['tempat_lahir']) ? trim($student['tempat_lahir']) : '';
                            $tanggal = '';
                            if (!empty($student['tanggal_lahir']) && $student['tanggal_lahir'] !== '0000-00-00') {
                                $ts = strtotime($student['tanggal_lahir']);
                                if ($ts) {
                                    $bulanIndo = [
                                        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
                                        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
                                    ];
                                    $tanggal = date('j', $ts) . ' ' . ($bulanIndo[(int)date('n', $ts)] ?? date('M', $ts)) . ' ' . date('Y', $ts);
                                }
                            }
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors group">
                                <td class="whitespace-nowrap py-3.5 pl-6 pr-3 text-xs text-slate-500 font-medium">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-full overflow-hidden bg-slate-100 border border-slate-200 flex-shrink-0 flex items-center justify-center shadow-sm">
                                            <?php if ($avatarPath): ?>
                                                <img class="h-full w-full object-cover" src="<?php echo htmlspecialchars($avatarPath); ?>" alt="">
                                            <?php else: ?>
                                                <i class="fa-solid fa-user text-xs text-slate-400"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-900 text-sm leading-tight">
                                                <?php echo htmlspecialchars(ucwords(strtolower($student['nama_siswa']))); ?>
                                            </div>
                                            <div class="text-xs text-slate-400 mt-0.5">
                                                NISN: <?php echo htmlspecialchars($student['nomor_induk'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                    <?php if ($tempat && $tanggal): ?>
                                        <div class="font-medium text-slate-800 leading-tight">
                                            <?php echo htmlspecialchars(ucwords(strtolower($tempat))); ?>
                                        </div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            <?php echo htmlspecialchars($tanggal); ?>
                                        </div>
                                    <?php elseif ($tempat): ?>
                                        <span class="text-slate-700"><?php echo htmlspecialchars(ucwords(strtolower($tempat))); ?></span>
                                    <?php elseif ($tanggal): ?>
                                        <span class="text-slate-700"><?php echo htmlspecialchars($tanggal); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3.5 text-xs text-slate-600 max-w-[240px]">
                                    <?php if (!empty($student['alamat'])): ?>
                                        <span class="block truncate" title="<?php echo htmlspecialchars($student['alamat']); ?>">
                                            <?php echo htmlspecialchars($student['alamat']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-600">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80">
                                        <?php echo htmlspecialchars($student['unit_name'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-600 font-medium">
                                    <?php
                                    if (!empty($student['class_id']) && !empty($active_year['name'])) {
                                        echo htmlspecialchars($active_year['name']);
                                    } else {
                                        echo htmlspecialchars($student['tahun_ajaran'] ?? '-');
                                    }
                                    ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-700 font-medium">
                                    <?php echo htmlspecialchars($student['class_name'] ?? 'Non-Kelas'); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $badgeStyle; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $dotColor; ?>"></span>
                                        <?php echo htmlspecialchars($statusLabel); ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right text-xs">
                                    <div class="flex items-center justify-end gap-1">
                                        <?php if ($status === 'Aktif'): ?>
                                            <button type="button"
                                                onclick="openStatusModal(<?php echo $student['id']; ?>, '<?php echo addslashes(htmlspecialchars(ucwords(strtolower($student['nama_siswa'])))); ?>')"
                                                class="p-1.5 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer"
                                                title="Ubah Status Siswa">
                                                <i class="fa-solid fa-user-clock text-sm"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php url('views/students/edit.php?id=' . $student['id']); ?>"
                                            class="p-1.5 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg transition-colors"
                                            title="Edit Data Siswa">
                                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                                        </a>
                                        <button type="button"
                                            onclick="openDeleteModal('<?php url('logic/students/delete.php?id=' . $student['id']); ?>')"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                            title="Hapus Siswa">
                                            <i class="fa-solid fa-trash-can text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center text-slate-500 text-sm">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <i class="fa-solid fa-user text-xl"></i>
                                    </div>
                                    <p class="font-semibold text-slate-700 text-sm">Tidak ada data siswa ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter yang dipilih.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Mobile Pagination -->
            <div class="flex sm:hidden flex-col items-center gap-2 w-full">
                <p class="text-xs text-slate-500">
                    Menampilkan <span class="font-semibold text-slate-800"><?php echo $total_students > 0 ? $offset + 1 : 0; ?></span> - <span
                        class="font-semibold text-slate-800"><?php echo min($offset + $limit, $total_students); ?></span>
                    dari <span class="font-semibold text-slate-800"><?php echo $total_students; ?></span> data
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desktop Pagination -->
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-slate-600">
                        Menampilkan
                        <?php if ($total_students > 0): ?>
                            <span class="font-semibold text-slate-900"><?php echo $offset + 1; ?></span> - <span
                                class="font-semibold text-slate-900"><?php echo min($offset + $limit, $total_students); ?></span>
                            dari <span class="font-semibold text-slate-900"><?php echo $total_students; ?></span> data
                        <?php else: ?>
                            <span class="font-semibold text-slate-900">0</span> data
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm border border-slate-300 overflow-hidden bg-white text-xs" aria-label="Pagination">
                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $range = 1;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                    class="relative inline-flex items-center px-3.5 py-2 font-semibold border-x border-slate-200 transition-colors <?php echo ($i == $page) ? 'bg-cyan-600 text-white border-cyan-600' : 'text-slate-700 hover:bg-slate-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                <span class="relative inline-flex items-center px-3 py-2 font-semibold text-slate-400 border-x border-slate-200">...</span>
                            <?php endif;
                        endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                                <i class="fa-solid fa-chevron-right text-[11px]"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Status Confirmation Modal (Standard Clean Enterprise Style) -->
<div id="statusModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="statusModalBackdrop" class="fixed inset-0 bg-slate-900/40 transition-opacity duration-200 opacity-0 backdrop-blur-sm" onclick="closeStatusModal()"></div>

    <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
        <!-- Modal Panel -->
        <div id="statusModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-xl transition-all duration-200 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-md border border-slate-200">
            <form action="<?php url('logic/students/update_status.php'); ?>" method="POST">
                <input type="hidden" name="student_id" id="modal_student_id">

                <!-- Header -->
                <div class="p-6 border-b border-slate-100 flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
                        <i class="fa-solid fa-triangle-exclamation text-base"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900" id="modal-title">Perbarui Status Siswa</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Perbarui status untuk <strong id="modal_student_name" class="text-slate-800"></strong>.
                        </p>
                    </div>
                    <button type="button" onclick="closeStatusModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 space-y-4">
                    <div>
                        <label for="modal_status_select" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                            Pilih Status Baru <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <select name="status" id="modal_status_select" required
                                class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 bg-white text-slate-800 text-sm focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm appearance-none">
                                <option value="Non_aktif">Non-aktif (Umum)</option>
                                <option value="Lulus">Lulus (Tamat Pendidikan)</option>
                                <option value="Pindah">Pindah (Sekolah Lain)</option>
                                <option value="Dikeluarkan">Dikeluarkan</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1.5">
                            Siswa dengan status selain "Aktif" tidak akan dimunculkan di daftar siswa aktif utama.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3 rounded-b-xl">
                    <button type="button" onclick="closeStatusModal()"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors shadow-sm cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-600 cursor-pointer">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
    // --- Dropdown Toggle with Outside Click Listener ---
    function toggleDropdown(name, event) {
        if (event) event.stopPropagation();
        const menu = document.getElementById(name + '-menu');
        const arrow = document.getElementById(name + '-arrow');
        const isClosed = menu.classList.contains('hidden');

        // Close all other dropdowns
        document.querySelectorAll('[id$="-menu"]').forEach(el => {
            if (el.id !== name + '-menu') el.classList.add('hidden');
        });
        document.querySelectorAll('[id$="-arrow"]').forEach(el => {
            if (el.id !== name + '-arrow') el.classList.remove('rotate-180');
        });

        if (isClosed) {
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
        } else {
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id$="-container"]')) {
            document.querySelectorAll('[id$="-menu"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id$="-arrow"]').forEach(el => el.classList.remove('rotate-180'));
        }
    });

    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        document.getElementById('filter-form').submit();
    }

    // --- Limit Option Selection ---
    function selectLimit(value) {
        document.getElementById('limit-input').value = value;
        document.getElementById('filter-form').submit();
    }

    // --- Status Modal Logic ---
    function openStatusModal(id, name) {
        document.getElementById('modal_student_id').value = id;
        document.getElementById('modal_student_name').innerText = name;

        const modal = document.getElementById('statusModal');
        const backdrop = document.getElementById('statusModalBackdrop');
        const panel = document.getElementById('statusModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeStatusModal() {
        const modal = document.getElementById('statusModal');
        const backdrop = document.getElementById('statusModalBackdrop');
        const panel = document.getElementById('statusModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 200);
    }
</script>

<?php include '../layouts/footer.php'; ?>