<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Log Absensi";

$db = new Database();
$conn = $db->getConnection();

// --- Filter & Search ---
$default_start = date('Y-m-d', strtotime('-1 month'));
$default_end = date('Y-m-d');

$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;
$division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;
$unit_id = isset($_GET['unit_id']) && $_GET['unit_id'] !== '' ? (int)$_GET['unit_id'] : null;
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : $default_end;

// Ambil List Bidang (Divisions) dan Unit (Units) untuk Filter Berjenjang
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units_all = $conn->query("SELECT id, name, division_id FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// Build WHERE Clause
$where = " WHERE (e.status = 'active' OR e.status IS NULL) ";
$params = [];

if ($search) {
    $where .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where .= " AND e.unit_id = :unit_id ";
    $params[':unit_id'] = $unit_id;
}
if ($start_date) {
    $where .= " AND a.date >= :start_date ";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $where .= " AND a.date <= :end_date ";
    $params[':end_date'] = $end_date;
}

// Hitung Total
$count_query = "SELECT COUNT(*) FROM attendances a JOIN employees e ON a.user_id = e.id $where";
$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $total_stmt->bindValue($key, $val);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Helper Function Hitung Jarak (Haversine)
if (!function_exists('calcDistanceMeters')) {
    function calcDistanceMeters($lat1, $lon1, $lat2, $lon2) {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) return null;
        $earthRadius = 6371000;
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + 
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * 
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c);
    }
}

// Check if location_id_out column exists in attendances table, auto-add if missing
$has_loc_out = false;
try {
    $col_check = $conn->query("SHOW COLUMNS FROM attendances LIKE 'location_id_out'")->fetchAll();
    if (empty($col_check)) {
        $conn->exec("ALTER TABLE attendances ADD COLUMN location_id_out INT(11) NULL AFTER location_id");
        $has_loc_out = true;
    } else {
        $has_loc_out = true;
    }
} catch (Exception $e) {
    $has_loc_out = false;
}

