<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Kelola Penilaian Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Total rows for pagination
$count_query = "SELECT COUNT(*) FROM tahfidz_assessment_types";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch data
$query = "SELECT * FROM tahfidz_assessment_types ORDER BY name ASC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Kelola Penilaian Tahfidz</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Kelola jenis-jenis penilaian Tahfidz (Ziyadah, Murojaah, Ujian, dll).</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-cyan-700 transition-all active:scale-95">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jenis Penilaian
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden text-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-20 text-center">No</th>
                        <th class="px-6 py-4">Nama Penilaian</th>
                        <th class="px-6 py-4">Deskripsi</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 italic-last-td">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-slate-400 font-medium">Belum ada data jenis penilaian Tahfidz.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($data as $index => $item): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-400 font-bold">
                                <?php echo $offset + $index + 1; ?>
                            </td>
                            <td class="px-6 py-4 font-black text-slate-800">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500 italic">
                                <?php echo htmlspecialchars($item['description'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($item['is_active']): ?>
                                    <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-600 border border-emerald-100">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-[10px] font-black uppercase text-slate-500 border border-slate-200">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openFormModal(<?php echo json_encode($item); ?>)'
                                        class="p-2 text-slate-300 hover:text-cyan-600 hover:bg-cyan-50 rounded-xl transition-all" title="Edit">
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
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_loop = max(1, $page - 2);
                    $end_loop = min($total_pages, $page + 2);
                    for ($i = $start_loop; $i <= $end_loop; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($i == $page) ? 'bg-cyan-600 text-white shadow-md shadow-cyan-600/20' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo $search; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
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
        
        <div id="modalContent" class="relative bg-white rounded-xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-md border border-slate-100 overflow-hidden">
            <form id="typeForm" onsubmit="submitForm(event)">
                <input type="hidden" name="id" id="form-id">
                
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800" id="modal-title">Jenis Penilaian</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Tahfidz Quran</p>
                    </div>
                    <button type="button" onclick="closeFormModal()" class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 shadow-sm transition-all group">
                        <svg class="h-5 w-5 transform group-hover:rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-6 py-6 space-y-6">
                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Nama Penilaian</label>
                        <input type="text" name="name" id="form-name" required placeholder="Misal: Sema'an 1 Juz"
                            class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all placeholder:text-slate-300">
                    </div>
                    
                    <div class="space-y-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Deskripsi</label>
                        <textarea name="description" id="form-description" rows="3" placeholder="Jelaskan kriteria atau tujuan penilaian..."
                            class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all resize-none placeholder:text-slate-300"></textarea>
                    </div>

                    <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-lg border border-slate-100">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" id="form-is_active" value="1" checked class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-cyan-600"></div>
                        </label>
                        <span class="text-xs font-bold text-slate-600 uppercase tracking-tight">Status Aktif</span>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row-reverse gap-3">
                    <button type="submit" class="sm:flex-1 h-12 rounded-lg bg-cyan-600 text-white font-bold text-sm shadow-sm hover:bg-cyan-700 transition-all active:scale-[0.98]">
                        Simpan Perubahan
                    </button>
                    <button type="button" onclick="closeFormModal()" class="sm:flex-1 h-12 rounded-lg border border-slate-200 bg-white text-sm font-bold text-slate-500 hover:bg-slate-50 transition-colors">
                        Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openFormModal(data = null) {
    const modal = document.getElementById('formModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const form = document.getElementById('typeForm');
    const title = document.getElementById('modal-title');
    
    form.reset();
    document.getElementById('form-id').value = '';
    document.getElementById('form-is_active').checked = true;
    
    if (data) {
        title.textContent = 'Edit Jenis Penilaian';
        document.getElementById('form-id').value = data.id;
        document.getElementById('form-name').value = data.name;
        document.getElementById('form-description').value = data.description || '';
        document.getElementById('form-is_active').checked = data.is_active == 1;
    } else {
        title.textContent = 'Tambah Penilaian Baru';
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
    
    // Convert checkbox to tinyint
    data.is_active = document.getElementById('form-is_active').checked ? 1 : 0;

    try {
        const response = await fetch('../../api/tahfidz/save_assessment_type.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
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
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Sistem Error',
            text: 'Terjadi kesalahan saat memproses data.',
            borderRadius: '20px'
        });
    }
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Jenis Penilaian?',
        text: "Data yang sudah dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        borderRadius: '20px'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('../../api/tahfidz/delete_assessment_type.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
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
