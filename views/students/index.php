<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Siswa";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// --- Filter Logic ---
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
    $classes_stmt = $conn->prepare("SELECT id, name FROM grade_levels WHERE education_unit_id = :uid ORDER BY name ASC");
    $classes_stmt->execute([':uid' => $unit_id]);
} else {
    $classes_stmt = $conn->query("SELECT id, name FROM grade_levels ORDER BY name ASC");
}
$classes = $classes_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build Where Clause
$where_clauses = ["1=1"]; // Default true
$params = [];

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
    $where_clauses[] = "s.status = :status";
    $params[':status'] = $status;
}

$where_sql = implode(" AND ", $where_clauses);

// Total Students with filters
// Total Students with filters
$count_query = "
    SELECT COUNT(*) 
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE s.status = 'Aktiv' AND ($where_sql)
";
$count_stmt = $conn->prepare($count_query);
// Bind active_year_id for join condition
$params[':active_year_id'] = 1;
$count_stmt->execute($params);
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
        gl.name AS class_name,
        gl.id AS class_id,
        eu.name AS unit_name
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE s.status = 'Aktiv' AND ($where_sql)
    ORDER BY eu.name ASC, s.nama_siswa ASC
    LIMIT :limit OFFSET :offset
";

// Merge pagination params
$query_params = array_merge($params, [':limit' => $limit, ':offset' => $offset]);

