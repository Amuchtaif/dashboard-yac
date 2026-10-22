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
$schedule_id = isset($_GET['schedule_id']) ? $_GET['schedule_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build Where Clause
$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search OR e.phone_number LIKE :search OR e.nik LIKE :search)";
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
if ($schedule_id !== '') {
    $where_clauses[] = "COALESCE(e.schedule_id, u.schedule_id, d.schedule_id, 1) = :schedule_id";
    $params[':schedule_id'] = $schedule_id;
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
$count_query = "
    SELECT COUNT(*) 
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id 
    WHERE $where_sql
";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Data with Limit/Offset
$query = "
    SELECT e.*, d.name as division_name, u.name as unit_name, p.name as position_name,
           ws.name as schedule_name
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN work_schedules ws ON COALESCE(e.schedule_id, u.schedule_id, d.schedule_id, 1) = ws.id
    WHERE $where_sql
    ORDER BY e.full_name ASC
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Divisions, Units, Positions, and Schedules for Filters
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
                    <span class="text-slate-800 font-medium">Data Pegawai</span>
                </li>
            </ol>
        </nav>

        <!-- Title & Action Buttons -->
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Data Pegawai</h1>
                <p class="text-slate-500 text-sm mt-1">Kelola data pegawai, penempatan unit/bidang, jadwal kerja, dan status akun.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <!-- Export Excel Button -->
                <a href="<?php echo htmlspecialchars($export_url); ?>" target="_blank"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-file-excel mr-2 text-emerald-600"></i>
                    Export Excel
                </a>
                <!-- Import CSV Button -->
                <a href="<?php url('views/employees/import.php'); ?>"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-file-arrow-down mr-2 text-slate-500"></i>
                    Impor Pegawai
                </a>
                <!-- Add New Button -->
                <a href="<?php url('views/employees/form.php?' . $filter_qs); ?>"
                    class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cyan-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cyan-600">
                    <i class="fa-solid fa-plus mr-1.5"></i>
                    Tambah Pegawai
                </a>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <form id="filter-form"
        class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col lg:flex-row gap-3 justify-between items-stretch lg:items-center mb-6"
        method="GET" action="">

        <!-- Search Input -->
        <div class="relative w-full lg:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass text-xs text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                placeholder="Cari nama, email, NIK, atau telepon..." onchange="this.form.submit()">
        </div>

        <!-- Filter Dropdowns Group -->
        <div class="flex flex-wrap items-center gap-2 flex-1 lg:justify-end">

            <!-- Division Filter (Bidang) -->
            <div class="relative" id="filter-division-container">
                <input type="hidden" name="division_id" id="filter-division-input" value="<?php echo $division_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-division', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[130px] cursor-pointer">
                    <span id="filter-division-text" class="truncate max-w-[140px]">
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
                    <i id="filter-division-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-division-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('division', '', 'Bidang: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Bidang
                        </li>
                        <?php foreach ($divisions as $div): ?>
                            <li onclick="selectFilterOption('division', '<?php echo $div['id']; ?>', 'Div: <?php echo htmlspecialchars(addslashes($div['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($div['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Unit Filter -->
            <div class="relative" id="filter-unit-container">
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[125px] cursor-pointer">
                    <span id="filter-unit-text" class="truncate max-w-[140px]">
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
                    <i id="filter-unit-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Unit: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Unit
                        </li>
                        <?php foreach ($units_all as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', 'Unit: <?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Position Filter (Jabatan) -->
            <div class="relative" id="filter-position-container">
                <input type="hidden" name="position_id" id="filter-position-input" value="<?php echo $position_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-position', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[130px] cursor-pointer">
                    <span id="filter-position-text" class="truncate max-w-[140px]">
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
                    <i id="filter-position-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-position-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('position', '', 'Jabatan: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Jabatan
                        </li>
                        <?php foreach ($positions as $pos): ?>
                            <li onclick="selectFilterOption('position', '<?php echo $pos['id']; ?>', 'Jab: <?php echo htmlspecialchars(addslashes($pos['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($pos['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Schedule Filter (Jadwal) -->
            <div class="relative" id="filter-schedule-container">
                <input type="hidden" name="schedule_id" id="filter-schedule-input" value="<?php echo htmlspecialchars($schedule_id); ?>">
                <button type="button" onclick="toggleDropdown('filter-schedule', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[125px] cursor-pointer">
                    <span id="filter-schedule-text" class="truncate max-w-[130px]">
                        <?php
                        $currSched = "Jadwal: Semua";
                        if ($schedule_id !== '') {
                            foreach ($schedules as $s) {
                                if ((string)$s['id'] === (string)$schedule_id) {
                                    $currSched = "Jadwal: " . $s['name'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($currSched);
                        ?>
                    </span>
                    <i id="filter-schedule-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-schedule-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('schedule', '', 'Jadwal: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Jadwal
                        </li>
                        <?php foreach ($schedules as $sched): ?>
                            <li onclick="selectFilterOption('schedule', '<?php echo $sched['id']; ?>', 'Jadwal: <?php echo htmlspecialchars(addslashes($sched['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($sched['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Status Filter -->
            <div class="relative" id="filter-status-container">
                <input type="hidden" name="status" id="filter-status-input" value="<?php echo $status; ?>">
                <button type="button" onclick="toggleDropdown('filter-status', event)"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm min-w-[115px] cursor-pointer">
                    <span id="filter-status-text" class="truncate">
                        <?php
                        $currStatus = "Status: Semua";
                        if ($status === 'active')
                            $currStatus = "Status: Aktif";
                        elseif ($status === 'inactive')
                            $currStatus = "Status: Nonaktif";
                        echo htmlspecialchars($currStatus);
                        ?>
                    </span>
                    <i id="filter-status-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                </button>
                <div id="filter-status-menu"
                    class="hidden absolute top-full left-0 mt-1 w-40 origin-top-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('status', '', 'Status: Semua')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Status
                        </li>
                        <li onclick="selectFilterOption('status', 'active', 'Status: Aktif')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            Aktif
                        </li>
                        <li onclick="selectFilterOption('status', 'inactive', 'Status: Nonaktif')"
                            class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                            Nonaktif
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Reset Button -->
            <a href="index.php"
                class="inline-flex items-center justify-center p-2 rounded-lg border border-slate-300 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm h-9 w-9 cursor-pointer"
                title="Reset Filter">
                <i class="fa-solid fa-rotate-left text-xs"></i>
            </a>

            <!-- Limit Selector -->
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
                            <?php foreach ([10, 20, 50, 100] as $opt): ?>
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

    <!-- Bulk Action Floating Bar (Clean & Professional) -->
    <div id="bulk-action-bar"
        class="opacity-0 pointer-events-none translate-y-10 fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[94%] max-w-5xl bg-white rounded-xl border border-slate-200 shadow-xl p-3 md:px-5 md:py-3 flex flex-col lg:flex-row gap-3 lg:gap-5 items-center justify-between transition-all duration-300 ease-out transform">

        <div class="flex items-center gap-3 lg:border-r lg:border-slate-200 lg:pr-5 w-full lg:w-auto">
            <div class="bg-cyan-50 text-cyan-600 w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-users text-sm"></i>
            </div>
            <div class="flex items-center gap-2">
                <span id="selected-count" class="inline-flex items-center justify-center bg-cyan-100 text-cyan-800 text-xs font-bold px-2 py-0.5 rounded-md min-w-[20px]">0</span>
                <span class="text-xs font-medium text-slate-700">Pegawai Terpilih</span>
            </div>
        </div>

        <form action="../../logic/employees/bulk_update.php" method="POST"
            class="flex flex-wrap lg:flex-nowrap gap-2.5 w-full lg:w-auto items-center">
            <!-- Hidden inputs for selected IDs -->
            <div id="bulk-ids-container"></div>
            <input type="hidden" name="return_filters" value="<?php echo htmlspecialchars($filter_qs); ?>">

            <div class="flex flex-wrap items-center gap-2">
                <!-- Division Dropdown -->
                <div class="relative" id="bulk-division-container">
                    <input type="hidden" name="division_id" id="bulk-division-input">
                    <button type="button" onclick="toggleBulkDropdown('division', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm w-36 cursor-pointer">
                        <span id="bulk-division-text" class="block truncate">Ubah Bidang</span>
                        <i id="bulk-division-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="bulk-division-menu"
                        class="hidden absolute bottom-full left-0 mb-1.5 w-52 origin-bottom-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-56 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('division', '', 'Ubah Bidang')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Abaikan Bidang
                            </li>
                            <?php foreach ($divisions as $div): ?>
                                <li onclick="selectBulkOption('division', '<?php echo $div['id']; ?>', '<?php echo htmlspecialchars(addslashes($div['name']), ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    <?php echo htmlspecialchars($div['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Unit Dropdown -->
                <div class="relative" id="bulk-unit-container">
                    <input type="hidden" name="unit_id" id="bulk-unit-input">
                    <button type="button" onclick="toggleBulkDropdown('unit', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm w-36 cursor-pointer">
                        <span id="bulk-unit-text" class="block truncate">Ubah Unit</span>
                        <i id="bulk-unit-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="bulk-unit-menu"
                        class="hidden absolute bottom-full left-0 mb-1.5 w-52 origin-bottom-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-56 overflow-y-auto">
                        <ul class="py-1" id="bulk-unit-list">
                            <li onclick="selectBulkOption('unit', '', 'Ubah Unit')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Abaikan Unit
                            </li>
                            <li onclick="selectBulkOption('unit', 'NULL', 'Tidak Ada Unit')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                Tanpa Unit
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Position Dropdown -->
                <div class="relative" id="bulk-position-container">
                    <input type="hidden" name="position_id" id="bulk-position-input">
                    <button type="button" onclick="toggleBulkDropdown('position', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm w-36 cursor-pointer">
                        <span id="bulk-position-text" class="block truncate">Ubah Jabatan</span>
                        <i id="bulk-position-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="bulk-position-menu"
                        class="hidden absolute bottom-full left-0 mb-1.5 w-52 origin-bottom-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-56 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('position', '', 'Ubah Jabatan')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Abaikan Jabatan
                            </li>
                            <?php foreach ($positions as $pos): ?>
                                <li onclick="selectBulkOption('position', '<?php echo $pos['id']; ?>', '<?php echo htmlspecialchars(addslashes($pos['name']), ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Schedule Dropdown -->
                <div class="relative" id="bulk-schedule-container">
                    <input type="hidden" name="schedule_id" id="bulk-schedule-input">
                    <button type="button" onclick="toggleBulkDropdown('schedule', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm w-36 cursor-pointer">
                        <span id="bulk-schedule-text" class="block truncate">Ubah Jam Kerja</span>
                        <i id="bulk-schedule-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="bulk-schedule-menu"
                        class="hidden absolute bottom-full left-0 mb-1.5 w-52 origin-bottom-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-56 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('schedule', '', 'Ubah Jam Kerja')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Abaikan Jam Kerja
                            </li>
                            <li onclick="selectBulkOption('schedule', 'NULL', 'Ikuti Aturan Default')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                Ikuti Default
                            </li>
                            <?php foreach ($schedules as $sched): ?>
                                <li onclick="selectBulkOption('schedule', '<?php echo $sched['id']; ?>', '<?php echo htmlspecialchars(addslashes($sched['name']), ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    <?php echo htmlspecialchars($sched['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Gender Dropdown -->
                <div class="relative" id="bulk-gender-container">
                    <input type="hidden" name="gender" id="bulk-gender-input">
                    <button type="button" onclick="toggleBulkDropdown('gender', event)"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm w-32 cursor-pointer">
                        <span id="bulk-gender-text" class="block truncate">Ubah Gender</span>
                        <i id="bulk-gender-arrow" class="fa-solid fa-chevron-down text-[10px] text-slate-400 transition-transform duration-150"></i>
                    </button>
                    <div id="bulk-gender-menu"
                        class="hidden absolute bottom-full left-0 mb-1.5 w-44 origin-bottom-left rounded-xl bg-white shadow-lg border border-slate-200 py-1 focus:outline-none z-50 max-h-56 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('gender', '', 'Ubah Gender')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Abaikan Gender
                            </li>
                            <li onclick="selectBulkOption('gender', 'Male', 'Laki-laki')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                Laki-laki
                            </li>
                            <li onclick="selectBulkOption('gender', 'Female', 'Perempuan')"
                                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                Perempuan
                            </li>
                        </ul>
                    </div>
                </div>

                <button type="submit"
                    class="rounded-lg bg-cyan-600 hover:bg-cyan-700 px-4 py-2 text-xs font-semibold text-white shadow-sm transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-1">
                    Terapkan
                </button>
            </div>
        </form>

        <!-- Close Button -->
        <button type="button" onclick="clearSelection()"
            class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg p-1.5 transition-colors cursor-pointer ml-auto lg:ml-0"
            title="Tutup Pilihan">
            <i class="fa-solid fa-xmark text-sm"></i>
        </button>
    </div>

    <!-- Employees Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/80">
                    <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="py-3.5 pl-6 pr-3 w-12 text-center">
                            <input type="checkbox" id="select-all" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 h-4 w-4 cursor-pointer">
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left w-14">
                            No.
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[220px]">
                            Pegawai
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[190px]">
                            Kontak
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left w-24">
                            Gender
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[140px]">
                            Jabatan
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[170px]">
                            Bidang & Unit
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center w-28">
                            Status
                        </th>
                        <th scope="col" class="py-3.5 pl-3 pr-6 text-right w-24">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $index => $emp): ?>
                            <?php
                            $avatarPath = null;
                            if (!empty($emp['profile_photo']) && file_exists(BASE_PATH . '/uploads/profile_photos/' . $emp['profile_photo'])) {
                                $avatarPath = BASE_URL . '/uploads/profile_photos/' . $emp['profile_photo'];
                            }
                            ?>
                            <tr class="hover:bg-slate-50/60 transition-colors group">
                                <td class="whitespace-nowrap py-3.5 pl-6 pr-3 text-center">
                                    <input type="checkbox" name="selected_ids[]" value="<?php echo $emp['id']; ?>"
                                        class="employee-checkbox rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 h-4 w-4 cursor-pointer">
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-500 font-medium">
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
                                                <?php echo htmlspecialchars($emp['full_name'] ?? ''); ?>
                                            </div>
                                            <div class="text-xs text-slate-400 mt-0.5">
                                                NIK: <?php echo htmlspecialchars($emp['nik'] ?? '-'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="text-xs text-slate-700 font-medium truncate max-w-[170px]"
                                        title="<?php echo htmlspecialchars($emp['email']); ?>">
                                        <?php echo htmlspecialchars($emp['email'] ?? '-'); ?>
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5">
                                        <i class="fa-solid fa-phone text-[10px]"></i>
                                        <span><?php echo htmlspecialchars($emp['phone_number'] ?? '-'); ?></span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs font-medium">
                                    <?php 
                                    $genderVal = $emp['gender'] ?? '';
                                    if ($genderVal === 'Male') {
                                        echo '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200/80">Laki-laki</span>';
                                    } elseif ($genderVal === 'Female') {
                                        echo '<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200/80">Perempuan</span>';
                                    } else {
                                        echo '<span class="text-slate-400 text-xs">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-3 py-3.5 text-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/80">
                                        <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3.5 text-xs">
                                    <div class="font-semibold text-slate-800 leading-tight truncate max-w-[160px]"
                                        title="<?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?>">
                                        <?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5 truncate max-w-[160px]"
                                        title="<?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?>">
                                        <?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?>
                                    </div>
                                    <?php if (!empty($emp['schedule_name'])): ?>
                                        <div class="text-[11px] text-cyan-700 mt-0.5 flex items-center gap-1 truncate max-w-[160px]"
                                            title="Jadwal: <?php echo htmlspecialchars($emp['schedule_name']); ?>">
                                            <i class="fa-regular fa-clock text-[10px] flex-shrink-0"></i>
                                            <span class="truncate"><?php echo htmlspecialchars($emp['schedule_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-center">
                                    <?php
                                    $statusText = $emp['status'] ?? 'active';
                                    $isActive = ($statusText === 'active');
                                    $displayText = $isActive ? 'Aktif' : 'Nonaktif';
                                    ?>
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button"
                                            onclick="openConfirmModal('<?php url('logic/employees/toggle_status.php?id=' . $emp['id'] . '&' . $filter_qs); ?>', '<?php echo $isActive ? 'Nonaktifkan Pegawai' : 'Aktifkan Pegawai'; ?>', 'Apakah Anda yakin ingin <?php echo $isActive ? 'menonaktifkan' : 'mengaktifkan kembali'; ?> akun pegawai ini?', '<?php echo $isActive ? 'rose' : 'emerald'; ?>')"
                                            class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-1 <?php echo $isActive ? 'bg-emerald-500' : 'bg-slate-300'; ?>"
                                            title="<?php echo $isActive ? 'Klik untuk Nonaktifkan' : 'Klik untuk Aktifkan'; ?>">
                                            <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?php echo $isActive ? 'translate-x-4' : 'translate-x-0'; ?>"></span>
                                        </button>
                                        <span class="text-xs font-medium <?php echo $isActive ? 'text-emerald-700' : 'text-slate-400'; ?>">
                                            <?php echo $displayText; ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-3.5 pl-3 pr-6 text-right text-xs">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="<?php url('views/employees/form.php?id=' . $emp['id'] . '&' . $filter_qs); ?>"
                                            class="p-1.5 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg transition-colors"
                                            title="Ubah Data Pegawai">
                                            <i class="fa-solid fa-pen-to-square text-sm"></i>
                                        </a>
                                        <button type="button"
                                            onclick="openDeleteModal('<?php url('logic/employees/delete.php?id=' . $emp['id'] . '&' . $filter_qs); ?>')"
                                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                            title="Hapus Pegawai">
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
                                        <i class="fa-solid fa-users text-xl"></i>
                                    </div>
                                    <p class="font-semibold text-slate-700 text-sm">Tidak ada data pegawai ditemukan</p>
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
                    Menampilkan <span class="font-semibold text-slate-800"><?php echo $total_rows > 0 ? $offset + 1 : 0; ?></span> - <span
                        class="font-semibold text-slate-800"><?php echo min($offset + $limit, $total_rows); ?></span>
                    dari <span class="font-semibold text-slate-800"><?php echo $total_rows; ?></span> data
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desktop Pagination -->
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-slate-600">
                        Menampilkan
                        <?php if ($total_rows > 0): ?>
                            <span class="font-semibold text-slate-900"><?php echo $offset + 1; ?></span> - <span
                                class="font-semibold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span>
                            dari <span class="font-semibold text-slate-900"><?php echo $total_rows; ?></span> data
                        <?php else: ?>
                            <span class="font-semibold text-slate-900">0</span> data
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm border border-slate-300 overflow-hidden bg-white text-xs" aria-label="Pagination">
                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $range = 1;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
                                    class="relative inline-flex items-center px-3.5 py-2 font-semibold border-x border-slate-200 transition-colors <?php echo ($i == $page) ? 'bg-cyan-600 text-white border-cyan-600' : 'text-slate-700 hover:bg-slate-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                <span class="relative inline-flex items-center px-3 py-2 font-semibold text-slate-400 border-x border-slate-200">...</span>
                            <?php endif;
                        endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $pag_qs ? '&' . $pag_qs : ''; ?>"
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

<script nonce="<?php echo csp_nonce(); ?>">
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
        if (!selectedCountSpan || !bulkActionBar || !bulkIdsContainer) return;

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

        if (selectAll) {
            selectAll.checked = allVisibleSelected && checkboxes.length > 0;
        }
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
    if (selectAll) {
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
    }

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

    // --- Dropdown Toggle with Outside Click Listener ---
    function toggleDropdown(name, event) {
        if (event) event.stopPropagation();
        const menu = document.getElementById(name + '-menu');
        const arrow = document.getElementById(name + '-arrow');
        const isClosed = menu ? menu.classList.contains('hidden') : true;

        // Close all other dropdowns
        document.querySelectorAll('[id$="-menu"]').forEach(el => {
            if (el.id !== name + '-menu') el.classList.add('hidden');
        });
        document.querySelectorAll('[id$="-arrow"]').forEach(el => {
            if (el.id !== name + '-arrow') el.classList.remove('rotate-180');
        });

        if (menu && isClosed) {
            menu.classList.remove('hidden');
            if (arrow) arrow.classList.add('rotate-180');
        } else if (menu) {
            menu.classList.add('hidden');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    }

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('[id$="-container"]')) {
            document.querySelectorAll('[id$="-menu"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[id$="-arrow"]').forEach(el => el.classList.remove('rotate-180'));
        }
    });

    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        const input = document.getElementById('filter-' + name + '-input');
        if (input) {
            input.value = value;
            document.getElementById('filter-form').submit();
        }
    }

    // --- Limit Option Selection ---
    function selectLimit(value) {
        const input = document.getElementById('limit-input');
        if (input) {
            input.value = value;
            document.getElementById('filter-form').submit();
        }
    }

    // --- Bulk Option Selection ---
    function toggleBulkDropdown(name, event) {
        toggleDropdown('bulk-' + name, event);
    }

    function selectBulkOption(name, value, text) {
        const input = document.getElementById(`bulk-${name}-input`);
        const textDisplay = document.getElementById(`bulk-${name}-text`);
        if (input) input.value = value;
        if (textDisplay) textDisplay.textContent = text;

        toggleDropdown('bulk-' + name); // Close

        if (name === 'division') {
            loadBulkUnits(value);
        }
    }

    // Load Units for Bulk Edit
    function loadBulkUnits(divisionId) {
        const unitList = document.getElementById('bulk-unit-list');
        const unitText = document.getElementById('bulk-unit-text');
        const unitInput = document.getElementById('bulk-unit-input');

        if (!unitList || !unitText || !unitInput) return;

        // Reset Unit Selection
        unitInput.value = '';
        unitText.textContent = 'Ubah Unit';

        unitList.innerHTML = `
            <li onclick="selectBulkOption('unit', '', 'Ubah Unit')" 
                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">Abaikan Unit</li>
            <li onclick="selectBulkOption('unit', 'NULL', 'Tidak Ada Unit')" 
                class="cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Tanpa Unit</li>
        `;

        if (divisionId) {
            fetch(`../../logic/units/get_by_division.php?division_id=${divisionId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(unit => {
                        const li = document.createElement('li');
                        li.className = "cursor-pointer px-3.5 py-1.5 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors";
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