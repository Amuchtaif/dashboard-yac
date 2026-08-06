<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Manajemen Penugasan";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// --- Filters ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(a.title LIKE :search OR e_assignee.full_name LIKE :search OR e_creator.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($status) {
    $where[] = "a.status = :status";
    $params[':status'] = $status;
}
if ($priority) {
    $where[] = "a.priority = :priority";
    $params[':priority'] = $priority;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count
$count_q = "SELECT COUNT(*) FROM assignments a
    LEFT JOIN employees e_creator ON a.created_by = e_creator.id
    LEFT JOIN employees e_assignee ON a.assigned_to = e_assignee.id
    $where_sql";
$count_stmt = $conn->prepare($count_q);
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_rows / $limit));

// Fetch
$query = "SELECT a.*,
    e_creator.full_name as creator_name, e_creator.profile_photo as creator_photo,
    p_creator.name as creator_position,
    e_assignee.full_name as assignee_name, e_assignee.profile_photo as assignee_photo,
    p_assignee.name as assignee_position
    FROM assignments a
    LEFT JOIN employees e_creator ON a.created_by = e_creator.id
    LEFT JOIN positions p_creator ON e_creator.position_id = p_creator.id
    LEFT JOIN employees e_assignee ON a.assigned_to = e_assignee.id
    LEFT JOIN positions p_assignee ON e_assignee.position_id = p_assignee.id
    $where_sql
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$stats_q = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='Belum Dimulai' THEN 1 ELSE 0 END) as belum,
    SUM(CASE WHEN status='Sedang Dikerjakan' THEN 1 ELSE 0 END) as berjalan,
    SUM(CASE WHEN status='Selesai' THEN 1 ELSE 0 END) as selesai,
    SUM(CASE WHEN status='Dibatalkan' THEN 1 ELSE 0 END) as batal
    FROM assignments";
$stats = $conn->query($stats_q)->fetch(PDO::FETCH_ASSOC);

// Employees for form
$employees = $conn->query("SELECT e.id, e.full_name, p.name as position_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.status='active' ORDER BY e.full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build filter query string
function buildFilterUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return '?' . http_build_query($params);
}

include '../layouts/header.php';
?>

<!-- Tom Select -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/tom-select/2.2.2/js/tom-select.complete.min.js"></script>

