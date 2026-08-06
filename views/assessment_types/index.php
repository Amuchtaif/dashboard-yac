<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Jenis Penilaian";

$db = new Database();
$conn = $db->getConnection();

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// --- Logika Filter ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(name LIKE :search OR code LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Total baris untuk paginasi
$count_query = "SELECT COUNT(*) FROM assessment_types $where_sql";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Ambil data
$query = "SELECT * FROM assessment_types $where_sql ORDER BY name ASC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$assessment_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Jenis Penilaian</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola jenis-jenis penilaian (UH, PTS, PAS, dll).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Jenis Penilaian
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <form
        class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center"
        method="GET">
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari berdasarkan nama atau kode..." onchange="this.form.submit()">
        </div>
        <div class="flex gap-2">
            <a href="index.php"
                class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors"
                title="Reset Filter">
                <i class="fa-solid fa-xmark h-4 w-4"></i>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">
                            No.</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Kode</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Nama Penilaian</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Bobot</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Deskripsi</th>
                        <th
                            class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($assessment_types)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">Belum ada data jenis penilaian.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($assessment_types as $index => $item): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-cyan-600">
                                <?php echo htmlspecialchars($item['code'] ?? '-'); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700">
                                <?php echo $item['weight']; ?>%
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 italic">
                                <?php echo htmlspecialchars($item['description'] ?: '-'); ?>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-3 text-gray-400">
                                    <button onclick='openFormModal(<?php echo json_encode($item); ?>)'
                                        class="hover:text-cyan-600 transition-colors" title="Edit">
                                        <i class="fa-solid fa-pen-to-square w-5 h-5"></i>
                                    </button>
                                    <button type="button" 
                                        data-id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-name="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="handleDelete(this)"
                                        class="hover:text-red-600 transition-colors" title="Hapus">
                                        <i class="fa-solid fa-trash w-5 h-5"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginasi -->
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700">
                                Menampilkan <span class="font-medium"><?php echo $offset + 1; ?></span> ke <span
                                    class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span
                                    class="font-medium"><?php echo $total_rows; ?></span> hasil
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>"
                                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                                    </a>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'; ?> focus:z-20 focus:outline-offset-0">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>"
                                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <i class="fa-solid fa-chevron-right h-5 w-5"></i>
                                    </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Form Modal -->
<div id="formModal" class="fixed inset-0 z-50 invisible transition-all duration-300 overflow-y-auto"
    aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalOverlay"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300"
            onclick="closeFormModal()"></div>

        <div id="modalContent"
            class="relative bg-white rounded-3xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-md border border-slate-100 overflow-hidden">
            <form id="assessmentForm" onsubmit="submitForm(event)">
                <input type="hidden" name="id" id="form-id">

                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-lg font-black text-slate-800" id="modal-title">Tambah Jenis Penilaian</h3>
                    <button type="button" onclick="closeFormModal()"
                        class="p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 shadow-sm transition-all">
                        <i class="fa-solid fa-xmark h-6 w-6"></i>
                    </button>
                </div>

                <div class="px-8 py-8 space-y-6">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Nama Penilaian</label>
                        <input type="text" name="name" id="form-name" required placeholder="Contoh: Ulangan Harian"
                            class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kode</label>
                        <input type="text" name="code" id="form-code" placeholder="Contoh: UH"
                            class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bobot (%)</label>
                        <input type="number" name="weight" id="form-weight" value="0" min="0" max="100"
                            class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Deskripsi</label>
                        <textarea name="description" id="form-description" rows="3"
                            class="block w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all resize-none"></textarea>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-3">
                    <button type="button" onclick="closeFormModal()"
                        class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Batal</button>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-8 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 transition-all active:scale-95">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function handleDelete(btn) {
        const id = btn.getAttribute('data-id');
        
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (!confirmBtn) return;
        
        // Hijack the global confirm button
        confirmBtn.onclick = async (e) => {
            e.preventDefault();
            confirmBtn.style.pointerEvents = 'none';
            const originalText = confirmBtn.innerText;
            confirmBtn.innerText = 'Menghapus...';

            try {
                const response = await fetch('../../api/assessment_types/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = 'index.php?success=' + encodeURIComponent(result.message || 'Data berhasil dihapus');
                } else {
                    alert('Gagal: ' + result.message);
                    confirmBtn.style.pointerEvents = 'auto';
                    confirmBtn.innerText = originalText;
                    if (typeof closeDeleteModal === 'function') closeDeleteModal();
                }
            } catch (error) {
                alert('Terjadi kesalahan sistem.');
                confirmBtn.style.pointerEvents = 'auto';
                confirmBtn.innerText = originalText;
                if (typeof closeDeleteModal === 'function') closeDeleteModal();
            }
        };

        // Call the global function
        if (typeof openDeleteModal === 'function') {
            openDeleteModal('#');
        }
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
            title.textContent = 'Edit Jenis Penilaian';
            document.getElementById('form-id').value = data.id;
            document.getElementById('form-name').value = data.name;
            document.getElementById('form-code').value = data.code || '';
            document.getElementById('form-weight').value = data.weight;
            document.getElementById('form-description').value = data.description || '';
        } else {
            title.textContent = 'Tambah Jenis Penilaian';
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
            const response = await fetch('../../api/assessment_types/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                window.location.href = 'index.php?success=' + encodeURIComponent(result.message || 'Data berhasil disimpan');
            } else {
                alert('Gagal: ' + result.message);
            }
        } catch (e) {
            alert('Terjadi kesalahan sistem.');
        }
    }


</script>

<?php include '../layouts/footer.php'; ?>