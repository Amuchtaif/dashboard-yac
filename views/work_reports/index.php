<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
// Menggunakan helper can() yang sudah tersedia di app.php
if (!can('manage_employees') && (!isset($_SESSION['position_name']) || $_SESSION['position_name'] !== 'Administrator')) {
    header("Location: ../dashboard/index.php");
    exit();
}

$page_title = "Laporan Kerja Pegawai";

$db = new Database();
$conn = $db->getConnection();

// Filter
$employee_id = $_GET['employee_id'] ?? '';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-d');

$query = "
    SELECT 
        wr.*, 
        e.full_name as employee_name,
        p.name as position_name,
        u.name as unit_name
    FROM work_reports wr
    JOIN employees e ON wr.user_id = e.id
    LEFT JOIN positions p ON e.position_id = p.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE wr.report_date BETWEEN :start AND :end
";

if (!empty($employee_id)) {
    $query .= " AND wr.user_id = :employee_id";
}

$query .= " ORDER BY wr.report_date DESC, wr.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bindParam(':start', $date_start);
$stmt->bindParam(':end', $date_end);
if (!empty($employee_id)) {
    $stmt->bindParam(':employee_id', $employee_id);
}
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua pegawai untuk dropdown filter
$employees_sql = "SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC";
$employees = $conn->query($employees_sql)->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <div class="sm:flex sm:items-center justify-between mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Laporan Kerja Pegawai</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Monitoring aktivitas harian dan laporan kerja seluruh pegawai.</p>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-8">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Pegawai</label>
                <select name="employee_id" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-cyan-500 focus:ring-cyan-500 py-2.5">
                    <option value="">Semua Pegawai</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $employee_id == $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($emp['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Tanggal Mulai</label>
                <input type="date" name="date_start" value="<?php echo $date_start; ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-cyan-500 focus:ring-cyan-500 py-2.5">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Tanggal Selesai</label>
                <input type="date" name="date_end" value="<?php echo $date_end; ?>" class="w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-cyan-500 focus:ring-cyan-500 py-2.5">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-cyan-600/20 transition-all active:scale-95">
                    Filter
                </button>
                <a href="index.php" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-bold rounded-xl transition-all text-center">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Table Section -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tanggal</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pegawai</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Kategori</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Kegiatan</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Deskripsi</th>
                        <th class="px-6 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bukti</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="h-10 w-10 text-slate-200 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="text-sm font-medium text-slate-400">Tidak ada laporan kerja ditemukan untuk periode ini.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($reports as $report): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-[13px] font-bold text-slate-700"><?php echo date('d M Y', strtotime($report['report_date'])); ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="h-8 w-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 text-xs font-bold mr-3 uppercase">
                                        <?php echo substr($report['employee_name'], 0, 1); ?>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-bold text-slate-800"><?php echo htmlspecialchars($report['employee_name']); ?></span>
                                        <span class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($report['unit_name'] ?: '-'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-cyan-50 text-cyan-600 border border-cyan-100 uppercase">
                                    <?php echo htmlspecialchars($report['category']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-[13px] font-bold text-slate-700 truncate max-w-[150px]" title="<?php echo htmlspecialchars($report['title']); ?>">
                                    <?php echo htmlspecialchars($report['title']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-[12px] text-slate-500 line-clamp-2 max-w-[200px]" title="<?php echo htmlspecialchars($report['description']); ?>">
                                    <?php echo htmlspecialchars($report['description']); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($report['evidence_photo']): ?>
                                    <a href="<?php echo url('uploads/work_reports/' . $report['evidence_photo']); ?>" target="_blank" class="inline-flex items-center gap-1.5 text-cyan-600 hover:text-cyan-700 font-bold text-[11px] bg-cyan-50 px-2 py-1 rounded-lg transition-colors border border-cyan-100">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Bukti Foto
                                    </a>
                                <?php else: ?>
                                    <span class="text-slate-300 text-[11px] italic">Tidak ada foto</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
