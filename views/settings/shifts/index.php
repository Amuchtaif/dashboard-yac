<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_permission('manage_employees');

$page_title = "Tukar Shift";
$db = new Database();
$conn = $db->getConnection();

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Count total records
$total_query = "SELECT COUNT(*) FROM shift_exchanges";
$total_records = $conn->query($total_query)->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch shift exchanges with pagination
$exchanges_query = "
    SELECT se.*, e1.full_name as requester_name, e2.full_name as substitute_name, e3.full_name as approver_name
    FROM shift_exchanges se
    JOIN employees e1 ON se.requester_id = e1.id
    JOIN employees e2 ON se.substitute_id = e2.id
    LEFT JOIN employees e3 ON se.approved_by = e3.id
    ORDER BY se.exchange_date DESC, se.created_at DESC
    LIMIT $limit OFFSET $offset
";
$exchanges = $conn->query($exchanges_query)->fetchAll(PDO::FETCH_ASSOC);

// For adding exchange request
$all_employees = $conn->query("SELECT id, full_name FROM employees ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Kelola permohonan pertukaran shift antar pegawai.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <button onclick="openModal('modal-add-exchange')" 
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-700 transition-all font-bold">
                <svg class="-ml-1 mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Ajukan Tukar Shift
            </button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Pemohon</th>
                        <th class="px-6 py-4">Pengganti</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($exchanges) > 0): ?>
                        <?php foreach ($exchanges as $index => $e): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-6 py-4 text-slate-400 font-medium"><?php echo $offset + $index + 1; ?>.</td>
                                <td class="px-6 py-4 font-mono text-slate-500"><?php echo date('d M Y', strtotime($e['exchange_date'])); ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($e['requester_name']); ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($e['substitute_name']); ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $statusColor = [
                                            'Menunggu' => 'orange',
                                            'Disetujui' => 'emerald',
                                            'Ditolak' => 'rose'
                                        ][$e['status']] ?? 'slate';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-<?php echo $statusColor; ?>-50 text-<?php echo $statusColor; ?>-700 border border-<?php echo $statusColor; ?>-100">
                                        <?php echo $e['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <?php if ($e['status'] == 'Menunggu'): ?>
                                            <button onclick="processExchange(<?php echo $e['id']; ?>, 'Disetujui')" class="text-emerald-600 hover:bg-emerald-50 p-2 rounded-lg" title="Setujui">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            </button>
                                            <button onclick="processExchange(<?php echo $e['id']; ?>, 'Ditolak')" class="text-rose-600 hover:bg-rose-50 p-2 rounded-lg" title="Tolak">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="openDeleteModal('delete.php?id=<?php echo $e['id']; ?>')" class="text-slate-400 hover:text-red-600 p-2 ml-2 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 italic">Belum ada data pertukaran shift.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Menampilkan <span class="font-bold"><?php echo count($exchanges); ?></span> dari <span class="font-bold"><?php echo $total_records; ?></span> data
            </p>
            <div class="flex gap-2">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" 
                       class="px-3 py-1 text-xs font-bold rounded-lg border <?php echo $i == $page ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; ?> transition-all">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Add Exchange -->
<div id="modal-add-exchange" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeModal('modal-add-exchange')"></div>
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg z-10 overflow-hidden border border-slate-200">
            <form action="../../../logic/employees/manage_shifts.php" method="POST">
                <input type="hidden" name="action" value="create_exchange">
                <div class="p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6 font-primary">Ajukan Tukar Shift</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Pemohon (Diri Sendiri)</label>
                            <select name="requester_id" required class="hybrid-select w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-cyan-500 focus:border-cyan-500">
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" <?php echo (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $emp['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Pegawai Pengganti</label>
                            <select name="substitute_id" required class="hybrid-select w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-cyan-500 focus:border-cyan-500">
                                <option value="">Pilih Pegawai...</option>
                                <?php foreach ($all_employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tanggal Pertukaran</label>
                            <input type="date" name="exchange_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-cyan-500 focus:border-cyan-500">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Alasan</label>
                            <textarea name="reason" rows="3" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:ring-cyan-500 focus:border-cyan-500" placeholder="Berikan alasan pertukaran..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="bg-slate-50 p-6 flex flex-row-reverse gap-3">
                    <button type="submit" class="bg-cyan-600 text-white font-bold px-8 py-2.5 rounded-xl hover:bg-cyan-700 transition-all shadow-md">Kirim Permohonan</button>
                    <button type="button" onclick="closeModal('modal-add-exchange')" class="text-slate-600 font-bold px-6 py-2.5">Batal</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); document.body.style.overflow = 'auto'; }
    
    function processExchange(id, status) {
        if(confirm(`Konfirmasi pertukaran shift ini?`)) {
            const f = document.createElement('form'); f.method='POST'; f.action='../../../logic/employees/manage_shifts.php';
            const a = document.createElement('input'); a.type='hidden'; a.name='action'; a.value='process_exchange'; f.appendChild(a);
            const i = document.createElement('input'); i.type='hidden'; i.name='id'; i.value=id; f.appendChild(i);
            const s = document.createElement('input'); s.type='hidden'; s.name='status'; s.value=status; f.appendChild(s);
            document.body.appendChild(f); f.submit();
        }
    }

    function deleteExchange(id) {
        // Obsolete: uses global openDeleteModal now
    }
</script>

<?php include '../../layouts/footer.php'; ?>
