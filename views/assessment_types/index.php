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
if ($page < 1) $page = 1;
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
            <p class="mt-2 text-sm text-slate-500">Kelola jenis-jenis penilaian (UH, PTS, PAS, dll) yang akan tampil di Flutter.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
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
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari berdasarkan nama atau kode..." onchange="this.form.submit()">
        </div>
        <div class="flex gap-2">
            <a href="index.php"
                class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors"
                title="Reset Filter">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200 table-fixed">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">No.</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kode</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Nama Penilaian</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Bobot</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Deskripsi</th>
                        <th class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($assessment_types)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">Belum ada data jenis penilaian.</td>
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
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $item['id']; ?>)"
                                        class="hover:text-red-600 transition-colors" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
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
                                Menampilkan <span class="font-medium"><?php echo $offset + 1; ?></span> ke <span class="font-medium"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-medium"><?php echo $total_rows; ?></span> hasil
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>"
                                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                                        </svg>
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
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                                        </svg>
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
<div id="formModal" class="fixed inset-0 z-50 invisible transition-all duration-300 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeFormModal()"></div>
        
        <div id="modalContent" class="relative bg-white rounded-3xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-md border border-slate-100 overflow-hidden">
            <form id="assessmentForm" onsubmit="submitForm(event)">
                <input type="hidden" name="id" id="form-id">
                
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-lg font-black text-slate-800" id="modal-title">Tambah Jenis Penilaian</h3>
                    <button type="button" onclick="closeFormModal()" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 shadow-sm transition-all">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
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
                    <button type="button" onclick="closeFormModal()" class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Batal</button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-8 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 transition-all active:scale-95">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

async function confirmDelete(id) {
    if (confirm('Yakin ingin menghapus jenis penilaian ini?')) {
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
            }
        } catch (e) {
            alert('Terjadi kesalahan sistem.');
        }
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