// Ambil Data
if ($has_loc_out) {
    $query = "
        SELECT 
            a.*, 
            e.full_name, 
            e.email, 
            d.name as division_name,
            u.name as unit_name,
            l.name as location_name, 
            l.latitude as loc_lat_in,
            l.longitude as loc_long_in,
            l.radius_meter as location_radius_in,
            l_out.name as location_out_name,
            l_out.latitude as loc_lat_out,
            l_out.longitude as loc_long_out,
            l_out.radius_meter as location_radius_out
        FROM attendances a
        JOIN employees e ON a.user_id = e.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN locations l ON a.location_id = l.id
        LEFT JOIN locations l_out ON a.location_id_out = l_out.id
        $where
        ORDER BY a.date DESC, a.time_in DESC
        LIMIT :limit OFFSET :offset
    ";
} else {
    $query = "
        SELECT 
            a.*, 
            e.full_name, 
            e.email, 
            d.name as division_name,
            u.name as unit_name,
            l.name as location_name, 
            l.latitude as loc_lat_in,
            l.longitude as loc_long_in,
            l.radius_meter as location_radius_in,
            l.name as location_out_name,
            l.latitude as loc_lat_out,
            l.longitude as loc_long_out,
            l.radius_meter as location_radius_out
        FROM attendances a
        JOIN employees e ON a.user_id = e.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN locations l ON a.location_id = l.id
        $where
        ORDER BY a.date DESC, a.time_in DESC
        LIMIT :limit OFFSET :offset
    ";
}
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <span class="text-slate-800 font-medium">Log Absensi</span>
                </li>
            </ol>
        </nav>

        <!-- Title & Action Buttons -->
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Log Absensi</h1>
                <p class="text-slate-500 text-sm mt-1">Log lengkap absen masuk dan pulang pegawai.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <a href="export_excel.php?<?php echo http_build_query(['search' => $search ?? '', 'division_id' => $division_id ?? '', 'unit_id' => $unit_id ?? '', 'start_date' => $start_date ?? '', 'end_date' => $end_date ?? '']); ?>" 
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors cursor-pointer">
                    <i class="fa-solid fa-file-excel mr-2 text-emerald-600"></i>
                    Export Excel
                </a>
            </div>
        </div>
    </div>

    <!-- Filter Toolbar -->
    <form id="filter-form" method="GET" class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row flex-wrap gap-3 items-stretch md:items-center justify-between mb-6">
        <input type="hidden" name="limit" value="<?php echo $limit; ?>">

        <!-- Left / Search & Dropdown Filters -->
        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2.5 flex-1">
            <!-- Search Input -->
            <div class="relative w-full sm:w-56">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-magnifying-glass text-xs text-slate-400"></i>
                </div>
                <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search ?? ''); ?>"
                    class="block w-full rounded-lg border border-slate-300 bg-white pl-9 pr-3 py-2 text-xs text-slate-800 placeholder:text-slate-400 focus:border-cyan-600 focus:ring-1 focus:ring-cyan-600 focus:outline-none transition-colors shadow-sm"
                    placeholder="Cari nama pegawai...">
            </div>

            <!-- Division Select -->
            <select name="division_id" id="division_id"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm">
                <option value="">Semua Bidang</option>
                <?php foreach ($divisions as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo $division_id == $d['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Unit Select -->
            <select name="unit_id" id="unit_id"
                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm">
                <option value="">Semua Unit</option>
                <?php foreach ($units_all as $u): ?>
                    <option value="<?php echo $u['id']; ?>" data-division="<?php echo $u['division_id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Date Range -->
            <div class="flex items-center gap-1.5">
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm">
                <span class="text-xs text-slate-400 font-medium">s/d</span>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600 shadow-sm">
            </div>
        </div>

        <!-- Right / Action Buttons -->
        <div class="flex items-center gap-2 self-end sm:self-auto">
            <button type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-cyan-700 transition-colors focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-1 cursor-pointer">
                <i class="fa-solid fa-filter mr-1.5 text-[11px]"></i>
                Filter
            </button>
            <?php if ($search || $division_id || $unit_id || $start_date != $default_start || $end_date != $default_end): ?>
                <a href="index.php"
                    class="inline-flex items-center justify-center p-2 rounded-lg border border-slate-300 bg-white text-slate-500 hover:text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm h-8 w-8 cursor-pointer"
                    title="Reset Filter">
                    <i class="fa-solid fa-rotate-left text-xs"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Attendance Table Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/80">
                    <tr class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-14">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Pegawai</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[160px]">Tanggal</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[130px]">Masuk</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[130px]">Pulang</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Lokasi & Jarak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (count($logs) > 0): ?>
                        <?php
                        $days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
                        foreach ($logs as $index => $log):
                        ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="whitespace-nowrap py-3.5 pl-6 pr-3 text-xs text-slate-500 font-medium">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5">
                                    <div class="font-semibold text-slate-900 text-sm leading-tight">
                                        <?php echo htmlspecialchars($log['full_name']); ?>
                                    </div>
                                    <div class="text-xs text-slate-500 mt-0.5">
                                        <?php echo htmlspecialchars($log['email']); ?>
                                    </div>
                                    <?php if (!empty($log['division_name']) || !empty($log['unit_name'])): ?>
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            <?php echo htmlspecialchars(implode(' • ', array_filter([$log['division_name'] ?? '', $log['unit_name'] ?? '']))); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs text-slate-600">
                                    <div class="font-medium text-slate-800">
                                        <?php
                                        $dayName = date('l', strtotime($log['date']));
                                        echo ($days[$dayName] ?? $dayName) . ', ' . date('d M Y', strtotime($log['date']));
                                        ?>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                    <div class="font-semibold text-slate-800">
                                        <?php echo date('H:i:s', strtotime($log['time_in'])); ?>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium mt-1 <?php echo ($log['status'] === 'Hadir') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/80' : 'bg-rose-50 text-rose-700 border border-rose-200/80'; ?>">
                                        <?php echo htmlspecialchars($log['status']); ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-3.5 text-xs">
                                    <?php if ($log['time_out']): ?>
                                        <div class="font-semibold text-slate-800">
                                            <?php echo date('H:i:s', strtotime($log['time_out'])); ?>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium mt-1 <?php echo ($log['status_out'] === 'Pulang') ? 'bg-emerald-50 text-emerald-700 border border-emerald-200/80' : 'bg-rose-50 text-rose-700 border border-rose-200/80'; ?>">
                                            <?php echo htmlspecialchars($log['status_out'] ?? 'Pulang'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200/80">
                                            Belum Pulang
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-3.5 text-xs">
                                    <?php 
                                        $distIn = calcDistanceMeters($log['lat_in'], $log['long_in'], $log['loc_lat_in'], $log['loc_long_in']);
                                        $distOut = !empty($log['lat_out']) ? calcDistanceMeters($log['lat_out'], $log['long_out'], $log['loc_lat_out'] ?? $log['loc_lat_in'], $log['loc_long_out'] ?? $log['loc_long_in']) : null;
                                    ?>
                                    <div class="text-slate-800">
                                        <span class="font-medium">Masuk: <?php echo htmlspecialchars($log['location_name'] ?? '-'); ?></span>
                                        <?php if ($distIn !== null): ?>
                                            <span class="text-slate-400 text-[11px] ml-1">(<?php echo $distIn; ?> m)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($log['time_out'])): ?>
                                        <?php $locOutName = $log['location_out_name'] ?? $log['location_name'] ?? '-'; ?>
                                        <div class="text-slate-500 text-[11px] mt-0.5">
                                            <span>Pulang: <?php echo htmlspecialchars($locOutName); ?></span>
                                            <?php if ($distOut !== null): ?>
                                                <span class="text-slate-400 ml-1">(<?php echo $distOut; ?> m)</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500 text-sm">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                                    </div>
                                    <p class="font-semibold text-slate-700 text-sm">Tidak ada catatan absensi ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-1">Coba sesuaikan filter pencarian atau rentang tanggal.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php
        // Helper to build URL with current filters
        function buildUrl($p, $l, $d, $s, $e, $search = null, $u = null) {
            return "?page=$p&limit=$l" . 
                   ($search ? "&search=" . urlencode($search) : "") .
                   ($d ? "&division_id=$d" : "") . 
                   ($u ? "&unit_id=$u" : "") . 
                   ($s ? "&start_date=$s" : "") . 
                   ($e ? "&end_date=$e" : "");
        }
        ?>
        <div class="border-t border-slate-200 bg-slate-50/50 px-4 py-3 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Mobile Pagination -->
            <div class="flex sm:hidden flex-col items-center gap-2 w-full">
                <p class="text-xs text-slate-500">
                    Menampilkan <span class="font-semibold text-slate-800"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> - <span
                        class="font-semibold text-slate-800"><?php echo min($offset + $limit, $total_rows); ?></span>
                    dari <span class="font-semibold text-slate-800"><?php echo $total_rows; ?></span> data
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date, $search, $unit_id); ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date, $search, $unit_id); ?>"
                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 shadow-sm">Next</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desktop Pagination -->
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">Tampilkan:</span>
                        <select onchange="window.location.href='<?php echo buildUrl(1, '', $division_id, $start_date, $end_date, $search, $unit_id); ?>'.replace('limit=', 'limit='+this.value)"
                            class="rounded-lg border border-slate-300 py-1 pl-2.5 pr-7 text-xs font-medium text-slate-700 bg-white focus:outline-none focus:ring-1 focus:ring-cyan-600 focus:border-cyan-600">
                            <?php foreach ([10, 20, 50, 100] as $val): ?>
                                <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                    <?php echo $val; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                            <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date, $search, $unit_id); ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 hover:text-slate-700 transition-colors">
                                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $range = 1;
                        for ($i = 1; $i <= $total_pages; $i++):
                            if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                <a href="<?php echo buildUrl($i, $limit, $division_id, $start_date, $end_date, $search, $unit_id); ?>"
                                    class="relative inline-flex items-center px-3.5 py-2 font-semibold border-x border-slate-200 transition-colors <?php echo ($i == $page) ? 'bg-cyan-600 text-white border-cyan-600' : 'text-slate-700 hover:bg-slate-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                <span class="relative inline-flex items-center px-3 py-2 font-semibold text-slate-400 border-x border-slate-200">...</span>
                            <?php endif;
                        endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date, $search, $unit_id); ?>"
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
document.addEventListener('DOMContentLoaded', function() {
    const divisionSelect = document.getElementById('division_id');
    const unitSelect = document.getElementById('unit_id');

    if (!divisionSelect || !unitSelect) return;

    // Cache list of all units from the DOM
    const initialUnits = [];
    const options = unitSelect.querySelectorAll('option');
    options.forEach(opt => {
        if (opt.value !== '') {
            initialUnits.push({
                value: opt.value,
                text: opt.textContent.trim(),
                divisionId: opt.getAttribute('data-division') || ''
            });
        }
    });

    function updateUnitDropdown(selectedDivId, preserveCurrentValue = false) {
        const currentVal = preserveCurrentValue ? unitSelect.value : '';
        unitSelect.innerHTML = '';

        // Default placeholder
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = selectedDivId ? 'Semua Unit di Bidang Ini' : 'Semua Unit';
        unitSelect.appendChild(defaultOption);

        let valueStillValid = false;

        initialUnits.forEach(unit => {
            // If no division is selected, show all units; if selected, show only matching division
            if (!selectedDivId || String(unit.divisionId) === String(selectedDivId)) {
                const opt = document.createElement('option');
                opt.value = unit.value;
                opt.textContent = unit.text;
                opt.setAttribute('data-division', unit.divisionId);
                if (currentVal && String(unit.value) === String(currentVal)) {
                    opt.selected = true;
                    valueStillValid = true;
                }
                unitSelect.appendChild(opt);
            }
        });

        if (!valueStillValid) {
            unitSelect.value = '';
        }
    }

    // On division change: filter units dynamically
    divisionSelect.addEventListener('change', function() {
        updateUnitDropdown(this.value, false);
    });

    // Run on initial load if division is already selected
    if (divisionSelect.value) {
        updateUnitDropdown(divisionSelect.value, true);
    }
});
</script>

<?php include '../layouts/footer.php'; ?>