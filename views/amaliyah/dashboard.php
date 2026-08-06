<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';

check_login();
if (!hasPermission($_SESSION['user_id'], 'manage_activities') && (!isset($_SESSION['position_name']) || $_SESSION['position_name'] !== 'Administrator')) {
    include '../layouts/no_access.php';
    exit;
}

$page_title = "Statistik Aktivitas Amaliyah";

$db = new Database();
$conn = $db->getConnection();

// --- Query stats data directly in PHP for rendering ---
$today = date('Y-m-d');
$first_day_of_month = date('Y-m-01');

// 1. Total Hari Ini
$today_stmt = $conn->prepare("SELECT COUNT(*) FROM student_activities WHERE activity_date = ? AND deleted_at IS NULL");
$today_stmt->execute([$today]);
$total_today = $today_stmt->fetchColumn();

// 2. Total Bulan Ini
$month_stmt = $conn->prepare("SELECT COUNT(*) FROM student_activities WHERE activity_date >= ? AND deleted_at IS NULL");
$month_stmt->execute([$first_day_of_month]);
$total_month = $month_stmt->fetchColumn();

// 3. Tipe Personal vs Event
$type_stmt = $conn->query("
    SELECT t.type, COUNT(*) as count 
    FROM student_activities a 
    JOIN activity_types t ON a.activity_type_id = t.id 
    WHERE a.deleted_at IS NULL 
    GROUP BY t.type
");
$type_counts = ['personal' => 0, 'event' => 0];
while ($row = $type_stmt->fetch(PDO::FETCH_ASSOC)) {
    $type_counts[$row['type']] = (int)$row['count'];
}

// 4. Top 5 Aktivitas Terbanyak
$top_stmt = $conn->query("
    SELECT t.name, t.color, COUNT(*) as count 
    FROM student_activities a 
    JOIN activity_types t ON a.activity_type_id = t.id 
    WHERE a.deleted_at IS NULL 
    GROUP BY a.activity_type_id 
    ORDER BY count DESC 
    LIMIT 5
");
$top_activities = $top_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Tren Bulanan (Last 6 Months)
$trend_stmt = $conn->query("
    SELECT DATE_FORMAT(activity_date, '%Y-%m') as month, COUNT(*) as count 
    FROM student_activities 
    WHERE deleted_at IS NULL AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY month 
    ORDER BY month ASC
");
$monthly_trend = [];
while ($row = $trend_stmt->fetch(PDO::FETCH_ASSOC)) {
    $monthly_trend[] = [
        'label' => date('M Y', strtotime($row['month'] . '-01')),
        'count' => (int)$row['count']
    ];
}

include '../layouts/header.php';
?>

<style>
/* Custom premium styles for form inputs, dropdowns, and textareas */
input[type="text"], input[type="date"], select, textarea {
    border-color: #cbd5e1 !important;
    border-width: 1px !important;
    border-radius: 0.75rem !important;
    padding: 0.625rem 0.875rem !important;
    font-size: 0.875rem !important;
    background-color: #f8fafc !important;
    color: #334155 !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.02) !important;
}
input[type="text"]:hover, input[type="date"]:hover, select:hover, textarea:hover {
    border-color: #94a3b8 !important;
    background-color: #ffffff !important;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05) !important;
}
input[type="text"]:focus, input[type="date"]:focus, select:focus, textarea:focus {
    border-color: #6366f1 !important;
    background-color: #ffffff !important;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12) !important;
    transform: translateY(-1px);
    outline: none !important;
}
</style>

<!-- Include Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Dashboard Statistik Amaliyah</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Statistik real-time, tren, dan ringkasan aktivitas pembiasaan ibadah santri.</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-8">
        <!-- Card 1 -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg shadow-indigo-500/20 hover:scale-[1.02] transition-transform duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-100">Aktivitas Hari Ini</span>
                    <h3 class="text-3xl font-black mt-2"><?php echo number_format($total_today); ?></h3>
                </div>
                <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm">
                    <i class="fa-solid fa-clock w-6 h-6"></i>
                </div>
            </div>
            <p class="text-xs text-indigo-200 mt-4 font-semibold">Tercatat pada tanggal <?php echo date('d-m-Y'); ?></p>
        </div>

        <!-- Card 2 -->
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg shadow-emerald-500/20 hover:scale-[1.02] transition-transform duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-100">Aktivitas Bulan Ini</span>
                    <h3 class="text-3xl font-black mt-2"><?php echo number_format($total_month); ?></h3>
                </div>
                <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm">
                    <i class="fa-solid fa-calendar-days w-6 h-6"></i>
                </div>
            </div>
            <p class="text-xs text-emerald-200 mt-4 font-semibold">Akumulasi sejak awal bulan ini</p>
        </div>

        <!-- Card 3 -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-lg shadow-purple-500/20 hover:scale-[1.02] transition-transform duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-purple-100">Aktivitas Personal</span>
                    <h3 class="text-3xl font-black mt-2"><?php echo number_format($type_counts['personal']); ?></h3>
                </div>
                <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm">
                    <i class="fa-solid fa-user w-6 h-6"></i>
                </div>
            </div>
            <p class="text-xs text-purple-200 mt-4 font-semibold">Tipe pembiasaan pribadi santri</p>
        </div>

        <!-- Card 4 -->
        <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-2xl p-6 text-white shadow-lg shadow-cyan-500/20 hover:scale-[1.02] transition-transform duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-cyan-100">Aktivitas Event</span>
                    <h3 class="text-3xl font-black mt-2"><?php echo number_format($type_counts['event']); ?></h3>
                </div>
                <div class="bg-white/10 p-3 rounded-xl backdrop-blur-sm">
                    <i class="fa-solid fa-users w-6 h-6"></i>
                </div>
            </div>
            <p class="text-xs text-cyan-200 mt-4 font-semibold">Tipe kegiatan sosial/kelompok</p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
        <!-- Line Chart: Monthly Trend -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm md:col-span-2 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-800">Tren Aktivitas Bulanan</h3>
                <span class="text-xs text-slate-400 font-semibold">6 Bulan Terakhir</span>
            </div>
            <div class="h-80 w-full relative">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </div>

        <!-- Doughnut Chart: Top Activities -->
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-slate-800">Top 5 Aktivitas Santri</h3>
                <span class="text-xs text-slate-400 font-semibold">Akumulasi</span>
            </div>
            <div class="h-80 w-full relative flex items-center justify-center">
                <canvas id="topActivitiesChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// --- Monthly Trend Line Chart ---
const trendLabels = <?php echo json_encode(array_column($monthly_trend, 'label')); ?>;
const trendData = <?php echo json_encode(array_column($monthly_trend, 'count')); ?>;

const ctxTrend = document.getElementById('monthlyTrendChart').getContext('2d');
new Chart(ctxTrend, {
    type: 'line',
    data: {
        labels: trendLabels.length > 0 ? trendLabels : ['-'],
        datasets: [{
            label: 'Total Aktivitas',
            data: trendData.length > 0 ? trendData : [0],
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.05)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#6366f1',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9' },
                ticks: { color: '#64748b', font: { weight: 'bold' } }
            },
            x: {
                grid: { display: false },
                ticks: { color: '#64748b', font: { weight: 'bold' } }
            }
        }
    }
});

// --- Top Activities Doughnut Chart ---
const topActivities = <?php echo json_encode($top_activities); ?>;
const topLabels = topActivities.map(a => a.name);
const topData = topActivities.map(a => a.count);
const topColors = topActivities.map(a => a.color || '#cbd5e1');

const ctxTop = document.getElementById('topActivitiesChart').getContext('2d');
new Chart(ctxTop, {
    type: 'doughnut',
    data: {
        labels: topLabels.length > 0 ? topLabels : ['Belum ada data'],
        datasets: [{
            data: topData.length > 0 ? topData : [1],
            backgroundColor: topColors.length > 0 ? topColors : ['#cbd5e1'],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: '#475569',
                    font: { weight: 'bold', size: 11 },
                    padding: 15
                }
            }
        },
        cutout: '65%'
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
