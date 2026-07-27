<?php
// views/tahfidz/baselines.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Baseline Hafalan Santri";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Academic Years ---
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY is_active DESC, name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);

// Active Academic Year Default
$active_ay_id = 0;
foreach ($academic_years as $ay) {
    if ($ay['is_active']) {
        $active_ay_id = $ay['id'];
        break;
    }
}
if ($active_ay_id === 0 && !empty($academic_years)) {
    $active_ay_id = $academic_years[0]['id'];
}

// --- Filters & Pagination ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_ay = isset($_GET['filter_ay']) ? $_GET['filter_ay'] : $active_ay_id;
$limit = isset($_GET['limit']) && in_array((int)$_GET['limit'], [10, 25, 50, 100]) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Summary Stats ---
$stats = [
    'total' => 0,
    'avg' => 0.0,
    'max' => 0.0,
    'min' => 0.0
];

$stat_sql = "SELECT 
    COUNT(*) as total_santri,
    COALESCE(AVG(baseline_juz), 0) as avg_juz,
    COALESCE(MAX(baseline_juz), 0) as max_juz,
    COALESCE(MIN(baseline_juz), 0) as min_juz
    FROM memorization_baselines";
if ($filter_ay) {
    $stat_sql .= " WHERE academic_year_id = " . intval($filter_ay);
}
$stat_res = $conn->query($stat_sql)->fetch(PDO::FETCH_ASSOC);
if ($stat_res) {
    $stats['total'] = (int)$stat_res['total_santri'];
    $stats['avg'] = round((float)$stat_res['avg_juz'], 1);
    $stats['max'] = (float)$stat_res['max_juz'];
    $stats['min'] = (float)$stat_res['min_juz'];
}

// --- Build Data Query ---
$where_clauses = ["1=1"];
$params = [];

if ($filter_ay !== '' && $filter_ay !== 'all') {
    $where_clauses[] = "b.academic_year_id = :filter_ay";
    $params[':filter_ay'] = $filter_ay;
}

