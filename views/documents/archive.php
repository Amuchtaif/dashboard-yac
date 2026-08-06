<?php
// views/documents/archive.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_documents');

$page_title = "Arsip Digital";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$where_clause = "status = 'completed'";
$params = [];

if (!$is_admin) {
    $stmtEmpInfo = $conn->prepare("SELECT division_id, department_id, unit_id FROM employees WHERE id = ?");
    $stmtEmpInfo->execute([$user_id]);
    $emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);
    $user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
    $user_unit_id = $emp_info['unit_id'] ?? 0;

    $where_clause = "d.status = 'completed' AND (
        d.creator_id = :user_id 
        OR (d.receiver_division_id = :user_div_id AND (d.receiver_unit_id IS NULL OR d.receiver_unit_id = :user_unit_id OR :user_unit_id = 0))
        OR (d.receiver_unit_id = :user_unit_id AND :user_unit_id != 0)
        OR d.id IN (SELECT document_id FROM document_dispositions WHERE to_user_id = :user_id)
    )";
    $params[':user_id'] = $user_id;
    $params[':user_div_id'] = $user_div_id;
    $params[':user_unit_id'] = $user_unit_id;
} else {
    $where_clause = "d.status = 'completed'";
}

if (!empty($search)) {
    $where_clause .= " AND (d.title LIKE :search OR d.document_number LIKE :search OR d.sender LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($type_filter)) {
    $where_clause .= " AND d.type = :type";
    $params[':type'] = $type_filter;
}

if (!empty($start_date)) {
    $where_clause .= " AND DATE(d.created_at) >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clause .= " AND DATE(d.created_at) <= :end_date";
    $params[':end_date'] = $end_date;
}

