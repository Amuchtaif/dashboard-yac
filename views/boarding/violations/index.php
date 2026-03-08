<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('can_access_kesantrian');

$page_title = "Kelola Pelanggaran";
$db = new Database();
$conn = $db->getConnection();

// Fetch violations
$violations_query = "
    SELECT bv.*, s.nama_siswa, s.kelas, bvt.type_name, bvt.category, bvt.points, e.full_name as reporter_name
    FROM boarding_violations bv
    JOIN students s ON bv.student_id = s.id
    JOIN boarding_violation_types bvt ON bv.type_id = bvt.id
    JOIN employees e ON bv.reporter_id = e.id
    ORDER BY bv.date DESC, bv.created_at DESC
";
$violations = $conn->query($violations_query)->fetchAll(PDO::FETCH_ASSOC);

// Fetch total points per student
$points_summary_query = "
    SELECT s.nama_siswa, s.kelas, SUM(bvt.points) as total_points
    FROM boarding_violations bv
    JOIN students s ON bv.student_id = s.id
    JOIN boarding_violation_types bvt ON bv.type_id = bvt.id
    GROUP BY s.id
    ORDER BY total_points DESC
    LIMIT 10
";
$points_summary = $conn->query($points_summary_query)->fetchAll(PDO::FETCH_ASSOC);

// For adding violation
$all_students = $conn->query("SELECT id, nama_siswa, kelas FROM students ORDER BY nama_siswa ASC")->fetchAll(PDO::FETCH_ASSOC);
$violation_types = $conn->query("SELECT id, type_name, points, category FROM boarding_violation_types ORDER BY category DESC")->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Catat dan pantau kedisiplinan santri.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-violation')" 
                class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 transition-all font-bold">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Input Pelanggaran
            </button>
        </div>
    </div>

    <!-- Stats summary -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-bold text-slate-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    Daftar Pelanggaran Terbaru
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                            <th class="px-6 py-4">Tanggal</th>
                            <th class="px-6 py-4">Santri</th>
                            <th class="px-6 py-4">Pelanggaran</th>
                            <th class="px-6 py-4">Poin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if (count($violations) > 0): ?>
                            <?php foreach ($violations as $v): ?>
                                <tr class="hover:bg-slate-50/50 transition-all">
                                    <td class="px-6 py-4 font-mono text-slate-500"><?php echo date('d/m/Y', strtotime($v['date'])); ?></td>
                                    <td class="px-6 py-4">
                                        <p class="font-bold text-slate-800"><?php echo htmlspecialchars($v['nama_siswa']); ?></p>
                                        <p class="text-xs text-slate-400"><?php echo htmlspecialchars($v['kelas']); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="text-slate-700 font-bold italic"><?php echo htmlspecialchars($v['type_name']); ?></p>
                                        <p class="text-xs text-slate-500 max-w-xs truncate"><?php echo htmlspecialchars($v['description']); ?></p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="text-red-600 font-bold">+<?php echo $v['points']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-6 py-20 text-center text-slate-400 italic">Belum ada catatan pelanggaran.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-red-50/30">
                    <h3 class="font-bold text-red-800 flex items-center gap-2 text-sm italic">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                        Poin Tertinggi (Top 10)
                    </h3>
                </div>
                <div class="p-6 space-y-4">
                    <?php if (count($points_summary) > 0): ?>
                        <?php foreach ($points_summary as $s): ?>
                            <div class="flex items-center justify-between group">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-400 border border-slate-200">
                                        <?php echo substr($s['nama_siswa'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($s['nama_siswa']); ?></p>
                                        <p class="text-[10px] text-slate-400 uppercase font-medium tracking-tight"><?php echo htmlspecialchars($s['kelas']); ?></p>
                                    </div>
                                </div>
                                <span class="bg-red-600 text-white text-[10px] font-bold px-2 py-1 rounded-md shadow-sm">
                                    <?php echo $s['total_points']; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center py-4 text-xs text-slate-400 italic font-medium">Data akumulasi belum tersedia.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Violation -->
<div id="modal-add-violation" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-add-violation')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg z-10 overflow-hidden border border-slate-200">
            <form action="../../../logic/boarding/manage_violations.php" method="POST">
                <input type="hidden" name="action" value="create_violation">
                <input type="hidden" name="reporter_id" value="<?php echo $_SESSION['user_id']; ?>">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6">Input Pelanggaran Baru</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal</label>
                                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-red-500 focus:border-red-500 transition-all">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Santri</label>
                                <select name="student_id" required class="hybrid-select w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-red-500 focus:border-red-500">
                                    <option value="">Pilih Santri...</option>
                                    <?php foreach ($all_students as $s): ?>
                                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['nama_siswa']); ?> (<?php echo htmlspecialchars($s['kelas']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Jenis Pelanggaran</label>
                            <select name="type_id" required class="hybrid-select w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-red-500 focus:border-red-500">
                                <option value="">Pilih Pelanggaran...</option>
                                <?php foreach ($violation_types as $t): ?>
                                    <option value="<?php echo $t['id']; ?>">[<?php echo $t['category']; ?>] <?php echo htmlspecialchars($t['type_name']); ?> (+<?php echo $t['points']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Keterangan / Kronologi</label>
                            <textarea name="description" rows="3" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-red-500 focus:border-red-500" placeholder="Jelaskan kronologi kejadian jika diperlukan..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-red-600 text-white font-bold px-8 py-2.5 rounded-xl hover:bg-red-700 transition-all shadow-md">Catat Sekarang</button>
                    <button type="button" onclick="closeModal('modal-add-violation')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
</script>

<?php include '../../layouts/footer.php'; ?>
