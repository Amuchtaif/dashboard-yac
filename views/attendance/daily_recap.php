<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Rekap Harian Absensi";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Ambil List Bidang & Unit untuk Filter
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$units = $conn->query("SELECT id, name FROM units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE Clause (Base Employees)
$where_emp = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params_emp = [':target_date' => $target_date];

if ($search) {
    $where_emp .= " AND e.full_name LIKE :search ";
    $params_emp[':search'] = "%$search%";
}
if ($division_id) {
    $where_emp .= " AND e.division_id = :division_id ";
    $params_emp[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_emp .= " AND e.unit_id = :unit_id ";
    $params_emp[':unit_id'] = $unit_id;
}

// 1. Query Belum Absen
$query_absent = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where_emp
    AND e.id NOT IN (SELECT user_id FROM attendances WHERE date = :target_date)
    ORDER BY e.full_name ASC
";

$stmt_absent = $conn->prepare($query_absent);
foreach ($params_emp as $key => $val) {
    $stmt_absent->bindValue($key, $val);
}
$stmt_absent->execute();
$absent_employees = $stmt_absent->fetchAll(PDO::FETCH_ASSOC);

// 2. Query Telat
$query_late = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name,
        a.time_in as check_in_time,
        a.status as attendance_status
    FROM attendances a
    JOIN employees e ON a.user_id = e.id
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    " . str_replace('e.', 'e.', $where_emp) . "
    AND a.date = :target_date
    AND a.status IN ('Telat', 'Late')
    ORDER BY a.time_in ASC
";

$stmt_late = $conn->prepare($query_late);
foreach ($params_emp as $key => $val) {
    $stmt_late->bindValue($key, $val);
}
$stmt_late->execute();
$late_employees = $stmt_late->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center mb-8">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Rekap Harian Absensi</h1>
            <p class="mt-2 text-sm text-slate-500">Monitoring pegawai yang belum absen dan terlambat pada tanggal tertentu.</p>
        </div>
        <div class="mt-4 sm:ml-16 sm:mt-0 flex gap-3">
            <a href="export_daily_recap_excel.php?<?php echo http_build_query($_GET); ?>" target="_blank"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                <svg class="-ml-1 mr-2 h-4 w-4 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                Export to Excel
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="mb-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
            <!-- Search by Name -->
            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Nama Pegawai</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari nama..."
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 px-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all text-slate-700 font-medium outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Tanggal</label>
                <input type="date" name="date" value="<?php echo $target_date; ?>"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all text-slate-700 font-medium">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Filter Bidang</label>
                <select name="division_id" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all appearance-none cursor-pointer text-slate-700 font-medium">
                    <option value="">Semua Bidang</option>
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $division_id == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-400 uppercase mb-2 ml-1 tracking-wider">Filter Unit</label>
                <select name="unit_id" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 pl-4 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/10 focus:bg-white transition-all appearance-none cursor-pointer text-slate-700 font-medium">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 inline-flex items-center justify-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 transition-all active:scale-[0.98]">
                    Filter
                </button>
                <?php if ($search || $division_id || $unit_id || $target_date != date('Y-m-d')): ?>
                    <a href="?" class="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-orange-50 text-orange-600 hover:bg-orange-100 transition-all active:scale-95 border border-orange-100 shrink-0" title="Bersihkan Filter">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                            <path d="M3 3v5h5"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Table 1: Belum Absen -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center">
                    <span class="w-2 h-2 rounded-full bg-rose-500 mr-2"></span>
                    Belum Absen
                </h3>
                <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 text-[10px] font-black uppercase">
                    <?php echo count($absent_employees); ?> Pegawai
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-3 w-16 text-center">No.</th>
                            <th class="px-6 py-3">Nama Pegawai</th>
                            <th class="px-6 py-3">Unit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (count($absent_employees) > 0): ?>
                            <?php foreach ($absent_employees as $index => $row): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 text-center text-slate-400 font-medium"><?php echo $index + 1; ?>.</td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($row['division_name'] ?? '-'); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-1 text-[10px] font-bold text-slate-600 border border-slate-200 uppercase">
                                            <?php echo htmlspecialchars($row['unit_name'] ?? '-'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-slate-400 text-xs italic">
                                    Semua pegawai sudah melakukan absensi.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Table 2: Telat -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center">
                    <span class="w-2 h-2 rounded-full bg-amber-500 mr-2"></span>
                    Pegawai Terlambat (Telat)
                </h3>
                <span class="px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 text-[10px] font-black uppercase">
                    <?php echo count($late_employees); ?> Pegawai
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                            <th class="px-6 py-3 w-16 text-center">No.</th>
                            <th class="px-6 py-3">Nama Pegawai</th>
                            <th class="px-6 py-3 text-center">Waktu Absen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (count($late_employees) > 0): ?>
                            <?php foreach ($late_employees as $index => $row): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-6 py-4 text-center text-slate-400 font-medium"><?php echo $index + 1; ?>.</td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($row['full_name']); ?></div>
                                        <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($row['unit_name'] ?? '-'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center rounded-xl bg-rose-50 px-3 py-1 text-xs font-black text-rose-700 ring-1 ring-inset ring-rose-500/20">
                                            <?php echo date('H:i:s', strtotime($row['check_in_time'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-6 py-10 text-center text-slate-400 text-xs italic">
                                    Tidak ada pegawai yang terlambat hari ini.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
