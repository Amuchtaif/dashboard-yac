<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Beranda";

// Fetch Stats
$db = new Database();
$conn = $db->getConnection();

$emp_count = $conn->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$dept_count = $conn->query("SELECT COUNT(*) FROM divisions")->fetchColumn();
$unit_count = $conn->query("SELECT COUNT(*) FROM units")->fetchColumn();
$student_count = $conn->query("SELECT COUNT(*) FROM students WHERE status = 'Aktif'")->fetchColumn();

// --- Attendance Stats (Today) ---
$today = date('Y-m-d');
$active_count = $conn->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$inactive_count = $conn->query("SELECT COUNT(*) FROM employees WHERE status = 'inactive'")->fetchColumn();

// Fix: 'Telat' was missing. Added case-insensitive checks effectively.
$present_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendances WHERE date = '$today' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')")->fetchColumn();
$late_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendances WHERE date = '$today' AND status IN ('Late', 'Telat')")->fetchColumn();
$absent_count = $active_count - $present_count; // Only count active employees for absence

// Attendance Rates
$present_rate = $active_count > 0 ? round(($present_count / $active_count) * 100) : 0;
$absent_rate = $active_count > 0 ? round(($absent_count / $active_count) * 100) : 0;

// --- Chart Data (Last 7 Days) ---
$chart_data = [];
$days = ['Mgg', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D', strtotime($d));

    $daily_count = $conn->query("SELECT COUNT(DISTINCT user_id) FROM attendances WHERE date = '$d' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')")->fetchColumn();

    // Calculate percentage height relative to total employees (max 100%)
    $height_percent = $emp_count > 0 ? round(($daily_count / $emp_count) * 100) : 0;

    $chart_data[] = [
        'day' => $day_label,
        'date' => $d,
        'count' => $daily_count,
        'height' => $height_percent
    ];
}

// --- Recent Activity ---
// Union query to get both Check In and Check Out events ordered by time
$activity_query = "
    SELECT a.user_id, e.full_name, a.time_in as time, 'Check In' as event_type, a.status as status_label, a.status as status_code
    FROM attendances a 
    JOIN employees e ON a.user_id = e.id 
    WHERE a.date = '$today'
    UNION
    SELECT a.user_id, e.full_name, a.time_out as time, 'Check Out' as event_type, a.status_out as status_label, a.status_out as status_code
    FROM attendances a 
    JOIN employees e ON a.user_id = e.id 
    WHERE a.date = '$today' AND a.time_out IS NOT NULL
    ORDER BY time DESC 
    LIMIT 5
";
$recent_activities = $conn->query($activity_query)->fetchAll(PDO::FETCH_ASSOC);

// --- Attendance Ratio (Pie Chart) ---
$stmt = $conn->prepare("SELECT COUNT(*) FROM permits WHERE status = 'Approved' AND start_date <= :today AND end_date >= :today");
$stmt->execute([':today' => $today]);
$permits_today_count = $stmt->fetchColumn();

$sudah_absen = (int)$present_count;
$tidak_absen = (int)$permits_today_count;
$belum_absen = max(0, (int)$active_count - $sudah_absen - $tidak_absen);

$total_for_pie = $sudah_absen + $tidak_absen + $belum_absen;
$sudah_percent = $total_for_pie > 0 ? round(($sudah_absen / $total_for_pie) * 100) : 0;
$tidak_percent = $total_for_pie > 0 ? round(($tidak_absen / $total_for_pie) * 100) : 0;
$belum_percent = $total_for_pie > 0 ? round(($belum_absen / $total_for_pie) * 100) : 0;

// --- Recent Permits (5 entries) ---
$recent_permits_query = "
    SELECT p.*, e.full_name, pos.name as position_name
    FROM permits p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    ORDER BY p.created_at DESC
    LIMIT 5
";
$recent_permits = $conn->query($recent_permits_query)->fetchAll(PDO::FETCH_ASSOC);

