<?php
// views/work_reports/categories.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Kategori Laporan Kerja";
require_once __DIR__ . '/../layouts/header.php';
?>

<div class="w-full">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-black text-slate-800 tracking-tight">Kategori Laporan</h1>
            <p class="text-slate-500 text-sm mt-1 font-medium">Kelola klasifikasi laporan kerja harian pegawai.</p>
        </div>
        <button onclick="openCategoryModal()" class="inline-flex items-center justify-center px-6 py-3 bg-cyan-600 hover:bg-cyan-700 text-white rounded-2xl font-bold text-sm transition-all active:scale-95 gap-2 group">
            <div class="p-1 bg-white/20 rounded-lg group-hover:rotate-90 transition-transform duration-300">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
            </div>
            Tambah Kategori
        </button>
    </div>

    <!-- Main Card -->
    <div class="bg-white rounded-[2.5rem] shadow-xl shadow-slate-200/60 border border-slate-100 overflow-hidden">
        <!-- Search & Filter Bar -->
        <div class="p-6 border-b border-slate-50 bg-slate-50/30">
            <div class="relative max-w-md">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" id="category-search" oninput="filterCategories()" class="block w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-2xl text-sm placeholder-slate-400 focus:outline-none focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 transition-all font-medium" placeholder="Cari nama kategori...">
            </div>
        </div>

        <!-- Table Container -->
        <div class="p-0 relative min-h-[400px]">
            <table class="w-full text-left border-collapse table-fixed">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] w-24">No</th>
                        <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em]">Nama Kategori</th>
                        <th class="px-8 py-5 text-[11px] font-black text-slate-400 uppercase tracking-[0.2em] text-right w-40">Aksi</th>
                    </tr>
                </thead>
                <tbody id="category-table-body" class="divide-y divide-slate-50">
                    <!-- Data loaded via JS -->
                </tbody>
            </table>

            <!-- Loading State -->
            <div id="table-loading" class="absolute inset-0 flex flex-col items-center justify-center bg-white/80 backdrop-blur-[2px] z-10 gap-4 transition-opacity duration-300">
                <div class="relative">
                    <div class="w-12 h-12 rounded-full border-4 border-slate-100 border-t-cyan-600 animate-spin"></div>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="w-2 h-2 bg-cyan-600 rounded-full animate-pulse"></div>
                    </div>
                </div>
                <span class="text-slate-500 text-xs font-bold uppercase tracking-widest animate-pulse">Sinkronisasi Data...</span>
            </div>

            <!-- Empty State -->
            <div id="table-empty" class="hidden flex flex-col items-center justify-center py-24 px-8 text-center">
                <div class="w-32 h-32 bg-slate-50 rounded-[2.5rem] flex items-center justify-center text-slate-200 mb-6 relative overflow-hidden">
                    <svg class="w-16 h-16 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <div class="absolute -bottom-4 -right-4 w-16 h-16 bg-slate-100 rounded-full blur-2xl"></div>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-1">Belum Ada Kategori</h3>
                <p class="text-slate-400 text-sm max-w-xs mx-auto">Mulai dengan menambahkan kategori baru untuk laporan kerja pegawai Anda.</p>
                <button onclick="openCategoryModal()" class="mt-6 px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold rounded-xl transition-all shadow-lg shadow-slate-200">
                    Tambah Kategori Pertama
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Form -->
<div id="category-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden opacity-0 transition-all duration-300">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCategoryModal()"></div>
    <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden transform transition-all scale-95 relative z-10" id="modal-content">
        <div class="px-8 py-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/30">
            <div>
                <h3 class="text-xl font-black text-slate-800" id="modal-title">Tambah Kategori</h3>
                <p class="text-xs text-slate-400 font-bold mt-0.5 tracking-wide">DETAIL INFORMASI</p>
            </div>
            <button onclick="closeCategoryModal()" class="w-10 h-10 flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form id="category-form" onsubmit="saveCategory(event)" class="p-8">
            <input type="hidden" id="category_id">
            
            <div class="mb-6">
                <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.15em] mb-3">Nama Kategori</label>
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-cyan-500 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                    </div>
                    <input type="text" id="category_name" required class="w-full rounded-2xl border-2 border-slate-100 pl-12 pr-4 py-4 focus:ring-4 focus:ring-cyan-500/10 focus:border-cyan-500 outline-none transition-all bg-slate-50/50 focus:bg-white text-slate-700 font-bold placeholder-slate-300" placeholder="Masukkan nama kategori...">
                </div>
            </div>

            <div class="flex flex-col gap-3 mt-8">
                <button type="submit" id="submit-btn" class="w-full py-4 bg-cyan-600 hover:bg-cyan-700 text-white rounded-2xl font-black text-sm transition-all shadow-lg shadow-cyan-100 flex items-center justify-center gap-3 active:scale-[0.98]">
                    <span>Simpan Kategori</span>
                </button>
                <button type="button" onclick="closeCategoryModal()" class="w-full py-4 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-2xl text-xs font-black uppercase tracking-widest transition-all">
                    Batalkan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Delete Confirmation -->
