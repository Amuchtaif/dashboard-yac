<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';
require_once '../../../config/permission.php';

check_permission('can_access_kesantrian');

$page_title = "Kelola Petugas Pelanggaran";
$db = new Database();

require_once '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="p-2 bg-white border border-slate-200 rounded-xl text-slate-400 hover:text-slate-600 transition-all shadow-sm">
                    <i class="fa-solid fa-arrow-left w-5 h-5"></i>
                </a>
                <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            </div>
            <p class="text-slate-500 mt-1 ml-12">Tunjuk karyawan yang berwenang menindaklanjuti laporan pelanggaran.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openAddModal()" 
                class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Petugas
            </button>
        </div>
    </div>

    <!-- Officers Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-12 text-center">No.</th>
                        <th class="px-6 py-4">Karyawan</th>
                        <th class="px-6 py-4">Jabatan</th>
                        <th class="px-6 py-4 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody id="officer-list" class="divide-y divide-slate-100 text-sm">
                    <tr>
                        <td colspan="4" class="px-6 py-20 text-center text-slate-400 italic">Memuat data petugas...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Officer -->
<div id="modal-officer" class="fixed inset-0 z-[60] hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md border border-slate-200 transform transition-all">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-xl font-bold text-slate-800">Tunjuk Petugas</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-2 hover:bg-slate-50 rounded-xl">
                    <i class="fa-solid fa-xmark h-6 w-6"></i>
                </button>
            </div>
            
            <form id="officer-form" class="p-8 space-y-6">
                <div class="space-y-2">
                    <label class="block text-[11px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Karyawan</label>
                    <select id="form-employee" name="employee_id" class="hybrid-select" required>
                        <option value="">Cari karyawan...</option>
                    </select>
                </div>

                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="closeModal()" class="px-6 py-3 text-sm font-bold text-slate-400 hover:text-slate-600 transition-colors">Batal</button>
                    <button type="submit" class="px-10 py-3 bg-indigo-600 text-white text-sm font-bold rounded-2xl shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all">Simpan Petugas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        loadOfficers();
        loadEmployees();
    });

    async function loadEmployees() {
        try {
            const res = await fetch('<?php echo url('api/violation_officers/get_employees.php'); ?>');
            const result = await res.json();
            if (result.success) {
                const select = document.getElementById('form-employee');
                result.data.forEach(e => {
                    const opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = `${e.full_name} (${e.nik})`;
                    select.appendChild(opt);
                });
                if (window.initHybridSelects) initHybridSelects();
            }
        } catch (e) {
            console.error('Failed to load employees');
        }
    }

    async function loadOfficers() {
        const list = document.getElementById('officer-list');
        try {
            const res = await fetch('<?php echo url('api/violation_officers/list.php'); ?>');
            const result = await res.json();
            
            if (result.success) {
                if (result.data.length === 0) {
                    list.innerHTML = '<tr><td colspan="4" class="px-6 py-20 text-center text-slate-400 italic">Belum ada petugas ditunjuk</td></tr>';
                    return;
                }
                
                list.innerHTML = result.data.map((o, i) => `
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-center font-mono text-slate-400">${i + 1}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-800">${o.full_name}</span>
                                <span class="text-[10px] text-slate-400 font-mono tracking-tight">${o.nik}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-slate-600">${o.position_name || '-'}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button onclick="deleteOfficer(${o.id})" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus Petugas">
                                <i class="fa-solid fa-trash w-5 h-5"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (e) {
            list.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-rose-500 font-bold">Gagal memuat petugas</td></tr>';
        }
    }

    function openAddModal() {
        document.getElementById('modal-officer').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('modal-officer').classList.add('hidden');
    }

    document.getElementById('officer-form').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());

        try {
            const res = await fetch('<?php echo url('api/violation_officers/add.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message);
                closeModal();
                loadOfficers();
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Gagal menambahkan petugas', 'error');
        }
    };

    async function deleteOfficer(id) {
        if (!confirm('Hapus status petugas karyawan ini?')) return;
        try {
            const res = await fetch('<?php echo url('api/violation_officers/delete.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
            const result = await res.json();
            if (result.success) {
                showToast(result.message);
                loadOfficers();
            } else {
                showToast(result.message, 'error');
            }
        } catch (e) {
            showToast('Gagal menghapus data', 'error');
        }
    }
</script>

<?php require_once '../../layouts/footer.php'; ?>