if ($search !== '') {
    $where_clauses[] = "(s.nama_siswa LIKE :search OR s.nomor_induk LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// Total Records for Pagination
$count_query = "SELECT COUNT(*) FROM memorization_baselines b 
                JOIN students s ON b.student_id = s.id 
                WHERE $where_sql";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Main Query
$query = "SELECT b.*, 
                 s.nama_siswa, s.nomor_induk as nis, s.tingkat,
                 COALESCE(gl.name, s.kelas, '-') as nama_kelas,
                 COALESCE(hg.group_name, '-') as group_name,
                 ay.name as academic_year_name, ay.semester as academic_year_semester
          FROM memorization_baselines b
          JOIN students s ON b.student_id = s.id
          LEFT JOIN academic_years ay ON b.academic_year_id = ay.id
          LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = b.academic_year_id
          LEFT JOIN grade_levels gl ON sch.class_id = gl.id
          LEFT JOIN halaqah_members hm ON s.id = hm.student_id
          LEFT JOIN halaqah_groups hg ON hm.group_id = hg.id
          WHERE $where_sql
          ORDER BY b.updated_at DESC, s.nama_siswa ASC
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$baselines = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Students for Modal Select Option
$students = $conn->query("SELECT id, nama_siswa, nomor_induk as nis, status FROM students WHERE status = 'Aktif' OR status = 'Active' ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="min-h-screen pb-12">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-6 mb-8">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Tahfidz</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-semibold text-cyan-600">Baseline Hafalan Santri</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-800 tracking-tight">Baseline Hafalan Santri</h1>
            <p class="mt-1 text-sm text-slate-500 font-medium">Pantau dan kelola baseline awal capaian hafalan (Juz) santri secara terpusat.</p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="openModal()"
                class="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 hover:from-emerald-600 hover:to-teal-700 transition-all active:scale-95">
                <svg class="-ml-1 mr-2 h-5 w-5 text-emerald-100" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Set / Edit Baseline
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['success'])): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm">
            <svg class="h-5 w-5 text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($_GET['success']); ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-sm">
            <svg class="h-5 w-5 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($_GET['error']); ?></span>
        </div>
    <?php endif; ?>

    <!-- Summary Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <!-- Total Santri -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Ter-Baseline</p>
                    <h3 class="text-2xl font-black text-slate-800 mt-1"><?php echo number_format($stats['total']); ?> <span class="text-sm font-semibold text-slate-400">Santri</span></h3>
                </div>
                <div class="h-12 w-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Rata-rata Baseline -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Rata-Rata Baseline</p>
                    <h3 class="text-2xl font-black text-emerald-600 mt-1"><?php echo number_format($stats['avg'], 1); ?> <span class="text-sm font-semibold text-slate-400">Juz</span></h3>
                </div>
                <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Highest Baseline -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Baseline Tertinggi</p>
                    <h3 class="text-2xl font-black text-indigo-600 mt-1"><?php echo number_format($stats['max'], 1); ?> <span class="text-sm font-semibold text-slate-400">Juz</span></h3>
                </div>
                <div class="h-12 w-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 11l7-7 7 7M5 19l7-7 7 7" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Lowest Baseline -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Baseline Terendah</p>
                    <h3 class="text-2xl font-black text-amber-600 mt-1"><?php echo number_format($stats['min'], 1); ?> <span class="text-sm font-semibold text-slate-400">Juz</span></h3>
                </div>
                <div class="h-12 w-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm mb-6">
        <form method="GET" action="" class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 flex-1">
                <!-- Search Input -->
                <div class="relative flex-1 min-w-[220px]">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Cari Nama Santri / NIS..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all">
                </div>

                <!-- Academic Year Filter -->
                <div class="w-full sm:w-auto">
                    <select name="filter_ay" onchange="this.form.submit()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all">
                        <option value="all" <?php echo $filter_ay === 'all' ? 'selected' : ''; ?>>Semua Tahun Ajaran</option>
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo (string)$filter_ay === (string)$ay['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['name']); ?> (<?php echo htmlspecialchars($ay['semester']); ?>) <?php echo $ay['is_active'] ? '• Aktif' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="px-4 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition-colors">
                    Filter
                </button>

                <?php if ($search !== '' || $filter_ay !== (string)$active_ay_id): ?>
                    <a href="baselines.php" class="px-3 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700">
                        Reset Filter
                    </a>
                <?php endif; ?>
            </div>

            <!-- Limit selector -->
            <div class="flex items-center gap-2 self-end md:self-auto">
                <span class="text-xs font-medium text-slate-500">Tampilkan:</span>
                <select name="limit" onchange="this.form.submit()"
                    class="bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="10" <?php echo $limit === 10 ? 'selected' : ''; ?>>10</option>
                    <option value="25" <?php echo $limit === 25 ? 'selected' : ''; ?>>25</option>
                    <option value="50" <?php echo $limit === 50 ? 'selected' : ''; ?>>50</option>
                    <option value="100" <?php echo $limit === 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3.5 px-4 text-center w-12">No</th>
                        <th class="py-3.5 px-4">Santri</th>
                        <th class="py-3.5 px-4">Kelas / Tingkat</th>
                        <th class="py-3.5 px-4">Grup Halaqah</th>
                        <th class="py-3.5 px-4">Tahun Ajaran</th>
                        <th class="py-3.5 px-4 text-center">Baseline (Juz)</th>
                        <th class="py-3.5 px-4">Keterangan</th>
                        <th class="py-3.5 px-4 text-right">Diperbarui</th>
                        <th class="py-3.5 px-4 text-center w-28">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm font-medium text-slate-700">
                    <?php if (empty($baselines)): ?>
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <svg class="h-12 w-12 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                Belum ada data baseline hafalan yang ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($baselines as $idx => $row): ?>
                            <tr class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3.5 px-4 text-center text-xs text-slate-400 font-semibold">
                                    <?php echo $offset + $idx + 1; ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                    <div class="text-xs text-slate-400 font-medium">NIS: <?php echo htmlspecialchars($row['nis'] ?: '-'); ?></div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600">
                                        <?php echo htmlspecialchars($row['nama_kelas']); ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        <?php echo htmlspecialchars($row['group_name']); ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-xs font-semibold text-slate-600">
                                    <?php echo htmlspecialchars($row['academic_year_name'] ?: '-'); ?> (<?php echo htmlspecialchars($row['academic_year_semester'] ?: '-'); ?>)
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-xl text-xs font-black bg-emerald-50 text-emerald-700 border border-emerald-200/80 shadow-xs">
                                        <?php echo number_format((float)$row['baseline_juz'], 1); ?> Juz
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-xs text-slate-500 max-w-[220px]">
                                    <?php if (!empty($row['notes'])): ?>
                                        <div onmouseenter="showNoteTooltip(event, <?php echo htmlspecialchars(json_encode($row['notes'])); ?>)"
                                             onmouseleave="hideNoteTooltip()"
                                             title="<?php echo htmlspecialchars($row['notes']); ?>"
                                             class="truncate font-medium text-slate-700 cursor-pointer flex items-center gap-1.5 hover:text-emerald-600 transition-colors">
                                            <span class="truncate"><?php echo htmlspecialchars($row['notes']); ?></span>
                                            <svg class="h-3.5 w-3.5 text-slate-400 hover:text-emerald-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 font-medium">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right text-xs text-slate-400 font-medium">
                                    <?php echo date('d M Y H:i', strtotime($row['updated_at'])); ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick='editBaseline(<?php echo json_encode($row); ?>)'
                                            title="Edit Baseline"
                                            class="p-1.5 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 active:scale-95 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <a href="../../logic/tahfidz/manage_baseline.php?action=delete&id=<?php echo $row['id']; ?>"
                                            onclick="return confirm('Apakah Anda yakin ingin menghapus baseline santri ini?')"
                                            title="Hapus Baseline"
                                            class="p-1.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 active:scale-95 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="px-5 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-slate-500">
                <div>
                    Menampilkan <?php echo min($offset + 1, $total_rows); ?> - <?php echo min($offset + $limit, $total_rows); ?> dari <?php echo $total_rows; ?> data
                </div>
                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter_ay=<?php echo $filter_ay; ?>&limit=<?php echo $limit; ?>"
                            class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50">
                            Sebelumnya
                        </a>
                    <?php endif; ?>

                    <span class="px-3 py-1.5 text-slate-400">Halaman <?php echo $page; ?> dari <?php echo $total_pages; ?></span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter_ay=<?php echo $filter_ay; ?>&limit=<?php echo $limit; ?>"
                            class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50">
                            Selanjutnya
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form Add/Edit Baseline (Premium Styled) -->
<div id="baselineModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
    <div class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl overflow-hidden transform transition-all border border-slate-100">
        <!-- Modal Header -->
        <div class="px-6 py-5 bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 text-white flex items-center justify-between relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
            <div class="flex items-center gap-3.5 relative z-10">
                <div class="p-2.5 bg-white/15 backdrop-blur-md rounded-2xl border border-white/20 shadow-inner">
                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <h3 id="modalTitle" class="text-lg font-extrabold tracking-tight">Atur Baseline Hafalan Santri</h3>
                    <p class="text-xs text-emerald-100 font-medium">Atur angka capaian awal juz hafalan santri dengan mudah.</p>
                </div>
            </div>
            <button onclick="closeModal()" class="relative z-10 text-white/80 hover:text-white p-1.5 rounded-xl hover:bg-white/15 transition-all">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form action="../../logic/tahfidz/manage_baseline.php" method="POST" class="p-6 space-y-5">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="modal_id" value="0">
            <input type="hidden" name="student_id" id="modal_student_id_hidden" value="">

            <!-- Premium Custom Searchable Student Dropdown -->
            <div class="relative">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 flex items-center justify-between">
                    <span>Pilih Santri <span class="text-rose-500">*</span></span>
                    <span class="text-[10px] text-slate-400 font-medium font-normal lowercase">cari & pilih dari daftar</span>
                </label>
                
                <div class="relative">
                    <button type="button" id="studentDropdownBtn" onclick="toggleStudentDropdown()"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all flex items-center justify-between shadow-xs">
                        <span id="selectedStudentText" class="truncate text-slate-400">-- Cari & Pilih Santri --</span>
                        <svg class="h-4 w-4 text-slate-400 flex-shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Dropdown List Container -->
                    <div id="studentDropdownMenu" class="absolute z-40 left-0 right-0 mt-2 bg-white rounded-2xl shadow-2xl border border-slate-100 hidden overflow-hidden transition-all">
                        <div class="p-2.5 bg-slate-50 border-b border-slate-100">
                            <div class="relative">
                                <input type="text" id="studentSearchInput" oninput="filterStudents()" placeholder="Ketik nama / NIS santri..."
                                    class="w-full pl-9 pr-3.5 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                <svg class="h-4 w-4 text-slate-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                        <div id="studentOptionsList" class="max-h-56 overflow-y-auto divide-y divide-slate-50 p-1">
                            <?php foreach ($students as $st): ?>
                                <div onclick='selectStudent(<?php echo json_encode($st); ?>)'
                                    data-name="<?php echo strtolower(htmlspecialchars($st['nama_siswa'])); ?>"
                                    data-nis="<?php echo strtolower(htmlspecialchars($st['nis'] ?: '')); ?>"
                                    class="student-option-item p-2.5 rounded-xl hover:bg-emerald-50/80 cursor-pointer transition-colors flex items-center justify-between group">
                                    <div>
                                        <div class="text-sm font-bold text-slate-800 group-hover:text-emerald-700"><?php echo htmlspecialchars($st['nama_siswa']); ?></div>
                                        <div class="text-xs text-slate-400 font-medium">NIS: <?php echo htmlspecialchars($st['nis'] ?: '-'); ?></div>
                                    </div>
                                    <span class="text-xs font-semibold text-emerald-600 opacity-0 group-hover:opacity-100 transition-opacity">Pilih</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Year Selection -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Tahun Ajaran <span class="text-rose-500">*</span></label>
                <div class="relative">
                    <select name="academic_year_id" id="modal_academic_year_id" required
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all appearance-none">
                        <?php foreach ($academic_years as $ay): ?>
                            <option value="<?php echo $ay['id']; ?>" <?php echo (string)$active_ay_id === (string)$ay['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ay['name']); ?> (<?php echo htmlspecialchars($ay['semester']); ?>) <?php echo $ay['is_active'] ? '• Aktif' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                    </div>
                </div>
            </div>

            <!-- Baseline Juz Input & Preset Chips -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Baseline Hafalan (Juz) <span class="text-rose-500">*</span></label>
                
                <!-- Quick Preset Chips -->
                <div class="flex flex-wrap items-center gap-1.5 mb-2.5">
                    <span class="text-[11px] font-semibold text-slate-400 mr-1">Pilihan Cepat:</span>
                    <?php foreach ([0, 1, 3, 5, 10, 15, 20, 30] as $preset): ?>
                        <button type="button" onclick="setPresetJuz(<?php echo $preset; ?>)"
                            class="px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600 hover:bg-emerald-500 hover:text-white transition-all active:scale-95">
                            <?php echo $preset; ?> Juz
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="relative">
                    <input type="number" step="0.1" min="0" max="30" name="baseline_juz" id="modal_baseline_juz" required placeholder="Contoh: 5.5"
                        class="w-full bg-slate-50 border border-slate-200 rounded-2xl pl-4 pr-12 py-3 text-sm font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all">
                    <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-xs font-extrabold text-slate-400">
                        Juz
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Catatan / Keterangan (Opsional)</label>
                <textarea name="notes" id="modal_notes" rows="3" placeholder="Tuliskan keterangan kondisi awal hafalan santri..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-2xl p-4 text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"></textarea>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="closeModal()"
                    class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 active:scale-95 transition-all">
                    Batal
                </button>
                <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white text-sm font-bold shadow-lg shadow-teal-500/25 active:scale-95 transition-all">
                    Simpan Baseline
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Global Fixed Position Tooltip (Prevents Table Overflow/Clipping Bugs) -->
<div id="globalNoteTooltip" class="fixed z-[9999] hidden max-w-xs p-3.5 bg-slate-900/95 backdrop-blur-md text-white text-xs rounded-2xl shadow-2xl border border-slate-700/60 transition-opacity duration-150 pointer-events-none">
    <div class="font-bold text-emerald-400 mb-1 flex items-center gap-1.5 border-b border-slate-800 pb-1">
        <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
        Catatan Baseline:
    </div>
    <p id="globalNoteTooltipText" class="leading-relaxed text-slate-200 whitespace-pre-wrap"></p>
</div>

<script>
function showNoteTooltip(e, text) {
    const tooltip = document.getElementById('globalNoteTooltip');
    const tooltipText = document.getElementById('globalNoteTooltipText');
    if (!tooltip || !tooltipText || !text) return;

    tooltipText.innerText = text;
    tooltip.classList.remove('hidden');

    const rect = e.currentTarget.getBoundingClientRect();
    let left = rect.left + (rect.width / 2) - 140;
    let top = rect.top - tooltip.offsetHeight - 10;

    // Edge Detection: if top is offscreen, place below cell
    if (top < 10) {
        top = rect.bottom + 10;
    }
    // Keep within horizontal bounds
    if (left < 10) left = 10;
    if (left + 290 > window.innerWidth) left = window.innerWidth - 300;

    tooltip.style.left = left + 'px';
    tooltip.style.top = top + 'px';
}

function hideNoteTooltip() {
    const tooltip = document.getElementById('globalNoteTooltip');
    if (tooltip) tooltip.classList.add('hidden');
}

function toggleStudentDropdown() {
    const menu = document.getElementById('studentDropdownMenu');
    menu.classList.toggle('hidden');
    if (!menu.classList.contains('hidden')) {
        document.getElementById('studentSearchInput').focus();
    }
}

function filterStudents() {
    const q = document.getElementById('studentSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.student-option-item');
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        const nis = item.getAttribute('data-nis');
        if (name.includes(q) || nis.includes(q)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectStudent(st) {
    document.getElementById('modal_student_id_hidden').value = st.id;
    document.getElementById('selectedStudentText').innerText = st.nama_siswa + (st.nis ? ' (NIS: ' + st.nis + ')' : '');
    document.getElementById('selectedStudentText').classList.remove('text-slate-400');
    document.getElementById('selectedStudentText').classList.add('text-slate-800', 'font-bold');
    document.getElementById('studentDropdownMenu').classList.add('hidden');
}

function setPresetJuz(val) {
    document.getElementById('modal_baseline_juz').value = val;
}

function openModal() {
    document.getElementById('modalTitle').innerText = 'Atur Baseline Hafalan Santri';
    document.getElementById('modal_id').value = '0';
    document.getElementById('modal_student_id_hidden').value = '';
    document.getElementById('selectedStudentText').innerText = '-- Cari & Pilih Santri --';
    document.getElementById('selectedStudentText').className = 'truncate text-slate-400';
    document.getElementById('modal_baseline_juz').value = '';
    document.getElementById('modal_notes').value = '';
    document.getElementById('baselineModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('baselineModal').classList.add('hidden');
    document.getElementById('studentDropdownMenu').classList.add('hidden');
}

function editBaseline(data) {
    document.getElementById('modalTitle').innerText = 'Edit Baseline Hafalan Santri';
    document.getElementById('modal_id').value = data.id || '0';
    document.getElementById('modal_student_id_hidden').value = data.student_id || '';
    document.getElementById('selectedStudentText').innerText = data.nama_siswa + (data.nis ? ' (NIS: ' + data.nis + ')' : '');
    document.getElementById('selectedStudentText').className = 'truncate text-slate-800 font-bold';
    document.getElementById('modal_academic_year_id').value = data.academic_year_id || '';
    document.getElementById('modal_baseline_juz').value = data.baseline_juz !== undefined ? data.baseline_juz : '';
    document.getElementById('modal_notes').value = data.notes || '';
    document.getElementById('baselineModal').classList.remove('hidden');
}

// Close dropdown on outside click
document.addEventListener('click', function(e) {
    const btn = document.getElementById('studentDropdownBtn');
    const menu = document.getElementById('studentDropdownMenu');
    if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.add('hidden');
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
