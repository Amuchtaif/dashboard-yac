<?php
// views/tahfidz/dashboard.php

require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../app/Services/Tahfidz/DashboardTahfidzService.php';

check_login();

// Verify role access for supervisor levels
$user_id = (int)$_SESSION['user_id'];
$service = new DashboardTahfidzService();

try {
    $scope = $service->resolveScope($user_id);
} catch (Exception $e) {
    die("Akses ditolak: " . $e->getMessage());
}

if ($scope['role'] === 'restricted') {
    die("Akses ditolak: Anda tidak memiliki wewenang mengakses Dashboard Pimpinan.");
}

$page_title = "Dashboard Tahfidz Eksekutif";

// Get active academic year
$ay = $service->getActiveAcademicYear();

// --- Process Filter Parameters ---
$selected_unit = $_GET['unit'] ?? null;
$selected_kelas = $_GET['kelas'] ?? null;
$selected_halaqah_id = isset($_GET['halaqah_id']) && $_GET['halaqah_id'] !== '' ? (int)$_GET['halaqah_id'] : null;
$selected_pengampu_id = isset($_GET['pengampu_id']) && $_GET['pengampu_id'] !== '' ? (int)$_GET['pengampu_id'] : null;
$selected_date = $_GET['date'] ?? date('Y-m-d');
$search_query = $_GET['search'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;

$filters = [
    'unit' => $selected_unit,
    'kelas' => $selected_kelas,
    'halaqah_id' => $selected_halaqah_id,
    'pengampu_id' => $selected_pengampu_id,
    'date' => $selected_date,
    'search' => $search_query
];

// --- Retrieve Dashboard Data via Service ---
$summary = $service->getExecutiveSummary($user_id, $filters);
$attendance = $service->getAttendanceDashboard($user_id, $filters);
$progress = $service->getProgressHafalan($user_id, $filters);
$distribution = $service->getDistribusiHafalan($user_id, $filters);
$attention = $service->getSantriAttentionNeeded($user_id, $filters);
$insights = $service->getExecutiveInsight($user_id, $filters);
$comparison = $service->getCompareUnits($user_id, $filters);
$health = $service->getHealthScore($user_id, $filters);

// Rankings
$rank_halaqah = $service->getRanking($user_id, 'halaqah', 'progress', $filters);
$rank_pengampu = $service->getRanking($user_id, 'pengampu', 'progress', $filters);

// Paginated lists
$halaqah_monitoring = $service->getMonitoringHalaqoh($user_id, $filters, $limit, $page);
$santri_monitoring = $service->getMonitoringSantri($user_id, $filters, 15, $page);
$pengampu_submissions = $service->getDailyPengampuSubmissions($user_id, $filters);
$daily_memorization_log = $service->getDailyMemorizationLog($user_id, $filters);

// Fetch filter dropdown options from DB
$db_conn = (new Database())->getConnection();

// 1. Available Scoped Units (filtered by active academic year)
$active_year_id = $ay ? (int)$ay['id'] : 1;

$stmt = $db_conn->prepare("
    SELECT DISTINCT s.tingkat 
    FROM students s
    JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
    WHERE s.status = 'Aktif' AND s.tingkat IS NOT NULL AND s.tingkat != '' AND s.tingkat != 'TKIT' 
    ORDER BY s.tingkat ASC
");
$stmt->execute([':active_year_id' => $active_year_id]);
$db_units = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (!empty($scope['units'])) {
    $db_units = array_intersect($db_units, $scope['units']);
}

// 2. Classes based on selected unit (filtered by active academic year)
$classes_query = "
    SELECT DISTINCT gl.name as kelas 
    FROM students s
    JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
    JOIN grade_levels gl ON sch.class_id = gl.id
    WHERE s.status = 'Aktif' AND s.tingkat != 'TKIT'
";
if ($selected_unit) {
    $classes_query .= " AND s.tingkat = :unit";
}
$classes_query .= " ORDER BY gl.name ASC";

$classes_stmt = $db_conn->prepare($classes_query);
$classes_params = [':active_year_id' => $active_year_id];
if ($selected_unit) {
    $classes_params[':unit'] = $selected_unit;
}
$classes_stmt->execute($classes_params);
$db_classes = $classes_stmt->fetchAll(PDO::FETCH_COLUMN);

// 3. Halaqahs
$halaqahs_query = "SELECT id, group_name FROM halaqah_groups ORDER BY group_name ASC";
$db_halaqahs = $db_conn->query($halaqahs_query)->fetchAll(PDO::FETCH_ASSOC);

// 4. Teachers
$teachers_query = "SELECT DISTINCT e.id, e.full_name FROM employees e JOIN halaqah_groups hg ON hg.teacher_id = e.id ORDER BY e.full_name ASC";
$db_teachers = $db_conn->query($teachers_query)->fetchAll(PDO::FETCH_ASSOC);

// Include Layout Header
include '../layouts/header.php';
?>

<!-- HTML Interface Layout -->
<div class="space-y-6 pb-12">

    <!-- Global Filter Bar -->
    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <!-- Unit -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Unit Pendidikan</label>
                <select name="unit" class="w-full text-sm border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500" onchange="this.form.submit()">
                    <option value="">Semua Unit</option>
                    <?php foreach ($db_units as $unit): ?>
                        <option value="<?= $unit ?>" <?= $selected_unit === $unit ? 'selected' : '' ?>><?= $unit ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Kelas -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Kelas</label>
                <select name="kelas" class="w-full text-sm border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($db_classes as $cls): ?>
                        <option value="<?= $cls ?>" <?= $selected_kelas === $cls ? 'selected' : '' ?>>Kelas <?= $cls ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Halaqah -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Halaqah</label>
                <select name="halaqah_id" class="w-full text-sm border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500" onchange="this.form.submit()">
                    <option value="">Semua Halaqah</option>
                    <?php foreach ($db_halaqahs as $h): ?>
                        <option value="<?= $h['id'] ?>" <?= $selected_halaqah_id === (int)$h['id'] ? 'selected' : '' ?>><?= htmlspecialchars($h['group_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Pengampu -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Pengampu</label>
                <select name="pengampu_id" class="w-full text-sm border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500" onchange="this.form.submit()">
                    <option value="">Semua Pengampu</option>
                    <?php foreach ($db_teachers as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $selected_pengampu_id === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tanggal -->
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Tanggal Analisis</label>
                <input type="date" name="date" value="<?= htmlspecialchars($selected_date) ?>" class="w-full text-sm border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500" onchange="this.form.submit()">
            </div>
        </form>
    </div>

    <!-- Executive Insight Alert Banner -->
    <?php if (!empty($insights['insights'])): ?>
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 p-5 rounded-3xl flex gap-4">
            <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-600 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-amber-900">Executive Insight Hari Ini</h4>
                <ul class="list-disc list-inside text-xs text-amber-800 mt-2 space-y-1">
                    <?php foreach ($insights['insights'] as $ins): ?>
                        <li><?= htmlspecialchars($ins) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPI Summary Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <!-- Santri Aktif -->
        <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <p class="text-xs text-slate-400 font-bold uppercase">Santri Aktif</p>
            <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?= $summary['total_santri'] ?></h3>
            <span class="text-[10px] text-teal-600 font-bold block mt-1">Dalam Scope Anda</span>
        </div>

        <!-- Total Pengampu -->
        <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <p class="text-xs text-slate-400 font-bold uppercase">Pengampu Tahfidz</p>
            <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?= $summary['total_pengampu'] ?></h3>
            <span class="text-[10px] text-teal-600 font-bold block mt-1"><?= $summary['total_halaqah'] ?> Halaqah Terbentuk</span>
        </div>

        <!-- Setoran Hari Ini -->
        <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <p class="text-xs text-slate-400 font-bold uppercase">Setoran Hari Ini</p>
            <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?= $summary['total_setoran_hari_ini'] ?></h3>
            <span class="text-[10px] text-teal-600 font-bold block mt-1">Hafalan Baru Tercatat</span>
        </div>

        <!-- Murojaah Hari Ini -->
        <div class="bg-white p-5 rounded-3xl border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <p class="text-xs text-slate-400 font-bold uppercase">Murojaah Hari Ini</p>
            <h3 class="text-3xl font-extrabold text-slate-800 mt-2"><?= $summary['total_murajaah_hari_ini'] ?></h3>
            <span class="text-[10px] text-teal-600 font-bold block mt-1">Repetisi/Murojaah Lancar</span>
        </div>
    </div>

    <!-- Progress Target & Attendance Breakdown Charts -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Target Semester Progress Widget -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col justify-between">
            <div>
                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight">Progress Capaian Juz</h4>
                <p class="text-xs text-slate-400 mt-1">Agregasi semester ini terhadap target target_hafalan</p>
            </div>
            
            <div class="my-6">
                <div class="flex justify-between items-baseline mb-2">
                    <span class="text-3xl font-black text-slate-800"><?= $progress['total_hafalan_baru_juz'] ?> <span class="text-sm text-slate-400 font-normal">Juz</span></span>
                    <span class="text-sm font-bold text-teal-600"><?= $progress['progress_percentage'] ?>%</span>
                </div>
                <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden">
                    <div class="bg-teal-500 h-full rounded-full" style="width: <?= min(100.0, $progress['progress_percentage']) ?>%"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 border-t border-slate-100 pt-4 gap-4">
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Target Semester</p>
                    <p class="text-sm font-bold text-slate-700"><?= $progress['target_semester_juz'] ?> Juz</p>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase">Target Tahunan</p>
                    <p class="text-sm font-bold text-slate-700"><?= $progress['target_tahunan_juz'] ?> Juz</p>
                </div>
            </div>
        </div>

        <!-- Attendance Breakdown Pie -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm md:col-span-2">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight mb-4">Statistik Kehadiran Hari Ini</h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Santri chart -->
                <div>
                    <p class="text-xs text-slate-500 font-bold mb-3 text-center">Kehadiran Santri</p>
                    <div class="relative w-full h-40">
                        <canvas id="santriAttendanceChart"></canvas>
                    </div>
                </div>
                <!-- Pengampu chart -->
                <div>
                    <p class="text-xs text-slate-500 font-bold mb-3 text-center">Kehadiran Pengampu</p>
                    <div class="relative w-full h-40">
                        <canvas id="teacherAttendanceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hafalan Distribution & History Section -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Distribution bar chart -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm md:col-span-2">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight mb-4">Distribusi Capaian Juz Santri</h4>
            <div class="h-64 relative">
                <canvas id="distributionBarChart"></canvas>
            </div>
        </div>

        <!-- Attention Box Alerts -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm flex flex-col">
            <div class="border-b border-slate-100 pb-4 mb-4">
                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping"></span>
                    Santri Perlu Perhatian
                </h4>
                <p class="text-xs text-slate-400 mt-1">Siswa yang tidak setor > 3 hari atau tingkat alfa tinggi</p>
            </div>
            
            <div class="flex-1 overflow-y-auto max-h-64 space-y-3 pr-1">
                <?php if (!empty($attention)): ?>
                    <?php foreach (array_slice($attention, 0, 5) as $att_student): ?>
                        <div class="p-3 bg-red-50/50 hover:bg-red-50 rounded-2xl border border-red-100 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h5 class="text-xs font-bold text-slate-800"><?= htmlspecialchars($att_student['full_name']) ?></h5>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Kelas <?= htmlspecialchars($att_student['kelas']) ?> • Halaqah <?= htmlspecialchars($att_student['halaqah_name']) ?></p>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-red-100 text-red-700 text-[8px] font-bold uppercase tracking-tight">
                                    <?= $att_student['days_since_last_setor'] ?> hari
                                </span>
                            </div>
                            <p class="text-[10px] text-red-700 mt-2 font-medium">⚠️ <?= htmlspecialchars($att_student['reasons'][0]) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="h-full flex flex-col items-center justify-center text-center text-slate-400 py-12">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-200 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-xs font-medium">Tidak ada santri bermasalah</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ranking & Compare Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Compare Units Table -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight mb-4">Perbandingan Kinerja Unit</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-bold border-b border-slate-100">
                            <th class="px-4 py-2">Unit</th>
                            <th class="px-4 py-2 text-center">Jumlah Santri</th>
                            <th class="px-4 py-2 text-center">Jumlah Pengampu</th>
                            <th class="px-4 py-2 text-center">Setoran Semester</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                        <?php foreach ($comparison as $comp_item): ?>
                            <tr>
                                <td class="px-4 py-3 font-bold text-slate-800"><?= $comp_item['unit'] ?></td>
                                <td class="px-4 py-3 text-center"><?= $comp_item['student_count'] ?></td>
                                <td class="px-4 py-3 text-center"><?= $comp_item['teacher_count'] ?></td>
                                <td class="px-4 py-3 text-center text-teal-600 font-extrabold"><?= $comp_item['total_memorized_juz_semester'] ?> Juz</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rankings Board -->
        <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight mb-4">10 Besar Capaian Halaqah</h4>
            <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                <?php if (!empty($rank_halaqah)): ?>
                    <?php foreach ($rank_halaqah as $rank_item): ?>
                        <div class="flex items-center justify-between p-2.5 rounded-2xl hover:bg-slate-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-600"><?= $rank_item['rank'] ?></span>
                                <div>
                                    <h5 class="text-xs font-bold text-slate-800"><?= htmlspecialchars($rank_item['name']) ?></h5>
                                    <p class="text-[10px] text-slate-400 font-medium">Ustadz: <?= htmlspecialchars($rank_item['subtitle']) ?></p>
                                </div>
                            </div>
                            <span class="text-xs font-black text-teal-600"><?= $rank_item['value'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-xs text-slate-400 text-center py-12">Belum ada pemeringkatan</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Drill Down Navigation List -->
    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight mb-4">Navigasi Drill Down Bertingkat</h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Drill Unit -->
            <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Langkah 1: Pilih Unit</span>
                <div class="mt-3 space-y-1.5">
                    <?php
                    $drill_units = $service->getDrillDown($user_id, 'unit', null, $filters);
                    foreach ($drill_units as $du): ?>
                        <a href="?unit=<?= urlencode($du['id']) ?>&date=<?= $selected_date ?>" class="flex items-center justify-between p-2 rounded-xl bg-white hover:bg-teal-50 border border-slate-100 text-xs font-bold text-slate-700 transition-colors">
                            <span><?= $du['name'] ?></span>
                            <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[8px]"><?= $du['student_count'] ?> Santri</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Drill Class -->
            <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Langkah 2: Pilih Kelas</span>
                <div class="mt-3 space-y-1.5 max-h-48 overflow-y-auto pr-1">
                    <?php if ($selected_unit): ?>
                        <?php
                        $drill_classes = $service->getDrillDown($user_id, 'class', $selected_unit, $filters);
                        foreach ($drill_classes as $dc): ?>
                            <a href="?unit=<?= urlencode($selected_unit) ?>&kelas=<?= urlencode($dc['id']) ?>&date=<?= $selected_date ?>" class="flex items-center justify-between p-2 rounded-xl bg-white hover:bg-teal-50 border border-slate-100 text-xs font-bold text-slate-700 transition-colors">
                                <span><?= $dc['name'] ?></span>
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 text-[8px]"><?= $dc['student_count'] ?> Santri</span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-[10px] text-slate-400 text-center py-8">Silakan pilih Unit Pendidikan terlebih dahulu.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Drill Halaqah -->
            <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                <span class="text-[10px] text-slate-400 font-bold uppercase">Langkah 3: Pilih Halaqah</span>
                <div class="mt-3 space-y-1.5 max-h-48 overflow-y-auto pr-1">
                    <?php if ($selected_kelas): ?>
                        <?php
                        $drill_halaqahs = $service->getDrillDown($user_id, 'halaqah', $selected_kelas, $filters);
                        foreach ($drill_halaqahs as $dh): ?>
                            <a href="?unit=<?= urlencode($selected_unit) ?>&kelas=<?= urlencode($selected_kelas) ?>&halaqah_id=<?= $dh['id'] ?>&date=<?= $selected_date ?>" class="flex items-center justify-between p-2 rounded-xl bg-white hover:bg-teal-50 border border-slate-100 text-xs font-bold text-slate-700 transition-colors">
                                <span class="truncate pr-2"><?= $dh['name'] ?></span>
                                <span class="text-[9px] text-slate-400 font-medium truncate max-w-[80px]"><?= $dh['teacher_name'] ?></span>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($drill_halaqahs)): ?>
                            <p class="text-[10px] text-slate-400 text-center py-8">Tidak ada halaqah terdaftar.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-[10px] text-slate-400 text-center py-8">Silakan pilih Kelas terlebih dahulu.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Reset drill down -->
        <?php if ($selected_unit || $selected_kelas || $selected_halaqah_id): ?>
            <div class="mt-4 flex justify-end">
                <a href="?" class="inline-flex items-center px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition-colors">
                    Reset Filter Drill Down
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Daily Memorization Entries Activity Log -->
    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm mb-6">
        <div class="border-b border-slate-100 pb-4 mb-4">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight">Log Aktivitas Setoran Santri Hari Ini</h4>
            <p class="text-xs text-slate-400 mt-1">Daftar setoran hafalan baru dan murojaah masuk tanggal <span class="font-semibold text-teal-600"><?= date('d-m-Y', strtotime($selected_date)) ?></span></p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold border-b border-slate-100 uppercase tracking-wider">
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Nama Santri</th>
                        <th class="px-4 py-3">Kelas / Halaqah</th>
                        <th class="px-4 py-3">Pengampu</th>
                        <th class="px-4 py-3 text-center">Jenis</th>
                        <th class="px-4 py-3">Rincian Materi</th>
                        <th class="px-4 py-3 text-center">Kelancaran</th>
                        <th class="px-4 py-3">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                    <?php if (!empty($daily_memorization_log)): ?>
                        <?php foreach ($daily_memorization_log as $log_item): 
                            $time_str = date('H:i', strtotime($log_item['created_at']));
                            $is_ziyadah = ($log_item['entry_type'] === 'HAFALAN_BARU');
                            
                            $type_badge = $is_ziyadah 
                                ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">Ziyadah</span>'
                                : '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">Murojaah</span>';
                            
                            $materi_str = htmlspecialchars($log_item['surah_start']) . ': ' . $log_item['start_ayah'];
                            if (!empty($log_item['surah_end']) && $log_item['surah_end'] !== $log_item['surah_start']) {
                                $materi_str .= ' s.d. ' . htmlspecialchars($log_item['surah_end']) . ': ' . $log_item['end_ayah'];
                            } else if (!empty($log_item['end_ayah']) && $log_item['end_ayah'] != $log_item['start_ayah']) {
                                $materi_str .= '-' . $log_item['end_ayah'];
                            }
                            $materi_str .= ' (' . $log_item['line_count'] . ' Baris)';
                            
                            $quality_text = ucfirst(strtolower($log_item['quality'] ?? '-'));
                            $quality_class = "text-slate-500";
                            if ($quality_text === 'Lancar') $quality_class = "text-green-600 font-bold";
                            elseif ($quality_text === 'Kurang') $quality_class = "text-amber-600 font-bold";
                            elseif ($quality_text === 'Tidak') $quality_class = "text-red-600 font-bold";
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3.5 text-slate-400 font-mono"><?= $time_str ?></td>
                                <td class="px-4 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($log_item['student_name']) ?></td>
                                <td class="px-4 py-3.5">
                                    <div class="text-slate-600"><?= htmlspecialchars($log_item['kelas']) ?></div>
                                    <div class="text-[10px] text-teal-600 font-semibold"><?= htmlspecialchars($log_item['halaqah_name'] ?? 'Halaqah -') ?></div>
                                </td>
                                <td class="px-4 py-3.5 text-slate-600"><?= htmlspecialchars($log_item['teacher_name'] ?? '-') ?></td>
                                <td class="px-4 py-3.5 text-center"><?= $type_badge ?></td>
                                <td class="px-4 py-3.5 text-slate-800 font-medium"><?= $materi_str ?></td>
                                <td class="px-4 py-3.5 text-center <?= $quality_class ?>"><?= $quality_text ?></td>
                                <td class="px-4 py-3.5 text-slate-500 italic font-normal max-w-xs truncate" title="<?= htmlspecialchars($log_item['notes'] ?? '') ?>"><?= htmlspecialchars($log_item['notes'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">Belum ada setoran masuk untuk tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Daily Teacher Submissions Status Card -->
    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm mb-6">
        <div class="border-b border-slate-100 pb-4 mb-4">
            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight">Status Input Setoran & Absensi Pengampu</h4>
            <p class="text-xs text-slate-400 mt-1">Status pengisian laporan harian tanggal <span class="font-semibold text-teal-600"><?= date('d-m-Y', strtotime($selected_date)) ?></span></p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold border-b border-slate-100 uppercase tracking-wider">
                        <th class="px-4 py-3">Nama Pengampu</th>
                        <th class="px-4 py-3">Halaqah</th>
                        <th class="px-4 py-3 text-center">Jumlah Santri</th>
                        <th class="px-4 py-3 text-center">Setoran Baru</th>
                        <th class="px-4 py-3 text-center">Murojaah</th>
                        <th class="px-4 py-3 text-center">Absensi Santri</th>
                        <th class="px-4 py-3 text-center">Status Input</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                    <?php if (!empty($pengampu_submissions)): ?>
                        <?php foreach ($pengampu_submissions as $sub): 
                            $all_setor_done = ($sub['setoran_count'] >= $sub['member_count'] && $sub['member_count'] > 0);
                            $any_setor_done = ($sub['setoran_count'] > 0 || $sub['murojaah_count'] > 0);
                            
                            if ($all_setor_done) {
                                $status_badge_class = "bg-green-50 text-green-700 border-green-200";
                                $status_badge_text = "Lengkap";
                            } elseif ($any_setor_done) {
                                $status_badge_class = "bg-amber-50 text-amber-700 border-amber-200";
                                $status_badge_text = "Sebagian";
                            } else {
                                $status_badge_class = "bg-slate-50 text-slate-600 border-slate-200";
                                $status_badge_text = "Belum Menginput";
                            }
                            
                            $absensi_filled = ($sub['attendance_count'] > 0);
                            $absensi_badge_class = $absensi_filled ? "bg-teal-50 text-teal-700 border-teal-200" : "bg-red-50 text-red-700 border-red-200";
                            $absensi_badge_text = $absensi_filled ? "Sudah Diisi" : "Belum Diisi";
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($sub['teacher_name']) ?></td>
                                <td class="px-4 py-3.5 text-teal-600 font-bold"><?= htmlspecialchars($sub['group_name']) ?></td>
                                <td class="px-4 py-3.5 text-center font-semibold"><?= $sub['member_count'] ?> Santri</td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                        <?= $sub['setoran_count'] ?> Santri
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700">
                                        <?= $sub['murojaah_count'] ?> Santri
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold border <?= $absensi_badge_class ?>">
                                        <?= $absensi_badge_text ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border <?= $status_badge_class ?>">
                                        <?= $status_badge_text ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-slate-400">Tidak ada data pengampu ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active Student Monitoring List -->
    <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 mb-4 gap-3">
            <div>
                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-tight">Monitoring Santri Tahfidz</h4>
                <p class="text-xs text-slate-400 mt-1">Daftar capaian juz, halaqah, dan kehadiran santri</p>
            </div>
            
            <form method="GET" class="flex gap-2">
                <?php if ($selected_unit): ?><input type="hidden" name="unit" value="<?= htmlspecialchars($selected_unit) ?>"><?php endif; ?>
                <?php if ($selected_kelas): ?><input type="hidden" name="kelas" value="<?= htmlspecialchars($selected_kelas) ?>"><?php endif; ?>
                <?php if ($selected_halaqah_id): ?><input type="hidden" name="halaqah_id" value="<?= $selected_halaqah_id ?>"><?php endif; ?>
                <?php if ($selected_pengampu_id): ?><input type="hidden" name="pengampu_id" value="<?= $selected_pengampu_id ?>"><?php endif; ?>
                <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>">
                
                <input type="text" name="search" placeholder="Cari nama santri..." value="<?= htmlspecialchars($search_query ?? '') ?>" class="text-xs border-slate-200 rounded-xl focus:ring-teal-500 focus:border-teal-500 px-3 py-1.5 w-48">
                <button type="submit" class="px-3 py-1.5 bg-teal-600 text-white rounded-xl text-xs font-bold hover:bg-teal-700 transition-colors">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 font-bold border-b border-slate-100 uppercase tracking-wider">
                        <th class="px-4 py-3">Nama Santri</th>
                        <th class="px-4 py-3">Kelas</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Halaqah</th>
                        <th class="px-4 py-3">Pengampu</th>
                        <th class="px-4 py-3 text-center">Terakhir Setor</th>
                        <th class="px-4 py-3 text-center">Total Capaian</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                    <?php if (!empty($santri_monitoring)): ?>
                        <?php foreach ($santri_monitoring as $sm_item): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-4 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($sm_item['full_name']) ?></td>
                                <td class="px-4 py-3.5"><?= htmlspecialchars($sm_item['kelas']) ?></td>
                                <td class="px-4 py-3.5"><?= htmlspecialchars($sm_item['tingkat']) ?></td>
                                <td class="px-4 py-3.5 text-teal-600 font-bold"><?= htmlspecialchars($sm_item['halaqah_name']) ?></td>
                                <td class="px-4 py-3.5"><?= htmlspecialchars($sm_item['teacher_name']) ?></td>
                                <td class="px-4 py-3.5 text-center"><?= $sm_item['last_setoran_date'] ? date('d-m-Y', strtotime($sm_item['last_setoran_date'])) : '-' ?></td>
                                <td class="px-4 py-3.5 text-center text-teal-600 font-black text-sm"><?= $sm_item['total_juz'] ?> Juz</td>
                                <td class="px-4 py-3.5 text-center">
                                    <a href="?detail_student_id=<?= $sm_item['id'] ?>&unit=<?= urlencode($selected_unit ?? '') ?>&kelas=<?= urlencode($selected_kelas ?? '') ?>&date=<?= $selected_date ?>" class="inline-flex items-center px-3 py-1 bg-teal-50 hover:bg-teal-100 text-teal-700 rounded-lg text-[10px] font-bold transition-colors">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-slate-400">Tidak ada data santri ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Dynamic Overlay Modal for Student Details -->
<?php
$detail_student_id = isset($_GET['detail_student_id']) ? (int)$_GET['detail_student_id'] : 0;
if ($detail_student_id > 0):
    try {
        $student_details = $service->getDetailSantri($user_id, $detail_student_id);
        $sd_profile = $student_details['profile'];
        $sd_stats = $student_details['stats'];
        $sd_att = $student_details['attendance_last_30_days'];
        $sd_history = $student_details['history'];
        ?>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-3xl w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-2xl animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-teal-800 to-cyan-900 p-6 text-white flex justify-between items-center">
                    <div>
                        <span class="text-[10px] text-teal-300 font-bold uppercase tracking-widest">Detail Santri</span>
                        <h3 class="text-xl font-bold mt-1"><?= htmlspecialchars($sd_profile['full_name']) ?></h3>
                        <p class="text-xs text-teal-100/80 mt-0.5">Kelas <?= htmlspecialchars($sd_profile['kelas']) ?> • Halaqah <?= htmlspecialchars($sd_profile['halaqah_name']) ?></p>
                    </div>
                    <!-- Close button -->
                    <a href="?unit=<?= urlencode($selected_unit ?? '') ?>&kelas=<?= urlencode($selected_kelas ?? '') ?>&date=<?= $selected_date ?>" class="text-white/60 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </a>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto flex-1 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Left: Profile Info & Stats -->
                    <div class="space-y-6">
                        <!-- Profile Card -->
                        <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl">
                            <h4 class="text-xs font-bold text-slate-400 uppercase">Ringkasan Capaian</h4>
                            <div class="mt-4 flex justify-between items-baseline">
                                <span class="text-3xl font-black text-slate-800"><?= $sd_stats['total_juz'] ?> <span class="text-xs text-slate-400 font-normal">Juz</span></span>
                                <span class="text-[10px] text-teal-600 font-bold uppercase">Target Aktif</span>
                            </div>
                            
                            <div class="grid grid-cols-2 mt-4 pt-4 border-t border-slate-200/60 gap-3 text-xs">
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Baseline Awal</p>
                                    <p class="font-bold text-slate-700"><?= $sd_stats['baseline_juz'] ?> Juz</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase">Tambahan Baru</p>
                                    <p class="font-bold text-slate-700"><?= $sd_stats['memorized_juz_semester'] ?> Juz</p>
                                </div>
                            </div>
                        </div>

                        <!-- 30-Day Attendance Stats -->
                        <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl">
                            <h4 class="text-xs font-bold text-slate-400 uppercase mb-3">Kehadiran (30 Hari Terakhir)</h4>
                            <div class="grid grid-cols-4 gap-2 text-center text-xs font-bold">
                                <div class="bg-emerald-50 text-emerald-700 p-2 rounded-xl">
                                    <p class="text-base"><?= $sd_att['Hadir'] ?></p>
                                    <p class="text-[9px] uppercase mt-1">Hadir</p>
                                </div>
                                <div class="bg-blue-50 text-blue-700 p-2 rounded-xl">
                                    <p class="text-base"><?= $sd_att['Izin'] ?></p>
                                    <p class="text-[9px] uppercase mt-1">Izin</p>
                                </div>
                                <div class="bg-amber-50 text-amber-700 p-2 rounded-xl">
                                    <p class="text-base"><?= $sd_att['Sakit'] ?></p>
                                    <p class="text-[9px] uppercase mt-1">Sakit</p>
                                </div>
                                <div class="bg-rose-50 text-rose-700 p-2 rounded-xl">
                                    <p class="text-base"><?= $sd_att['Alpha'] ?></p>
                                    <p class="text-[9px] uppercase mt-1">Alfa</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: History List (spans 2 cols) -->
                    <div class="md:col-span-2 space-y-4">
                        <h4 class="text-xs font-bold text-slate-400 uppercase">Riwayat Aktivitas Hafalan</h4>
                        <div class="overflow-x-auto border border-slate-100 rounded-2xl max-h-80 overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 font-bold uppercase tracking-wider">
                                        <th class="px-4 py-2.5">Tanggal</th>
                                        <th class="px-4 py-2.5">Jenis</th>
                                        <th class="px-4 py-2.5">Hafalan / Surah</th>
                                        <th class="px-4 py-2.5 text-center">Baris</th>
                                        <th class="px-4 py-2.5 text-center">Kelancaran</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                                    <?php if (!empty($sd_history)): ?>
                                        <?php foreach ($sd_history as $hist): ?>
                                            <tr>
                                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap"><?= date('d-m-Y', strtotime($hist['date'])) ?></td>
                                                <td class="px-4 py-3">
                                                    <?php
                                                    $tag_class = 'bg-slate-100 text-slate-600';
                                                    $lbl = 'Setoran';
                                                    if ($hist['entry_type'] === 'MUROJAAH') { $tag_class = 'bg-teal-50 text-teal-700'; $lbl = 'Murojaah'; }
                                                    elseif ($hist['entry_type'] === 'UJIAN') { $tag_class = 'bg-purple-50 text-purple-700'; $lbl = 'Ujian'; }
                                                    ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold <?= $tag_class ?>"><?= $lbl ?></span>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-800">
                                                    <?= htmlspecialchars($hist['surah_start']) ?> (<?= $hist['start_ayah'] ?>) s.d. <?= htmlspecialchars($hist['surah_end']) ?> (<?= $hist['end_ayah'] ?>)
                                                </td>
                                                <td class="px-4 py-3 text-center"><?= $hist['line_count'] ?> baris</td>
                                                <td class="px-4 py-3 text-center text-teal-600 font-bold"><?= htmlspecialchars($hist['status'] ?: ($hist['score'] && $hist['score'] != 0 ? $hist['score'] : '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada riwayat setoran.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-slate-50 px-6 py-4 flex justify-end">
                    <a href="?unit=<?= urlencode($selected_unit ?? '') ?>&kelas=<?= urlencode($selected_kelas ?? '') ?>&date=<?= $selected_date ?>" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                        Tutup
                    </a>
                </div>
            </div>
        </div>
        <?php
    } catch (Exception $e) {
        // Safe skip on error
    }
endif;
?>

<!-- Include Chart.js CDN for Visualizations -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Santri Attendance Pie Chart
    const ctxSantri = document.getElementById('santriAttendanceChart').getContext('2d');
    new Chart(ctxSantri, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Izin', 'Sakit', 'Alfa'],
            datasets: [{
                data: [
                    <?= $attendance['santri']['Hadir'] ?>,
                    <?= $attendance['santri']['Izin'] ?>,
                    <?= $attendance['santri']['Sakit'] ?>,
                    <?= $attendance['santri']['Alfa'] ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 10, family: 'Poppins' } }
                }
            }
        }
    });

    // 2. Teacher Attendance Pie Chart
    const ctxTeacher = document.getElementById('teacherAttendanceChart').getContext('2d');
    new Chart(ctxTeacher, {
        type: 'doughnut',
        data: {
            labels: ['Hadir', 'Izin', 'Sakit', 'Mangkir', 'Belum Absen'],
            datasets: [{
                data: [
                    <?= $attendance['pengampu']['Hadir'] ?>,
                    <?= $attendance['pengampu']['Izin'] ?>,
                    <?= $attendance['pengampu']['Sakit'] ?>,
                    <?= $attendance['pengampu']['Tidak Hadir'] ?>,
                    <?= $attendance['pengampu']['Belum Absen'] ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#e2e8f0'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 10, font: { size: 10, family: 'Poppins' } }
                }
            }
        }
    });

    // 3. Distribution Bar Chart
    const ctxDist = document.getElementById('distributionBarChart').getContext('2d');
    new Chart(ctxDist, {
        type: 'bar',
        data: {
            labels: ['<1 Juz', '1-5 Juz', '6-10 Juz', '11-20 Juz', '21-29 Juz', '30 Juz'],
            datasets: [{
                label: 'Jumlah Santri',
                data: [
                    <?= $distribution['Belum 1 Juz'] ?>,
                    <?= $distribution['1-5 Juz'] ?>,
                    <?= $distribution['6-10 Juz'] ?>,
                    <?= $distribution['11-20 Juz'] ?>,
                    <?= $distribution['21-29 Juz'] ?>,
                    <?= $distribution['30 Juz'] ?>
                ],
                backgroundColor: '#0d9488',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Poppins', size: 10 } } },
                x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 10 } } }
            }
        }
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
