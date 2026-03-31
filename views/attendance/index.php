<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Log Absensi";

$db = new Database();
$conn = $db->getConnection();

// --- Filter & Search ---
$division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : null;

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
$where = " WHERE 1=1 ";
$params = [];

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

// Ambil Data
$query = "
    SELECT a.*, e.full_name, e.email, l.name as location_name 
    FROM attendances a
    JOIN employees e ON a.user_id = e.id
    LEFT JOIN locations l ON a.location_id = l.id
    $where
    ORDER BY a.date DESC, a.time_in DESC
    LIMIT :limit OFFSET :offset
";
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
    <div class="sm:flex sm:items-center mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-semibold text-gray-900">Riwayat Absensi</h1>
            <p class="mt-2 text-sm text-gray-700">Log lengkap absen masuk dan pulang pegawai.</p>
        </div>
    </div>

    <!-- Dynamic Filter Section -->
    <div class="mb-8 overflow-hidden rounded-2xl bg-white shadow-sm border border-slate-200">
        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 uppercase tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-cyan-500">
                    <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd" />
                </svg>
                Filter Pencarian
            </h3>
        </div>
        <form method="GET" class="p-6">
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                
                <!-- Division Filter (Bidang) -->
                <div class="md:col-span-4">
                    <label for="division_id" class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Bidang</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M2.25 14.5A2.25 2.25 0 014.5 12h11a2.25 2.25 0 012.25 2.25V16a2.25 2.25 0 01-2.25 2.25h-11A2.25 2.25 0 012.25 16v-1.5zM4.5 13.5a.75.75 0 00-.75.75V16c0 .414.336.75.75.75h11a.75.75 0 00.75-.75v-1.75a.75.75 0 00-.75-.75h-11z" clip-rule="evenodd" />
                                <path fill-rule="evenodd" d="M10 2a3 3 0 100 6 3 3 0 000-6zM8.5 5a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm-5.5 5.5A2.25 2.25 0 015.25 8h9.5A2.25 2.25 0 0117 10.25V11a.75.75 0 01-1.5 0v-.75a.75.75 0 00-.75-.75h-9.5a.75.75 0 00-.75.75V11a.75.75 0 01-1.5 0v-.75z" clip-rule="evenodd" />
                            </svg>
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
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Date Range Filters -->
                <div class="md:col-span-5">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Rentang Tanggal</label>
                    <div class="flex items-center gap-3">
                        <div class="relative w-full group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $start_date; ?>" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all">
                        </div>
                        <span class="text-slate-400 text-sm font-bold">s/d</span>
                        <div class="relative w-full group">
                            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                    <path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $end_date; ?>" class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="md:col-span-3 flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 hover:shadow-cyan-600/40 focus:ring-4 focus:ring-cyan-500/30 transition-all active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                        Terapkan
                    </button>
                    <?php if ($division_id || $start_date || $end_date): ?>
                        <a href="?" class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-orange-50 text-orange-600 hover:bg-orange-100 transition-all active:scale-95 border border-orange-100" title="Bersihkan Filter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                <path d="M3 3v5h5"/>
                            </svg>
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
                                                <?php echo date('H:i', strtotime($log['time_in'])); ?>
                                            </div>
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-[10px] font-bold <?php echo ($log['status'] === 'Hadir') ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'; ?>">
                                                <?php echo htmlspecialchars($log['status']); ?>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <?php if ($log['time_out']): ?>
                                                <div class="font-bold text-slate-700">
                                                    <?php echo date('H:i', strtotime($log['time_out'])); ?>
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
                                            <div class="font-bold text-slate-700">
                                                <?php echo htmlspecialchars($log['location_name'] ?? '-'); ?>
                                            </div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-1 leading-tight">
                                                In: <?php echo number_format((float)($log['lat_in'] ?? 0), 4); ?>, <?php echo number_format((float)($log['long_in'] ?? 0), 4); ?>
                                                <?php if(!empty($log['lat_out'])): ?>
                                                    <br>Out: <?php echo number_format((float)($log['lat_out'] ?? 0), 4); ?>, <?php echo number_format((float)($log['long_out'] ?? 0), 4); ?>
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
                    function buildUrl($p, $l, $d, $s, $e) {
                        return "?page=$p&limit=$l" . 
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
                                    <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Prev</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date); ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Desktop/Tablet Pagination Info -->
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div class="flex items-center gap-4">
                                <div class="relative group">
                                    <select onchange="window.location.href='<?php echo buildUrl(1, '', $division_id, $start_date, $end_date); ?>'.replace('limit=', 'limit='+this.value)"
                                        class="block rounded-xl border-slate-200 py-1.5 pl-3 pr-8 text-slate-700 text-xs font-bold bg-slate-50 group-hover:bg-white focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 transition-all appearance-none cursor-pointer">
                                        <?php foreach ([10, 20, 50, 100] as $val): ?>
                                            <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                                Tampil <?php echo $val; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none text-slate-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 011.06 0L10 11.94l3.72-3.72a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.22 9.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                                        </svg>
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
                                        <a href="<?php echo buildUrl($page - 1, $limit, $division_id, $start_date, $end_date); ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>

                                    <?php
                                    $range = 2;
                                    $initial_num = $page - $range;
                                    $condition_limit_num = ($page + $range) + 1;

                                    for ($i = 1; $i <= $total_pages; $i++) {
                                        if ($i == 1 || $i == $total_pages || ($i >= $initial_num && $i < $condition_limit_num)) {
                                            ?>
                                            <a href="<?php echo buildUrl($i, $limit, $division_id, $start_date, $end_date); ?>"
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
                                        <a href="<?php echo buildUrl($page + 1, $limit, $division_id, $start_date, $end_date); ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
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
    </div>

    <?php include '../layouts/footer.php'; ?>