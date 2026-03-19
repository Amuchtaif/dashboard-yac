<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Kelola Izin Santri";
$db = new Database();
$conn = $db->getConnection();

// Fetch permits
$permits_query = "
    SELECT bp.*, s.nama_siswa, s.kelas, e.full_name as approver_name
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    LEFT JOIN employees e ON bp.approved_by = e.id
    ORDER BY bp.created_at DESC
";
$permits = $conn->query($permits_query)->fetchAll(PDO::FETCH_ASSOC);

// For adding permit
$all_students = $conn->query("SELECT id, nama_siswa, kelas FROM students ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Kelola permohonan izin keluar/pulang santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-permit')" 
                class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-all font-bold">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Buat Surat Izin
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4">
        <?php if (count($permits) > 0): ?>
            <?php foreach ($permits as $p): ?>
                <div class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition-all">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 font-bold border border-slate-200 flex-shrink-0">
                                <?php echo substr($p['nama_siswa'], 0, 1); ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($p['nama_siswa']); ?> <span class="text-xs text-slate-400 font-medium ml-2 font-mono"><?php echo htmlspecialchars($p['kelas']); ?></span></h3>
                                <p class="text-sm text-slate-600 mt-1 font-medium"><span class="text-slate-400">Alasan:</span> <?php echo htmlspecialchars($p['reason']); ?></p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3">
                                    <div class="flex items-center text-[11px] text-slate-400 font-bold uppercase tracking-wider">
                                        <svg class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <?php echo date('d M Y H:i', strtotime($p['start_date'])); ?> s/d <?php echo date('d M Y H:i', strtotime($p['end_date'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <?php 
                                $statusClasses = [
                                    'Menunggu' => 'bg-orange-50 text-orange-700 border-orange-100',
                                    'Disetujui' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                    'Ditolak' => 'bg-rose-50 text-rose-700 border-rose-100',
                                    'Kembali' => 'bg-indigo-50 text-indigo-700 border-indigo-100'
                                ];
                                $cls = $statusClasses[$p['status']] ?? 'bg-slate-50 text-slate-700 border-slate-100';
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border <?php echo $cls; ?>">
                                <?php echo $p['status']; ?>
                            </span>

                            <div class="flex items-center gap-1">
                                <?php if ($p['status'] == 'Menunggu'): ?>
                                    <button onclick="updatePermitStatus(<?php echo $p['id']; ?>, 'Disetujui')" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors" title="Setujui">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    </button>
                                    <button onclick="updatePermitStatus(<?php echo $p['id']; ?>, 'Ditolak')" class="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Tolak">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                <?php elseif ($p['status'] == 'Disetujui'): ?>
                                    <button onclick="updatePermitStatus(<?php echo $p['id']; ?>, 'Kembali')" class="px-3 py-1.5 bg-indigo-600 text-white text-[10px] font-bold rounded-lg hover:bg-indigo-700 shadow-sm" title="Konfirmasi Kembali">
                                        Sudah Kembali
                                    </button>
                                <?php endif; ?>
                                <button onclick="deletePermit(<?php echo $p['id']; ?>)" class="p-2 text-slate-400 hover:text-red-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php if ($p['approver_name']): ?>
                        <div class="mt-4 pt-4 border-t border-slate-50 flex items-center gap-2">
                             <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">Diproses oleh: <span class="text-slate-600 font-bold not-italic"><?php echo htmlspecialchars($p['approver_name']); ?></span></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="py-20 bg-white border border-dashed border-slate-300 rounded-2xl text-center flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-slate-200 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                <p class="text-slate-400 font-medium">Belum ada permohonan izin.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Add Permit -->
<div id="modal-add-permit" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-add-permit')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg z-10 overflow-hidden border border-slate-200">
            <form action="../../../logic/boarding/manage_permits.php" method="POST">
                <input type="hidden" name="action" value="create_permit">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Buat Permohonan Izin</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Santri</label>
                            <select name="student_id" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Pilih Santri...</option>
                                <?php foreach ($all_students as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_siswa']); ?> (<?php echo htmlspecialchars($s['kelas']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Kepulangan</label>
                            <select name="category" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="Izin">Izin Pulang (Urusan Keluarga, dll)</option>
                                <option value="Sakit">Izin Sakit</option>
                                <option value="Libur">Masa Libur</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Alasan Izin</label>
                            <input type="text" name="reason" placeholder="Contoh: Sakit / Urusan Keluarga Penting" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Mulai</label>
                                <input type="datetime-local" name="start_date" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Hingga</label>
                                <input type="datetime-local" name="end_date" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-indigo-600 text-white font-bold px-8 py-2.5 rounded-xl hover:bg-indigo-700 transition-all font-bold shadow-md">Ajukan Izin</button>
                    <button type="button" onclick="closeModal('modal-add-permit')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
    
    function updatePermitStatus(id, status) {
        if(confirm(`Ubah status izin menjadi ${status}?`)) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_permits.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='update_status'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            const s = document.createElement('input'); s.type='hidden'; s.name='status'; s.value=status; f.appendChild(s);
            document.body.appendChild(f); f.submit();
        }
    }

    function deletePermit(id) {
        if(confirm('Hapus data izin ini?')) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/boarding/manage_permits.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='delete_permit'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            document.body.appendChild(f); f.submit();
        }
    }
</script>

<?php include '../../layouts/footer.php'; ?>