$stmt = $conn->prepare($query);
// Bind params manually
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':active_year_id', 1, PDO::PARAM_INT); // Assuming Active Year ID = 1
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 pb-10">

    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
                    Beranda
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Manajemen Siswa</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="md:flex md:items-center md:justify-between mb-6">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Data Siswa</h2>
            <p class="mt-2 text-sm text-slate-500 max-w-2xl">Kelola data siswa, perbarui profil, dan atur kelas untuk
                tahun ajaran aktif.</p>
        </div>
        <div class="mt-4 flex gap-3 md:ml-4 md:mt-0">
            <button type="submit" form="filter-form" formaction="<?php url('logic/students/export_csv.php'); ?>"
                class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none transition-colors">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
                Export CSV
            </button>
            <a href="<?php url('views/students/import.php'); ?>"
                class="inline-flex items-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none transition-colors">
                <svg class="-ml-1 mr-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                Import CSV
            </a>
            <a href="<?php url('views/students/create.php'); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <svg class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
                Tambah Siswa
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <form id="filter-form"
        class="mt-8 mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center"
        method="GET" action="">

        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari nama atau NISN..." onchange="this.form.submit()">
        </div>

        <!-- Filter Dropdowns -->
        <div class="flex gap-2 w-full sm:w-auto flex-wrap items-center flex-1">

            <!-- Unit Filter (Jenjang) -->
            <div class="relative group" id="filter-unit-container">
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-32">
                    <span id="filter-unit-text" class="truncate">
                        <?php
                        $unit_name = "Jenjang: Semua";
                        foreach ($units as $u) {
                            if ($u['id'] == $unit_id)
                                $unit_name = "Jenjang: " . $u['name'];
                        }
                        echo $unit_name;
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-48 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Jenjang: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Jenjang</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', 'Jenjang: <?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Class Filter (Kelas) -->
            <div class="relative group" id="filter-class-container">
                <input type="hidden" name="class_id" id="filter-class-input" value="<?php echo $class_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-class')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-32">
                    <span id="filter-class-text" class="truncate">
                        <?php
                        $class_name = "Kelas: Semua";
                        foreach ($classes as $c) {
                            if ($c['id'] == $class_id)
                                $class_name = "Kelas: " . $c['name'];
                        }
                        echo $class_name;
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-class-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-class-menu"
                    class="hidden absolute top-full left-0 mt-1 w-48 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('class', '', 'Kelas: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Kelas</li>
                        <?php foreach ($classes as $c): ?>
                            <li onclick="selectFilterOption('class', '<?php echo $c['id']; ?>', 'Kelas: <?php echo $c['name']; ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="relative group" id="filter-status-container">
                <input type="hidden" name="status" id="filter-status-input" value="<?php echo $status; ?>">
                <button type="button" onclick="toggleDropdown('filter-status')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-32">
                    <span id="filter-status-text" class="truncate">
                        <?php echo $status ? ucfirst($status) : "Status: Semua"; ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-status-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-status-menu"
                    class="hidden absolute top-full left-0 mt-1 w-32 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('status', '', 'Status: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua</li>
                        <?php foreach (['Aktif', 'Izin', 'Nonaktif', 'Lulus'] as $s): ?>
                            <li onclick="selectFilterOption('status', '<?php echo $s; ?>', '<?php echo $s; ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo $s; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <a href="index.php"
                class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors"
                title="Reset Filters">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>

        <!-- Limit Selector (Rows Per Page) - Custom Style -->
        <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-600">Tampilkan:</span>
            <div class="relative" id="limit-dropdown-container">
                <button type="button" onclick="toggleDropdown('limit-dropdown')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors min-w-[80px]">
                    <span id="limit-text"><?php echo $limit; ?> baris</span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="limit-dropdown-arrow"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="limit-dropdown-menu"
                    class="hidden absolute right-0 top-full mt-1 w-24 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <ul class="py-1">
                        <?php foreach ([10, 50, 100] as $opt): ?>
                            <li onclick="selectLimit(<?php echo $opt; ?>)"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo $limit == $opt ? 'bg-slate-50 font-semibold' : ''; ?>">
                                <?php echo $opt; ?> baris
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <!-- Hidden Input to store value -->
                <input type="hidden" name="limit" id="limit-input" value="<?php echo $limit; ?>">
            </div>
        </div>
    </form>

    <!-- Students Table -->
    <div class="bg-white shadow-sm ring-1 ring-gray-200 rounded-xl overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col"
                        class="pl-6 py-3.5 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">
                        No.</th>
                    <th scope="col"
                        class="pl-4 py-3.5 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-4">
                        Nama Siswa</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Jenjang</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Tahun Ajaran</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Kelas</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Status</th>
                    <th scope="col"
                        class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $index => $student): ?>
                        <?php
                        // Logic Avatar
                        $avatarPath = "https://ui-avatars.com/api/?name=" . urlencode($student['nama_siswa']) . "&background=random";
                        if (!empty($student['foto']) && file_exists("../../uploads/students/" . $student['foto'])) {
                            $avatarPath = "../../uploads/students/" . $student['foto'];
                        }

                        // Logic Status Color
                        $status = $student['status'] ?? 'Aktif';
                        $statusColor = 'bg-green-50 text-green-700 ring-green-600/20'; // Default Aktif
                
                        if ($status == 'Izin') {
                            $statusColor = 'bg-yellow-50 text-yellow-700 ring-yellow-600/20';
                        } elseif ($status == 'Nonaktif') {
                            $statusColor = 'bg-red-50 text-red-700 ring-red-600/20';
                        } elseif ($status == 'Lulus') {
                            $statusColor = 'bg-gray-50 text-gray-700 ring-gray-600/20';
                        }

                        // Logic Email (Optional)
                        $email = '-'; // Default logic if no email column
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm sm:pl-0">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <img class="h-10 w-10 rounded-full object-cover border border-slate-200"
                                            src="<?php echo htmlspecialchars($avatarPath); ?>" alt="">
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-bold text-slate-900">
                                            <?php echo htmlspecialchars($student['nama_siswa']); ?>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            NIS: <?php echo htmlspecialchars($student['nomor_induk'] ?? '-'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                <span
                                    class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                    <?php echo htmlspecialchars($student['unit_name'] ?? '-'); ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-900">
                                <?php echo htmlspecialchars($student['tahun_ajaran'] ?? ''); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                <div class="font-medium text-slate-900">
                                    <?php echo htmlspecialchars($student['class_name'] ?? 'Non-Kelas'); ?>
                                </div>
                                <!-- <div class="text-xs text-slate-400"><?php // echo htmlspecialchars($student['kelas']); ?></div> -->
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?php echo $statusColor; ?>">
                                    <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-current"></span>
                                    <?php echo htmlspecialchars($status); ?>
                                </span>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex justify-end gap-3 text-gray-400">
                                    <a href="#" class="hover:text-cyan-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </a>
                                    <a href="#" class="hover:text-cyan-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <a href="<?php url('logic/students/delete.php?id=' . $student['id']); ?>"
                                        onclick="return confirm('Are you sure?')" class="hover:text-red-600 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-slate-500 text-sm">
                            <div class="flex flex-col items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-10 h-10 mb-3 text-slate-300">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                </svg>
                                <p>Tidak ada data siswa.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Menampilkan
                        <?php if ($total_students > 0): ?>
                            <span class="font-medium"><?php echo $offset + 1; ?></span>
                            sampai
                            <span class="font-medium"><?php echo min($offset + $limit, $total_students); ?></span>
                            dari
                            <span class="font-medium"><?php echo $total_students; ?></span>
                            data
                        <?php else: ?>
                            <span class="font-medium">0</span> data
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                        clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>

                        <?php
                        // Pagination Logic: Show partial pages if too many
                        $show_max_pages = 5;
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1)
                            echo '<span class="px-2 py-2 text-gray-400">...</span>';

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white focus-visible:outline-cyan-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'; ?> focus:z-20 focus:outline-offset-0">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor;

                        if ($end_page < $total_pages)
                            echo '<span class="px-2 py-2 text-gray-400">...</span>';
                        ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo urlencode($unit_id); ?>&class_id=<?php echo urlencode($class_id); ?>&status=<?php echo urlencode($status); ?>"
                                class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                        clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Generic Dropdown Logic (Copied from Employees) ---
    let activeDropdownId = null;

    function toggleDropdown(id) {
        const menu = document.getElementById(id + '-menu');
        const arrow = document.getElementById(id + '-arrow');

        // Close currently active if different
        if (activeDropdownId && activeDropdownId !== id) {
            const activeMenu = document.getElementById(activeDropdownId + '-menu');
            const activeArrow = document.getElementById(activeDropdownId + '-arrow');

            if (activeMenu) {
                activeMenu.classList.add('hidden');
            }
            if (activeArrow) {
                activeArrow.classList.remove('rotate-180');
            }
        }

        if (menu.classList.contains('hidden')) {
            // Open
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
            activeDropdownId = id;
        } else {
            // Close
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
            activeDropdownId = null;
        }
    }

    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        // Submit immediately
        document.getElementById('filter-form').submit();
    }

    // --- Limit Option Selection ---
    function selectLimit(value) {
        document.getElementById('limit-input').value = value;
        document.getElementById('filter-form').submit();
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        // Generic close logic for all active dropdowns
        const dropdowns = ['filter-unit', 'filter-class', 'filter-status', 'limit-dropdown'];

        dropdowns.forEach(id => {
            if (activeDropdownId === id) {
                const container = document.getElementById(id + '-container');
                if (container && !container.contains(e.target)) {
                    toggleDropdown(id);
                }
            }
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>