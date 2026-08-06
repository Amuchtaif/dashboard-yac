<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');
$page_title = "Absensi Pengampu Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Parameters ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d'); // Default today
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Build Query ---
$query = "
    SELECT 
        ta.*,
        e.full_name AS teacher_name,
        c.full_name AS coordinator_name
    FROM tahfidz_teacher_attendance ta
    LEFT JOIN employees e ON ta.teacher_id = e.id
    LEFT JOIN employees c ON ta.approved_by = c.id
    WHERE ta.date BETWEEN :start_date AND :end_date
";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

if (!$is_admin) {
    $query .= " AND ta.teacher_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
}

if (!empty($status_filter)) {
    $query .= " AND ta.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (e.full_name LIKE :search OR ta.notes LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY ta.date DESC, ta.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Absensi Pengampu</h1>
            <p class="text-slate-500 mt-1">Data kehadiran guru Tahfidz.</p>
        </div>
    </div>
    <!-- Filter Card -->
    <form id="filter-form" method="GET" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <!-- Search -->
        <div class="relative w-full sm:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari pengampu atau halaqoh..." onchange="this.form.submit()">
        </div>

        <!-- Right side filters -->
        <div class="flex gap-2 w-full sm:w-auto flex-wrap items-center">
            <!-- Status Filter -->
            <div class="relative group" id="filter-status-container">
                <input type="hidden" name="status" id="filter-status-input" value="<?php echo $status_filter; ?>">
                <button type="button" onclick="toggleDropdown('filter-status')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-36">
                    <span id="filter-status-text" class="truncate">
                        <?php
                        $displayStatus = "Semua Status";
                        if ($status_filter) $displayStatus = "Status: " . $status_filter;
                        echo htmlspecialchars($displayStatus);
                        ?>
                    </span>
                    <i id="filter-status-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-status-menu" class="hidden absolute top-full right-0 mt-1 w-36 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('status', '', 'Semua Status')" class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">Semua Status</li>
                        <li onclick="selectFilterOption('status', 'Hadir', 'Status: Hadir')" class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700">Hadir</li>
                        <li onclick="selectFilterOption('status', 'Sakit', 'Status: Sakit')" class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700">Sakit</li>
                        <li onclick="selectFilterOption('status', 'Izin', 'Status: Izin')" class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700">Izin</li>
                        <li onclick="selectFilterOption('status', 'Alpha', 'Status: Alpha')" class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700">Alpha</li>
                    </ul>
                </div>
            </div>

            <!-- Date Range -->
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1">
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="bg-transparent border-none text-xs text-slate-600 focus:ring-0 p-1 w-32" onchange="this.form.submit()">
                <span class="text-slate-400 text-xs">-</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="bg-transparent border-none text-xs text-slate-600 focus:ring-0 p-1 w-32" onchange="this.form.submit()">
            </div>

            <a href="teacher_attendance.php" class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-red-500 focus:outline-none transition-colors" title="Reset Filters">
                <i class="fa-solid fa-xmark h-4 w-4"></i>
            </a>
        </div>
    </form>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-16 text-center">No</th>
                        <th class="px-6 py-4 min-w-[120px]">Tanggal</th>
                        <th class="px-6 py-4 min-w-[200px]">Nama Pengampu</th>
                        <th class="px-6 py-4 min-w-[200px]">Status & Verifikasi</th>
                        <th class="px-6 py-4 text-center min-w-[100px]">Jam Masuk</th>
                        <th class="px-6 py-4 text-center min-w-[100px]">Jam Pulang</th>
                        <th class="px-6 py-4 min-w-[200px]">Halaqoh</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php 
                    $no = 1;
                    if (count($data) > 0): 
                        foreach ($data as $row): 
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors <?php echo ($row['status_approval'] == 'rejected') ? 'bg-red-50 hover:bg-red-100' : ''; ?>">
                        <td class="px-6 py-4 text-center text-slate-400"><?php echo $no++; ?></td>
                        <td class="px-6 py-4 text-slate-600">
                            <?php echo date('d/m/Y', strtotime($row['date'])); ?>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-800">
                            <?php echo htmlspecialchars($row['teacher_name'] ?? 'Unknown'); ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                                $statusClass = 'bg-slate-100 text-slate-600';
                                $s = strtolower($row['status']);
                                if ($s == 'hadir') $statusClass = 'bg-green-100 text-green-700';
                                elseif ($s == 'sakit') $statusClass = 'bg-blue-100 text-blue-700';
                                elseif ($s == 'izin') $statusClass = 'bg-yellow-100 text-yellow-700';
                                elseif ($s == 'alpha') $statusClass = 'bg-red-100 text-red-700';
                            ?>
                            <div class="flex flex-col gap-1.5">
                                <div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </div>
                                
                                <!-- Approval Status -->
                                <?php if ($row['status_approval'] == 'approved'): ?>
                                    <div class="flex items-center text-xs text-green-600">
                                        <i class="fa-solid fa-check w-3.5 h-3.5 mr-1"></i>
                                        Disetujui: <?php echo htmlspecialchars($row['coordinator_name'] ?? 'Administrator'); ?>
                                    </div>
                                <?php elseif ($row['status_approval'] == 'rejected'): ?>
                                    <div class="flex items-start text-xs text-red-600">
                                        <i class="fa-solid fa-circle-xmark w-3.5 h-3.5 mr-1 mt-0.5 flex-shrink-0"></i>
                                        <div>
                                            <span class="font-bold">Ditolak:</span> <?php echo htmlspecialchars($row['rejection_reason'] ?: 'Tanpa alasan'); ?>
                                            <div class="text-[10px] opacity-75 mt-0.5">Oleh: <?php echo htmlspecialchars($row['coordinator_name'] ?? 'Administrator'); ?></div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="flex items-center text-xs text-yellow-600">
                                        <i class="fa-solid fa-clock w-3.5 h-3.5 mr-1"></i>
                                        Menunggu Verifikasi
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo $row['check_in_time'] ? date('H:i', strtotime($row['check_in_time'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo $row['check_out_time'] ? date('H:i', strtotime($row['check_out_time'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-slate-500 italic">
                            <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada data absensi pada periode ini.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        document.getElementById('filter-form').submit();
    }
</script>

<?php include '../layouts/footer.php'; ?>
