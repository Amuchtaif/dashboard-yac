<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$page_title = "Rencana Pelaksanaan Pembelajaran (RPP)";

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$is_draft = isset($_GET['draft']) ? (int)$_GET['draft'] : 0;
$search = $_GET['search'] ?? '';

$where = "WHERE r.is_draft = :draft";
$params = [':draft' => $is_draft];

if ($search) {
    $where .= " AND (r.title LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count total records
$count_query = "SELECT COUNT(*) FROM rpp r JOIN subjects s ON r.subject_id = s.id $where";
$stmt_count = $conn->prepare($count_query);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch RPPs
$query = "
    SELECT r.*, s.name as subject_name, gl.name as grade_name, ay.name as academic_year_name, e.full_name as teacher_name
    FROM rpp r
    JOIN subjects s ON r.subject_id = s.id
    JOIN grade_levels gl ON r.grade_level_id = gl.id
    JOIN academic_years ay ON r.academic_year_id = ay.id
    JOIN employees e ON r.employee_id = e.id
    $where
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$rpp_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Kelola dokumen rencana mengajar guru.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="create.php" 
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-700 transition-all">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Buat RPP Baru
            </a>
        </div>
    </div>

    <!-- Tabs & Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex bg-slate-100 p-1 rounded-xl w-fit">
            <a href="?draft=0" class="px-4 py-2 text-sm font-bold rounded-lg transition-all <?php echo !$is_draft ? 'bg-white text-cyan-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'; ?>">
                Daftar Terbit
            </a>
            <a href="?draft=1" class="px-4 py-2 text-sm font-bold rounded-lg transition-all <?php echo $is_draft ? 'bg-white text-cyan-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'; ?>">
                Draft
                <?php if ($is_draft): ?>
                    <span class="ml-1 px-1.5 py-0.5 text-[10px] bg-cyan-100 text-cyan-700 rounded-full"><?php echo $total_records; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <form method="GET" class="relative group max-w-sm w-full">
            <input type="hidden" name="draft" value="<?php echo $is_draft; ?>">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400 group-focus-within:text-cyan-500 transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                placeholder="Cari judul atau mapel..." 
                class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-xl text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all bg-white">
        </form>
    </div>

    <!-- Content Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Informasi RPP</th>
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4">Guru Pengampu</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($rpp_list) > 0): ?>
                        <?php foreach ($rpp_list as $index => $r): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-6 py-4 text-slate-400 font-medium"><?php echo $offset + $index + 1; ?>.</td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 line-clamp-1"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <div class="text-xs text-slate-500 mt-0.5 italic"><?php echo htmlspecialchars($r['subject_name']); ?> • Kelas <?php echo htmlspecialchars($r['grade_name']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700"><?php echo htmlspecialchars($r['academic_year_name']); ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $r['semester']; ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-600"><?php echo htmlspecialchars($r['teacher_name']); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($r['is_draft']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">Terbit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="edit.php?id=<?php echo $r['id']; ?>" class="p-2 text-slate-400 hover:text-cyan-600 transition-colors" title="Ubah">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </a>
                                        <button onclick="openDeleteModal('delete.php?id=<?php echo $r['id']; ?>')" class="p-2 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 italic">Belum ada dokumen RPP yang ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Menampilkan <span class="font-bold"><?php echo count($rpp_list); ?></span> dari <span class="font-bold"><?php echo $total_records; ?></span> data
            </p>
            <div class="flex gap-2">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&draft=<?php echo $is_draft; ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-3 py-1 text-xs font-bold rounded-lg border <?php echo $i == $page ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; ?> transition-all">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
