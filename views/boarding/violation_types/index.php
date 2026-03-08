<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Jenis Pelanggaran";
$db = new Database();
$conn = $db->getConnection();

// Fetch violation types
$types_query = "SELECT * FROM boarding_violation_types ORDER BY category DESC, points ASC";
$types = $conn->query($types_query)->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Daftar kategori dan poin pelanggaran santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-type')" 
                class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 transition-all">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jenis
            </button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-4">No.</th>
                    <th class="px-6 py-4">Masalah/Pelanggaran</th>
                    <th class="px-6 py-4">Kategori</th>
                    <th class="px-6 py-4">Poin</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm italic">
                <?php if (count($types) > 0): ?>
                    <?php foreach ($types as $index => $t): ?>
                        <tr class="hover:bg-slate-50/50 transition-all font-medium">
                            <td class="px-6 py-4 text-slate-400"><?php echo $index + 1; ?>.</td>
                            <td class="px-6 py-4 text-slate-800 font-bold"><?php echo htmlspecialchars($t['type_name']); ?></td>
                            <td class="px-6 py-4">
                                <?php 
                                    $catColor = $t['category'] == 'Berat' ? 'rose' : ($t['category'] == 'Sedang' ? 'orange' : 'emerald');
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-<?php echo $catColor; ?>-50 text-<?php echo $catColor; ?>-700 border border-<?php echo $catColor; ?>-100">
                                    <?php echo $t['category']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono font-bold text-slate-600"><?php echo $t['points']; ?> Poin</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="deleteType(<?php echo $t['id']; ?>)" class="text-slate-400 hover:text-red-600 p-2 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Belum ada data jenis pelanggaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Add Type -->
<div id="modal-add-type" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-add-type')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md z-10 overflow-hidden border border-slate-200">
            <form action="../../../logic/boarding/manage_violations.php" method="POST">
                <input type="hidden" name="action" value="create_type">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Tambah Jenis Pelanggaran</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Nama Pelanggaran</label>
                            <input type="text" name="type_name" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-rose-500 focus:border-rose-500 transition-all">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Kategori</label>
                                <select name="category" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-rose-500 focus:border-rose-500">
                                    <option value="Ringan">Ringan</option>
                                    <option value="Sedang">Sedang</option>
                                    <option value="Berat">Berat</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Poin</label>
                                <input type="number" name="points" value="5" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-rose-500 focus:border-rose-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-rose-600 text-white font-bold px-6 py-2.5 rounded-xl hover:bg-rose-700 transition-all">Simpan</button>
                    <button type="button" onclick="closeModal('modal-add-type')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
    function deleteType(id) {
        if(confirm('Hapus jenis pelanggaran ini?')) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_violations.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_type'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            document.body.appendChild(f); f.submit();
        }
    }
</script>

<?php include '../../layouts/footer.php'; ?>
