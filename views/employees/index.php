<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Data Pegawai";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$position_id = isset($_GET['position_id']) ? $_GET['position_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build Where Clause
$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search OR e.phone_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where_clauses[] = "e.division_id = :division_id";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_clauses[] = "e.unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}
if ($position_id) {
    $where_clauses[] = "e.position_id = :position_id";
    $params[':position_id'] = $position_id;
}
if ($status) {
    if ($status === 'active') {
        $where_clauses[] = "(e.status = 'active' OR e.status IS NULL)";
    } elseif ($status === 'inactive') {
        $where_clauses[] = "e.status = 'inactive'";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// Total Count with filters
$count_query = "SELECT COUNT(*) FROM employees e WHERE $where_sql";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Data with Limit/Offset
$query = "
    SELECT e.*, d.name as division_name, u.name as unit_name, p.name as position_name
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE $where_sql
    ORDER BY e.full_name ASC
    LIMIT :limit OFFSET :offset
";

// Merge pagination params
$query_params = array_merge($params, [':limit' => $limit, ':offset' => $offset]);

$stmt = $conn->prepare($query);
// Bind params manually because limit/offset strictly need INT
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Divisions and Units for Filters
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units_all = $conn->query("SELECT id, name, division_id FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$positions = $conn->query("SELECT id, name FROM positions ORDER BY level ASC")->fetchAll(PDO::FETCH_ASSOC);
$schedules = $conn->query("SELECT id, name FROM work_schedules ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Reusable Filter Query for CRUD redirects
$current_filters = $_GET;
unset($current_filters['success'], $current_filters['error'], $current_filters['id']);
$filter_qs = http_build_query($current_filters);

$pagination_params = $current_filters;
unset($pagination_params['page']);
$pag_qs = http_build_query($pagination_params);

// Export URL
$export_url = "export.php?" . $filter_qs;

include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Page Header -->
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Semua Pegawai</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola anggota tim dan izin akun mereka di sini.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex gap-3">
            <!-- Export Excel Button -->
            <a href="<?php echo htmlspecialchars($export_url); ?>" target="_blank"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-file-excel -ml-1 mr-2 h-4 w-4 text-emerald-600"></i>
                Export Excel
            </a>
            <a href="<?php url('views/employees/import.php'); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-download -ml-1 mr-2 h-4 w-4 text-slate-500"></i>
                Impor Pegawai
            </a>
            <a href="<?php url('views/employees/form.php?' . $filter_qs); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Pegawai Baru
            </a>
        </div>
    </div>

    <form id="filter-form"
        class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col lg:flex-row gap-4 justify-between items-stretch lg:items-center"
        method="GET" action="">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">

        <!-- Search -->
        <div class="relative w-full lg:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari nama, email, atau ID..." onchange="this.form.submit()">
        </div>

        <!-- Filter Dropdowns Group -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:flex gap-2 items-center flex-1 lg:flex-none">

            <!-- Division Filter -->
            <div class="relative" id="filter-division-container">
                <input type="hidden" name="division_id" id="filter-division-input" value="<?php echo $division_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-division')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full lg:w-40 h-10">
                    <span id="filter-division-text" class="truncate">
                        <?php
                        $currDiv = "Bidang: Semua";
                        if ($division_id) {
                            foreach ($divisions as $d) {
                                if ($d['id'] == $division_id) {
                                    $currDiv = "Div: " . $d['name'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($currDiv);
                        ?>
                    </span>
                    <i id="filter-division-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-division-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('division', '', 'Bidang: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Bidang: Semua</li>
                        <?php foreach ($divisions as $div): ?>
                            <li onclick="selectFilterOption('division', '<?php echo $div['id']; ?>', 'Div: <?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($div['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Unit Filter -->
            <div class="relative" id="filter-unit-container">
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full lg:w-40 h-10">
                    <span id="filter-unit-text" class="truncate">
                        <?php
                        $currUnit = "Unit: Semua";
                        if ($unit_id) {
                            foreach ($units_all as $u) {
                                if ($u['id'] == $unit_id) {
                                    $currUnit = "Unit: " . $u['name'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($currUnit);
                        ?>
                    </span>
                    <i id="filter-unit-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Unit: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Unit: Semua</li>
                        <?php foreach ($units_all as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', 'Unit: <?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Position Filter -->
            <div class="relative" id="filter-position-container">
                <input type="hidden" name="position_id" id="filter-position-input" value="<?php echo $position_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-position')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full lg:w-40 h-10">
                    <span id="filter-position-text" class="truncate">
                        <?php
                        $currPos = "Jabatan: Semua";
                        if ($position_id) {
                            foreach ($positions as $p) {
                                if ($p['id'] == $position_id) {
                                    $currPos = "Jab: " . $p['name'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($currPos);
                        ?>
                    </span>
                    <i id="filter-position-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-position-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('position', '', 'Jabatan: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Jabatan: Semua</li>
                        <?php foreach ($positions as $pos): ?>
                            <li onclick="selectFilterOption('position', '<?php echo $pos['id']; ?>', 'Jab: <?php echo htmlspecialchars($pos['name'], ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($pos['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="relative" id="filter-status-container">
                <input type="hidden" name="status" id="filter-status-input" value="<?php echo $status; ?>">
                <button type="button" onclick="toggleDropdown('filter-status')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] font-bold text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full lg:w-32 h-10">
                    <span id="filter-status-text" class="truncate">
                        <?php
                        $currStatus = "Status";
                        if ($status === 'active')
                            $currStatus = "Aktif";
                        elseif ($status === 'inactive')
                            $currStatus = "Nonaktif";
                        echo htmlspecialchars($currStatus);
                        ?>
                    </span>
                    <i id="filter-status-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-status-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full lg:w-32 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('status', '', 'Status: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Status: Semua</li>
                        <li onclick="selectFilterOption('status', 'active', 'Aktif')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            Aktif</li>
                        <li onclick="selectFilterOption('status', 'inactive', 'Nonaktif')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            Nonaktif</li>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <div class="col-span-2 md:col-span-1 lg:w-auto">
                <a href="index.php"
                    class="flex items-center justify-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors h-10 w-full lg:w-10"
                    title="Reset Filters">
                    <i class="fa-solid fa-xmark h-4 w-4"></i>
                    <span
                        class="ml-2 lg:hidden text-[11px] font-bold uppercase tracking-widest text-slate-500">Reset</span>
                </a>
            </div>
        </div>
    </form>

    <!-- Bulk Action Bar (Hidden by default) -->
    <div id="bulk-action-bar"
        class="opacity-0 pointer-events-none translate-y-10 fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[92%] lg:w-auto min-w-0 lg:min-w-[800px] max-w-5xl bg-white/95 backdrop-blur-md border border-slate-200/80 shadow-[0_20px_50px_rgba(15,118,110,0.15)] p-3.5 md:p-4 rounded-2xl flex flex-col lg:flex-row gap-4 lg:gap-6 items-center justify-between transition-all duration-500 ease-out transform">

        <div class="flex items-center gap-4 lg:border-r lg:border-slate-200 lg:pr-6 w-full lg:w-auto">
            <div class="bg-cyan-50 text-cyan-600 p-2.5 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-users h-5 w-5"></i>
            </div>
            <div>
                <p class="font-extrabold text-slate-800 text-sm"><span id="selected-count"
                        class="text-cyan-600 font-black text-lg bg-cyan-100/50 px-2 py-0.5 rounded-lg mr-1.5">0</span> Pengguna Terpilih</p>
                <p class="text-xs text-slate-500 font-medium">Lakukan aksi massal</p>
            </div>
        </div>

        <form action="../../logic/employees/bulk_update.php" method="POST"
            class="flex flex-col lg:flex-row gap-3 w-full lg:w-auto items-center">
            <!-- Hidden inputs for selected IDs -->
            <div id="bulk-ids-container"></div>
            <input type="hidden" name="return_filters" value="<?php echo htmlspecialchars($filter_qs); ?>">

            <div class="flex flex-wrap gap-2.5 justify-center lg:justify-start">
                <!-- Division Dropdown -->
                <div class="relative group" id="bulk-division-container">
                    <input type="hidden" name="division_id" id="bulk-division-input">
                    <button type="button" onclick="toggleBulkDropdown('division')"
                        class="flex w-40 items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                        <span id="bulk-division-text" class="block truncate">Bidang</span>
                        <i id="bulk-division-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="bulk-division-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-xl bg-white shadow-2xl border border-slate-100 ring-0 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('division', '', 'Ubah Divisi')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">
                                Ubah Bidang</li>
                            <?php foreach ($divisions as $div): ?>
                                <li onclick="selectBulkOption('division', '<?php echo $div['id']; ?>', '<?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                    <?php echo htmlspecialchars($div['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Unit Dropdown -->
                <div class="relative group" id="bulk-unit-container">
                    <input type="hidden" name="unit_id" id="bulk-unit-input">
                    <button type="button" onclick="toggleBulkDropdown('unit')"
                        class="flex w-40 items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                        <span id="bulk-unit-text" class="block truncate">Unit</span>
                        <i id="bulk-unit-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="bulk-unit-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-xl bg-white shadow-2xl border border-slate-100 ring-0 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1" id="bulk-unit-list">
                            <li onclick="selectBulkOption('unit', '', 'Change Unit')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">
                                Ubah Unit</li>
                            <li onclick="selectBulkOption('unit', 'NULL', 'Tidak Ada Unit')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                Tidak Ada Unit</li>
                        </ul>
                    </div>
                </div>

                <!-- Position Dropdown -->
                <div class="relative group" id="bulk-position-container">
                    <input type="hidden" name="position_id" id="bulk-position-input">
                    <button type="button" onclick="toggleBulkDropdown('position')"
                        class="flex w-40 items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                        <span id="bulk-position-text" class="block truncate">Jabatan</span>
                        <i id="bulk-position-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="bulk-position-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-xl bg-white shadow-2xl border border-slate-100 ring-0 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('position', '', 'Ubah Jabatan')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">
                                Ubah Jabatan</li>
                            <?php foreach ($positions as $pos): ?>
                                <li onclick="selectBulkOption('position', '<?php echo $pos['id']; ?>', '<?php echo htmlspecialchars($pos['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Schedule Dropdown -->
                <div class="relative group" id="bulk-schedule-container">
                    <input type="hidden" name="schedule_id" id="bulk-schedule-input">
                    <button type="button" onclick="toggleBulkDropdown('schedule')"
                        class="flex w-40 items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                        <span id="bulk-schedule-text" class="block truncate">Jam Kerja</span>
                        <i id="bulk-schedule-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="bulk-schedule-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-xl bg-white shadow-2xl border border-slate-100 ring-0 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('schedule', '', 'Jam Kerja')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">
                                Ubah Jam Kerja</li>
                            <li onclick="selectBulkOption('schedule', 'NULL', 'Ikuti Aturan Default')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                Ikuti Aturan Default</li>
                            <?php foreach ($schedules as $sched): ?>
                                <li onclick="selectBulkOption('schedule', '<?php echo $sched['id']; ?>', '<?php echo htmlspecialchars($sched['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                    <?php echo htmlspecialchars($sched['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Gender Dropdown -->
                <div class="relative group" id="bulk-gender-container">
                    <input type="hidden" name="gender" id="bulk-gender-input">
                    <button type="button" onclick="toggleBulkDropdown('gender')"
                        class="flex w-40 items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-300 px-3.5 py-2.5 text-sm font-medium text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-200 transition-all">
                        <span id="bulk-gender-text" class="block truncate">Gender</span>
                        <i id="bulk-gender-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="bulk-gender-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-xl bg-white shadow-2xl border border-slate-100 ring-0 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('gender', '', 'Gender')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">
                                Ubah Gender</li>
                            <li onclick="selectBulkOption('gender', 'Male', 'Laki-laki')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                Laki-laki</li>
                            <li onclick="selectBulkOption('gender', 'Female', 'Perempuan')"
                                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">
                                Perempuan</li>
                        </ul>
                    </div>
                </div>


                <button type="submit"
                    class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/10 hover:bg-cyan-500 hover:shadow-cyan-500/20 active:scale-95 transition-all">
                    Terapkan
                </button>
            </div>
        </form>

        <!-- Close Button -->
        <button type="button" onclick="clearSelection()"
            class="absolute -top-2.5 -right-2.5 bg-white text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-full p-1.5 shadow-md border border-slate-100 transition-all transform hover:scale-105 active:scale-95">
            <i class="fa-solid fa-xmark h-4 w-4"></i>
        </button>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 w-12 text-center">
                            <input type="checkbox" id="select-all" class="custom-checkbox mx-auto">
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left w-16">
                            No.
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[220px]">
                            Pegawai
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">
                            Kontak
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left w-24">
                            Gender
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">
                            Jabatan
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[180px]">
                            Bidang & Unit
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left w-28">
                            Status
                        </th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32 border-none">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($employees as $index => $emp): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3 text-center">
                                <input type="checkbox" name="selected_ids[]" value="<?php echo $emp['id']; ?>"
                                    class="employee-checkbox custom-checkbox mx-auto">
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 font-medium">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <?php if (!empty($emp['profile_photo']) && file_exists(BASE_PATH . '/uploads/profile_photos/' . $emp['profile_photo'])): ?>
                                            <img class="h-10 w-10 rounded-full border-2 border-slate-100 object-cover"
                                                src="<?php echo BASE_URL . '/uploads/profile_photos/' . $emp['profile_photo']; ?>"
                                                alt="">
                                        <?php else: ?>
                                            <img class="h-10 w-10 rounded-full border-2 border-slate-100 object-cover"
                                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random&color=fff&bold=true"
                                                alt="">
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-bold text-slate-900 text-sm">
                                            <?php echo htmlspecialchars($emp['full_name'] ?? ''); ?>
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight">
                                            NIK: <?php echo htmlspecialchars($emp['nik'] ?? '-'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-4">
                                <div class="text-sm text-slate-600 font-medium truncate max-w-[160px]"
                                    title="<?php echo htmlspecialchars($emp['email']); ?>">
                                    <?php echo htmlspecialchars($emp['email'] ?? ''); ?>
                                </div>
                                <div class="text-xs text-slate-400 mt-1 flex items-center gap-1">
                                    <i class="fa-solid fa-phone w-3 h-3"></i>
                                    <?php echo htmlspecialchars($emp['phone_number'] ?? '-'); ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 font-medium">
                                <?php 
                                $genderVal = $emp['gender'] ?? '';
                                if ($genderVal === 'Male') {
                                    echo '<span class="inline-flex items-center rounded-md bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">Laki-laki</span>';
                                } elseif ($genderVal === 'Female') {
                                    echo '<span class="inline-flex items-center rounded-md bg-pink-50 px-2.5 py-0.5 text-xs font-medium text-pink-700 ring-1 ring-inset ring-pink-700/10">Perempuan</span>';
                                } else {
                                    echo '<span class="inline-flex items-center rounded-md bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-inset ring-slate-500/10">-</span>';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-4">
                                <span class="inline-flex items-center rounded-md bg-cyan-50 px-2 py-1 text-xs font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10">
                                    <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                </span>
                            </td>
                            <td class="px-3 py-4">
                                <div class="text-sm text-slate-800 font-bold truncate max-w-[140px]"
                                    title="<?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?>">
                                    <?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?>
                                </div>
                                <div class="text-[11px] text-slate-500 font-medium mt-1 truncate max-w-[140px]"
                                    title="<?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?>">
                                    <?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <?php
                                $statusText = $emp['status'] ?? 'active';
                                $isActive = ($statusText === 'active');
                                $displayText = $isActive ? 'Aktif' : 'Nonaktif';
                                ?>
                                <div class="flex items-center gap-3">
                                    <button type="button"
                                        onclick="openConfirmModal('<?php url('logic/employees/toggle_status.php?id=' . $emp['id'] . '&' . $filter_qs); ?>', '<?php echo $isActive ? 'Nonaktifkan Pegawai' : 'Aktifkan Pegawai'; ?>', 'Apakah Anda yakin ingin <?php echo $isActive ? 'menonaktifkan' : 'mengaktifkan kembali'; ?> akun pegawai ini?', '<?php echo $isActive ? 'rose' : 'emerald'; ?>')"
                                        class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 <?php echo $isActive ? 'bg-emerald-500' : 'bg-slate-200'; ?>"
                                        title="<?php echo $isActive ? 'Klik untuk Nonaktifkan' : 'Klik untuk Aktifkan'; ?>">
                                        <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?php echo $isActive ? 'translate-x-4' : 'translate-x-0'; ?>"></span>
                                    </button>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-tight"><?php echo $displayText; ?></span>
                                </div>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">

                                    <a href="<?php url('views/employees/form.php?id=' . $emp['id'] . '&' . $filter_qs); ?>"
                                        class="p-2 text-slate-400 rounded-lg" title="Ubah">
                                        <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('logic/employees/delete.php?id=' . $emp['id'] . '&' . $filter_qs); ?>')"
                                        class="p-2 text-slate-400 rounded-lg" title="Hapus">
                                        <i class="fa-solid fa-trash w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Dynamic Pagination -->
            <div
                class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
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
        </div>
    </div>
</div>

<script>
    const STORAGE_KEY = 'selected_employee_ids';
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    const bulkActionBar = document.getElementById('bulk-action-bar');
    const selectedCountSpan = document.getElementById('selected-count');
    const bulkIdsContainer = document.getElementById('bulk-ids-container');

    // Initialize Selection Set from SessionStorage
    let selectedIds = new Set(JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'));

    function saveSelection() {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(Array.from(selectedIds)));
    }

    function updateBulkActionUI() {
        // Update Count
        selectedCountSpan.textContent = selectedIds.size;

        // Show/Hide Bar
        if (selectedIds.size > 0) {
            bulkActionBar.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-10');
            bulkActionBar.classList.add('opacity-100', 'translate-y-0');
        } else {
            bulkActionBar.classList.remove('opacity-100', 'translate-y-0');
            bulkActionBar.classList.add('opacity-0', 'pointer-events-none', 'translate-y-10');
        }

        // Update Hidden Inputs
        bulkIdsContainer.innerHTML = '';
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'employee_ids[]';
            input.value = id;
            bulkIdsContainer.appendChild(input);
        });

        // Update "Select All" Checkbox State based on current page
        // If all VISIBLE checkboxes are in the set, check "Select All"
        // Also check individual boxes
        let allVisibleSelected = true;
        if (checkboxes.length === 0) allVisibleSelected = false;

        checkboxes.forEach(cb => {
            if (selectedIds.has(cb.value)) {
                cb.checked = true;
            } else {
                cb.checked = false;
                allVisibleSelected = false;
            }
        });

        selectAll.checked = allVisibleSelected && checkboxes.length > 0;
    }

    // Initialize UI on Load
    updateBulkActionUI();

    // Clear Selection (Close Button)
    function clearSelection() {
        selectedIds.clear();
        saveSelection();
        updateBulkActionUI();
    }

    // Select All Toggle
    selectAll.addEventListener('change', (e) => {
        const isChecked = e.target.checked;
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
            if (isChecked) {
                selectedIds.add(cb.value);
            } else {
                selectedIds.delete(cb.value);
            }
        });
        saveSelection();
        updateBulkActionUI();
    });

    // Individual Checkbox Toggle
    checkboxes.forEach(cb => {
        cb.addEventListener('change', (e) => {
            if (e.target.checked) {
                selectedIds.add(e.target.value);
            } else {
                selectedIds.delete(e.target.value);
            }
            saveSelection();
            updateBulkActionUI();
        });
    });

    // --- Generic Dropdown Logic ---
    // Uses global toggleDropdown from footer.php


    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        // Submit immediately
        document.getElementById('filter-form').submit();
    }

    // --- Bulk Option Selection (Existing Logic Adapted) ---
    function toggleBulkDropdown(name) {
        toggleDropdown('bulk-' + name);
    }

    function selectBulkOption(name, value, text) {
        document.getElementById(`bulk-${name}-input`).value = value;
        document.getElementById(`bulk-${name}-text`).textContent = text;

        // Reset active styles in menu
        document.querySelectorAll(`#bulk-${name}-menu li`).forEach(li => {
            li.classList.remove('bg-cyan-50', 'text-cyan-700');
            li.classList.add('text-slate-700');
        });

        toggleDropdown('bulk-' + name); // Close

        if (name === 'division') {
            loadBulkUnits(value);
        }
    }

    // Global click listener in footer.php handles closing


    // Load Units for Bulk Edit (Custom Dropdown Version)
    function loadBulkUnits(divisionId) {
        const unitList = document.getElementById('bulk-unit-list');
        const unitText = document.getElementById('bulk-unit-text');
        const unitInput = document.getElementById('bulk-unit-input');

        // Reset Unit Selection
        unitInput.value = '';
        unitText.textContent = 'Unit';

        // Clear list and add default
        unitList.innerHTML = `
            <li onclick="selectBulkOption('unit', '', 'Ubah Unit')" 
                class="cursor-pointer px-3 py-2 text-sm text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-lg m-1 transition-colors">Ubah Unit</li>
            <li onclick="selectBulkOption('unit', 'NULL', 'Tidak Ada Unit')" 
                class="cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors">Tidak Ada Unit</li>
        `;

        if (divisionId) {
            fetch(`../../logic/units/get_by_division.php?division_id=${divisionId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(unit => {
                        const li = document.createElement('li');
                        li.className = "cursor-pointer px-3 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 rounded-lg m-1 transition-colors";
                        li.textContent = unit.name;
                        li.onclick = () => selectBulkOption('unit', unit.id, unit.name);
                        unitList.appendChild(li);
                    });
                })
                .catch(error => console.error('Error fetching units:', error));
        }
    }
</script>

<?php include '../layouts/footer.php'; ?>