<div class="w-full pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Penugasan</h1>
            <p class="mt-1 text-sm text-slate-500">Kelola dan pantau semua tugas yang didelegasikan kepada pegawai.</p>
        </div>
        <button onclick="document.getElementById('create-modal').classList.remove('hidden')"
            class="mt-4 sm:mt-0 inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 transition-all">
            <i class="fa-solid fa-plus -ml-0.5 mr-2 h-4 w-4"></i>
            Buat Tugas Baru
        </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6">
        <?php
        $stat_items = [
            ['label' => 'Total Tugas', 'value' => $stats['total'], 'color' => 'slate', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['label' => 'Belum Dimulai', 'value' => $stats['belum'], 'color' => 'amber', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Sedang Dikerjakan', 'value' => $stats['berjalan'], 'color' => 'blue', 'icon' => 'M13 10V3L4 14h7v7l9-11h-7z'],
            ['label' => 'Selesai', 'value' => $stats['selesai'], 'color' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['label' => 'Dibatalkan', 'value' => $stats['batal'], 'color' => 'red', 'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ];
        foreach ($stat_items as $si): ?>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-<?php echo $si['color']; ?>-50">
                    <i class="fa-solid fa-list-check h-5 w-5 text-<?php echo $si['color']; ?>-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-800"><?php echo $si['value']; ?></p>
                    <p class="text-xs text-slate-500"><?php echo $si['label']; ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center mb-6">
        <div class="relative w-full sm:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 py-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400"
                placeholder="Cari judul tugas atau nama pegawai..." onchange="this.form.submit()">
        </div>
        <div class="flex gap-2 flex-wrap items-center">
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">
                <option value="">Status: Semua</option>
                <?php foreach (['Belum Dimulai','Sedang Dikerjakan','Selesai','Dibatalkan'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                <?php endforeach; ?>
            </select>
            <select name="priority" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600">
                <option value="">Prioritas: Semua</option>
                <?php foreach (['Tinggi','Rutin','Biasa'] as $p): ?>
                <option value="<?php echo $p; ?>" <?php echo $priority === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
            </select>
            <a href="index.php" class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition-colors" title="Reset">
                <i class="fa-solid fa-xmark h-4 w-4"></i>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-4 py-3.5 text-left w-12">No</th>
                        <th class="px-4 py-3.5 text-left min-w-[250px]">Tugas</th>
                        <th class="px-4 py-3.5 text-left min-w-[180px]">Pemberi</th>
                        <th class="px-4 py-3.5 text-left min-w-[180px]">Penerima</th>
                        <th class="px-4 py-3.5 text-left min-w-[120px]">Prioritas</th>
                        <th class="px-4 py-3.5 text-left min-w-[150px]">Status</th>
                        <th class="px-4 py-3.5 text-left min-w-[120px]">Deadline</th>
                        <th class="px-4 py-3.5 text-right min-w-[100px]">Aksi</th>
                    </tr>
                </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($tasks)): ?>
                <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-slate-500">Belum ada data penugasan.</td></tr>
                <?php endif; ?>
                <?php foreach ($tasks as $i => $t):
                    $priorityMap = ['Tinggi' => ['red','bg-red-50 text-red-700 ring-red-600/20'], 'Rutin' => ['amber','bg-amber-50 text-amber-700 ring-amber-600/20'], 'Biasa' => ['slate','bg-slate-50 text-slate-600 ring-slate-500/20']];
                    $statusMap = ['Belum Dimulai' => 'bg-amber-50 text-amber-700 ring-amber-600/20', 'Sedang Dikerjakan' => 'bg-blue-50 text-blue-700 ring-blue-600/20', 'Selesai' => 'bg-green-50 text-green-700 ring-green-600/20', 'Dibatalkan' => 'bg-red-50 text-red-700 ring-red-600/20'];
                    $pClass = $priorityMap[$t['priority']][1] ?? 'bg-slate-50 text-slate-600 ring-slate-500/20';
                    $sClass = $statusMap[$t['status']] ?? 'bg-slate-50 text-slate-600 ring-slate-500/20';
                    $creatorAvatar = "https://ui-avatars.com/api/?name=" . urlencode($t['creator_name'] ?? 'U') . "&background=random";
                    $assigneeAvatar = "https://ui-avatars.com/api/?name=" . urlencode($t['assignee_name'] ?? 'U') . "&background=random";
                    $isOverdue = $t['due_date'] && strtotime($t['due_date']) < time() && !in_array($t['status'], ['Selesai','Dibatalkan']);
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3 text-sm text-slate-500"><?php echo $offset + $i + 1; ?></td>
                    <td class="px-4 py-3 max-w-[250px]">
                        <div class="text-sm font-semibold text-slate-800 truncate" title="<?php echo htmlspecialchars($t['title']); ?>"><?php echo htmlspecialchars($t['title']); ?></div>
                        <div class="text-xs text-slate-400 truncate mt-0.5" title="<?php echo htmlspecialchars($t['description'] ?? ''); ?>"><?php echo htmlspecialchars(mb_strimwidth($t['description'] ?? '-', 0, 60, '...')); ?></div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <img class="h-7 w-7 rounded-full border border-slate-200" src="<?php echo $creatorAvatar; ?>" alt="">
                            <div>
                                <div class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($t['creator_name'] ?? '-'); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($t['creator_position'] ?? ''); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <img class="h-7 w-7 rounded-full border border-slate-200" src="<?php echo $assigneeAvatar; ?>" alt="">
                            <div>
                                <div class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($t['assignee_name'] ?? '-'); ?></div>
                                <div class="text-[10px] text-slate-400"><?php echo htmlspecialchars($t['assignee_position'] ?? ''); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $pClass; ?>"><?php echo htmlspecialchars($t['priority']); ?></span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset <?php echo $sClass; ?>"><?php echo htmlspecialchars($t['status']); ?></span>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($t['due_date']): ?>
                        <span class="text-sm <?php echo $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-600'; ?>">
                            <?php echo date('d M Y', strtotime($t['due_date'])); ?>
                        </span>
                        <?php if ($isOverdue): ?>
                        <span class="block text-[10px] text-red-500 font-medium">Terlambat</span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-sm text-slate-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="viewDetail(<?php echo $t['id']; ?>)" class="p-1.5 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-lg transition-all" title="Detail">
                                <i class="fa-solid fa-eye w-5 h-5"></i>
                            </button>
                            <button onclick="confirmDelete(<?php echo $t['id']; ?>)" class="p-1.5 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="Hapus">
                                <i class="fa-solid fa-trash w-5 h-5"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($total_pages > 1): ?>
    <div class="mt-4 flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-xl border border-slate-200 shadow-sm">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <select onchange="window.location.href='?page=1&limit='+this.value+'&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>'"
                    class="block rounded-lg border-slate-200 py-1.5 pl-3 pr-8 text-slate-900 ring-1 ring-inset ring-slate-100 focus:ring-2 focus:ring-cyan-600 sm:text-xs">
                    <?php foreach ([10,20,50,100] as $v): ?>
                    <option value="<?php echo $v; ?>" <?php echo $limit==$v?'selected':''; ?>>Tampilkan <?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-sm text-slate-500">
                    Menampilkan <span class="font-bold text-slate-900"><?php echo min($offset+1, $total_rows); ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset+$limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                </p>
            </div>
            <nav class="isolate inline-flex -space-x-px rounded-xl border border-slate-200 overflow-hidden shadow-sm" aria-label="Pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&limit=<?php echo $limit; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>" class="relative inline-flex items-center px-4 py-2 text-slate-400 hover:bg-slate-50 transition-colors"><i class="fa-solid fa-chevron-left h-5 w-5"></i></a>
                <?php endif; ?>
                
                <?php for ($pg = max(1,$page-2); $pg <= min($total_pages,$page+2); $pg++): ?>
                <a href="?page=<?php echo $pg; ?>&limit=<?php echo $limit; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo $pg==$page ? 'bg-cyan-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors"><?php echo $pg; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&limit=<?php echo $limit; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>" class="relative inline-flex items-center px-4 py-2 text-slate-400 hover:bg-slate-50 transition-colors"><i class="fa-solid fa-chevron-right h-5 w-5"></i></a>
                <?php endif; ?>
            </nav>
        </div>
        
        <!-- Mobile Pagination Controls -->
        <div class="flex flex-1 items-center justify-between sm:hidden w-full">
            <a href="?page=<?php echo max(1, $page-1); ?>&limit=<?php echo $limit; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>" class="relative inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors <?php echo $page <= 1 ? 'pointer-events-none opacity-50' : ''; ?>">Previous</a>
            <a href="?page=<?php echo min($total_pages, $page+1); ?>&limit=<?php echo $limit; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page'=>'','limit'=>''])); ?>" class="relative ml-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors <?php echo $page >= $total_pages ? 'pointer-events-none opacity-50' : ''; ?>">Next</a>
        </div>
    </div>
<?php endif; ?>
    </div>
</div>

<!-- Create Task Modal -->
<div id="create-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" onclick="document.getElementById('create-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg p-6 z-10">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-slate-900">Buat Tugas Baru</h3>
                <button onclick="document.getElementById('create-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark w-5 h-5"></i></button>
            </div>
            <form action="../../logic/task_assignments/store.php" method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Judul Tugas *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none shadow-sm" placeholder="Masukkan judul tugas">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi</label>
                    <textarea name="description" rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none shadow-sm" placeholder="Deskripsi tugas..."></textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Instruksi Khusus</label>
                    <textarea name="special_instructions" rows="2" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none shadow-sm" placeholder="Instruksi tambahan..."></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Penerima Tugas *</label>
                        <select name="assigned_to" id="modal-assigned-to" required class="tom-select-modal w-full">
                            <option value="">Pilih pegawai...</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Prioritas</label>
                        <select name="priority" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none shadow-sm">
                            <option value="Rutin">Rutin</option>
                            <option value="Tinggi">Tinggi</option>
                            <option value="Biasa">Biasa</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Deadline</label>
                    <input type="date" name="due_date" class="w-full px-4 py-2.5 rounded-lg border border-slate-300 text-sm focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 focus:outline-none shadow-sm">
                </div>
                <input type="hidden" name="created_by" value="<?php echo $_SESSION['user_id']; ?>">
                <button type="submit" class="w-full px-6 py-3 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-lg shadow-sm hover:shadow-md transition-all">Buat Tugas</button>
            </form>
        </div>
    </div>
</div>

<!-- Detail Modal -->
<div id="detail-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <!-- Backdrop with Fade Animation -->
        <div id="detail-backdrop" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity duration-300 opacity-0" onclick="hideDetail()"></div>
        
        <!-- Modal Content with Zoom & Slide Animation -->
        <div id="detail-panel" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden z-10 transform transition-all duration-300 scale-95 opacity-0 translate-y-4">
            <!-- Modal Header -->
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-cyan-100 rounded-lg">
                        <i class="fa-solid fa-clipboard-list w-5 h-5 text-cyan-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 line-clamp-1" id="detail-title-display">Detail Tugas</h3>
                        <p class="text-xs text-slate-500 font-medium tracking-wide">RINCIAN PENUGASAN PEGAWAI</p>
                    </div>
                </div>
                <button onclick="hideDetail()" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-full transition-all">
                    <i class="fa-solid fa-xmark w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-0 max-h-[75vh] overflow-y-auto scrollbar-hide">
                <div id="detail-content-area">
                    <!-- Dynamic Content -->
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button onclick="hideDetail()" class="px-6 py-2 bg-white border border-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-slate-100">
                    Tutup Rincian
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new TomSelect('#modal-assigned-to', { create: false, sortField: { field: 'text', direction: 'asc' } });
});

const tasks = <?php echo json_encode($tasks); ?>;

function viewDetail(id) {
    const t = tasks.find(x => x.id == id);
    if (!t) return;
    
    const priorityMap = {
        'Tinggi': 'bg-red-50 text-red-700 border-red-200',
        'Rutin': 'bg-amber-50 text-amber-700 border-amber-200',
        'Biasa': 'bg-slate-50 text-slate-600 border-slate-200'
    };
    
    const statusMap = {
        'Belum Dimulai': 'bg-amber-100 text-amber-800',
        'Sedang Dikerjakan': 'bg-blue-100 text-blue-800',
        'Selesai': 'bg-green-100 text-green-800',
        'Dibatalkan': 'bg-red-100 text-red-800'
    };

    const creatorAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(t.creator_name || 'U')}&background=random&size=128`;
    const assigneeAvatar = `https://ui-avatars.com/api/?name=${encodeURIComponent(t.assignee_name || 'U')}&background=random&size=128`;

    document.getElementById('detail-title-display').textContent = t.title;
    document.getElementById('detail-content-area').innerHTML = `
        <div class="p-6 space-y-6">
            <!-- Header Grid: Status & Priority -->
            <div class="grid grid-cols-2 gap-4">
                <div class="p-3 bg-white border border-slate-100 rounded-2xl shadow-sm flex flex-col justify-center items-center text-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Status Tugas</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold ${statusMap[t.status] || ''}">
                        <div class="w-1.5 h-1.5 rounded-full bg-current mr-2 animate-pulse"></div>
                        ${t.status.toUpperCase()}
                    </span>
                </div>
                <div class="p-3 bg-white border border-slate-100 rounded-2xl shadow-sm flex flex-col justify-center items-center text-center">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Urgensi</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold border ${priorityMap[t.priority] || ''}">
                        ${t.priority.toUpperCase()}
                    </span>
                </div>
            </div>

            <!-- Description Block -->
            <div class="group">
                <div class="flex items-center gap-2 mb-2 text-slate-400">
                    <i class="fa-solid fa-list w-3.5 h-3.5"></i>
                    <h4 class="text-[10px] font-bold uppercase tracking-widest">Deskripsi Penugasan</h4>
                </div>
                <div class="bg-slate-50/50 rounded-2xl p-4 border border-slate-100/80 text-sm text-slate-700 leading-relaxed min-h-[80px]">
                    ${t.description ? `<p class="font-medium text-slate-800">${t.description}</p>` : '<span class="italic text-slate-300">N/A</span>'}
                    
                    ${t.special_instructions ? `
                    <div class="mt-3 pt-3 border-t border-slate-200/60 leading-tight">
                        <div class="flex gap-2 text-cyan-700/80 text-[12px] italic">
                            <span class="font-bold text-cyan-600 not-italic">NOTE:</span>
                            <span>${t.special_instructions}</span>
                        </div>
                    </div>` : ''}
                </div>
            </div>

            <!-- Personnel & Timeline Row -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Personnel -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-slate-400">
                        <i class="fa-solid fa-users w-3.5 h-3.5"></i>
                        <h4 class="text-[10px] font-bold uppercase tracking-widest">Delegasi</h4>
                    </div>
                    <div class="space-y-3 pl-1">
                        <!-- Creator -->
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <img src="${t.creator_photo ? '../../public/uploads/employees/' + t.creator_photo : creatorAvatar}" class="h-8 w-8 rounded-full ring-2 ring-slate-100 bg-white">
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-medium text-slate-400 leading-none mb-0.5">Dari</p>
                                <p class="text-[12px] font-bold text-slate-800 truncate">${t.creator_name || '-'}</p>
                            </div>
                        </div>
                        <!-- Assignee -->
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <img src="${t.assignee_photo ? '../../public/uploads/employees/' + t.assignee_photo : assigneeAvatar}" class="h-8 w-8 rounded-full ring-2 ring-cyan-100 bg-white">
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-medium text-cyan-400 leading-none mb-0.5">Kepada</p>
                                <p class="text-[12px] font-bold text-slate-800 truncate">${t.assignee_name || '-'}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-slate-400">
                        <i class="fa-solid fa-calendar-days w-3.5 h-3.5"></i>
                        <h4 class="text-[10px] font-bold uppercase tracking-widest">Waktu</h4>
                    </div>
                    <div class="space-y-3 pl-1">
                        <div>
                            <p class="text-[10px] font-medium text-slate-400 leading-none mb-1">Dibuat</p>
                            <p class="text-[12px] font-bold text-slate-700">${new Date(t.created_at).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-medium text-red-400 leading-none mb-1">Deadline</p>
                            <p class="text-[12px] font-bold text-slate-900">${t.due_date ? new Date(t.due_date).toLocaleDateString('id-ID', {day:'2-digit', month:'short', year:'numeric'}) : '-'}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pre-Check Attachment (Teacher Side) if exists -->
            ${t.attachment || t.attachment_path ? `
            <div class="space-y-2 border-t border-slate-100 pt-4">
                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Lampiran Tugas:</h4>
                ${getFilePreviewElement(t.attachment || t.attachment_path)}
            </div>` : ''}

            <!-- Report results (Employee Side) -->
            ${t.report_notes || t.report_attachment ? `
            <div class="pt-2">
                <div class="p-4 bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl border border-green-100/50 shadow-sm shadow-green-500/5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 bg-green-200/50 rounded-lg text-green-700">
                                <i class="fa-solid fa-certificate h-3.5 w-3.5"></i>
                            </div>
                            <h4 class="text-[10px] font-bold text-green-800 uppercase tracking-widest">Laporan Selesai</h4>
                        </div>
                    </div>
                    ${t.report_notes ? `<p class="text-[12px] text-green-900 font-medium leading-normal italic mb-4">"${t.report_notes}"</p>` : ''}
                    ${t.report_attachment ? `
                    <div class="space-y-2">
                        <p class="text-[9px] font-bold text-green-700/60 uppercase tracking-widest pl-1">File Hasil:</p>
                        ${getFilePreviewElement(t.report_attachment)}
                    </div>` : ''}
                </div>
            </div>` : ''}
        </div>`;

    showDetail();
}

function getFilePreviewElement(filename) {
    if (!filename) return '';
    const ext = filename.split('.').pop().toLowerCase();
    const filePath = `../../uploads/assignments/${filename}`;
    
    // Images
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
        return `
            <div class="relative group cursor-pointer overflow-hidden rounded-xl border border-slate-200 shadow-sm" onclick="window.open('${filePath}', '_blank')">
                <img src="${filePath}" class="w-full h-auto max-h-48 object-cover transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 bg-slate-900/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <div class="bg-white/90 p-2 rounded-full shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-transform">
                        <i class="fa-solid fa-magnifying-glass h-5 w-5 text-slate-700"></i>
                    </div>
                </div>
            </div>`;
    }
    
    // PDF
    if (ext === 'pdf') {
        return `
            <a href="${filePath}" target="_blank" class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:bg-slate-50 transition-all shadow-sm group">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-red-50 rounded-lg text-red-600 transition-transform">
                        <i class="fa-solid fa-file-arrow-down h-4 w-4"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold text-slate-700 truncate">${filename}</p>
                        <p class="text-[9px] text-slate-400">Pusat Dokumen PDF</p>
                    </div>
                </div>
                <i class="fa-solid fa-up-right-from-square h-3.5 w-3.5 text-slate-400"></i>
            </a>`;
    }
    
    // Others (ZIP, doc, etc)
    return `
        <a href="${filePath}" target="_blank" class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:bg-slate-50 transition-all shadow-sm group">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 rounded-lg text-blue-600 transition-transform">
                    <i class="fa-solid fa-download h-4 w-4"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-700 truncate">${filename}</p>
                    <p class="text-[9px] text-slate-400">${ext.toUpperCase()} File Archive</p>
                </div>
            </div>
            <i class="fa-solid fa-download h-3.5 w-3.5 text-slate-400"></i>
        </a>`;
}

function showDetail() {
    const modal = document.getElementById('detail-modal');
    const backdrop = document.getElementById('detail-backdrop');
    const panel = document.getElementById('detail-panel');
    
    modal.classList.remove('hidden');
    // Force reflow for transitions
    modal.offsetHeight;
    
    backdrop.classList.remove('opacity-0');
    backdrop.classList.add('opacity-100');
    
    panel.classList.remove('scale-95', 'opacity-0', 'translate-y-4');
    panel.classList.add('scale-100', 'opacity-100', 'translate-y-0');
}

function hideDetail() {
    const backdrop = document.getElementById('detail-backdrop');
    const panel = document.getElementById('detail-panel');
    
    backdrop.classList.replace('opacity-100', 'opacity-0');
    panel.classList.replace('scale-100', 'opacity-100', 'translate-y-0', 'scale-95');
    panel.classList.add('opacity-0', 'translate-y-4');
    
    setTimeout(() => {
        document.getElementById('detail-modal').classList.add('hidden');
    }, 300);
}


function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Tugas?',
        text: 'Data penugasan akan dihapus secara permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            window.location.href = '../../logic/task_assignments/delete.php?id=' + id;
        }
    });
}
</script>

<?php include '../layouts/footer.php'; ?>
