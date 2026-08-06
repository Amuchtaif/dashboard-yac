<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';
require_once '../../../config/permission.php';

check_permission('can_access_kesantrian');

$page_title = "Manajemen Pelanggaran Santri";
$db = new Database();
$conn = $db->getConnection();

$categories = $conn->query("SELECT id, type_name as nama_kategori, points as poin, category FROM boarding_violation_types ORDER BY category ASC, type_name ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Sistem pencatatan dan tindak lanjut kedisiplinan santri.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex gap-3">
            <a href="officers.php" 
                class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 shadow-sm hover:border-slate-300 transition-all">
                <i class="fa-solid fa-users -ml-1 mr-2 h-4 w-4 text-slate-400"></i>
                Kelola Petugas
            </a>
            <button onclick="openAddModal()" 
                class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-rose-200 hover:bg-rose-700 transition-all hover:-translate-y-0.5 active:translate-y-0">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Catat Pelanggaran
            </button>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Total Pelanggaran</p>
            <h3 class="text-2xl font-bold text-slate-800" id="stat-total">0</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <p class="text-[10px] font-black text-amber-500 uppercase tracking-widest mb-1">Diproses</p>
            <h3 class="text-2xl font-bold text-slate-800" id="stat-process">0</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">Selesai</p>
            <h3 class="text-2xl font-bold text-slate-800" id="stat-done">0</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
            <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest mb-1">Kategori Berat</p>
            <h3 class="text-2xl font-bold text-slate-800" id="stat-heavy">0</h3>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Status</label>
                <select id="filter-status" onchange="loadViolations()" class="hybrid-select" data-searchable="false">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="dilaporkan">Dilaporkan</option>
                    <option value="diproses">Diproses</option>
                    <option value="selesai">Selesai</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Kategori</label>
                <select id="filter-category" onchange="loadViolations()" class="hybrid-select" data-searchable="false">
                    <option value="">Semua Kategori</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nama_kategori']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 ml-1">Cari Santri / Deskripsi</label>
                <div class="relative">
                    <input type="text" id="filter-search" oninput="debounceLoad()" 
                        class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all pl-10" 
                        placeholder="Ketik nama santri atau kejadian...">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Violation Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-12 text-center">No.</th>
                        <th class="px-6 py-4 min-w-[200px]">Santri</th>
                        <th class="px-6 py-4 min-w-[300px]">Pelanggaran</th>
                        <th class="px-6 py-4 min-w-[200px]">Tanggal & Lokasi</th>
                        <th class="px-6 py-4 text-center min-w-[120px]">Foto Bukti</th>
                        <th class="px-6 py-4 text-center min-w-[120px]">Status</th>
                        <th class="px-6 py-4 text-center min-w-[150px] border-none">Tindakan</th>
                    </tr>
                </thead>
                <tbody id="violation-list" class="divide-y divide-slate-100 text-sm">
                    <tr>
                        <td colspan="7" class="px-6 py-20 text-center text-slate-400 italic">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Create Violation -->
<div id="modal-violation" class="fixed inset-0 z-[60] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden border border-slate-200 transform transition-all">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
                <h3 class="text-xl font-bold text-slate-800" id="modal-title">Catat Pelanggaran</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 hover:bg-slate-50 rounded-xl">
                    <i class="fa-solid fa-xmark h-6 w-6"></i>
                </button>
            </div>
            
            <form id="violation-form" class="p-8 space-y-5">
                <input type="hidden" id="violation-id" name="id">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Santri</label>
                        <select name="santri_id" id="form-santri" class="hybrid-select" required>
                            <option value="">Pilih Santri...</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Kategori</label>
                        <select name="kategori_id" id="form-kategori" class="hybrid-select" data-searchable="false" required>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nama_kategori']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Tanggal</label>
                        <input type="date" name="tanggal" id="form-tanggal" value="<?php echo date('Y-m-d'); ?>" required 
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Lokasi</label>
                        <input type="text" name="lokasi" id="form-lokasi" 
                            class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all" 
                            placeholder="Tempat kejadian...">
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Deskripsi Kejadian</label>
                    <textarea name="deskripsi" id="form-deskripsi" rows="3" required
                        class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all resize-none" 
                        placeholder="Jelaskan kronologi kejadian secara singkat..."></textarea>
                </div>

                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Foto Bukti (Opsional)</label>
                    <div id="form-existing-attachment-container" class="hidden flex items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-2xl">
                        <img id="form-existing-attachment-preview" src="" class="w-12 h-12 object-cover rounded-lg border border-slate-200">
                        <div class="flex-1">
                            <p class="text-xs font-semibold text-slate-600">Foto bukti saat ini</p>
                            <button type="button" id="form-existing-attachment-link" class="text-[10px] font-bold text-rose-600 hover:text-rose-700">Lihat Foto</button>
                        </div>
                    </div>
                    <input type="file" name="attachment" id="form-attachment" accept="image/*"
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-2xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-500 outline-none transition-all file:mr-4 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-rose-50 file:text-rose-700 hover:file:bg-rose-100">
                    <p class="text-[10px] text-slate-400 italic ml-1">Unggah foto baru jika ingin mengganti bukti yang lama.</p>
                </div>

                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">Batal</button>
                    <button type="submit" class="px-10 py-3 bg-rose-600 text-white text-sm font-bold rounded-2xl shadow-lg shadow-rose-200 hover:bg-rose-700 transition-all transform active:scale-95">Simpan Laporan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let debounceTimer;

    document.addEventListener('DOMContentLoaded', () => {
        loadStudents();
        loadViolations();
    });

    function debounceLoad() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadViolations, 500);
    }

    async function loadStudents() {
        try {
            const res = await fetch('<?php echo url('api/student_violations/get_students.php'); ?>');
            const result = await res.json();
            if (result.success) {
                const select = document.getElementById('form-santri');
                result.data.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.nama_siswa} (${s.kelas})`;
                    select.appendChild(opt);
                });
                if (window.initHybridSelects) initHybridSelects();
            }
        } catch (e) {
            console.error('Failed to load students');
        }
    }

    async function loadViolations() {
        const list = document.getElementById('violation-list');
        const status = document.getElementById('filter-status').value;
        const category = document.getElementById('filter-category').value;
        const search = document.getElementById('filter-search').value;

        try {
            const res = await fetch(`<?php echo url('api/student_violations/list.php'); ?>?status=${status}&kategori_id=${category}&search=${search}`);
            const result = await res.json();
            
            if (result.success) {
                updateStats(result.data);
                renderTable(result.data);
            }
        } catch (e) {
            list.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-rose-500 font-bold">Gagal memuat data</td></tr>';
        }
    }

    function updateStats(data) {
        document.getElementById('stat-total').textContent = data.length;
        document.getElementById('stat-process').textContent = data.filter(v => v.status === 'diproses').length;
        document.getElementById('stat-done').textContent = data.filter(v => v.status === 'selesai').length;
        document.getElementById('stat-heavy').textContent = data.filter(v => v.category === 'Berat').length;
    }

    function renderTable(data) {
        const list = document.getElementById('violation-list');
        if (data.length === 0) {
            list.innerHTML = '<tr><td colspan="7" class="px-6 py-20 text-center text-slate-400 italic">Tidak ada data ditemukan</td></tr>';
            return;
        }

        list.innerHTML = data.map((v, i) => `
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-6 py-4 text-center font-mono text-slate-400">${i + 1}</td>
                <td class="px-6 py-4">
                    <div class="flex flex-col">
                        <span class="font-bold text-slate-800 capitalize">${v.nama_siswa}</span>
                        <span class="text-[10px] text-slate-400 uppercase tracking-tighter">Reported by: ${v.pelapor_name}</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${getCategoryClass(v.nama_kategori)}">${v.nama_kategori}</span>
                            <span class="text-[10px] font-bold text-rose-600">+${v.poin} Poin</span>
                        </div>
                        <span class="text-slate-600 line-clamp-2 leading-relaxed">${v.deskripsi}</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-col">
                        <span class="font-semibold text-slate-700">${v.tanggal_pelanggaran}</span>
                        <span class="text-xs text-slate-400">${v.lokasi || '-'}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-center">
                    ${v.attachment ? `
                        <div class="flex justify-center">
                            <img src="${v.attachment_url}" 
                                 onclick="openImageModal('${v.attachment_url}')"
                                 class="w-10 h-10 object-cover rounded-xl border border-slate-200 shadow-sm cursor-zoom-in hover:scale-105 transition-all animate-fade-in"
                                 title="Klik untuk memperbesar">
                        </div>
                    ` : `
                        <span class="text-slate-300 font-mono">-</span>
                    `}
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${getStatusClass(v.status)}">
                        ${v.status}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <div class="flex justify-center gap-2">
                        <button onclick="viewDetail(${v.id})" class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-xl transition-all" title="Detail & Tindak Lanjut">
                            <i class="fa-solid fa-eye w-5 h-5"></i>
                        </button>
                        <button onclick="editViolation(${JSON.stringify(v).replace(/"/g, '&quot;')})" class="p-2 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-xl transition-all" title="Ubah">
                            <i class="fa-solid fa-pen-to-square w-5 h-5"></i>
                        </button>
                        <button onclick="deleteViolation(${v.id})" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus">
                            <i class="fa-solid fa-trash w-5 h-5"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function getStatusClass(status) {
        switch(status) {
            case 'draft': return 'bg-slate-100 text-slate-500';
            case 'dilaporkan': return 'bg-amber-100 text-amber-600';
            case 'diproses': return 'bg-indigo-100 text-indigo-600';
            case 'selesai': return 'bg-emerald-100 text-emerald-600';
            default: return 'bg-slate-100 text-slate-500';
        }
    }

    function getCategoryClass(cat) {
        cat = cat.toLowerCase();
        if (cat.includes('ringan')) return 'bg-blue-100 text-blue-700';
        if (cat.includes('sedang')) return 'bg-amber-100 text-amber-700';
        if (cat.includes('berat')) return 'bg-rose-100 text-rose-700';
        return 'bg-slate-100 text-slate-700';
    }

    function openAddModal() {
        document.getElementById('modal-title').textContent = 'Catat Pelanggaran';
        document.getElementById('violation-form').reset();
        document.getElementById('violation-id').value = '';
        document.getElementById('form-attachment').value = '';
        document.getElementById('form-existing-attachment-container').classList.add('hidden');
        document.getElementById('modal-violation').classList.remove('hidden');
    }

    function editViolation(v) {
        document.getElementById('modal-title').textContent = 'Ubah Pelanggaran';
        document.getElementById('violation-id').value = v.id;
        document.getElementById('form-santri').value = v.santri_id;
        document.getElementById('form-kategori').value = v.kategori_id;
        document.getElementById('form-tanggal').value = v.tanggal_pelanggaran;
        document.getElementById('form-lokasi').value = v.lokasi || '';
        document.getElementById('form-deskripsi').value = v.deskripsi;
        document.getElementById('form-attachment').value = '';
        
        const existContainer = document.getElementById('form-existing-attachment-container');
        if (v.attachment) {
            existContainer.classList.remove('hidden');
            document.getElementById('form-existing-attachment-preview').src = v.attachment_url;
            document.getElementById('form-existing-attachment-link').onclick = () => openImageModal(v.attachment_url);
        } else {
            existContainer.classList.add('hidden');
        }
        
        // Refresh hybrid selects if needed
        if (window.initHybridSelects) initHybridSelects();
        
        document.getElementById('modal-violation').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modal-violation').classList.add('hidden');
    }

    async function deleteViolation(id) {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (!confirmBtn) return;

        confirmBtn.onclick = async (e) => {
            e.preventDefault();
            try {
                const res = await fetch('<?php echo url('api/student_violations/delete.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id })
                });
                const result = await res.json();
                if (result.success) {
                    showToast(result.message);
                    if (typeof closeDeleteModal === 'function') closeDeleteModal();
                    loadViolations();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (e) {
                showToast('Gagal menghapus data', 'error');
            }
        };

        openDeleteModal('#');
    }

    document.getElementById('violation-form').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const isUpdate = !!formData.get('id');
        const endpoint = isUpdate ? 'api/student_violations/update.php' : 'api/student_violations/create.php';

        try {
            const res = await fetch(`<?php echo url(''); ?>${endpoint}`, {
                method: 'POST',
                body: formData
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message);
                closeModal();
                loadViolations();
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Gagal menyimpan data', 'error');
        }
    };

    function viewDetail(id) {
        window.location.href = `detail.php?id=${id}`;
    }
</script>

<?php require_once '../../layouts/footer.php'; ?>