<div id="delete-category-modal" class="fixed inset-0 z-[70] flex items-center justify-center hidden opacity-0 transition-all duration-300">
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCategoryDeleteModal()"></div>
    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-sm:mx-4 max-w-sm overflow-hidden transform transition-all scale-95 relative z-10" id="delete-modal-content">
        <div class="p-10 text-center">
            <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-[2rem] bg-rose-50 mb-8 relative">
                <div class="absolute inset-0 rounded-[2rem] border-2 border-rose-100/50 animate-ping opacity-20"></div>
                <svg class="h-12 w-12 text-rose-500 relative z-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
            </div>
            <h3 class="text-xl font-black text-slate-800 mb-3">Hapus Kategori?</h3>
            <p class="text-sm text-slate-500 mb-10 leading-relaxed font-medium" id="delete-message">Apakah Anda yakin ingin menghapus kategori ini? Tindakan ini tidak dapat dibatalkan.</p>
            
            <div class="flex flex-col gap-3">
                <button type="button" id="confirm-delete-btn" class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-black text-sm transition-all shadow-lg shadow-rose-100 active:scale-[0.98]">Hapus Permanen</button>
                <button type="button" onclick="closeCategoryDeleteModal()" class="w-full py-4 text-slate-400 hover:text-slate-600 hover:bg-slate-50 rounded-2xl text-xs font-black uppercase tracking-widest transition-all">Batal</button>
            </div>
        </div>
    </div>
</div>

