<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';

check_login();
if (!hasPermission($_SESSION['user_id'], 'manage_activities') && (!isset($_SESSION['position_name']) || $_SESSION['position_name'] !== 'Administrator')) {
    include '../layouts/no_access.php';
    exit;
}

$page_title = "Monitoring Aktivitas Santri";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination & Filters ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit = isset($_GET['unit']) ? trim($_GET['unit']) : '';
$class = isset($_GET['class']) ? trim($_GET['class']) : '';
$activity_type_id = isset($_GET['activity_type_id']) ? (int)$_GET['activity_type_id'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$where_clauses = ["a.deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE :search OR a.note LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($unit)) {
    $where_clauses[] = "s.tingkat = :unit";
    $params[':unit'] = $unit;
}
if (!empty($class)) {
    $where_clauses[] = "s.kelas = :class";
    $params[':class'] = $class;
}
if ($activity_type_id > 0) {
    $where_clauses[] = "a.activity_type_id = :activity_type_id";
    $params[':activity_type_id'] = $activity_type_id;
}
if (!empty($status)) {
    $where_clauses[] = "a.status = :status";
    $params[':status'] = $status;
}
if (!empty($start_date)) {
    $where_clauses[] = "a.activity_date >= :start_date";
    $params[':start_date'] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "a.activity_date <= :end_date";
    $params[':end_date'] = $end_date;
}

$where_sql = implode(" AND ", $where_clauses);

// Count total
$count_query = "SELECT COUNT(*) FROM student_activities a JOIN students s ON a.student_id = s.id WHERE $where_sql";
$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $total_stmt->bindValue($key, $val);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT a.*, 
           s.nama_siswa as student_name, s.kelas as student_class, s.tingkat as student_unit,
           t.name as activity_name, t.type as activity_type, t.icon, t.color, t.point,
           e.full_name as creator_name
    FROM student_activities a
    JOIN students s ON a.student_id = s.id
    JOIN activity_types t ON a.activity_type_id = t.id
    LEFT JOIN employees e ON a.created_by = e.id
    WHERE $where_sql 
    ORDER BY a.activity_date DESC, a.created_at DESC 
    LIMIT :limit OFFSET :offset
";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch filters options
$units = $conn->query("SELECT DISTINCT tingkat FROM students WHERE tingkat IS NOT NULL AND tingkat != '' ORDER BY tingkat ASC")->fetchAll(PDO::FETCH_COLUMN);
$classes = $conn->query("SELECT DISTINCT kelas FROM students WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC")->fetchAll(PDO::FETCH_COLUMN);
$activity_types = $conn->query("SELECT id, name FROM activity_types WHERE deleted_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<style>
/* Custom premium styles for form inputs, dropdowns, and textareas */
input[type="text"], input[type="date"], select, textarea {
    border-color: #cbd5e1 !important;
    border-width: 1px !important;
    border-radius: 0.75rem !important;
    padding: 0.625rem 0.875rem !important;
    font-size: 0.875rem !important;
    background-color: #f8fafc !important;
    color: #334155 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.02) !important;
}
input[type="text"]:hover, input[type="date"]:hover, select:hover, textarea:hover {
    border-color: #94a3b8 !important;
    background-color: #ffffff !important;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05) !important;
}
input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
    border-color: #6366f1 !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12) !important;
    transform: translateY(-1px);
    outline: none !important;
}
</style>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Monitoring Aktivitas Santri</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Pantau dan edit semua aktivitas pembiasaan amaliyah santri yang telah diinput.</p>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="mt-6 bg-white p-4 border border-slate-200 rounded-xl shadow-sm">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Cari Santri / Catatan</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari..."
                    class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Unit Pendidikan</label>
                <select name="unit" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $unit === $u ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Kelas</label>
                <select name="class" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $class === $c ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Aktivitas</label>
                <select name="activity_type_id" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="0">Semua Aktivitas</option>
                    <?php foreach ($activity_types as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $activity_type_id === (int)$t['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status</label>
                <select name="status" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="">Semua Status</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="not_completed" <?php echo $status === 'not_completed' ? 'selected' : ''; ?>>Not Completed</option>
                    <option value="excused" <?php echo $status === 'excused' ? 'selected' : ''; ?>>Excused</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Mulai Tanggal</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                    class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                    class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 px-4 rounded-lg text-sm transition-all">
                    Filter
                </button>
                <?php if (!empty($search) || !empty($unit) || !empty($class) || $activity_type_id > 0 || !empty($status) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="?" class="bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold py-2 px-3 rounded-lg text-sm transition-all" title="Reset Filter">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="mt-6 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden text-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-28">Tanggal</th>
                        <th class="px-6 py-4">Nama Santri</th>
                        <th class="px-6 py-4">Jenis Aktivitas</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Catatan</th>
                        <th class="px-6 py-4">Penginput</th>
                        <th class="px-6 py-4 text-center">Dokumen</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 italic-last-td">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-slate-400 font-medium">Belum ada data aktivitas santri yang tercatat.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($data as $item): 
                        // Fetch files count
                        $stmtFile = $conn->prepare("SELECT COUNT(*) FROM activity_files WHERE activity_id = ?");
                        $stmtFile->execute([$item['id']]);
                        $files_count = $stmtFile->fetchColumn();
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-700 whitespace-nowrap">
                                <?php echo date('d-m-Y', strtotime($item['activity_date'])); ?>
                                <?php if (!empty($item['start_time'])): ?>
                                    <div class="text-[10px] text-slate-400 font-mono mt-0.5"><?php echo substr($item['start_time'], 0, 5); ?> - <?php echo substr($item['end_time'] ?? 'Selesai', 0, 5); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($item['student_name']); ?></div>
                                <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($item['student_unit'] . ' - ' . $item['student_class']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background-color: <?php echo htmlspecialchars($item['color'] ?: '#cbd5e1'); ?>"></span>
                                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($item['activity_name']); ?></span>
                                </div>
                                <div class="text-[10px] uppercase font-bold text-slate-400 mt-0.5"><?php echo htmlspecialchars($item['activity_type']); ?> (<?php echo htmlspecialchars($item['point']); ?> Poin)</div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                $status_classes = [
                                    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                    'not_completed' => 'bg-rose-50 text-rose-700 border-rose-100',
                                    'excused' => 'bg-amber-50 text-amber-700 border-amber-100',
                                ];
                                $status_class = $status_classes[$item['status']] ?? 'bg-slate-50 text-slate-600';
                                ?>
                                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold uppercase border <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $item['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-500 italic max-w-xs truncate">
                                <?php echo htmlspecialchars($item['note'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-semibold text-slate-700"><?php echo htmlspecialchars($item['creator_name'] ?: 'System'); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($files_count > 0): ?>
                                    <button onclick="viewFiles(<?php echo $item['id']; ?>)" 
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 border border-indigo-100 hover:bg-indigo-100 transition-all">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                        </svg>
                                        <?php echo $files_count; ?>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openEditModal(<?php echo json_encode($item); ?>)'
                                        class="p-2 text-slate-300 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all" title="Edit">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $item['id']; ?>)"
                                        class="p-2 text-slate-300 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <p class="text-[12px] text-slate-500 font-medium">
                    Menampilkan <span class="text-slate-800 font-bold"><?php echo $offset + 1; ?></span> - <span class="text-slate-800 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="text-slate-800 font-bold"><?php echo $total_rows; ?></span> data
                </p>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit=<?php echo urlencode($unit); ?>&class=<?php echo urlencode($class); ?>&activity_type_id=<?php echo $activity_type_id; ?>&status=<?php echo urlencode($status); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_loop = max(1, $page - 2);
                    $end_loop = min($total_pages, $page + 2);
                    for ($i = $start_loop; $i <= $end_loop; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit=<?php echo urlencode($unit); ?>&class=<?php echo urlencode($class); ?>&activity_type_id=<?php echo $activity_type_id; ?>&status=<?php echo urlencode($status); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($i == $page) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit=<?php echo urlencode($unit); ?>&class=<?php echo urlencode($class); ?>&activity_type_id=<?php echo $activity_type_id; ?>&status=<?php echo urlencode($status); ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Edit -->
<div id="editModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="editActivityForm" onsubmit="saveEdit(event)">
                <input type="hidden" id="edit_id" name="id">
                
                <div class="bg-white px-6 pt-6 pb-4 sm:p-6 sm:pb-4 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800">Koreksi Aktivitas Santri</h3>
                </div>
                
                <div class="px-6 py-4 space-y-4 text-sm text-slate-700">
                    <div>
                        <label class="block font-bold text-slate-500">Santri</label>
                        <div id="edit_student_name" class="font-bold text-slate-800 text-base mt-0.5">Nama Santri</div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-500">Aktivitas</label>
                        <div id="edit_activity_name" class="font-bold text-slate-800 mt-0.5">Nama Aktivitas</div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Status <span class="text-rose-500">*</span></label>
                        <select id="edit_status" name="status" required
                            class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                            <option value="completed">Completed</option>
                            <option value="not_completed">Not Completed</option>
                            <option value="excused">Excused</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Catatan</label>
                        <textarea id="edit_note" name="note" rows="3" placeholder="Tambahkan catatan koreksi..."
                            class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200"></textarea>
                    </div>
                </div>

                <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-2">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none transition-all active:scale-95 sm:w-auto">
                        Simpan Perubahan
                    </button>
                    <button type="button" onclick="closeEditModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-200 shadow-sm px-4 py-2 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all sm:mt-0 sm:w-auto">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Lightbox Files -->
<div id="filesModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-800 bg-opacity-90 transition-opacity" onclick="closeFilesModal()"></div>
        
        <div class="relative bg-white rounded-2xl max-w-4xl w-full max-h-[85vh] overflow-y-auto shadow-2xl z-10 flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between flex-shrink-0">
                <h3 class="text-lg font-bold text-slate-800">Lampiran / Dokumentasi Aktivitas</h3>
                <button onclick="closeFilesModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-grow grid grid-cols-1 md:grid-cols-2 gap-4" id="attachments_container">
                <!-- Dynamic Attachments Load -->
            </div>
        </div>
    </div>
</div>

<script>
const editModal = document.getElementById('editModal');
const filesModal = document.getElementById('filesModal');

function openEditModal(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_student_name').innerText = data.student_name + ' (' + data.student_unit + ' - ' + data.student_class + ')';
    document.getElementById('edit_activity_name').innerText = data.activity_name;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_note').value = data.note || '';
    editModal.classList.remove('invisible');
}

function closeEditModal() {
    editModal.classList.add('invisible');
}

async function saveEdit(e) {
    e.preventDefault();
    const id = document.getElementById('edit_id').value;
    const formData = {
        status: document.getElementById('edit_status').value,
        note: document.getElementById('edit_note').value
    };

    try {
        const response = await fetch(`../../api/student-activities/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const result = await response.json();

        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: result.message,
                timer: 1500,
                showConfirmButton: false,
                borderRadius: '20px'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: result.message,
                borderRadius: '20px'
            });
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Sistem Error',
            text: 'Gagal menghubungi server.',
            borderRadius: '20px'
        });
    }
}

async function viewFiles(activityId) {
    const container = document.getElementById('attachments_container');
    container.innerHTML = `<div class="col-span-2 text-center text-slate-400 py-10 font-medium">Memuat lampiran...</div>`;
    filesModal.classList.remove('invisible');

    try {
        const response = await fetch(`../../api/student-activities/${activityId}`);
        const result = await response.json();
        
        if (result.success && result.data.attachments.length > 0) {
            container.innerHTML = '';
            result.data.attachments.forEach(file => {
                const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(file.file_type.toLowerCase());
                let html = '';
                
                if (isImage) {
                    html = `
                        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col bg-slate-50">
                            <div class="h-64 overflow-hidden relative group">
                                <img src="../../${file.file_path}" alt="${file.caption || 'Dokumentasi'}" class="w-full h-full object-cover">
                                <a href="../../${file.file_path}" target="_blank" class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <span class="bg-white/90 text-slate-800 font-bold px-3 py-1.5 rounded-lg text-xs">Buka Ukuran Penuh</span>
                                </a>
                            </div>
                            <div class="p-3 border-t border-slate-100 flex items-center justify-between flex-grow bg-white">
                                <div class="text-xs text-slate-600 font-semibold truncate pr-4">${file.caption || 'Tanpa keterangan'}</div>
                                <button onclick="deleteFile(${activityId}, ${file.id})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg transition-colors flex-shrink-0" title="Hapus Dokumentasi">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    `;
                } else {
                    html = `
                        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-sm flex flex-col bg-white p-4 justify-between h-64">
                            <div class="flex flex-col items-center justify-center flex-grow">
                                <svg class="w-16 h-16 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                <span class="text-sm font-bold text-slate-700 uppercase">${file.file_type} File</span>
                            </div>
                            <div class="p-3 border-t border-slate-100 flex items-center justify-between bg-white mt-4">
                                <div class="text-xs text-slate-600 font-semibold truncate pr-4 flex-grow">${file.caption || 'Tanpa keterangan'}</div>
                                <div class="flex items-center gap-1.5">
                                    <a href="../../${file.file_path}" target="_blank" class="text-indigo-600 hover:text-indigo-800 p-1.5 rounded-lg hover:bg-indigo-50 transition-colors" title="Download">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                        </svg>
                                    </a>
                                    <button onclick="deleteFile(${activityId}, ${file.id})" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-1.5 rounded-lg transition-colors flex-shrink-0" title="Hapus Dokumentasi">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }
                container.innerHTML += html;
            });
        } else {
            container.innerHTML = `<div class="col-span-2 text-center text-slate-400 py-10 font-medium">Belum ada lampiran.</div>`;
        }
    } catch (e) {
        container.innerHTML = `<div class="col-span-2 text-center text-rose-500 py-10 font-bold">Gagal memuat lampiran.</div>`;
    }
}

function closeFilesModal() {
    filesModal.classList.add('invisible');
}

async function deleteFile(activityId, fileId) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Lampiran ini akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        borderRadius: '20px'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(`../../api/student-activities/${activityId}/attachments/${fileId}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false,
                        borderRadius: '20px'
                    }).then(() => {
                        // Refresh the files container
                        viewFiles(activityId);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: result.message,
                        borderRadius: '20px'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sistem Error',
                    text: 'Gagal menghubungi server.',
                    borderRadius: '20px'
                });
            }
        }
    });
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#475569',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal',
        borderRadius: '20px'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(`../../api/student-activities/${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terhapus!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false,
                        borderRadius: '20px'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: result.message,
                        borderRadius: '20px'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sistem Error',
                    text: 'Gagal menghubungi server.',
                    borderRadius: '20px'
                });
            }
        }
    });
}
</script>

<?php include '../layouts/footer.php'; ?>
