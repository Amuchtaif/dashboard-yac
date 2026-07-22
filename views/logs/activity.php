<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

// Authorization Check
$position = $_SESSION['position_name'] ?? '';
$allowed_roles = ['Administrator', 'Manager', 'Developer', 'Super Admin'];
if (!in_array($position, $allowed_roles)) {
    header("Location: " . BASE_URL . "/views/dashboard/index");
    exit;
}

$page_title = "Log Aktivitas";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Inputs ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';
$module = isset($_GET['module']) ? $_GET['module'] : '';
$level = isset($_GET['level']) ? $_GET['level'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

$where_clauses = ["1=1"];
$params = [];

if ($start_date) {
    $where_clauses[] = "created_at >= :start_date";
    $params[':start_date'] = $start_date . ' 00:00:00';
}
if ($end_date) {
    $where_clauses[] = "created_at <= :end_date";
    $params[':end_date'] = $end_date . ' 23:59:59';
}
if ($user) {
    $where_clauses[] = "(user_name LIKE :user OR user_id = :user_id)";
    $params[':user'] = "%$user%";
    $params[':user_id'] = is_numeric($user) ? (int)$user : -99;
}
if ($role) {
    $where_clauses[] = "role = :role";
    $params[':role'] = $role;
}
if ($module) {
    $where_clauses[] = "module = :module";
    $params[':module'] = $module;
}
if ($level) {
    $where_clauses[] = "level = :level";
    $params[':level'] = $level;
}
if ($action) {
    $where_clauses[] = "action = :action";
    $params[':action'] = $action;
}
if ($keyword) {
    $where_clauses[] = "(description LIKE :keyword OR table_name LIKE :keyword)";
    $params[':keyword'] = "%$keyword%";
}

$where_sql = implode(" AND ", $where_clauses);

// --- Pagination ---
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
if (!in_array($limit, [10, 20, 50, 100])) $limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count for pagination
$count_query = "SELECT COUNT(*) FROM activity_logs WHERE $where_sql";
$stmt_count = $conn->prepare($count_query);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch logs
$query = "SELECT * FROM activity_logs WHERE $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
$stmt_logs = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt_logs->bindValue($key, $val);
}
$stmt_logs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_logs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_logs->execute();
$logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct metadata for filter dropdowns
$modules = $conn->query("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC")->fetchAll(PDO::FETCH_COLUMN);
$roles = $conn->query("SELECT DISTINCT role FROM activity_logs ORDER BY role ASC")->fetchAll(PDO::FETCH_COLUMN);
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 pt-6 gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-cyan-600">Log Aktivitas</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Log Aktivitas</h1>
            <p class="mt-1 text-slate-500">Audit trail lengkap mengenai seluruh tindakan pengguna di aplikasi.</p>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 mb-6">
        <h2 class="text-sm font-semibold text-slate-700 mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-cyan-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
            </svg>
            Filter Log
        </h2>
        <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <!-- Start Date -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <!-- End Date -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <!-- User -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Pengguna (Nama/ID)</label>
                <input type="text" name="user" value="<?php echo htmlspecialchars($user); ?>" placeholder="ID atau Nama"
                    class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <!-- Role -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Jabatan / Role</label>
                <select name="role" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <option value="">Semua Role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $role === $r ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Module -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Modul</label>
                <select name="module" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <option value="">Semua Modul</option>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?php echo htmlspecialchars($m); ?>" <?php echo $module === $m ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Aksi</label>
                <select name="action" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <option value="">Semua Aksi</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $action === $a ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($a); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Level -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Tingkat (Level)</label>
                <select name="level" class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <option value="">Semua Level</option>
                    <?php foreach (['INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'SECURITY'] as $l): ?>
                        <option value="<?php echo $l; ?>" <?php echo $level === $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Keyword -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Kata Kunci Deskripsi</label>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Deskripsi log..."
                    class="w-full bg-slate-50 border border-slate-200 text-slate-700 py-2 px-3 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
            </div>

            <!-- Buttons -->
            <div class="sm:col-span-2 md:col-span-4 flex justify-end gap-3 mt-2">
                <a href="?" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center">
                    Reset
                </a>
                <button type="submit" class="bg-cyan-600 hover:bg-cyan-500 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors flex items-center">
                    Terapkan Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Main List Card -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden w-full max-w-full">
        <!-- Toolbar -->
        <div class="p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center bg-white border-b border-slate-100 gap-4">
            <div class="text-sm font-medium text-slate-700">
                Ditemukan <span class="font-bold text-cyan-600"><?php echo number_format($total_rows); ?></span> baris log
            </div>
            <!-- limit selector -->
            <div class="flex items-center gap-2 self-end sm:self-auto">
                <span class="text-xs font-semibold text-slate-500">Tampilkan:</span>
                <select onchange="window.location.href=this.value" class="bg-white border border-slate-200 text-slate-700 py-1.5 px-3 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-cyan-500">
                    <?php foreach ([10, 20, 50, 100] as $lim): ?>
                        <option value="?<?php echo http_build_query(array_merge($_GET, ['limit' => $lim, 'page' => 1])); ?>" <?php echo $limit === $lim ? 'selected' : ''; ?>>
                            <?php echo $lim; ?> baris
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto w-full min-w-full">
            <table class="min-w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-slate-100">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                        <th class="px-6 py-4 w-12">No.</th>
                        <th class="px-6 py-4 text-left">Tanggal & IP</th>
                        <th class="px-6 py-4 text-left">Pengguna</th>
                        <th class="px-6 py-4 text-left">Role / Jabatan</th>
                        <th class="px-6 py-4">Modul & Aksi</th>
                        <th class="px-6 py-4 text-left min-w-[300px]">Deskripsi</th>
                        <th class="px-6 py-4">Level</th>
                        <th class="px-6 py-4 text-right">Opsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-slate-500 italic">
                                Belum ada log aktivitas tercatat yang sesuai dengan filter.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $index => $log): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors text-center font-normal">
                            <!-- No -->
                            <td class="px-6 py-4 text-slate-400 font-medium">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <!-- Created At & IP -->
                            <td class="px-6 py-4 text-left">
                                <div class="font-medium text-slate-900">
                                    <?php echo date('d M Y, H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                    IP: <?php echo htmlspecialchars($log['ip_address']); ?>
                                </div>
                            </td>
                            <!-- User -->
                            <td class="px-6 py-4 text-left">
                                <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                <div class="text-[10px] text-slate-400">ID: <?php echo $log['user_id'] ?? '-'; ?></div>
                            </td>
                            <!-- Role -->
                            <td class="px-6 py-4 text-left text-slate-600">
                                <?php echo htmlspecialchars($log['role']); ?>
                            </td>
                            <!-- Module & Action -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-cyan-50 text-cyan-700 border border-cyan-100">
                                        <?php echo htmlspecialchars($log['module']); ?>
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700 uppercase">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </div>
                            </td>
                            <!-- Description -->
                            <td class="px-6 py-4 text-left text-slate-600 truncate max-w-[300px]" title="<?php echo htmlspecialchars($log['description']); ?>">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </td>
                            <!-- Level -->
                            <td class="px-6 py-4">
                                <?php
                                $badge_class = 'bg-slate-50 text-slate-700 border-slate-200';
                                if ($log['level'] === 'INFO') $badge_class = 'bg-blue-50 text-blue-700 border-blue-100';
                                if ($log['level'] === 'WARNING') $badge_class = 'bg-yellow-50 text-yellow-700 border-yellow-100';
                                if ($log['level'] === 'ERROR' || $log['level'] === 'CRITICAL') $badge_class = 'bg-red-50 text-red-700 border-red-100';
                                if ($log['level'] === 'SECURITY') $badge_class = 'bg-purple-50 text-purple-700 border-purple-100';
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $badge_class; ?>">
                                    <?php echo $log['level']; ?>
                                </span>
                            </td>
                            <!-- Action buttons -->
                            <td class="px-6 py-4 text-right">
                                <button type="button" 
                                        onclick="showLogDetail(<?php echo htmlspecialchars(json_encode($log)); ?>)"
                                        class="inline-flex items-center px-2.5 py-1.5 bg-slate-50 text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-100 transition-colors text-xs font-semibold">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50/50">
                <div class="text-xs text-slate-500">
                    Menampilkan <span class="font-bold text-slate-900"><?php echo $offset + 1; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                </div>
                <div class="flex gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors">Sebelumnya</a>
                    <?php endif; ?>
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="rounded-lg px-3 py-1.5 text-xs font-bold transition-colors <?php echo $i === $page ? 'bg-cyan-600 text-white' : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors">Selanjutnya</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</div>

<!-- Log Detail Modal -->
<div id="logModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="logModalBackdrop" class="fixed inset-0 bg-slate-900/60 transition-opacity duration-300 opacity-0 backdrop-blur-sm" onclick="closeLogModal()"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="logModalPanel" class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-2xl">
            <!-- Modal Header -->
            <div class="bg-slate-50/50 border-b border-slate-100 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="text-base font-bold text-slate-900" id="modal-title">Rincian Log Aktivitas</h3>
                    <p class="text-xs text-slate-500 mt-0.5" id="log-time-title"></p>
                </div>
                <button type="button" onclick="closeLogModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <!-- Modal Body -->
            <div class="px-6 py-5 space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Pengguna</span>
                        <span class="text-sm font-bold text-slate-800" id="log-user">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Jabatan / Role</span>
                        <span class="text-sm font-medium text-slate-700" id="log-role">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Modul</span>
                        <span class="text-sm font-bold text-slate-800" id="log-module">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Aksi</span>
                        <span class="text-sm font-bold text-slate-800 uppercase" id="log-action">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Tabel Database</span>
                        <span class="text-sm font-mono text-cyan-600" id="log-table">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Record ID</span>
                        <span class="text-sm font-mono text-cyan-600" id="log-record-id">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">IP Address</span>
                        <span class="text-sm font-mono text-slate-700" id="log-ip">-</span>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase">Browser / Agent</span>
                        <span class="text-sm text-slate-700 truncate block" id="log-browser" title="">-</span>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Request URL</span>
                    <span class="text-xs font-mono bg-slate-50 text-slate-700 p-2 rounded-lg border border-slate-150 block break-all" id="log-url">-</span>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Deskripsi</span>
                    <p class="text-sm text-slate-700 bg-slate-50 p-3 rounded-lg border border-slate-150 font-medium" id="log-description">-</p>
                </div>

                <!-- JSON Data Diff Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Data Lama (Sebelum)</span>
                        <pre class="text-xs font-mono bg-slate-900 text-slate-200 p-3 rounded-lg overflow-x-auto max-h-48" id="log-old-data">{}</pre>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Data Baru (Sesudah)</span>
                        <pre class="text-xs font-mono bg-slate-900 text-slate-200 p-3 rounded-lg overflow-x-auto max-h-48" id="log-new-data">{}</pre>
                    </div>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="bg-slate-50 px-6 py-3.5 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="closeLogModal()" class="w-full sm:w-auto inline-flex justify-center rounded-lg bg-slate-800 hover:bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-all transform active:scale-95">
                    Tutup Detail
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function showLogDetail(log) {
        // Parse dates and basic fields
        document.getElementById('log-time-title').innerText = log.created_at;
        document.getElementById('log-user').innerText = log.user_name + ' (ID: ' + (log.user_id || '-') + ')';
        document.getElementById('log-role').innerText = log.role;
        document.getElementById('log-module').innerText = log.module;
        document.getElementById('log-action').innerText = log.action;
        document.getElementById('log-table').innerText = log.table_name || '-';
        document.getElementById('log-record-id').innerText = log.record_id || '-';
        document.getElementById('log-ip').innerText = log.ip_address || '-';
        document.getElementById('log-browser').innerText = log.browser || '-';
        document.getElementById('log-browser').title = log.browser || '';
        document.getElementById('log-url').innerText = log.url || '-';
        document.getElementById('log-description').innerText = log.description;

        // Try parsing JSON payloads nicely
        try {
            const oldData = log.old_data ? JSON.parse(log.old_data) : null;
            document.getElementById('log-old-data').innerText = oldData ? JSON.stringify(oldData, null, 2) : '{}';
        } catch (e) {
            document.getElementById('log-old-data').innerText = log.old_data || '{}';
        }

        try {
            const newData = log.new_data ? JSON.parse(log.new_data) : null;
            document.getElementById('log-new-data').innerText = newData ? JSON.stringify(newData, null, 2) : '{}';
        } catch (e) {
            document.getElementById('log-new-data').innerText = log.new_data || '{}';
        }

        // Open modal
        const modal = document.getElementById('logModal');
        const backdrop = document.getElementById('logModalBackdrop');
        const panel = document.getElementById('logModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeLogModal() {
        const modal = document.getElementById('logModal');
        const backdrop = document.getElementById('logModalBackdrop');
        const panel = document.getElementById('logModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeLogModal();
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>