<script>
    let categories = [];
    let deleteId = null;

    async function fetchCategories() {
        showLoading(true);
        try {
            const res = await fetch('<?php echo BASE_URL; ?>/api/work_reports/get_categories.php');
            const data = await res.json();
            if (data.success) {
                categories = data.data;
                renderTable();
            }
        } catch (err) {
            console.error(err);
            showToast("Gagal memuat data sistem.", "error");
        } finally {
            showLoading(false);
        }
    }

    function renderTable(dataToRender = null) {
        const tbody = document.getElementById('category-table-body');
        const emptyState = document.getElementById('table-empty');
        const table = tbody.closest('table');
        tbody.innerHTML = '';
        
        const items = dataToRender || categories;

        if (items.length === 0) {
            emptyState.classList.remove('hidden');
            table.classList.add('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        table.classList.remove('hidden');

        items.forEach((cat, index) => {
            const tr = document.createElement('tr');
            tr.className = "hover:bg-slate-50/50 transition-colors group";
            tr.style.animation = `fadeInUp 0.4s ease forwards ${index * 0.05}s`;
            tr.style.opacity = '0';
            tr.innerHTML = `
                <td class="px-8 py-5">
                    <span class="inline-flex items-center justify-center px-2 py-1 bg-slate-100 text-[10px] font-black text-slate-400 rounded-lg group-hover:bg-cyan-100 group-hover:text-cyan-600 transition-colors">${index + 1}</span>
                </td>
                <td class="px-8 py-5">
                    <div class="text-sm font-bold text-slate-700 group-hover:text-cyan-600 transition-colors">${cat.name}</div>
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-1">
                        <button onclick="openCategoryModal(${cat.id}, '${cat.name}')" class="p-2.5 text-slate-400 hover:text-cyan-600 hover:bg-cyan-50 rounded-xl transition-all" title="Edit Kategori">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                        </button>
                        <button onclick="confirmCategoryDelete(${cat.id}, '${cat.name}')" class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus Kategori">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function filterCategories() {
        const query = document.getElementById('category-search').value.toLowerCase();
        const filtered = categories.filter(cat => cat.name.toLowerCase().includes(query));
        renderTable(filtered);
    }

    function openCategoryModal(id = null, name = '') {
        const modal = document.getElementById('category-modal');
        const content = document.getElementById('modal-content');
        const title = document.getElementById('modal-title');
        
        document.getElementById('category_id').value = id || '';
        document.getElementById('category_name').value = name;
        title.innerText = id ? 'Edit Kategori' : 'Tambah Kategori';

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }

    function closeCategoryModal() {
        const modal = document.getElementById('category-modal');
        const content = document.getElementById('modal-content');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    async function saveCategory(e) {
        e.preventDefault();
        const id = document.getElementById('category_id').value;
        const name = document.getElementById('category_name').value;
        const btn = document.getElementById('submit-btn');

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = `<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div><span>Memproses...</span>`;

        const endpoint = id ? 'update_category.php' : 'create_category.php';
        
        try {
            const res = await fetch(`<?php echo BASE_URL; ?>/api/work_reports/${endpoint}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, name })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, "success");
                closeCategoryModal();
                fetchCategories();
            } else {
                showToast(data.message, "error");
            }
        } catch (err) {
            console.error(err);
            showToast("Terjadi gangguan koneksi.", "error");
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    function confirmCategoryDelete(id, name) {
        deleteId = id;
        document.getElementById('delete-message').innerHTML = `Apakah Anda yakin ingin menghapus kategori <strong>"${name}"</strong>? Data ini akan terhapus selamanya.`;
        const modal = document.getElementById('delete-category-modal');
        const content = document.getElementById('delete-modal-content');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.classList.add('opacity-100');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }

    function closeCategoryDeleteModal() {
        const modal = document.getElementById('delete-category-modal');
        const content = document.getElementById('delete-modal-content');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        modal.classList.remove('opacity-100');
        modal.classList.add('opacity-0');
        setTimeout(() => modal.classList.add('hidden'), 300);
    }

    document.getElementById('confirm-delete-btn').onclick = async () => {
        if (!deleteId) return;
        const btn = document.getElementById('confirm-delete-btn');
        btn.disabled = true;
        const originalText = btn.innerText;
        btn.innerText = 'Menghapus...';

        try {
            const res = await fetch(`<?php echo BASE_URL; ?>/api/work_reports/delete_category.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: deleteId })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, "success");
                closeCategoryDeleteModal();
                fetchCategories();
            } else {
                showToast(data.message, "error");
            }
        } catch (err) {
            console.error(err);
            showToast("Gagal menghapus data.", "error");
        } finally {
            btn.disabled = false;
            btn.innerText = originalText;
        }
    };

    function showLoading(show) {
        const loader = document.getElementById('table-loading');
        if (show) {
            loader.classList.remove('hidden');
            loader.style.opacity = '1';
        } else {
            loader.style.opacity = '0';
            setTimeout(() => loader.classList.add('hidden'), 300);
        }
    }

    document.addEventListener('DOMContentLoaded', fetchCategories);
</script>

<style>
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
