<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Kelola Liburan";
$db = new Database();
$conn = $db->getConnection();

// Fetch holidays
$holidays_query = "SELECT * FROM boarding_holidays ORDER BY start_date DESC";
$holidays = $conn->query($holidays_query)->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Atur jadwal liburan santri yang akan terhubung ke sistem kepulangan mobile.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-holiday')" 
                class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 transition-all font-bold">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Tambah Jadwal Libur
            </button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="px-6 py-4">Keterangan Libur</th>
                        <th class="px-6 py-4">Mulai</th>
                        <th class="px-6 py-4">Selesai</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($holidays) > 0): ?>
                        <?php foreach ($holidays as $h): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800"><?php echo htmlspecialchars($h['name']); ?></p>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?php echo date('d M Y', strtotime($h['start_date'])); ?></td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?php echo date('d M Y', strtotime($h['end_date'])); ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $statusColor = $h['status'] == 'Aktif' ? 'emerald' : 'slate';
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $statusColor; ?>-50 text-<?php echo $statusColor; ?>-700 border border-<?php echo $statusColor; ?>-100">
                                        <?php echo $h['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick="deleteHoliday(<?php echo $h['id']; ?>)" class="text-slate-400 hover:text-red-600 p-2 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Belum ada jadwal libur.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Holiday -->
<div id="modal-add-holiday" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div id="modal-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity opacity-0" onclick="closeModal('modal-add-holiday')"></div>
        <div id="modal-content" class="bg-white rounded-2xl shadow-xl w-full max-w-md z-10 overflow-hidden border border-slate-200 transition-all modal-enter">
            <form action="../../../logic/boarding/manage_holidays.php" method="POST">
                <input type="hidden" name="action" value="create_holiday">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Tambah Jadwal Libur</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama/Keterangan Libur</label>
                            <input type="text" name="name" placeholder="Contoh: Libur Akhir Semester Ganjil" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Mulai</label>
                                <input type="date" name="start_date" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Selesai</label>
                                <input type="date" name="end_date" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-emerald-500 focus:border-emerald-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-emerald-600 text-white font-bold px-8 py-2.5 rounded-xl hover:bg-emerald-700 transition-all font-bold">Simpan Jadwal</button>
                    <button type="button" onclick="closeModal('modal-add-holiday')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById('modal-backdrop');
        const content = document.getElementById('modal-content');
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Trigger animations
        setTimeout(() => {
            backdrop.classList.add('opacity-100');
            content.classList.remove('modal-enter');
            content.classList.add('modal-enter-active');
        }, 10);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        const backdrop = document.getElementById('modal-backdrop');
        const content = document.getElementById('modal-content');
        
        backdrop.classList.remove('opacity-100');
        content.classList.remove('modal-enter-active');
        content.classList.add('modal-exit-active');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            content.classList.remove('modal-exit-active');
            content.classList.add('modal-enter');
            document.body.style.overflow = 'auto';
        }, 300);
    }

    function deleteHoliday(id) {
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        if (!confirmBtn) return;

        confirmBtn.onclick = (e) => {
            e.preventDefault();
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_holidays.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_holiday'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            document.body.appendChild(f); f.submit();
        };

        openDeleteModal('#');
    }
</script>

<?php include '../../layouts/footer.php'; ?>
