<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

// Authorization Check
$position = $_SESSION['position_name'] ?? '';
$allowed_roles = ['Administrator', 'Developer', 'Super Admin'];
if (!in_array($position, $allowed_roles)) {
    header("Location: " . BASE_URL . "/views/dashboard/index");
    exit;
}

$page_title = "Log Sistem";

// Test DB Connection
$db_offline = false;
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    $db_offline = true;
}

// Log directories
$log_base_dir = __DIR__ . '/../../storage/logs';
$categories = ['activity', 'auth', 'error', 'security', 'api', 'scheduler', 'backup', 'system'];

$selected_category = isset($_GET['category']) && in_array($_GET['category'], $categories) ? $_GET['category'] : 'system';
$category_path = $log_base_dir . '/' . $selected_category;

// Create dirs if not exist
if (!file_exists($category_path)) {
    mkdir($category_path, 0755, true);
}

// List log files in selected category
$log_files = [];
if (is_dir($category_path)) {
    $files = scandir($category_path);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'archive') continue;
        $file_path = $category_path . '/' . $file;
        if (is_file($file_path)) {
            $log_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'mtime' => filemtime($file_path)
            ];
        }
    }
}

// Sort files by mtime DESC (newest files first)
usort($log_files, function($a, $b) {
    return $b['mtime'] - $a['mtime'];
});

$selected_file = isset($_GET['file']) ? $_GET['file'] : '';
// Validate selected file to prevent directory traversal
if ($selected_file && (strpos($selected_file, '..') !== false || !file_exists($category_path . '/' . $selected_file))) {
    $selected_file = '';
}

// Default to newest file if none selected
if (!$selected_file && !empty($log_files)) {
    $selected_file = $log_files[0]['name'];
}

$logs = [];
$total_lines = 0;
$total_pages = 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if (!in_array($limit, [20, 50, 100, 200])) $limit = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

