<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Perizinan";

$db = new Database();
$conn = $db->getConnection();

// --- Stats Logic ---
$stats = [
    'pending' => 0,
    'leave_today' => 0,
    'approval_rate' => 0
];

// 1. Pending Requests
$stats['pending'] = $conn->query("SELECT COUNT(*) FROM permits WHERE status = 'Pending'")->fetchColumn();

// 2. Staff on Leave Today
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM permits WHERE status = 'Approved' AND start_date <= :today AND end_date >= :today");
$stmt->execute([':today' => $today]);
$stats['leave_today'] = $stmt->fetchColumn();

// 3. Monthly Approval Rate
$currentMonth = date('Y-m');
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved
    FROM permits 
    WHERE DATE_FORMAT(created_at, '%Y-%m') = :currentMonth");
$stmt->execute([':currentMonth' => $currentMonth]);
$rateData = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['approval_rate'] = ($rateData['total'] > 0) ? round(($rateData['approved'] / $rateData['total']) * 100, 1) : 0;


// --- Filter & Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all'; // all, Pending, Approved, Rejected
$permit_type = isset($_GET['type']) ? $_GET['type'] : '';

$where_clauses = ["1=1"];
$params = [];

if ($tab !== 'all') {
    $where_clauses[] = "p.status = :status";
    $params[':status'] = $tab;
}
if ($permit_type) {
    $where_clauses[] = "p.permit_type = :type";
    $params[':type'] = $permit_type;
}

$where_sql = implode(" AND ", $where_clauses);

$query = "
    SELECT p.*, e.full_name, e.position_id, pos.name as position_name, DATEDIFF(p.end_date, p.start_date) + 1 as duration
    FROM permits p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
";

// Count for pagination
$count_query = "SELECT COUNT(*) FROM permits p JOIN employees e ON p.employee_id = e.id WHERE $where_sql";

