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
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : $default_end;

// Ambil List Bidang (Divisions) untuk Filter
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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

<div class="pb-10">
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-semibold text-gray-900">Riwayat Absensi</h1>
            <p class="mt-2 text-sm text-gray-700">Log lengkap absen masuk dan pulang pegawai.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="export_excel.php?<?php echo http_build_query(['search' => $search ?? '', 'division_id' => $division_id ?? '', 'start_date' => $start_date ?? '', 'end_date' => $end_date ?? '']); ?>" 
               class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 hover:shadow-emerald-600/40 focus:ring-4 focus:ring-emerald-500/30 transition-all active:scale-95">
                <i class="fa-solid fa-file-lines w-4 h-4"></i>
                Export Excel
            </a>
        </div>
    </div>

    <!-- Dynamic Filter Section -->
    <div class="mb-8 overflow-hidden rounded-2xl bg-white shadow-sm border border-slate-200">
        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 uppercase tracking-tight">
                <i class="fa-solid fa-filter w-4 h-4 text-cyan-500"></i>
                Filter Pencarian
            </h3>
        </div>
        <form method="GET" class="p-6">
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                
                <!-- Nama Karyawan Filter (Pencarian Nama) -->
                <div class="md:col-span-3">
                    <label for="search" class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Nama Pegawai</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                            <i class="fa-solid fa-magnifying-glass w-4 h-4"></i>
                        </div>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Cari nama..." class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all outline-none">
                    </div>
                </div>

                <!-- Division Filter (Bidang) -->
                <div class="md:col-span-3">
                    <label for="division_id" class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Bidang</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                            <i class="fa-solid fa-id-card w-4 h-4"></i>
                        </div>
                        <select name="division_id" id="division_id" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all appearance-none cursor-pointer">
                            <option value="">Semua Bidang</option>
                            <?php foreach ($divisions as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo $division_id == $d['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-400">
                            <i class="fa-solid fa-chevron-down w-4 h-4"></i>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div class="md:col-span-4">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Rentang Tanggal</label>
                    <div class="flex items-center gap-3">
                        <div class="relative w-full group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                                <i class="fa-solid fa-calendar-days w-4 h-4"></i>
                            </div>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all">
                        </div>
                        <span class="text-slate-400 text-sm font-bold">s/d</span>
                        <div class="relative w-full group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                                <i class="fa-solid fa-calendar-days w-4 h-4"></i>
                            </div>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="md:col-span-2 flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 hover:shadow-cyan-600/40 focus:ring-4 focus:ring-cyan-500/30 transition-all active:scale-95">
                        <i class="fa-solid fa-magnifying-glass w-4 h-4"></i>
                        Terapkan
                    </button>
                    <?php if ($search || $division_id || $start_date != $default_start || $end_date != $default_end): ?>
                        <a href="?" class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-orange-50 text-orange-600 hover:bg-orange-100 transition-all active:scale-95 border border-orange-100" title="Bersihkan Filter">
                            <i class="fa-solid fa-rotate w-5 h-5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Table Container -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Pegawai</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Tanggal</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px]">Masuk</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px]">Pulang</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Lokasi Kantor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                            <?php if (count($logs) > 0): ?>
                                <?php
                                $days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
                                foreach ($logs as $index => $log):
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500">
                                            <?php echo $offset + $index + 1; ?>.
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4">
                                            <div class="text-sm font-bold text-slate-800 tracking-tight">
                                                <?php echo htmlspecialchars($log['full_name']); ?>
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-medium">
                                                <?php echo htmlspecialchars($log['email']); ?>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 font-medium">
                                            <?php
                                            $dayName = date('l', strtotime($log['date']));
                                            echo ($days[$dayName] ?? $dayName) . ', ' . date('d M Y', strtotime($log['date']));
                                            ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <div class="font-bold text-slate-700">
                                                <?php echo date('H:i:s', strtotime($log['time_in'])); ?>
                                            </div>
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-bold <?php echo ($log['status'] === 'Hadir') ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'; ?>">
                                                <?php echo htmlspecialchars($log['status']); ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <?php if ($log['time_out']): ?>
                                                <div class="font-bold text-slate-700">
                                                    <?php echo date('H:i:s', strtotime($log['time_out'])); ?>
                                                </div>
                                                <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-bold <?php echo ($log['status_out'] === 'Pulang') ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'; ?>">
                                                    <?php echo htmlspecialchars($log['status_out'] ?? 'Pulang'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center rounded-lg bg-cyan-50 px-2.5 py-1 text-[10px] font-bold text-cyan-600 ring-1 ring-inset ring-cyan-500/20 animate-pulse">
                                                    AKTIF
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <div class="font-bold text-slate-700 flex items-center gap-1.5">
                                                <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 flex-shrink-0" title="Lokasi Masuk"></span>
                                                <span class="text-xs text-slate-400 font-semibold uppercase">Masuk:</span>
                                                <span class="truncate max-w-[160px]"><?php echo htmlspecialchars($log['location_name'] ?? '-'); ?></span>
                                            </div>
                                            <div class="font-bold text-slate-700 flex items-center gap-1.5 mt-1">
                                                <span class="inline-block w-2 h-2 rounded-full bg-orange-500 flex-shrink-0" title="Lokasi Pulang"></span>
                                                <span class="text-xs text-slate-400 font-semibold uppercase">Pulang:</span>
                                                <span class="truncate max-w-[160px]">
                                                    <?php 
                                                    if (!empty($log['time_out'])) {
                                                        echo htmlspecialchars($log['location_out_name'] ?? $log['location_name'] ?? '-');
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                             <?php 
                                                 $distIn = calcDistanceMeters($log['lat_in'], $log['long_in'], $log['loc_lat_in'], $log['loc_long_in']);
                                                 $distOut = !empty($log['lat_out']) ? calcDistanceMeters($log['lat_out'], $log['long_out'], $log['loc_lat_out'] ?? $log['loc_lat_in'], $log['loc_long_out'] ?? $log['loc_long_in']) : null;
                                             ?>
                                             <div class="text-[10px] text-slate-500 font-medium mt-1.5 leading-tight bg-slate-50 p-1.5 rounded-lg border border-slate-100">
                                                 <div>Jarak Masuk: <span class="font-bold text-slate-700"><?php echo ($distIn !== null) ? $distIn . ' m' : '-'; ?></span> <span class="text-slate-400 text-[9px]">(Batas: <?php echo $log['location_radius_in'] ?? 300; ?>m)</span></div>
                                                 <?php if(!empty($log['time_out'])): ?>
                                                     <div class="mt-0.5">Jarak Pulang: <span class="font-bold text-slate-700"><?php echo ($distOut !== null) ? $distOut . ' m' : '-'; ?></span> <span class="text-slate-400 text-[9px]">(Batas: <?php echo $log['location_radius_out'] ?? $log['location_radius_in'] ?? 300; ?>m)</span></div>
                                                 <?php endif; ?>
                                             </div>
                                         </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-3 py-4 text-sm text-center text-gray-500">Tidak ada catatan
                                        absensi ditemukan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php
                    // Helper to build URL with current filters
                    function buildUrl($p, $l, $d, $s, $e, $search = null) {
                        return "?page=$p&limit=$l" . 
                               ($search ? "&search=" . urlencode($search) : "") .
                               ($d ? "&division_id=$d" : "") . 
                               ($s ? "&start_date=$s" : "") . 
                               ($e ? "&end_date=$e" : "");
                    }
                    ?>
                    <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                        <!-- Mobile Pagination Info -->
                        <div class="flex sm:hidden flex-col items-center gap-2">
                            <p class="text-xs text-slate-500">
                                Menampilkan <span class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                            </p>
                            <div class="flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date, $search); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Prev</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date, $search); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Desktop/Tablet Pagination Info -->
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="relative group">
                                    <select onchange="window.location.href='<?php echo buildUrl(1, '', $division_id, $start_date, $end_date, $search); ?>'.replace('limit=', 'limit='+this.value)"
                                        class="block rounded-xl border-slate-200 py-1.5 pl-3 pr-8 text-slate-700 text-xs font-bold bg-slate-50 group-hover:bg-white focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 transition-all appearance-none cursor-pointer">
                                        <?php foreach ([10, 20, 50, 100] as $val): ?>
                                            <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                                Tampil <?php echo $val; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-slate-400">
                                        <i class="fa-solid fa-chevron-down w-3 h-3"></i>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600 font-medium">
                                    Menampilkan <span class="text-slate-900 font-bold"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> sampai <span class="text-slate-900 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="text-slate-900 font-bold"><?php echo $total_rows; ?></span> hasil
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination">
                                    <!-- Prev -->
                                    <?php if ($page > 1): ?>
                                        <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date, $search); ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                            <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $range = 2;
                                    $initial_num = $page - $range;
                                    $condition_limit_num = ($page + $range) + 1;

                                    for ($i = 1; $i <= $total_pages; $i++) {
                                        if ($i == 1 || $i == $total_pages || ($i >= $initial_num && $i < $condition_limit_num)) {
                                            ?>
                                            <a href="<?php echo buildUrl($i, $limit, $division_id, $start_date, $end_date, $search); ?>"
                                                class="relative inline-flex items-center px-4 py-2 text-sm font-bold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                                <?php echo $i; ?>
                                            </a>
                                            <?php
                                        } elseif ($i == $initial_num - 1 || $i == $condition_limit_num) {
                                            ?>
                                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50/50">...</span>
                                            <?php
                                        }
                                    }
                                    ?>

                                    <!-- Next -->
                                    <?php if ($page < $total_pages): ?>
                                        <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date, $search); ?>"
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
    </div>

    <?php include '../layouts/footer.php'; ?>