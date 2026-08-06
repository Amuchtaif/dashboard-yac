<?php
// views/documents/index.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_documents');

$page_title = "Dashboard Persuratan";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator';

// Fetch logged-in employee's division (Bidang) and unit details
$stmtEmpInfo = $conn->prepare("
    SELECT e.division_id, e.department_id, e.unit_id,
           divs.name as division_name, u.name as unit_name
    FROM employees e
    LEFT JOIN divisions divs ON e.division_id = divs.id
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.id = ?
");
$stmtEmpInfo->execute([$user_id]);
$emp_info = $stmtEmpInfo->fetch(PDO::FETCH_ASSOC);

$user_div_id = $emp_info['division_id'] ?: ($emp_info['department_id'] ?: 0);
$user_unit_id = $emp_info['unit_id'] ?: 0;
$user_div_name = $emp_info['division_name'] ?? 'Seluruh Bidang';
$user_unit_name = $emp_info['unit_name'] ?? 'Seluruh Unit';

// --- Retrieve Stats ---
// 1. Created today (Outgoing drafts or submits by creator)
$stmtToday = $conn->prepare("SELECT COUNT(*) FROM documents WHERE DATE(created_at) = CURDATE() AND creator_id = ?");
$stmtToday->execute([$user_id]);
$stats_today = $stmtToday->fetchColumn();

// 2. Waiting approval for the logged-in user
$stmtPending = $conn->prepare("SELECT COUNT(DISTINCT document_id) FROM document_approvals WHERE approver_id = ? AND status = 'pending'");
$stmtPending->execute([$user_id]);
$stats_pending = $stmtPending->fetchColumn();

// 3. Completed (Approved final documents)
if ($is_admin) {
    $stmtCompleted = $conn->prepare("SELECT COUNT(*) FROM documents WHERE status = 'completed' AND type = 'outgoing'");
    $stmtCompleted->execute();
} else {
    $stmtCompleted = $conn->prepare("
        SELECT COUNT(*) FROM documents 
        WHERE status = 'completed' AND type = 'outgoing'
          AND (creator_id = :uid 
               OR (receiver_division_id = :div_id AND (receiver_unit_id IS NULL OR receiver_unit_id = :unit_id OR :unit_id = 0))
               OR (receiver_unit_id = :unit_id AND :unit_id != 0))
    ");
    $stmtCompleted->execute([':uid' => $user_id, ':div_id' => $user_div_id, ':unit_id' => $user_unit_id]);
}
$stats_completed = $stmtCompleted->fetchColumn();

// 4. Rejected
$stmtRejected = $conn->prepare("SELECT COUNT(*) FROM documents WHERE status = 'rejected' AND creator_id = ?");
$stmtRejected->execute([$user_id]);
$stats_rejected = $stmtRejected->fetchColumn();

// 5. Outgoing this month
if ($is_admin) {
    $stmtOutMonth = $conn->prepare("SELECT COUNT(*) FROM documents WHERE type = 'outgoing' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmtOutMonth->execute();
} else {
    $stmtOutMonth = $conn->prepare("
        SELECT COUNT(*) FROM documents 
        WHERE type = 'outgoing' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
          AND (creator_id = :uid 
               OR (receiver_division_id = :div_id AND (receiver_unit_id IS NULL OR receiver_unit_id = :unit_id OR :unit_id = 0))
               OR (receiver_unit_id = :unit_id AND :unit_id != 0))
    ");
    $stmtOutMonth->execute([':uid' => $user_id, ':div_id' => $user_div_id, ':unit_id' => $user_unit_id]);
}
$stats_out_month = $stmtOutMonth->fetchColumn();

// 6. Incoming this month
if ($is_admin) {
    $stmtInMonth = $conn->prepare("SELECT COUNT(*) FROM documents WHERE type = 'incoming' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stmtInMonth->execute();
} else {
    $stmtInMonth = $conn->prepare("
        SELECT COUNT(*) FROM documents 
        WHERE type = 'incoming' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())
          AND (creator_id = :uid 
               OR (receiver_division_id = :div_id AND (receiver_unit_id IS NULL OR receiver_unit_id = :unit_id OR :unit_id = 0))
               OR (receiver_unit_id = :unit_id AND :unit_id != 0))
    ");
    $stmtInMonth->execute([':uid' => $user_id, ':div_id' => $user_div_id, ':unit_id' => $user_unit_id]);
}
$stats_in_month = $stmtInMonth->fetchColumn();

// --- Monthly Stats (Chart.js) for last 6 months ---
$monthly_labels = [];
$monthly_outgoing = [];
$monthly_incoming = [];

for ($i = 5; $i >= 0; $i--) {
    $month_ts = strtotime("-$i months");
    $month_val = date('m', $month_ts);
    $year_val = date('Y', $month_ts);
    $monthly_labels[] = date('F Y', $month_ts);

    if ($is_admin) {
        $stmtOut = $conn->prepare("SELECT COUNT(*) FROM documents WHERE type = 'outgoing' AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
        $stmtOut->execute([$month_val, $year_val]);
        $monthly_outgoing[] = (int)$stmtOut->fetchColumn();

        $stmtIn = $conn->prepare("SELECT COUNT(*) FROM documents WHERE type = 'incoming' AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
        $stmtIn->execute([$month_val, $year_val]);
        $monthly_incoming[] = (int)$stmtIn->fetchColumn();
    } else {
        $stmtOut = $conn->prepare("
            SELECT COUNT(*) FROM documents 
            WHERE type = 'outgoing' AND MONTH(created_at) = ? AND YEAR(created_at) = ?
              AND (creator_id = ? OR (receiver_division_id = ? AND (receiver_unit_id IS NULL OR receiver_unit_id = ? OR ? = 0)) OR (receiver_unit_id = ? AND ? != 0))
        ");
        $stmtOut->execute([$month_val, $year_val, $user_id, $user_div_id, $user_unit_id, $user_unit_id, $user_unit_id, $user_unit_id]);
        $monthly_outgoing[] = (int)$stmtOut->fetchColumn();

        $stmtIn = $conn->prepare("
            SELECT COUNT(*) FROM documents 
            WHERE type = 'incoming' AND MONTH(created_at) = ? AND YEAR(created_at) = ?
              AND (creator_id = ? OR (receiver_division_id = ? AND (receiver_unit_id IS NULL OR receiver_unit_id = ? OR ? = 0)) OR (receiver_unit_id = ? AND ? != 0))
        ");
        $stmtIn->execute([$month_val, $year_val, $user_id, $user_div_id, $user_unit_id, $user_unit_id, $user_unit_id, $user_unit_id]);
        $monthly_incoming[] = (int)$stmtIn->fetchColumn();
    }
}

// --- Letters by Unit (Chart.js) ---
$unit_labels = [];
$unit_counts = [];
$stmtUnitStats = $conn->query("
    SELECT u.name as unit_name, COUNT(d.id) as count 
    FROM documents d
    JOIN employees e ON d.creator_id = e.id
    JOIN units u ON e.unit_id = u.id
    GROUP BY e.unit_id
    LIMIT 6
");
while ($row = $stmtUnitStats->fetch(PDO::FETCH_ASSOC)) {
    $unit_labels[] = $row['unit_name'];
    $unit_counts[] = (int)$row['count'];
}

// --- Letters by Type / Template (Chart.js) ---
$type_labels = [];
$type_counts = [];
$stmtTypeStats = $conn->query("
    SELECT COALESCE(dt.name, 'Manual/Incoming') as tpl_name, COUNT(d.id) as count 
    FROM documents d
    LEFT JOIN document_templates dt ON d.template_id = dt.id
    GROUP BY d.template_id
    LIMIT 6
");
while ($row = $stmtTypeStats->fetch(PDO::FETCH_ASSOC)) {
    $type_labels[] = $row['tpl_name'];
    $type_counts[] = (int)$row['count'];
}

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Dashboard Persuratan & Dokumen</h1>
            <p class="mt-1.5 text-sm text-slate-500">Statistik dan ringkasan persuratan digital sesuai akun & bidang/unit Anda.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 border border-slate-200">
                <i class="fa-solid fa-user-circle mr-1.5 text-slate-500"></i>
                Akun: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </span>
            <span class="inline-flex items-center rounded-xl bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                <i class="fa-solid fa-sitemap mr-1.5 text-indigo-600"></i>
                Bidang: <?php echo htmlspecialchars($user_div_name); ?>
                <?php if ($user_unit_id > 0): ?>
                    &bull; Unit: <?php echo htmlspecialchars($user_unit_name); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mt-8">
        <!-- Hari Ini -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-file-pen absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-indigo-100">Dibuat Hari Ini</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_today; ?></p>
            <p class="text-[9px] text-indigo-100/80 mt-1">Dokumen pribadi</p>
        </div>

        <!-- Menunggu Approval -->
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-file-signature absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-amber-100">Butuh Approval</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_pending; ?></p>
            <p class="text-[9px] text-amber-100/80 mt-1">Menunggu respon Anda</p>
        </div>

        <!-- Selesai -->
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-file-circle-check absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-emerald-100">Surat Selesai</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_completed; ?></p>
            <p class="text-[9px] text-emerald-100/80 mt-1">Total surat disetujui</p>
        </div>

        <!-- Ditolak -->
        <div class="bg-gradient-to-br from-rose-500 to-rose-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-file-circle-xmark absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-rose-100">Draft Ditolak</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_rejected; ?></p>
            <p class="text-[9px] text-rose-100/80 mt-1">Perlu revisi/perbaikan</p>
        </div>

        <!-- Keluar Bulan Ini -->
        <div class="bg-gradient-to-br from-cyan-500 to-cyan-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-paper-plane absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-cyan-100">Keluar Bulan Ini</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_out_month; ?></p>
            <p class="text-[9px] text-cyan-100/80 mt-1">Surat eksternal/resmi</p>
        </div>

        <!-- Masuk Bulan Ini -->
        <div class="bg-gradient-to-br from-violet-500 to-violet-600 rounded-2xl p-4 text-white shadow-md relative overflow-hidden transition-transform duration-300 hover:scale-105">
            <i class="fa-solid fa-box-archive absolute -right-2 -bottom-2 text-6xl opacity-15"></i>
            <p class="text-[10px] uppercase font-bold tracking-widest text-violet-100">Masuk Bulan Ini</p>
            <p class="text-3xl font-black mt-2"><?php echo $stats_in_month; ?></p>
            <p class="text-[9px] text-violet-100/80 mt-1">Arsip dokumen masuk</p>
        </div>
    </div>

    <!-- Quick Action & Alert Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
        <!-- Quick Actions Panel -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Aksi Cepat</h3>
                <p class="text-xs text-slate-400 mt-1">Navigasi langsung ke tugas utama persuratan.</p>
                
                <div class="grid grid-cols-2 gap-3 mt-6">
                    <a href="<?php url('views/documents/templates.php'); ?>" class="flex flex-col items-center justify-center p-4 bg-indigo-50 border border-indigo-100 rounded-2xl hover:bg-indigo-100 transition-colors text-center group">
                        <span class="p-2.5 bg-indigo-600 text-white rounded-xl shadow-md group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-plus text-lg"></i>
                        </span>
                        <span class="text-xs font-bold text-indigo-900 mt-3">Buat Surat</span>
                    </a>

                    <a href="<?php url('views/documents/incoming.php'); ?>" class="flex flex-col items-center justify-center p-4 bg-emerald-50 border border-emerald-100 rounded-2xl hover:bg-emerald-100 transition-colors text-center group">
                        <span class="p-2.5 bg-emerald-600 text-white rounded-xl shadow-md group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-file-import text-lg"></i>
                        </span>
                        <span class="text-xs font-bold text-emerald-900 mt-3">Surat Masuk</span>
                    </a>

                    <a href="<?php url('views/documents/approval.php'); ?>" class="flex flex-col items-center justify-center p-4 bg-amber-50 border border-amber-100 rounded-2xl hover:bg-amber-100 transition-colors text-center group">
                        <span class="p-2.5 bg-amber-600 text-white rounded-xl shadow-md group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-stamp text-lg"></i>
                        </span>
                        <span class="text-xs font-bold text-amber-900 mt-3">Approval</span>
                    </a>

                    <a href="<?php url('views/documents/archive.php'); ?>" class="flex flex-col items-center justify-center p-4 bg-cyan-50 border border-cyan-100 rounded-2xl hover:bg-cyan-100 transition-colors text-center group">
                        <span class="p-2.5 bg-cyan-600 text-white rounded-xl shadow-md group-hover:scale-110 transition-transform">
                            <i class="fa-solid fa-search text-lg"></i>
                        </span>
                        <span class="text-xs font-bold text-cyan-900 mt-3">Cari Arsip</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Latest Internal Documents / Pending Approval Activity -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Persetujuan Tertunda Terbaru</h3>
            <p class="text-xs text-slate-400 mt-1">Daftar dokumen menunggu approval akhir Anda.</p>

            <div class="mt-5 space-y-3.5 max-h-[220px] overflow-y-auto">
                <?php
                $stmtPendingDocs = $conn->prepare("
                    SELECT d.id, d.title, d.document_number, d.created_at, e.full_name as creator_name 
                    FROM document_approvals da
                    JOIN documents d ON da.document_id = d.id
                    JOIN employees e ON d.creator_id = e.id
                    WHERE da.approver_id = ? AND da.status = 'pending'
                    ORDER BY d.created_at DESC
                    LIMIT 3
                ");
                $stmtPendingDocs->execute([$user_id]);
                $pending_docs = $stmtPendingDocs->fetchAll(PDO::FETCH_ASSOC);

                if (count($pending_docs) > 0):
                    foreach ($pending_docs as $doc):
                ?>
                    <div class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-100 rounded-xl hover:bg-slate-100/80 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-amber-100 text-amber-700 rounded-lg">
                                <i class="fa-solid fa-file-invoice text-lg"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-slate-800"><?php echo htmlspecialchars($doc['title']); ?></h4>
                                <p class="text-[10px] text-slate-400 mt-0.5">Dibuat oleh: <span class="font-medium text-slate-600"><?php echo htmlspecialchars($doc['creator_name']); ?></span> &bull; <?php echo date('d M Y, H:i', strtotime($doc['created_at'])); ?></p>
                            </div>
                        </div>
                        <a href="<?php url('views/documents/approval.php'); ?>" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                            Buka Approval
                        </a>
                    </div>
                <?php 
                    endforeach;
                else: 
                ?>
                    <div class="flex flex-col items-center justify-center py-10 text-center text-slate-400">
                        <i class="fa-solid fa-circle-check text-4xl text-slate-300 mb-2.5"></i>
                        <p class="text-xs font-bold mt-2.5">Kerjaan Beres!</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Tidak ada persetujuan yang tertunda saat ini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Visual Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
        <!-- Monthly stats chart -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm lg:col-span-2">
            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Statistik Bulanan</h3>
            <p class="text-xs text-slate-400 mt-1">Perbandingan volume surat keluar dan masuk 6 bulan terakhir.</p>
            
            <div class="mt-6 h-[260px] relative">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <!-- Distributions grid (2 small charts) -->
        <div class="grid grid-rows-2 gap-6">
            <!-- Stats per Unit -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Surat Per Unit</h3>
                    <div class="h-[100px] mt-2 relative">
                        <canvas id="unitChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Stats per Template -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Surat Berdasarkan Jenis</h3>
                    <div class="h-[100px] mt-2 relative">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. Monthly Chart (Line Chart)
    const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
    new Chart(ctxMonthly, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [
                {
                    label: 'Surat Keluar',
                    data: <?php echo json_encode($monthly_outgoing); ?>,
                    borderColor: '#4F46E5', // Indigo-600
                    backgroundColor: 'rgba(79, 70, 229, 0.05)',
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2
                },
                {
                    label: 'Surat Masuk',
                    data: <?php echo json_encode($monthly_incoming); ?>,
                    borderColor: '#10B981', // Emerald-500
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    tension: 0.35,
                    fill: true,
                    borderWidth: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { family: 'Outfit', size: 11 } } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Outfit', size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Outfit', size: 10 } } }
            }
        }
    });

    // 2. Unit Chart (Bar Chart)
    const ctxUnit = document.getElementById('unitChart').getContext('2d');
    new Chart(ctxUnit, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($unit_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($unit_counts); ?>,
                backgroundColor: '#3b82f6', // Blue-500
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { display: false },
                x: { grid: { display: false }, ticks: { font: { family: 'Outfit', size: 9 } } }
            }
        }
    });

    // 3. Type Chart (Doughnut Chart)
    const ctxType = document.getElementById('typeChart').getContext('2d');
    new Chart(ctxType, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($type_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($type_counts); ?>,
                backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#64748b']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 8, font: { family: 'Outfit', size: 8 } } }
            }
        }
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