// Map status to Indonesian labels
$statusTextMap = [
    'Pending' => 'Menunggu',
    'Approved' => 'Disetujui',
    'Rejected' => 'Ditolak'
];

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

        <!-- Total Employees Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-50 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-500">Total</span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Total Pegawai</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($emp_count); ?></div>
                    <span class="ml-2 text-sm font-medium text-green-600">
                        <svg class="self-center flex-shrink-0 h-4 w-4 text-green-500 inline-block" fill="currentColor"
                            viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
                                clip-rule="evenodd" />
                        </svg>
                        <span class="inline-block align-middle">2.5%</span>
                    </span>
                </dd>
            </div>
        </div>

        <!-- Active Employees Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-blue-50 text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M7.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM3.751 20.105a8.25 8.25 0 0116.498 0 .75.75 0 01-.437.695A18.683 18.683 0 0112 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 01-.437-.695z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Aktif</span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Pegawai Aktif</dt>
                <dd class="mt-1 text-2xl font-bold text-slate-900"><?php echo number_format($active_count); ?></dd>
            </div>
        </div>

        <!-- Inactive Employees Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-red-50 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M12 2.25a.75.75 0 01.75.75v9a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM6.166 5.106a.75.75 0 010 1.06 8.25 8.25 0 1011.668 0 .75.75 0 111.06-1.06c3.808 3.807 3.808 9.98 0 13.788-3.809 3.808-9.98 3.808-13.788 0-3.808-3.809-3.808-9.98 0-13.788a.75.75 0 011.06 0z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-600">Nonaktif</span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Pegawai Nonaktif</dt>
                <dd class="mt-1 text-2xl font-bold text-slate-900"><?php echo number_format($inactive_count); ?></dd>
            </div>
        </div>

        <!-- Present Today Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-green-50 text-green-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                    Hari Ini
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Hadir Hari Ini</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($present_count); ?></div>
                </dd>
            </div>
        </div>

        <!-- Late Arrivals Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-yellow-50 text-yellow-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path fill-rule="evenodd"
                            d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">
                    Terlambat
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Datang Terlambat</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($late_count); ?></div>
                </dd>
            </div>
        </div>

        <!-- Absent Today Card -->
        <div
            class="bg-white overflow-hidden shadow-sm rounded-xl border border-slate-100 hover:shadow-md transition-shadow p-5 relative">
            <div class="flex justify-between items-start">
                <div class="flex items-center justify-center h-10 w-10 rounded-lg bg-red-50 text-red-600">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6">
                        <path
                            d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375z" />
                        <path fill-rule="evenodd"
                            d="M3.087 9l.54 9.176A3 3 0 006.62 21h10.757a3 3 0 002.995-2.824L20.913 9H3.087zm6.163 3.75A.75.75 0 0110 12h4a.75.75 0 010 1.5h-4a.75.75 0 01-.75-.75z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700">
                    Absen
                </span>
            </div>
            <div class="mt-4">
                <dt class="text-sm font-medium text-slate-500 truncate">Tidak Hadir</dt>
                <dd class="mt-1 flex items-baseline">
                    <div class="text-2xl font-bold text-slate-900"><?php echo number_format($absent_count); ?></div>
                    <span class="ml-2 text-sm font-medium text-slate-400">Est.</span>
                </dd>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Attendance Ratio Pie Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-slate-800">Rasio Kehadiran</h3>
                    <p class="text-xs text-slate-500">Status kehadiran hari ini</p>
                </div>
            </div>
            
            <div class="relative h-56 flex items-center justify-center">
                <canvas id="attendancePieChart"></canvas>
            </div>
            
            <div class="mt-6 flex flex-wrap justify-center gap-4 text-xs font-medium">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-cyan-500"></span>
                    <span class="text-slate-600">Sudah Absen (<?php echo $sudah_percent; ?>%)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-orange-400"></span>
                    <span class="text-slate-600">Tidak Absen (<?php echo $tidak_percent; ?>%)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                    <span class="text-slate-600">Belum Absen (<?php echo $belum_percent; ?>%)</span>
                </div>
            </div>
        </div>

        <!-- Recent Permissions Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-slate-800">Perizinan Pegawai</h3>
                <a href="<?php url('views/permits/index.php'); ?>" class="text-xs font-semibold text-cyan-600 hover:text-cyan-700">Lihat Semua</a>
            </div>

            <div class="space-y-5">
                <?php if (count($recent_permits) > 0): ?>
                    <?php 
                    $no = 1;
                    foreach ($recent_permits as $permit): 
                    ?>
                        <div class="flex items-start gap-4">
                            <!-- Row Number -->
                            <div class="flex-shrink-0 pt-0.5">
                                <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-slate-50 border border-slate-200 text-slate-500 text-xs font-bold">
                                    <?php echo $no++; ?>
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">
                                    <?php echo htmlspecialchars($permit['full_name']); ?>
                                </p>
                                <p class="text-xs text-slate-500 truncate mt-0.5">
                                    <span class="font-medium text-slate-400"><?php echo htmlspecialchars($permit['permit_type']); ?>:</span> 
                                    <?php echo htmlspecialchars($permit['reason']); ?>
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-[10px] font-medium text-slate-400 uppercase">
                                    <?php echo date('d M', strtotime($permit['start_date'])); ?>
                                </p>
                                <?php
                                $statusLabelColor = 'text-orange-500';
                                if ($permit['status'] == 'Approved') $statusLabelColor = 'text-green-500';
                                if ($permit['status'] == 'Rejected') $statusLabelColor = 'text-red-500';
                                ?>
                                <p class="text-[10px] font-bold <?php echo $statusLabelColor; ?>">
                                    <?php echo strtoupper($statusTextMap[$permit['status']] ?? $permit['status']); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-slate-500 text-center py-4">Belum ada pengajuan izin.</p>
                <?php endif; ?>
            </div>
        </div>


        <!-- Real-time Activity -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-slate-800">Aktivitas Terkini</h3>
                <a href="#" class="text-xs font-semibold text-cyan-600 hover:text-cyan-700">Lihat Semua</a>
            </div>

            <div class="space-y-6">
                <?php if (count($recent_activities) > 0): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <div class="flex items-center gap-4">
                            <img class="h-10 w-10 rounded-full border border-slate-100"
                                src="https://ui-avatars.com/api/?name=<?php echo urlencode($activity['full_name']); ?>&background=random"
                                alt="">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">
                                    <?php echo htmlspecialchars($activity['full_name']); ?>
                                </p>
                                <p class="text-xs text-slate-500">
                                    <?php echo $activity['event_type']; ?>
                                    <span class="ml-1 text-[10px] text-slate-400">•
                                        <?php echo htmlspecialchars($activity['status_label']); ?></span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-bold text-slate-800">
                                    <?php echo date('H:i A', strtotime($activity['time'])); ?>
                                </p>
                                <?php
                                $statusColor = 'bg-slate-100 text-slate-600';

                                if ($activity['event_type'] == 'Check In') {
                                    if (in_array(strtolower($activity['status_code']), ['hadir', 'present'])) {
                                        $statusColor = 'bg-green-100 text-green-700'; // On Time
                                    } elseif (in_array(strtolower($activity['status_code']), ['late', 'telat'])) {
                                        $statusColor = 'bg-red-100 text-red-700'; // Late
                                    }
                                } elseif ($activity['event_type'] == 'Check Out') {
                                    if (strtolower($activity['status_code']) == 'pulang') {
                                        $statusColor = 'bg-green-100 text-green-700'; // Normal
                                    } elseif (strtolower($activity['status_code']) == 'pulang cepat') {
                                        $statusColor = 'bg-red-100 text-red-700'; // Early Leave
                                    }
                                }
                                ?>
                                <span
                                    class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold <?php echo $statusColor; ?>">
                                    <?php echo strtoupper($activity['status_label']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-slate-500 text-center py-4">Belum ada aktivitas hari ini.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendancePieChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Sudah Absen', 'Tidak Absen', 'Belum Absen'],
            datasets: [{
                data: [<?php echo $sudah_absen; ?>, <?php echo $tidak_absen; ?>, <?php echo $belum_absen; ?>],
                backgroundColor: ['#06b6d4', '#fb923c', '#e2e8f0'],
                borderWidth: 0,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>


<?php include '../layouts/footer.php'; ?>