// Get total count
$stmtCount = $conn->prepare("SELECT COUNT(*) FROM documents d WHERE $where_clause");
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch list
$stmtList = $conn->prepare("
    SELECT d.id, d.document_number, d.title, d.type, d.sender, d.file_path, d.created_at
    FROM documents d
    WHERE $where_clause
    ORDER BY d.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $k => $v) {
    $stmtList->bindValue($k, $v);
}
$stmtList->execute();
$archives = $stmtList->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Arsip Digital Yayasan</h1>
        <p class="mt-1 text-sm text-slate-500">Pusat pencarian berkas fisik dan surat keluar resmi yang sudah diselesaikan.</p>
    </div>

    <!-- Filters Panel -->
    <div class="mt-6 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 pb-2">Filter Pencarian</h3>
        
        <form class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4" method="GET">
            <!-- Search Query -->
            <div class="relative md:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Kata Kunci</label>
                <div class="pointer-events-none absolute bottom-3 left-3 flex items-center">
                    <i class="fa-solid fa-search text-slate-400 text-sm"></i>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="block w-full rounded-lg border-slate-200 pl-10 pr-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                    placeholder="Nomor surat, judul, pengirim...">
            </div>

            <!-- Type -->
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Jenis Dokumen</label>
                <select name="type" class="select-custom w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border py-2 pl-3 pr-8 text-slate-600">
                    <option value="">Semua Jenis</option>
                    <option value="outgoing" <?php echo $type_filter === 'outgoing' ? 'selected' : ''; ?>>Surat Keluar</option>
                    <option value="incoming" <?php echo $type_filter === 'incoming' ? 'selected' : ''; ?>>Surat Masuk</option>
                </select>
            </div>

            <!-- Actions buttons in-grid -->
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                    Terapkan
                </button>
                <a href="<?php url('views/documents/archive.php'); ?>" class="inline-flex w-full items-center justify-center rounded-lg bg-white border border-slate-300 px-4 py-2.5 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    Reset
                </a>
            </div>

            <!-- Date Range -->
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                       class="block w-full rounded-lg border-slate-200 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border text-slate-600">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                       class="block w-full rounded-lg border-slate-200 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 border text-slate-600">
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="mt-6 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Nomor Surat</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Perihal / Hal</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Jenis</th>
                    <th scope="col" class="px-3 py-3.5 text-left">Asal / Instansi</th>
                    <th scope="col" class="px-3 py-3.5 text-center">Tanggal Registrasi</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Pratinjau</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (count($archives) > 0): ?>
                    <?php foreach ($archives as $index => $arc): 
                        $is_out = ($arc['type'] === 'outgoing');
                    ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                <?php echo htmlspecialchars($arc['document_number']); ?>
                            </td>
                            <td class="px-3 py-4 text-xs text-slate-600 font-medium max-w-sm truncate">
                                <?php echo htmlspecialchars($arc['title']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-[9px] font-bold border <?php echo $is_out ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>">
                                    <?php echo $is_out ? 'Surat Keluar' : 'Surat Masuk'; ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                <?php echo htmlspecialchars($is_out ? 'Internal Yayasan' : ($arc['sender'] ?: '-')); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                <?php echo date('d/m/Y', strtotime($arc['created_at'])); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium">
                                <button type="button" onclick="previewArchive(<?php echo $arc['id']; ?>, '<?php echo $arc['type']; ?>', '<?php echo htmlspecialchars(addslashes($arc['title'])); ?>', '<?php echo !empty($arc['file_path']) ? BASE_URL . '/' . htmlspecialchars($arc['file_path']) : ''; ?>')"
                                        class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-200 transition-colors">
                                    Buka Arsip
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-sm text-slate-500 italic">Arsip surat kosong atau tidak ditemukan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between border-t border-slate-200 px-4 py-3 sm:px-6">
            <div class="flex flex-1 justify-between sm:hidden">
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs text-slate-700">
                        Menampilkan halaman <span class="font-bold"><?php echo $page; ?></span> dari <span class="font-bold"><?php echo $total_pages; ?></span> halaman (Total <span class="font-bold"><?php echo $total_rows; ?></span> surat).
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <a href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                               class="relative inline-flex items-center px-4 py-2 text-xs font-bold <?php echo $p === $page ? 'bg-indigo-600 text-white z-10' : 'text-slate-900 bg-white border border-slate-300 hover:bg-slate-50'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ARCHIVE PREVIEW MODAL -->
<div id="archiveModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeArchiveModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
            <!-- Header -->
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider" id="archive-modal-title">Pratinjau Arsip</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5" id="archive-modal-subtitle">Menyajikan berkas digital final</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" onclick="closeArchiveModal()">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Content Area -->
            <div class="p-6">
                <div class="border border-slate-200 rounded-xl bg-slate-50 min-h-[450px] relative">
                    <!-- Outgoing letter text preview -->
                    <div id="outgoing-text-preview" class="p-8 prose max-w-none text-slate-800 text-xs hidden" style="font-family: serif; max-height: 500px; overflow-y: auto;">
                        Memuat draf...
                    </div>
                    
                    <!-- Incoming PDF / Image preview -->
                    <iframe id="incoming-pdf-preview" class="w-full h-[500px] rounded-xl hidden" src=""></iframe>
                </div>

                <div class="mt-5 flex justify-between items-center">
                    <button type="button" onclick="closeArchiveModal()" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                        Tutup
                    </button>
                    
                    <!-- Open print preview / raw file -->
                    <a id="archive-print-btn" href="" target="_blank" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                        Buka / Cetak
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function previewArchive(id, type, title, filePath) {
    document.getElementById('archive-modal-title').innerText = title;
    
    const textPreview = document.getElementById('outgoing-text-preview');
    const pdfPreview = document.getElementById('incoming-pdf-preview');
    const printBtn = document.getElementById('archive-print-btn');

    if (type === 'outgoing') {
        textPreview.classList.remove('hidden');
        pdfPreview.classList.add('hidden');
        textPreview.innerHTML = 'Memuat arsip surat...';
        printBtn.href = '<?php url("views/documents/preview.php?id="); ?>' + id;

        fetch('<?php url("views/documents/outgoing.php?action=view&id="); ?>' + id)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const content = doc.querySelector('.prose').innerHTML;
                textPreview.innerHTML = content;
            })
            .catch(err => {
                textPreview.innerHTML = '<span class="text-rose-600 font-bold">Gagal memuat arsip.</span>';
            });
    } else {
        textPreview.classList.add('hidden');
        pdfPreview.classList.remove('hidden');
        pdfPreview.src = filePath;
        printBtn.href = filePath;
    }

    document.getElementById('archiveModal').classList.remove('hidden');
}

function closeArchiveModal() {
    document.getElementById('archiveModal').classList.add('hidden');
    document.getElementById('incoming-pdf-preview').src = '';
}
</script>

<?php include '../layouts/footer.php'; ?>