$total_stmt = $conn->prepare($count_query);
foreach ($params as $key => $val) {
    $total_stmt->bindValue($key, $val);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="min-h-screen pb-10">

    <!-- Top Header Section -->
    <div class="flex justify-between items-start mb-8 pt-6">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-500 mb-1">
                <span>Dashboard</span>
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-cyan-600">Manajemen Perizinan</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900">Manajemen Perizinan</h1>
            <p class="mt-1 text-slate-500">Tinjau dan kelola pengajuan izin dari organisasi Anda.</p>
        </div>
        <a href="<?php url('views/permits/create.php'); ?>"
            class="bg-cyan-50 text-cyan-700 hover:bg-cyan-100 px-4 py-2 rounded-lg text-sm font-medium flex items-center transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Pengajuan Manual
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Card 1 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-orange-50 rounded-lg">
                    <svg class="w-6 h-6 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">+5% vs minggu
                    lalu</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Menunggu Persetujuan</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['pending']; ?></h3>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-blue-50 rounded-lg">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </div>
                <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-1 rounded-full">-2% vs minggu
                    lalu</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Staf Izin Hari Ini</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['leave_today']; ?></h3>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div class="p-3 bg-green-50 rounded-lg">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <span class="text-xs font-medium text-green-600 bg-green-50 px-2 py-1 rounded-full">+1% vs bulan
                    lalu</span>
            </div>
            <div class="mt-4">
                <p class="text-slate-500 text-sm font-medium">Tingkat Persetujuan Bulanan</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['approval_rate']; ?>%</h3>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">

        <!-- Tabs -->
        <div class="border-b border-slate-200 px-6">
            <nav class="flex space-x-8" aria-label="Tabs">
                <?php
                $tabs = [
                    'all' => 'Semua Pengajuan',
                    'Pending' => 'Menunggu',
                    'Approved' => 'Disetujui',
                    'Rejected' => 'Ditolak'
                ];
                foreach ($tabs as $key => $label):
                    $isActive = ($tab === $key);
                    $badge = ($key === 'Pending' && $stats['pending'] > 0) ? '<span class="ml-2 bg-orange-100 text-orange-600 py-0.5 px-2 rounded-full text-xs font-medium">' . $stats['pending'] . '</span>' : '';
                    ?>
                    <a href="?tab=<?php echo $key; ?>"
                        class="<?php echo $isActive ? 'border-cyan-500 text-cyan-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center">
                        <?php echo $label; ?>
                        <?php echo $badge; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Toolbar (Filters) -->
        <div
            class="p-6 flex flex-col sm:flex-row justify-between items-center bg-white border-b border-slate-100 gap-4">
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <form action="" method="GET" class="flex gap-3 items-center">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">

                    <!-- Type Filter -->
                    <div class="relative">
                        <select name="type" onchange="this.form.submit()"
                            class="appearance-none bg-white border border-slate-300 text-slate-700 py-2 pl-4 pr-10 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                            <option value="">Jenis Izin: Semua</option>
                            <option value="Sick" <?php echo $permit_type == 'Sick' ? 'selected' : ''; ?>>Sakit</option>
                            <option value="Leave" <?php echo $permit_type == 'Leave' ? 'selected' : ''; ?>>Cuti Tahunan</option>
                            <option value="Hourly" <?php echo $permit_type == 'Hourly' ? 'selected' : ''; ?>>Izin Sementara (Jam)</option>
                            <option value="Other" <?php echo $permit_type == 'Other' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>

                    <!-- Limit Filter -->
                    <div class="relative">
                        <select name="limit" onchange="this.form.submit()"
                            class="appearance-none bg-white border border-slate-300 text-slate-700 py-2 pl-4 pr-10 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-cyan-500">
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 baris</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 baris</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 baris</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                </form>
            </div>

            <div class="text-sm text-slate-500">
                Menampilkan <?php echo count($permits); ?> hasil
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 border-b border-slate-100">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                        <th scope="col" class="px-6 py-4 w-12">No.</th>
                        <th scope="col" class="px-6 py-4 min-w-[200px] text-left">Pegawai</th>
                        <th scope="col" class="px-6 py-4 min-w-[250px] text-left">Izin & Alasan</th>
                        <th scope="col" class="px-6 py-4 min-w-[150px] text-left">Periode</th>
                        <th scope="col" class="px-6 py-4 min-w-[100px] text-center">Lampiran</th>
                        <th scope="col" class="px-6 py-4 min-w-[120px] text-left">Status</th>
                        <th scope="col" class="px-6 py-4 text-right">Opsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($permits)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                                Tidak ada pengajuan ditemukan.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php
                    $months = ['Jan' => 'Jan', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Apr', 'May' => 'Mei', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Agu', 'Sep' => 'Sep', 'Oct' => 'Okt', 'Nov' => 'Nov', 'Dec' => 'Des'];
                    foreach ($permits as $index => $permit):
                        ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <!-- No -->
                            <td class="px-6 py-4 text-center text-slate-500 font-medium">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <!-- Employee -->
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <img class="h-10 w-10 rounded-full object-cover"
                                        src="https://ui-avatars.com/api/?name=<?php echo urlencode($permit['full_name']); ?>&background=random"
                                        alt="">
                                    <div class="ml-4">
                                        <div class="font-medium text-slate-900">
                                            <?php echo htmlspecialchars($permit['full_name']); ?>
                                        </div>
                                        <div class="text-slate-500 text-xs">
                                            <?php echo htmlspecialchars($permit['position_name'] ?? 'Pegawai'); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Type & Reason -->
                            <td class="px-6 py-4">
                                <?php
                                $typeClass = '';
                                $typeLabel = '';
                                switch ($permit['permit_type']) {
                                    case 'Sick':
                                        $typeClass = 'bg-red-50 text-red-600 border border-red-100';
                                        $typeLabel = 'SAKIT';
                                        break;
                                    case 'Leave':
                                        $typeClass = 'bg-purple-50 text-purple-600 border border-purple-100';
                                        $typeLabel = 'CUTI TAHUNAN';
                                        break;
                                    case 'Hourly':
                                        $typeClass = 'bg-cyan-50 text-cyan-600 border border-cyan-100';
                                        $typeLabel = 'IZIN SEMENTARA (JAM)';
                                        break;
                                    case 'Other':
                                        $typeClass = 'bg-blue-50 text-blue-600 border border-blue-100';
                                        $typeLabel = 'LAINNYA';
                                        break;
                                    default:
                                        $typeClass = 'bg-gray-50 text-gray-600';
                                        $typeLabel = strtoupper($permit['permit_type']);
                                }
                                ?>
                                <div class="mb-1">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold <?php echo $typeClass; ?>">
                                        <?php echo $typeLabel; ?>
                                    </span>
                                </div>
                                <p class="text-slate-600 text-xs line-clamp-2"
                                    title="<?php echo htmlspecialchars($permit['reason']); ?>">
                                    <?php echo htmlspecialchars($permit['reason']); ?>
                                </p>
                            </td>

                            <!-- Period -->
                            <td class="px-6 py-4">
                                <div class="text-slate-900 font-medium text-xs">
                                    <?php
                                    $startMonth = date('M', strtotime($permit['start_date']));
                                    echo ($months[$startMonth] ?? $startMonth) . ' ' . date('d', strtotime($permit['start_date']));
                                    
                                    if (!$permit['is_hourly'] && $permit['start_date'] !== $permit['end_date']) {
                                        $endMonth = date('M', strtotime($permit['end_date']));
                                        echo ' - ' . ($months[$endMonth] ?? $endMonth) . ' ' . date('d', strtotime($permit['end_date']));
                                    }
                                    ?>
                                </div>
                                <div class="text-slate-500 text-[10px] mt-0.5">
                                    <?php if ($permit['is_hourly']): ?>
                                        <span class="flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <?php echo date('H:i', strtotime($permit['start_time'])); ?> - <?php echo date('H:i', strtotime($permit['end_time'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo $permit['duration']; ?> hari
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Attachment -->
                            <td class="px-6 py-4 text-center">
                                <?php if (!empty($permit['attachment'])): ?>
                                    <button type="button" 
                                       onclick="openImageModal('<?php echo BASE_URL; ?>/uploads/permits/<?php echo $permit['attachment']; ?>')"
                                       class="inline-flex items-center p-1.5 bg-cyan-50 text-cyan-600 rounded-lg hover:bg-cyan-100 transition-colors"
                                       title="Lihat Lampiran">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="px-6 py-4">
                                <?php
                                $statusColor = 'slate';
                                $statusText = $permit['status'];
                                if ($permit['status'] == 'Approved') {
                                    $statusColor = 'green';
                                    $statusText = 'Disetujui';
                                }
                                if ($permit['status'] == 'Pending') {
                                    $statusColor = 'orange';
                                    $statusText = 'Menunggu';
                                }
                                if ($permit['status'] == 'Rejected') {
                                    $statusColor = 'red';
                                    $statusText = 'Ditolak';
                                }
                                ?>
                                <div class="flex items-center text-<?php echo $statusColor; ?>-600 font-medium text-sm">
                                    <span class="h-2 w-2 rounded-full bg-<?php echo $statusColor; ?>-500 mr-2"></span>
                                    <?php echo $statusText; ?>
                                </div>
                            </td>
                            <!-- Action -->
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <?php if ($permit['status'] === 'Pending'): ?>
                                        <!-- Approve -->
                                        <a href="javascript:void(0)" 
                                           class="p-2 text-emerald-500 hover:bg-emerald-50 rounded-lg transition-all" 
                                           title="Setujui"
                                           onclick="openConfirmModal('<?php echo BASE_URL; ?>/logic/permits/quick_action.php?id=<?= $permit['id'] ?>&action=approve', 'Setujui Izin', 'Apakah Anda yakin ingin menyetujui pengajuan izin ini?', 'emerald')">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </a>
                                        <!-- Reject -->
                                        <a href="javascript:void(0)" 
                                           class="p-2 text-amber-500 hover:bg-amber-50 rounded-lg transition-all" 
                                           title="Tolak"
                                           onclick="openConfirmModal('<?php echo BASE_URL; ?>/logic/permits/quick_action.php?id=<?= $permit['id'] ?>&action=reject', 'Tolak Izin', 'Apakah Anda yakin ingin menolak pengajuan izin ini?', 'amber')">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Delete -->
                                    <button onclick="openDeleteModal('<?php url('logic/permits/delete.php?id=' . $permit['id']); ?>')" 
                                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all" 
                                            title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
            <!-- Mobile Pagination Info -->
            <div class="flex sm:hidden flex-col items-center gap-2 w-full">
                <p class="text-xs text-slate-500">
                    Menampilkan <span class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-all">Next</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desktop Pagination Info -->
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-600 font-medium">
                        Menampilkan <span class="text-slate-900 font-bold"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> sampai <span class="text-slate-900 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="text-slate-900 font-bold"><?php echo $total_rows; ?></span> hasil
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination">
                        <!-- Previous -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>&limit=<?php echo $limit; ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                <span class="sr-only">Previous</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>

                        <!-- Numbers -->
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <a href="?page=<?php echo $i; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>&limit=<?php echo $limit; ?>"
                                    class="relative inline-flex items-center px-4 py-2 text-sm font-bold <?php echo $i === $page ? 'bg-cyan-600 text-white' : 'text-slate-700 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400 bg-slate-50/50">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&tab=<?php echo $tab; ?>&type=<?php echo $permit_type; ?>&limit=<?php echo $limit; ?>"
                                class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 focus:z-20 transition-colors">
                                <span class="sr-only">Next</span>
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include '../layouts/footer.php'; ?>