<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Kinerja Pegawai";

$db = new Database();
$conn = $db->getConnection();

// --- Filter ---
$division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;
$search = isset($_GET['search']) && $_GET['search'] !== '' ? $_GET['search'] : null;

// Ambil List Bidang (Divisions) untuk Filter
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Build Leaderboard Query ---
$whereClause = " WHERE (e.status = 'active' OR e.status IS NULL) ";
$params = [];

if ($division_id) {
    $whereClause .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($search) {
    $whereClause .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}

$query = "
    SELECT 
        e.id,
        e.full_name,
        d.name as division_name,
        COALESCE(att.hadir_count, 0) as hadir_count,
        COALESCE(att.telat_count, 0) as telat_count,
        COALESCE(meet.meeting_count, 0) as meeting_count,
        COALESCE(att.att_points, 0) + COALESCE(meet.meet_points, 0) as total_points
    FROM employees e
    LEFT JOIN divisions d ON e.division_id = d.id
    LEFT JOIN (
        SELECT 
            user_id,
            SUM(CASE WHEN status = 'Hadir' OR status = 'Tepat Waktu' THEN 1 ELSE 0 END) as hadir_count,
            SUM(CASE WHEN status = 'Telat' THEN 1 ELSE 0 END) as telat_count,
            SUM(CASE WHEN status_out = 'Pulang' OR status_out = 'Tepat Waktu' THEN 1 ELSE 0 END) as pulang_count,
            SUM(CASE WHEN status_out = 'Pulang Cepat' THEN 1 ELSE 0 END) as cepat_count,
            SUM(
                CASE 
                    WHEN status = 'Hadir' OR status = 'Tepat Waktu' THEN 10 
                    WHEN status = 'Telat' THEN -5 
                    ELSE 0 
                END +
                CASE 
                    WHEN status_out = 'Pulang' OR status_out = 'Tepat Waktu' THEN 10 
                    WHEN status_out = 'Pulang Cepat' THEN -5 
                    ELSE 0 
                END
            ) as att_points 
        FROM attendances 
        GROUP BY user_id
    ) att ON e.id = att.user_id
    LEFT JOIN (
        SELECT 
            employee_id,
            COUNT(*) as meeting_count, 
            COUNT(*) * 10 as meet_points 
        FROM meeting_participants 
        WHERE status = 'present' 
        GROUP BY employee_id
    ) meet ON e.id = meet.employee_id
    $whereClause
    ORDER BY total_points DESC, e.full_name ASC
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate top 3 & stats
$total_employees = count($employees);
$top3 = array_slice($employees, 0, 3);
$avg_points = $total_employees > 0 ? round(array_sum(array_column($employees, 'total_points')) / $total_employees) : 0;
$max_points = $total_employees > 0 ? max(array_column($employees, 'total_points')) : 0;

// Status helper
function getStatusBadge($points) {
    if ($points > 800) return ['Sangat Baik', 'bg-emerald-100 text-emerald-700 ring-emerald-600/20'];
    if ($points > 500) return ['Baik', 'bg-green-100 text-green-700 ring-green-600/20'];
    if ($points >= 100) return ['Cukup', 'bg-amber-100 text-amber-700 ring-amber-600/20'];
    if ($points > 0) return ['Kurang', 'bg-orange-100 text-orange-700 ring-orange-600/20'];
    if ($points < 0) return ['Perlu Perbaikan', 'bg-red-100 text-red-700 ring-red-600/20'];
    return ['Belum Ada Data', 'bg-slate-100 text-slate-500 ring-slate-500/20'];
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Page Header -->
    <div class="sm:flex sm:items-center sm:justify-between mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-semibold text-gray-900">Kinerja Pegawai</h1>
            <p class="mt-2 text-sm text-gray-700">Pantau poin kinerja pegawai dari absensi dan kehadiran rapat.</p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-8">
        <!-- Total Pegawai -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 w-12 h-12 bg-cyan-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-cyan-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Pegawai</p>
                    <p class="text-2xl font-bold text-slate-800 mt-0.5"><?php echo $total_employees; ?></p>
                </div>
            </div>
        </div>

        <!-- Poin Rata-rata -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 w-12 h-12 bg-amber-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rata-rata Poin</p>
                    <p class="text-2xl font-bold text-slate-800 mt-0.5"><?php echo $avg_points; ?></p>
                </div>
            </div>
        </div>

        <!-- Poin Tertinggi -->
        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0016.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m0 0a6.023 6.023 0 01-2.77.704 6.023 6.023 0 01-2.77-.704" />
                    </svg>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Poin Tertinggi</p>
                    <p class="text-2xl font-bold text-slate-800 mt-0.5"><?php echo $max_points; ?></p>
                </div>
            </div>
        </div>

        <!-- Logika Perhitungan -->
        <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-2xl p-5 shadow-sm shadow-cyan-500/20">
            <p class="text-xs font-semibold text-cyan-100 uppercase tracking-wider mb-3">Logika Poin</p>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-white/90">Absen Tepat Waktu</span>
                    <span class="text-xs font-bold text-emerald-200">+10</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-white/90">Absen Terlambat</span>
                    <span class="text-xs font-bold text-red-200">-5</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-white/90">Pulang Tepat Waktu</span>
                    <span class="text-xs font-bold text-emerald-200">+10</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-white/90">Pulang Cepat</span>
                    <span class="text-xs font-bold text-red-200">-5</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-white/90">Hadir Rapat</span>
                    <span class="text-xs font-bold text-emerald-200">+10</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 3 Podium -->
    <?php if (count($top3) >= 3): ?>
    <div class="mb-8">
        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4 px-1">🏆 Podium Teratas</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Rank 2 -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all hover:-translate-y-1 order-1 md:order-1">
                <div class="text-center">
                    <div class="relative inline-block">
                        <img class="h-16 w-16 rounded-full border-2 border-slate-300 object-cover mx-auto"
                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($top3[1]['full_name']); ?>&background=94a3b8&color=fff&size=128&bold=true" alt="">
                        <span class="absolute -bottom-1 -right-1 inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-400 text-white text-xs font-bold shadow-lg">2</span>
                    </div>
                    <h3 class="mt-3 text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($top3[1]['full_name']); ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($top3[1]['division_name'] ?? '-'); ?></p>
                    <div class="mt-3 inline-flex items-center gap-1.5 bg-slate-50 rounded-full px-3 py-1.5">
                        <span class="text-lg font-bold text-slate-700"><?php echo $top3[1]['total_points']; ?></span>
                        <span class="text-xs text-slate-400 font-medium">poin</span>
                    </div>
                </div>
            </div>

            <!-- Rank 1 (Center, highlighted) -->
            <div class="bg-gradient-to-b from-amber-50 to-white rounded-2xl border-2 border-amber-200 p-6 shadow-md hover:shadow-lg transition-all hover:-translate-y-1 order-0 md:order-2 relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-400"></div>
                <div class="text-center">
                    <div class="relative inline-block">
                        <img class="h-20 w-20 rounded-full border-3 border-amber-400 object-cover mx-auto ring-4 ring-amber-100"
                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($top3[0]['full_name']); ?>&background=f59e0b&color=fff&size=128&bold=true" alt="">
                        <span class="absolute -bottom-1 -right-1 inline-flex items-center justify-center w-8 h-8 rounded-full bg-amber-500 text-white text-sm font-bold shadow-lg">👑</span>
                    </div>
                    <h3 class="mt-3 text-base font-bold text-slate-800 truncate"><?php echo htmlspecialchars($top3[0]['full_name']); ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($top3[0]['division_name'] ?? '-'); ?></p>
                    <div class="mt-3 inline-flex items-center gap-1.5 bg-amber-50 rounded-full px-4 py-2 border border-amber-200">
                        <span class="text-xl font-bold text-amber-700"><?php echo $top3[0]['total_points']; ?></span>
                        <span class="text-xs text-amber-500 font-medium">poin</span>
                    </div>
                </div>
            </div>

            <!-- Rank 3 -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-all hover:-translate-y-1 order-2 md:order-3">
                <div class="text-center">
                    <div class="relative inline-block">
                        <img class="h-16 w-16 rounded-full border-2 border-amber-700 object-cover mx-auto"
                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($top3[2]['full_name']); ?>&background=b45309&color=fff&size=128&bold=true" alt="">
                        <span class="absolute -bottom-1 -right-1 inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-700 text-white text-xs font-bold shadow-lg">3</span>
                    </div>
                    <h3 class="mt-3 text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($top3[2]['full_name']); ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($top3[2]['division_name'] ?? '-'); ?></p>
                    <div class="mt-3 inline-flex items-center gap-1.5 bg-orange-50 rounded-full px-3 py-1.5">
                        <span class="text-lg font-bold text-amber-800"><?php echo $top3[2]['total_points']; ?></span>
                        <span class="text-xs text-amber-600 font-medium">poin</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="mb-6 overflow-hidden rounded-2xl bg-white shadow-sm border border-slate-200">
        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 uppercase tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-cyan-500">
                    <path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd" />
                </svg>
                Filter & Pencarian
            </h3>
        </div>
        <form method="GET" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Search Name -->
                <div class="md:col-span-5">
                    <label for="search" class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Cari Pegawai</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search ?? ''); ?>"
                            placeholder="Ketik nama pegawai..."
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 focus:bg-white transition-all">
                    </div>
                </div>

                <!-- Division Filter -->
                <div class="md:col-span-4">
                    <label for="division_id" class="block text-xs font-bold text-slate-500 uppercase mb-2 ml-1">Bidang</label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                                <path fill-rule="evenodd" d="M2.25 14.5A2.25 2.25 0 014.5 12h11a2.25 2.25 0 012.25 2.25V16a2.25 2.25 0 01-2.25 2.25h-11A2.25 2.25 0 012.25 16v-1.5z" clip-rule="evenodd" />
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

                <!-- Action Buttons -->
                <div class="md:col-span-3 flex items-end gap-2">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 hover:shadow-cyan-600/40 focus:ring-4 focus:ring-cyan-500/30 transition-all active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                        Terapkan
                    </button>
                    <?php if ($division_id || $search): ?>
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

    <!-- Leaderboard Table -->
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm border border-slate-200">
        <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
            <h3 class="flex items-center gap-2 text-sm font-bold text-slate-800 uppercase tracking-tight">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4 text-cyan-500">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd" />
                </svg>
                Peringkat Kinerja Semua Pegawai
                <span class="ml-2 text-xs font-medium text-slate-400 normal-case">(<?php echo $total_employees; ?> pegawai)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">Rank</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[220px]">Pegawai</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[180px]">Bidang</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[100px] text-emerald-600">Hadir</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[100px] text-rose-500">Telat</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[100px] text-emerald-600">Pulang</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[100px] text-rose-500">Cepat</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[100px] text-blue-600">Rapat</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[120px]">Total Poin</th>
                        <th scope="col" class="px-3 py-3.5 text-center min-w-[150px]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php if (count($employees) > 0): ?>
                        <?php 
                        $rank = 0;
                        $prev_points = null;
                        foreach ($employees as $index => $emp): 
                            if ($prev_points === null || $emp['total_points'] < $prev_points) {
                                $rank = $index + 1;
                            }
                            $prev_points = $emp['total_points'];
                            $statusInfo = getStatusBadge($emp['total_points']);

                            // Rank badge styles
                            $rankBadge = '';
                            if ($rank == 1) $rankBadge = 'bg-amber-100 text-amber-700 ring-2 ring-amber-300';
                            elseif ($rank == 2) $rankBadge = 'bg-slate-200 text-slate-700 ring-2 ring-slate-300';
                            elseif ($rank == 3) $rankBadge = 'bg-orange-100 text-orange-700 ring-2 ring-orange-300';
                            else $rankBadge = 'bg-slate-50 text-slate-500';

                            // Progress bar width (proportionate)
                            $progressWidth = $max_points > 0 ? round(($emp['total_points'] / $max_points) * 100) : 0;
                            if ($progressWidth < 0) $progressWidth = 0;
                        ?>
                            <tr class="hover:bg-cyan-50/30 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold <?php echo $rankBadge; ?>">
                                        <?php echo $rank; ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <div class="flex items-center gap-3">
                                        <img class="h-9 w-9 rounded-full border border-slate-200 object-cover flex-shrink-0"
                                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['full_name']); ?>&background=random&size=64&bold=true" alt="">
                                        <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    <?php echo htmlspecialchars($emp['division_name'] ?? '-'); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600">
                                        <?php echo $emp['hadir_count']; ?>
                                        <span class="text-xs text-emerald-400">(+<?php echo $emp['hadir_count'] * 10; ?>)</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-red-500">
                                        <?php echo $emp['telat_count']; ?>
                                        <span class="text-xs text-red-400">(<?php echo $emp['telat_count'] > 0 ? '-' . ($emp['telat_count'] * 5) : '0'; ?>)</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-emerald-600">
                                        <?php echo $emp['pulang_count'] ?? 0; ?>
                                        <span class="text-xs text-emerald-400">(+<?php echo ($emp['pulang_count'] ?? 0) * 10; ?>)</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-red-500">
                                        <?php echo $emp['cepat_count'] ?? 0; ?>
                                        <span class="text-xs text-red-400">(<?php echo ($emp['cepat_count'] ?? 0) > 0 ? '-' . (($emp['cepat_count'] ?? 0) * 5) : '0'; ?>)</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-blue-600">
                                        <?php echo $emp['meeting_count']; ?>
                                        <span class="text-xs text-blue-400">(+<?php echo $emp['meeting_count'] * 10; ?>)</span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="text-sm font-bold text-slate-800"><?php echo $emp['total_points']; ?></span>
                                        <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full <?php echo $emp['total_points'] >= 0 ? 'bg-cyan-500' : 'bg-red-400'; ?>" style="width: <?php echo max($progressWidth, 3); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset <?php echo $statusInfo[1]; ?>">
                                        <?php echo $statusInfo[0]; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-3 py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                    </svg>
                                    <p class="text-sm text-slate-500">Tidak ada data pegawai ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
