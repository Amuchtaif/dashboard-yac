<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';

check_login();
if (!hasPermission($_SESSION['user_id'], 'manage_activities') && (!isset($_SESSION['position_name']) || $_SESSION['position_name'] !== 'Administrator')) {
    include '../layouts/no_access.php';
    exit;
}

$page_title = "Master Jenis Aktivitas";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination & Search ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? trim($_GET['type']) : '';
$status_filter = isset($_GET['is_active']) ? $_GET['is_active'] : '';

$where_clauses = ["deleted_at IS NULL"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(name LIKE :search OR description LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($type_filter)) {
    $where_clauses[] = "type = :type";
    $params[':type'] = $type_filter;
}
if ($status_filter !== '') {
    $where_clauses[] = "is_active = :is_active";
    $params[':is_active'] = (int)$status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// Count total
$count_query = "SELECT COUNT(*) FROM activity_types WHERE $where_sql";
$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $total_stmt->bindValue($key, $val);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "SELECT t.*, (
    SELECT COUNT(*) FROM student_activities a WHERE a.activity_type_id = t.id AND a.deleted_at IS NULL
) as total_used 
FROM activity_types t 
WHERE $where_sql 
ORDER BY t.sort_order ASC, t.name ASC 
LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<style>
/* Custom premium styles for form inputs, dropdowns, and textareas */
input[type="text"], input[type="number"], select, textarea {
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
input[type="text"]:hover, input[type="number"]:hover, select:hover, textarea:hover {
    border-color: #94a3b8 !important;
    background-color: #ffffff !important;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05) !important;
}
input[type="text"]:focus, input[type="number"]:focus, select:focus, textarea:focus {
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
            <h1 class="text-2xl font-bold text-slate-800">Master Jenis Aktivitas</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Kelola jenis pembiasaan amaliyah dan aktivitas santri secara dinamis.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-md hover:bg-indigo-700 transition-all active:scale-95">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jenis Aktivitas
            </button>
        </div>
    </div>

    <!-- Filters & Search -->
    <div class="mt-6 bg-white p-4 border border-slate-200 rounded-xl shadow-sm">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Cari Nama / Deskripsi</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari..."
                    class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Filter Tipe</label>
                <select name="type" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="">Semua Tipe</option>
                    <option value="personal" <?php echo $type_filter === 'personal' ? 'selected' : ''; ?>>Personal</option>
                    <option value="event" <?php echo $type_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Filter Status</label>
                <select name="is_active" class="w-full text-sm rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    <option value="">Semua Status</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Nonaktif</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2 px-4 rounded-lg text-sm transition-all">
                    Filter
                </button>
                <?php if (!empty($search) || !empty($type_filter) || $status_filter !== ''): ?>
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
                        <th class="px-6 py-4 w-16 text-center">Urutan</th>
                        <th class="px-6 py-4">Nama Aktivitas</th>
                        <th class="px-6 py-4">Tipe</th>
                        <th class="px-6 py-4">Warna & Icon</th>
                        <th class="px-6 py-4 text-center">Poin</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Total Digunakan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 italic-last-td">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-16 text-center text-slate-400 font-medium">Belum ada data jenis aktivitas.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($data as $item): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-400 font-bold">
                                <?php echo htmlspecialchars($item['sort_order']); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($item['description'] ?: '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold uppercase <?php echo $item['type'] === 'personal' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-purple-50 text-purple-700 border border-purple-100'; ?>">
                                    <?php echo htmlspecialchars($item['type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="inline-block w-4 h-4 rounded-full border border-slate-300" style="background-color: <?php echo htmlspecialchars($item['color'] ?: '#cbd5e1'); ?>"></span>
                                    <span class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars($item['icon'] ?: 'default'); ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-700">
                                <?php echo htmlspecialchars($item['point']); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <button onclick="toggleStatus(<?php echo $item['id']; ?>)" 
                                    class="inline-flex items-center rounded-lg px-2.5 py-1 text-[10px] font-black uppercase border transition-all hover:scale-105 active:scale-95 <?php echo $item['is_active'] ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-100 text-slate-500 border-slate-200'; ?>">
                                    <?php echo $item['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                </button>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-slate-500">
                                <?php echo htmlspecialchars($item['total_used']); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openFormModal(<?php echo json_encode($item); ?>)'
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
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&is_active=<?php echo $status_filter; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_loop = max(1, $page - 2);
                    $end_loop = min($total_pages, $page + 2);
                    for ($i = $start_loop; $i <= $end_loop; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&is_active=<?php echo $status_filter; ?>" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($i == $page) ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&type=<?php echo $type_filter; ?>&is_active=<?php echo $status_filter; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
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
    <div class="flex items-center justify-center min-h-screen p-4 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeFormModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="activityTypeForm" onsubmit="saveForm(event)">
                <input type="hidden" id="form_id" name="id" value="">
                
                <div class="bg-white px-6 pt-6 pb-4 sm:p-6 sm:pb-4 border-b border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800" id="modalTitle">Tambah Jenis Aktivitas</h3>
                </div>
                
                <div class="px-6 py-4 space-y-4 text-sm text-slate-700">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Aktivitas <span class="text-rose-500">*</span></label>
                        <input type="text" id="form_name" name="name" required placeholder="Contoh: Shalat Dhuha"
                            class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                    </div>
                    
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tipe <span class="text-rose-500">*</span></label>
                        <select id="form_type" name="type" required
                            class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                            <option value="personal">Personal</option>
                            <option value="event">Event</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Deskripsi</label>
                        <textarea id="form_description" name="description" placeholder="Penjelasan singkat..." rows="3"
                            class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Warna</label>
                            <input type="color" id="form_color" name="color" value="#6366f1"
                                class="w-full h-10 rounded-lg border border-slate-200 cursor-pointer p-1">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Icon Name</label>
                            <input type="text" id="form_icon" name="icon" placeholder="Contoh: sun, moon, users"
                                class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Poin Nilai</label>
                            <input type="number" id="form_point" name="point" value="0" min="0"
                                class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Urutan Tampilan</label>
                            <input type="number" id="form_sort_order" name="sort_order" value="0"
                                class="w-full rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-200">
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-6 py-4 sm:flex sm:flex-row-reverse gap-2">
                    <button type="submit"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-sm font-bold text-white hover:bg-indigo-700 focus:outline-none transition-all active:scale-95 sm:w-auto">
                        Simpan
                    </button>
                    <button type="button" onclick="closeFormModal()"
                        class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-200 shadow-sm px-4 py-2 bg-white text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all sm:mt-0 sm:w-auto">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const modal = document.getElementById('formModal');

function openFormModal(data = null) {
    document.getElementById('activityTypeForm').reset();
    
    if (data) {
        document.getElementById('modalTitle').innerText = "Edit Jenis Aktivitas";
        document.getElementById('form_id').value = data.id;
        document.getElementById('form_name').value = data.name;
        document.getElementById('form_type').value = data.type;
        document.getElementById('form_description').value = data.description || '';
        document.getElementById('form_color').value = data.color || '#6366f1';
        document.getElementById('form_icon').value = data.icon || '';
        document.getElementById('form_point').value = data.point || 0;
        document.getElementById('form_sort_order').value = data.sort_order || 0;
    } else {
        document.getElementById('modalTitle').innerText = "Tambah Jenis Aktivitas";
        document.getElementById('form_id').value = "";
        document.getElementById('form_color').value = "#6366f1";
    }
    
    modal.classList.remove('invisible');
}

function closeFormModal() {
    modal.classList.add('invisible');
}

async function saveForm(e) {
    e.preventDefault();
    const id = document.getElementById('form_id').value;
    const isEdit = id !== "";
    const endpoint = isEdit ? `../../api/activity-types/${id}` : '../../api/activity-types';
    const method = isEdit ? 'PUT' : 'POST';

    const formData = {
        name: document.getElementById('form_name').value,
        type: document.getElementById('form_type').value,
        description: document.getElementById('form_description').value,
        color: document.getElementById('form_color').value,
        icon: document.getElementById('form_icon').value,
        point: parseInt(document.getElementById('form_point').value) || 0,
        sort_order: parseInt(document.getElementById('form_sort_order').value) || 0
    };

    try {
        const response = await fetch(endpoint, {
            method: method,
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

async function toggleStatus(id) {
    try {
        const response = await fetch(`../../api/activity-types/${id}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' }
        });
        const result = await response.json();
        if (result.success) {
            window.location.reload();
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
                const response = await fetch(`../../api/activity-types/${id}`, {
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