if ($selected_file) {
    $full_file_path = $category_path . '/' . $selected_file;
    if (file_exists($full_file_path)) {
        // Read file lines
        $file_lines = file($full_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $total_lines = count($file_lines);
        $total_pages = ceil($total_lines / $limit);

        // Fetch lines for current page (reading from bottom/newest)
        $start_index = max(0, $total_lines - ($page * $limit));
        $slice_limit = $limit;
        if ($page * $limit > $total_lines) {
            $slice_limit = $total_lines - (($page - 1) * $limit);
        }

        $page_lines = array_slice($file_lines, $start_index, $slice_limit);
        $page_lines = array_reverse($page_lines); // newest first

        foreach ($page_lines as $line) {
            $parsed = json_decode($line, true);
            if ($parsed) {
                $logs[] = $parsed;
            }
        }
    }
}

// Formatter size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">

    <!-- Offline Alert -->
    <?php if ($db_offline): ?>
        <div class="mb-6 rounded-xl bg-orange-600 shadow-2xl px-5 py-4 border border-orange-500/30 flex items-center justify-between animate-bounce-in text-white">
            <div class="flex items-center gap-4">
                <div class="flex-shrink-0 bg-white/20 p-2 rounded-xl flex items-center justify-center">
                    <svg class="h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div>
                    <p class="text-[11px] font-black text-orange-100 uppercase tracking-widest leading-none mb-1">Database Offline</p>
                    <p class="text-sm font-bold">Koneksi database terputus. Log Viewer berjalan dalam mode File-Only secara offline.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 pt-6 gap-4">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-cyan-600">Log Sistem (File)</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Log Sistem (File Viewer)</h1>
            <p class="mt-1 text-slate-500">Membaca file log langsung dari penyimpanan server secara real-time.</p>
        </div>
    </div>

    <!-- Category Tabs -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden w-full max-w-full mb-6">
        <div class="border-b border-slate-200 px-6 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                <?php foreach ($categories as $cat): ?>
                    <?php $isActive = ($selected_category === $cat); ?>
                    <a href="?category=<?php echo $cat; ?>"
                        class="<?php echo $isActive ? 'border-cyan-500 text-cyan-600 font-bold' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center capitalize">
                        <?php echo $cat; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <div class="p-6 bg-slate-50/50 flex flex-col md:flex-row gap-4 items-center justify-between">
            <form method="GET" action="" class="flex flex-wrap gap-4 items-center w-full md:w-auto">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($selected_category); ?>">

                <!-- Log File Dropdown -->
                <div class="relative w-full sm:w-64">
                    <select name="file" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-slate-300 text-slate-700 py-2 pl-4 pr-10 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        <?php if (empty($log_files)): ?>
                            <option value="">Tidak ada file log</option>
                        <?php endif; ?>
                        <?php foreach ($log_files as $lf): ?>
                            <option value="<?php echo htmlspecialchars($lf['name']); ?>" <?php echo $selected_file === $lf['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lf['name']); ?> (<?php echo formatBytes($lf['size']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>

                <!-- limit selector -->
                <div class="relative w-full sm:w-32">
                    <select name="limit" onchange="this.form.submit()" class="appearance-none w-full bg-white border border-slate-300 text-slate-700 py-2 pl-4 pr-10 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        <?php foreach ([20, 50, 100, 200] as $lim): ?>
                            <option value="<?php echo $lim; ?>" <?php echo $limit === $lim ? 'selected' : ''; ?>>
                                <?php echo $lim; ?> baris
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </div>
            </form>

            <div class="text-sm font-medium text-slate-600">
                Total data: <span class="font-bold text-cyan-600"><?php echo number_format($total_lines); ?></span> baris log
            </div>
        </div>
    </div>

    <!-- Log Table Area -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden w-full max-w-full">
        <!-- Table -->
        <div class="overflow-x-auto w-full min-w-full">
            <table class="min-w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-slate-100">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                        <th class="px-6 py-4 w-12">No.</th>
                        <th class="px-6 py-4 text-left">Waktu & IP</th>
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
                                Tidak ada log di file ini.
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
                                    <?php echo htmlspecialchars($log['datetime'] ?? '-'); ?>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                    IP: <?php echo htmlspecialchars($log['ip'] ?? '-'); ?>
                                </div>
                            </td>
                            <!-- User -->
                            <td class="px-6 py-4 text-left">
                                <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($log['user'] ?? '-'); ?></div>
                                <div class="text-[10px] text-slate-400">ID: <?php echo $log['user_id'] ?? '-'; ?></div>
                            </td>
                            <!-- Role -->
                            <td class="px-6 py-4 text-left text-slate-600">
                                <?php echo htmlspecialchars($log['role'] ?? '-'); ?>
                            </td>
                            <!-- Module & Action -->
                            <td class="px-6 py-4">
                                <div class="flex flex-col items-center gap-1">
                                    <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-cyan-50 text-cyan-700 border border-cyan-100">
                                        <?php echo htmlspecialchars($log['module'] ?? '-'); ?>
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-100 text-slate-700 uppercase">
                                        <?php echo htmlspecialchars($log['action'] ?? '-'); ?>
                                    </span>
                                </div>
                            </td>
                            <!-- Description -->
                            <td class="px-6 py-4 text-left text-slate-600 truncate max-w-[300px]" title="<?php echo htmlspecialchars($log['description'] ?? ''); ?>">
                                <?php echo htmlspecialchars($log['description'] ?? ''); ?>
                            </td>
                            <!-- Level -->
                            <td class="px-6 py-4">
                                <?php
                                $log_level = $log['level'] ?? 'INFO';
                                $badge_class = 'bg-slate-50 text-slate-700 border-slate-200';
                                if ($log_level === 'INFO') $badge_class = 'bg-blue-50 text-blue-700 border-blue-100';
                                if ($log_level === 'WARNING') $badge_class = 'bg-yellow-50 text-yellow-700 border-yellow-100';
                                if ($log_level === 'ERROR' || $log_level === 'CRITICAL') $badge_class = 'bg-red-50 text-red-700 border-red-100';
                                if ($log_level === 'SECURITY') $badge_class = 'bg-purple-50 text-purple-700 border-purple-100';
                                ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?php echo $badge_class; ?>">
                                    <?php echo $log_level; ?>
                                </span>
                            </td>
                            <!-- Action button -->
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
                    <h3 class="text-base font-bold text-slate-900" id="modal-title">Rincian File Log Sistem</h3>
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
                        <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Data Lama</span>
                        <pre class="text-xs font-mono bg-slate-900 text-slate-200 p-3 rounded-lg overflow-x-auto max-h-48" id="log-old-data">{}</pre>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-400 uppercase mb-1">Data Baru</span>
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
        document.getElementById('log-time-title').innerText = log.datetime || '-';
        document.getElementById('log-user').innerText = (log.user || '-') + ' (ID: ' + (log.user_id || '-') + ')';
        document.getElementById('log-role').innerText = log.role || '-';
        document.getElementById('log-module').innerText = log.module || '-';
        document.getElementById('log-action').innerText = log.action || '-';
        document.getElementById('log-table').innerText = log.table || '-';
        document.getElementById('log-record-id').innerText = log.record_id || '-';
        document.getElementById('log-ip').innerText = log.ip || '-';
        document.getElementById('log-browser').innerText = log.browser || '-';
        document.getElementById('log-browser').title = log.browser || '';
        document.getElementById('log-url').innerText = log.url || '-';
        document.getElementById('log-description').innerText = log.description || '-';

        // Parse diff data
        document.getElementById('log-old-data').innerText = log.old_data ? JSON.stringify(log.old_data, null, 2) : '{}';
        document.getElementById('log-new-data').innerText = log.new_data ? JSON.stringify(log.new_data, null, 2) : '{}';

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
