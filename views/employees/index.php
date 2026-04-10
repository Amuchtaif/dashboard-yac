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
    SELECT e.*, d.name as division_name, u.name as unit_name 
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
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

// Export URL
$export_query = http_build_query(array_merge($_GET, ['action' => 'export']));
$export_url = "export.php?" . $export_query;

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
            <!-- Export Dropdown -->
            <div class="relative group" id="export-container">
                <button type="button" onclick="toggleDropdown('export')"
                    class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    </svg>
                    Export
                    <svg class="ml-2 h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div id="export-menu"
                    class="hidden absolute right-0 mt-2 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <div class="py-1">
                        <a href="<?php echo htmlspecialchars($export_url); ?>" target="_blank"
                            class="group flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-cyan-700">
                            <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-cyan-500"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                            Export CSV
                        </a>
                        <a href="export_pdf.php?<?php echo http_build_query($_GET); ?>" target="_blank"
                            class="group flex items-center px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-cyan-700">
                            <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-cyan-500"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 001.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                            </svg>
                            Export PDF
                        </a>
                    </div>
                </div>
            </div>
            <a href="<?php url('views/employees/form.php'); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Pegawai Baru
            </a>
        </div>
    </div>

    <form id="filter-form"
        class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col lg:flex-row gap-4 justify-between items-stretch lg:items-center"
        method="GET" action="">

        <!-- Search -->
        <div class="relative w-full lg:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
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
                        $currDiv = "Divisi: Semua";
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
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-division-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-division-menu"
                    class="hidden absolute top-full left-0 mt-1 w-56 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('division', '', 'Divisi: Semua')"
                            class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Divisi: Semua</li>
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
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-status-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span
                        class="ml-2 lg:hidden text-[11px] font-bold uppercase tracking-widest text-slate-500">Reset</span>
                </a>
            </div>
        </div>
    </form>

    <!-- Bulk Action Bar (Hidden by default) -->
    <div id="bulk-action-bar"
        class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50 w-[92%] sm:w-auto min-w-0 md:min-w-[600px] max-w-4xl bg-white/90 backdrop-blur-md border border-cyan-100 p-3 md:p-4 rounded-2xl shadow-2xl flex flex-col md:flex-row gap-4 md:gap-6 items-center justify-between transition-all duration-300 transform translate-y-0">

        <div class="flex items-center gap-4 md:border-r md:border-slate-200 md:pr-6 w-full md:w-auto">
            <div class="bg-cyan-100 p-2 rounded-full">
                <svg class="h-5 w-5 text-cyan-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <p class="font-bold text-slate-800 text-sm"><span id="selected-count"
                        class="text-cyan-600 text-lg">0</span> Pengguna Terpilih</p>
                <p class="text-xs text-slate-500">Lakukan aksi massal</p>
            </div>
        </div>

        <form action="../../logic/employees/bulk_update.php" method="POST"
            class="flex flex-col sm:flex-row gap-3 w-full items-center">
            <!-- Hidden inputs for selected IDs -->
            <div id="bulk-ids-container"></div>

            <div class="flex gap-2">
                <!-- Division Dropdown -->
                <div class="relative group" id="bulk-division-container">
                    <input type="hidden" name="division_id" id="bulk-division-input">
                    <button type="button" onclick="toggleBulkDropdown('division')"
                        class="flex w-40 items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        <span id="bulk-division-text" class="block truncate">Division</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="bulk-division-arrow"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="bulk-division-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('division', '', 'Change Division')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Ubah Divisi</li>
                            <?php foreach ($divisions as $div): ?>
                                <li onclick="selectBulkOption('division', '<?php echo $div['id']; ?>', '<?php echo htmlspecialchars($div['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
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
                        class="flex w-40 items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        <span id="bulk-unit-text" class="block truncate">Unit</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="bulk-unit-arrow"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="bulk-unit-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1" id="bulk-unit-list">
                            <li onclick="selectBulkOption('unit', '', 'Change Unit')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Ubah Unit</li>
                            <li onclick="selectBulkOption('unit', 'NULL', 'Tidak Ada Unit')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                Tidak Ada Unit</li>
                        </ul>
                    </div>
                </div>

                <!-- Position Dropdown -->
                <div class="relative group" id="bulk-position-container">
                    <input type="hidden" name="position_id" id="bulk-position-input">
                    <button type="button" onclick="toggleBulkDropdown('position')"
                        class="flex w-40 items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500">
                        <span id="bulk-position-text" class="block truncate">Position</span>
                        <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="bulk-position-arrow"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="bulk-position-menu"
                        class="hidden absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectBulkOption('position', '', 'Change Position')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Ubah Jabatan</li>
                            <?php foreach ($positions as $pos): ?>
                                <li onclick="selectBulkOption('position', '<?php echo $pos['id']; ?>', '<?php echo htmlspecialchars($pos['name'], ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <button type="submit"
                    class="rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all">
                    Terapkan
                </button>
            </div>
        </form>

        <!-- Close Button -->
        <button type="button" onclick="clearSelection()"
            class="absolute -top-2 -right-2 bg-white text-slate-400 hover:text-red-500 rounded-full p-1 shadow-md border border-slate-100">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
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
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[180px]">
                            Divisi & Unit
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
                                        <img class="h-10 w-10 rounded-full border-2 border-slate-100 object-cover"
                                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random&color=fff&bold=true"
                                            alt="">
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
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                                        </path>
                                    </svg>
                                    <?php echo htmlspecialchars($emp['phone_number'] ?? '-'); ?>
                                </div>
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
                                $statusColor = 'slate';
                                $statusText = $emp['status'] ?? 'active';
                                if ($statusText === 'active') {
                                    $statusColor = 'emerald';
                                    $statusText = 'Aktif';
                                } else {
                                    $statusColor = 'rose';
                                    $statusText = 'Nonaktif';
                                }
                                ?>
                                <span
                                    class="inline-flex items-center rounded-full bg-<?php echo $statusColor; ?>-50 px-2.5 py-0.5 text-[10px] font-bold text-<?php echo $statusColor; ?>-700 ring-1 ring-inset ring-<?php echo $statusColor; ?>-600/20 uppercase">
                                    <?php echo $statusText; ?>
                                </span>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Status Toggle -->
                                    <button type="button"
                                        onclick="openConfirmModal('<?php url('logic/employees/toggle_status.php?id=' . $emp['id']); ?>', '<?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'Aktifkan Pegawai' : 'Nonaktifkan Pegawai'; ?>', 'Apakah Anda yakin ingin <?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'mengaktifkan kembali' : 'menonaktifkan'; ?> akun pegawai ini?', '<?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'emerald' : 'rose'; ?>')"
                                        class="p-2 text-slate-400 rounded-lg hover:text-<?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'emerald' : 'rose'; ?>-600 transition-colors"
                                        title="<?php echo (isset($emp['status']) && $emp['status'] === 'inactive') ? 'Aktifkan' : 'Nonaktifkan'; ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M5.636 5.636a9 9 0 1012.728 0M12 3v9" />
                                        </svg>
                                    </button>
                                    <a href="<?php url('views/employees/form.php?id=' . $emp['id']); ?>"
                                        class="p-2 text-slate-400 rounded-lg" title="Ubah">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('logic/employees/delete.php?id=' . $emp['id']); ?>')"
                                        class="p-2 text-slate-400 rounded-lg" title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
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
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Desktop/Tablet Pagination Info -->
                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <select onchange="window.location.href='?page=1&limit='+this.value"
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
                                <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                    class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>

                            <?php
                            $range = 1;
                            for ($i = 1; $i <= $total_pages; $i++) {
                                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)) {
                                    ?>
                                    <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>"
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
                                <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                    class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
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
            bulkActionBar.classList.remove('hidden');
        } else {
            bulkActionBar.classList.add('hidden');
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
            <li onclick="selectBulkOption('unit', '', 'Change Unit')" 
                class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700 transition-colors">Change Unit</li>
            <li onclick="selectBulkOption('unit', 'NULL', 'No Unit')" 
                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">No Unit</li>
        `;

        if (divisionId) {
            fetch(`../../logic/units/get_by_division.php?division_id=${divisionId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(unit => {
                        const li = document.createElement('li');
                        li.className = "cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors";
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