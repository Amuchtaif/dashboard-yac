<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Kelola Kepulangan Santri";
$db = new Database();
$conn = $db->getConnection();

// Fetch returns
$returns_query = "
    SELECT br.*, s.nama_siswa, s.kelas
    FROM boarding_returns br
    JOIN students s ON br.student_id = s.id
    ORDER BY br.return_date DESC, br.created_at DESC
";
$returns = $conn->query($returns_query)->fetchAll(PDO::FETCH_ASSOC);

// For adding return tracking
$all_students = $conn->query("SELECT id, nama_siswa, kelas FROM students ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Pantau kehadiran santri setelah masa libur selesai.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-return')" 
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 transition-all font-bold">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                Catat Kepulangan
            </button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 min-w-[200px]">Santri</th>
                        <th class="px-6 py-4 min-w-[150px]">Jadwal Kembali</th>
                        <th class="px-6 py-4 min-w-[120px]">Status</th>
                        <th class="px-6 py-4 min-w-[200px]">Keterangan</th>
                        <th class="px-6 py-4 text-right min-w-[150px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($returns) > 0): ?>
                        <?php foreach ($returns as $r): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-6 py-4">
                                    <p class="font-bold text-slate-800"><?php echo htmlspecialchars($r['nama_siswa']); ?></p>
                                    <p class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($r['kelas']); ?></p>
                                </td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?php echo date('d M Y', strtotime($r['return_date'])); ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $statusColor = $r['status'] == 'Sudah Kembali' ? 'emerald' : 'orange';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $statusColor; ?>-50 text-<?php echo $statusColor; ?>-700 border border-<?php echo $statusColor; ?>-100">
                                        <?php echo $r['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-500 italic max-w-xs truncate">
                                    <?php echo htmlspecialchars($r['description'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($r['status'] == 'Belum Kembali'): ?>
                                        <button onclick="markAsReturned(<?php echo $r['id']; ?>)" class="text-emerald-600 hover:text-emerald-700 font-bold text-xs bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-100">
                                            Sudah Kembali
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="deleteReturn(<?php echo $r['id']; ?>)" class="text-slate-400 hover:text-red-600 p-2 ml-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-20 text-center text-slate-400 italic">Belum ada data kepulangan santri.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add Return -->
<div id="modal-add-return" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-add-return')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md z-10 overflow-hidden border border-slate-200">
            <form action="../../../logic/boarding/manage_returns.php" method="POST">
                <input type="hidden" name="action" value="create_return">
                <div class="p-8">
                    <!-- Modal Header with Bar Kotak Style -->
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-12 w-2 bg-cyan-600 rounded-full"></div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800">Catat Jadwal Kepulangan</h3>
                            <p class="text-xs text-slate-400 font-medium uppercase tracking-widest mt-0.5">Pantau dan catat kepulangan santri</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Santri</label>
                                <select name="student_id" required class="hybrid-select w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3.5 text-sm focus:ring-cyan-500 focus:border-cyan-500 transition-all font-semibold">
                                    <option value="">Pilih Santri...</option>
                                    <?php foreach ($all_students as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_siswa']); ?> (<?php echo htmlspecialchars($s['kelas']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Wajib Kembali Tanggal</label>
                                <input type="date" name="return_date" required class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3.5 text-sm focus:ring-cyan-500 focus:border-cyan-500 transition-all font-bold">
                            </div>
                            <div>
                                <label class="block text-[11px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">Keterangan</label>
                                <textarea name="description" rows="2" placeholder="Ketik keterangan tambahan jika ada..." class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-4 py-3.5 text-sm focus:ring-cyan-500 focus:border-cyan-500 transition-all placeholder:text-slate-300 font-semibold"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-cyan-600 text-white font-bold px-8 py-2.5 rounded-xl hover:bg-cyan-700 transition-all font-bold shadow-lg transform active:scale-95">Catat</button>
                    <button type="button" onclick="closeModal('modal-add-return')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
    
    function markAsReturned(id) {
        if(confirm('Konfirmasi bahwa santri ini sudah kembali?')) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_returns.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='mark_returned'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            document.body.appendChild(f); f.submit();
        }
    }

    function deleteReturn(id) {
        if(confirm('Hapus data kepulangan ini?')) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_returns.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_return'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            document.body.appendChild(f); f.submit();
        }
    }
</script>

<?php include '../../layouts/footer.php'; ?>
