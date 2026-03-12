<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Data Penilaian Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Filter Logic ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_id = isset($_GET['type_id']) ? $_GET['type_id'] : '';
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';

$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(s.nama_siswa LIKE :search OR e.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($type_id) {
    $where_clauses[] = "a.assessment_type_id = :type_id";
    $params[':type_id'] = $type_id;
}

if ($date_start) {
    $where_clauses[] = "a.assessment_date >= :date_start";
    $params[':date_start'] = $date_start;
}

if ($date_end) {
    $where_clauses[] = "a.assessment_date <= :date_end";
    $params[':date_end'] = $date_end;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Total rows
$count_query = "
    SELECT COUNT(*) 
    FROM tahfidz_assessments a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN employees e ON a.teacher_id = e.id
    $where_sql
";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "
    SELECT 
        a.*, 
        s.nama_siswa as student_name, 
        s.nomor_induk as nis,
        s.kelas,
        e.full_name as teacher_name,
        t.name as type_name
    FROM tahfidz_assessments a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN employees e ON a.teacher_id = e.id
    LEFT JOIN tahfidz_assessment_types t ON a.assessment_type_id = t.id
    $where_sql
    ORDER BY a.assessment_date DESC, a.created_at DESC 
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

// Fetch types for filter
$assessment_types = $conn->query("SELECT id, name FROM tahfidz_assessment_types WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch students for "Add New" form (limited for performance, better use searchable dropdown)
$students = $conn->query("SELECT id, nama_siswa, nomor_induk as nis, kelas FROM students ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);
$teachers = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Data Penilaian Tahfidz</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Rekapitulasi nilai hafalan santri (Ziyadah & Murojaah).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-700 transition-all active:scale-95 group">
                <svg class="-ml-1 mr-2 h-4 w-4 transform group-hover:rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Input Nilai Baru
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="mt-8 bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <form id="filterForm" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Cari Santri / Guru</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        class="block w-full rounded-lg border-slate-200 bg-slate-50 pl-11 pr-4 py-2 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all"
                        placeholder="Nama atau NIS...">
                </div>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Jenis Penilaian</label>
                <select name="type_id" class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all appearance-none cursor-pointer">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($assessment_types as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php echo $type_id == $type['id'] ? 'selected' : ''; ?>>
                            <?php echo $type['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Dari Tanggal</label>
                <input type="date" name="date_start" value="<?php echo $date_start; ?>"
                    class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all">
            </div>

            <div class="flex items-end gap-3">
                <div class="flex-1 space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Sampai Tanggal</label>
                    <input type="date" name="date_end" value="<?php echo $date_end; ?>"
                        class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all">
                </div>
                <button type="submit" class="p-2.5 rounded-lg bg-slate-800 text-white hover:bg-slate-900 shadow-sm transition-all active:scale-95">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg bg-white">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12 text-center">No</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Santri</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Info Kelas</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tanggal</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jenis</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">T / F / M</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Total</th>
                        <th class="px-3 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pr-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="8" class="py-20 text-center text-sm text-slate-500">Belum ada data penilaian Tahfidz.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($data as $index => $item): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-400 font-medium sm:pl-6 text-center">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-gray-900">
                                <?php echo htmlspecialchars($item['student_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <span class="font-medium text-gray-700"><?php echo $item['nis']; ?></span>
                                <span class="mx-1 text-gray-300">•</span>
                                <?php echo $item['kelas']; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600">
                                <?php echo date('d/m/Y', strtotime($item['assessment_date'])); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-cyan-50 text-cyan-700 border border-cyan-100">
                                    <?php echo htmlspecialchars($item['type_name'] ?: ($item['category'] ?: 'Tahfidz')); ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                <div class="flex justify-center gap-2 font-mono text-gray-600">
                                    <span title="Tajweed"><?php echo $item['tajweed_score']; ?></span>
                                    <span class="text-gray-200">|</span>
                                    <span title="Fluency"><?php echo $item['fluency_score']; ?></span>
                                    <span class="text-gray-200">|</span>
                                    <span title="Makhraj"><?php echo $item['makhraj_score']; ?></span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                <span class="font-bold text-gray-900"><?php echo $item['total_score']; ?></span>
                            </td>
                            <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-3 text-gray-400">
                                    <button onclick='openFormModal(<?php echo json_encode($item); ?>)' class="hover:text-cyan-600 transition-colors" title="Edit">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $item['id']; ?>)" class="hover:text-rose-600 transition-colors" title="Hapus">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
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
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between sm:rounded-b-lg">
                <p class="text-xs text-gray-500 font-medium italic">
                    Menampilkan <?php echo $offset + 1; ?> s/d <?php echo min($offset + $limit, $total_rows); ?> dari <?php echo $total_rows; ?> data
                </p>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-2 py-1 rounded border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="px-3 py-1 rounded text-xs font-semibold transition-colors <?php echo ($i == $page) ? 'bg-cyan-600 text-white border border-cyan-600' : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-2 py-1 rounded border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form -->
<div id="formModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeFormModal()"></div>
        
        <div id="modalContent" class="relative bg-white rounded-2xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-2xl border border-slate-100 overflow-hidden">
            <form id="assessmentForm" onsubmit="submitForm(event)">
                <input type="hidden" name="id" id="form-id">
                
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800 tracking-tight" id="modal-title">Input Nilai Tahfidz</h3>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">Laporan Santri Tahfidz Quran</p>
                    </div>
                    <button type="button" onclick="closeFormModal()" class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 shadow-sm transition-all">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-8 py-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Santri & Date -->
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nama Santri</label>
                            <select name="student_id" id="form-student_id" required class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none">
                                <option value="">Pilih Santri...</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_siswa']); ?> (<?php echo $s['nis']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Tanggal & Jenis</label>
                            <div class="flex gap-3">
                                <input type="date" name="assessment_date" id="form-assessment_date" value="<?php echo date('Y-m-d'); ?>" required class="flex-1 rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none">
                                <select name="assessment_type_id" id="form-assessment_type_id" required class="flex-1 rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none">
                                    <option value="">Jenis...</option>
                                    <?php foreach ($assessment_types as $t): ?>
                                        <option value="<?php echo $t['id']; ?>"><?php echo $t['name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Guru Pengampu</label>
                            <select name="teacher_id" id="form-teacher_id" required class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none">
                                <option value="">Pilih Guru...</option>
                                <?php foreach ($teachers as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Scores & Comments -->
                    <div class="space-y-6">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-2">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Tajweed</label>
                                <input type="number" name="tajweed_score" id="form-tajweed_score" value="0" min="0" max="100" class="block w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2.5 text-center text-sm font-bold text-slate-800 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none score-input">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Fluency</label>
                                <input type="number" name="fluency_score" id="form-fluency_score" value="0" min="0" max="100" class="block w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2.5 text-center text-sm font-bold text-slate-800 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none score-input">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Makhraj</label>
                                <input type="number" name="makhraj_score" id="form-makhraj_score" value="0" min="0" max="100" class="block w-full rounded-lg border-slate-200 bg-slate-50 px-3 py-2.5 text-center text-sm font-bold text-slate-800 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none score-input">
                            </div>
                        </div>
                        <div class="bg-cyan-600 rounded-xl p-6 text-center shadow-lg shadow-cyan-600/20">
                            <p class="text-[10px] font-bold text-cyan-100 uppercase tracking-wider mb-1">Nilai Akhir</p>
                            <p id="final-score-display" class="text-4xl font-bold text-white">0</p>
                            <input type="hidden" name="total_score" id="form-total_score" value="0">
                        </div>
                        <div class="space-y-2">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Catatan Guru</label>
                            <textarea name="comments" id="form-comments" rows="2" class="block w-full rounded-lg border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 transition-all outline-none resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row-reverse gap-3">
                    <button type="submit" class="sm:flex-1 h-12 rounded-lg bg-cyan-600 text-white font-bold text-sm shadow-sm hover:bg-cyan-700 transition-all active:scale-[0.98]">
                        Simpan Penilaian
                    </button>
                    <button type="button" onclick="closeFormModal()" class="sm:flex-1 h-12 rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-500 hover:bg-slate-50 transition-colors">Batalkan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Auto calculation
document.querySelectorAll('.score-input').forEach(input => {
    input.addEventListener('input', calculateTotal);
});

function calculateTotal() {
    const t = parseInt(document.getElementById('form-tajweed_score').value) || 0;
    const f = parseInt(document.getElementById('form-fluency_score').value) || 0;
    const m = parseInt(document.getElementById('form-makhraj_score').value) || 0;
    
    // Using average calculation as per submit_assessment.php logic
    const total = Math.round((t + f + m) / 3);
    document.getElementById('final-score-display').textContent = total;
    document.getElementById('form-total_score').value = total;
}

function openFormModal(data = null) {
    const modal = document.getElementById('formModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const form = document.getElementById('assessmentForm');
    const title = document.getElementById('modal-title');
    
    form.reset();
    document.getElementById('form-id').value = '';
    
    if (data) {
        title.textContent = 'Edit Nilai Tahfidz';
        document.getElementById('form-id').value = data.id;
        document.getElementById('form-student_id').value = data.student_id;
        document.getElementById('form-assessment_date').value = data.assessment_date;
        document.getElementById('form-assessment_type_id').value = data.assessment_type_id;
        document.getElementById('form-teacher_id').value = data.teacher_id;
        document.getElementById('form-tajweed_score').value = data.tajweed_score;
        document.getElementById('form-fluency_score').value = data.fluency_score;
        document.getElementById('form-makhraj_score').value = data.makhraj_score;
        document.getElementById('form-comments').value = data.comments || '';
        calculateTotal();
    } else {
        title.textContent = 'Input Nilai Tahfidz';
        document.getElementById('form-assessment_date').value = new Date().toISOString().split('T')[0];
        document.getElementById('final-score-display').textContent = '0';
    }

    modal.classList.remove('invisible');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        modalContent.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeFormModal() {
    const modal = document.getElementById('formModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    
    overlay.classList.add('opacity-0');
    modalContent.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('invisible');
    }, 300);
}

async function submitForm(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('../../api/tahfidz/save_assessment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Data Tersimpan!',
                text: result.message,
                timer: 1500,
                showConfirmButton: false,
                borderRadius: '30px'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: result.message,
                borderRadius: '30px'
            });
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Sistem Error',
            text: 'Gagal memproses data.',
            borderRadius: '30px'
        });
    }
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Penilaian?',
        text: "Data ini tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus Data',
        cancelButtonText: 'Batal',
        borderRadius: '30px'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('../../api/tahfidz/delete_assessment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Dihapus!',
                        text: result.message,
                        timer: 1500,
                        showConfirmButton: false,
                        borderRadius: '30px'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: result.message,
                        borderRadius: '30px'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'Sistem Error',
                    text: 'Gagal menghapus data.',
                    borderRadius: '30px'
                });
            }
        }
    });
}
</script>

<style>
/* Custom Table Styles */
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
tbody tr { animation: fadeIn 0.4s ease forwards; }
tbody tr:nth-child(2) { animation-delay: 0.1s; }
tbody tr:nth-child(3) { animation-delay: 0.2s; }

.italic-last-td td:nth-child(3) { font-style: italic; color: #94a3b8; }
</style>

<?php include '../layouts/footer.php'; ?>
