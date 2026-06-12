<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Dashboard Tahfidz";

// Initialize Database
$db = new Database();
$conn = $db->getConnection();

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Stats Logic ---
$selected_date = $_GET['date'] ?? date('Y-m-d');
$date_limit = date('Y-m-d');
if ($selected_date > $date_limit) {
    $selected_date = $date_limit;
}
$is_today = ($selected_date === $date_limit);

$where_today = "date = :date";
if (!$is_admin) {
    $where_today .= " AND teacher_id = " . (int)$_SESSION['user_id'];
}

// Total Setoran
$todayItemsQuery = "SELECT COUNT(*) FROM tahfidz_memorization WHERE $where_today";
$todayCountStmt = $conn->prepare($todayItemsQuery);
$todayCountStmt->execute(['date' => $selected_date]);
$todayCount = $todayCountStmt->fetchColumn();

// Total Santri Setor (Distinct)
$todayStudentsQuery = "SELECT COUNT(DISTINCT student_id) FROM tahfidz_memorization WHERE $where_today";
$todayStudentCountStmt = $conn->prepare($todayStudentsQuery);
$todayStudentCountStmt->execute(['date' => $selected_date]);
$todayStudentCount = $todayStudentCountStmt->fetchColumn();

// Fetch Recent Setoran Data (Selected Date)
$query = "
    SELECT 
        tm.id, tm.student_id, tm.teacher_id, tm.date, tm.surah_start, tm.ayat_start, tm.surah_end, tm.ayat_end, tm.juz, tm.status, tm.notes, tm.created_at,
        s.nama_siswa AS student_name,
        s.kelas AS student_class,
        s.tingkat AS student_level,
        e.full_name AS teacher_name
    FROM tahfidz_memorization tm
    LEFT JOIN students s ON tm.student_id = s.id
    LEFT JOIN employees e ON tm.teacher_id = e.id
    WHERE tm.date = :date
";

if (!$is_admin) {
    $query .= " AND tm.teacher_id = " . (int)$_SESSION['user_id'];
}

$query .= " ORDER BY tm.id DESC LIMIT 50";

$stm = $conn->prepare($query);
$stm->execute(['date' => $selected_date]);
$activities = $stm->fetchAll(PDO::FETCH_ASSOC);

// --- Active Academic Year Target stats ---
$active_ay_query = "SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_ay_stmt = $conn->query($active_ay_query);
$active_ay = $active_ay_stmt->fetch(PDO::FETCH_ASSOC);

$stats_achieved = 0;
$stats_not_achieved = 0;
$class_stats = [];
$unit_stats = [];
$top_rankings = [];
$has_active_ay = (bool)$active_ay;

if ($has_active_ay) {
    $ay_name = $active_ay['name'];
    $ay_records = $conn->prepare("SELECT * FROM academic_years WHERE name = :name");
    $ay_records->execute([':name' => $ay_name]);
    $all_semesters = $ay_records->fetchAll(PDO::FETCH_ASSOC);

    $sem1_start = null; $sem1_end = null; $sem1_id = null;
    $sem2_start = null; $sem2_end = null; $sem2_id = null;
    foreach ($all_semesters as $ay) {
        if ($ay['semester'] === 'Ganjil') {
            $sem1_start = $ay['start_date'];
            $sem1_end = $ay['end_date'];
            $sem1_id = $ay['id'];
        } else {
            $sem2_start = $ay['start_date'];
            $sem2_end = $ay['end_date'];
            $sem2_id = $ay['id'];
        }
    }

    // Fetch active students in MTs and MA
    $students_query = "SELECT id, nama_siswa, kelas, tingkat FROM students WHERE status = 'Aktif' AND (tingkat IN ('MTS', 'MA', 'Mts', 'Ma') OR tingkat IS NULL)";
    $students_stmt = $conn->query($students_query);
    $all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch boarding student ids
    $boarding_student_ids = $conn->query("SELECT student_id FROM boarding_room_members")->fetchAll(PDO::FETCH_COLUMN);
    $boarding_student_set = array_flip($boarding_student_ids);

    // Fetch targets map
    $sem_ids = array_filter([$sem1_id, $sem2_id]);
    $targets_map = [];
    if (!empty($sem_ids)) {
        $targets_stmt = $conn->query("SELECT * FROM target_hafalan WHERE status_aktif = 'Aktif' AND tahun_ajaran_id IN (" . implode(',', $sem_ids) . ")");
        $raw_targets = $targets_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($raw_targets as $t) {
            $ta = $t['tahun_ajaran_id'];
            $u = $t['unit_id'];
            $p = $t['program_id'] ?? 'NULL';
            $k = $t['kelas_id'];
            $targets_map[$ta][$u][$p][$k] = (float)$t['target_juz'];
        }
    }

    // Fetch sum lines for Ganjil and Genap
    $sem1_lines = [];
    if ($sem1_start && $sem1_end) {
        $stmt1 = $conn->prepare("SELECT student_id, SUM(total_baris) as total FROM tahfidz_memorization WHERE date BETWEEN :start AND :end GROUP BY student_id");
        $stmt1->execute([':start' => $sem1_start, ':end' => $sem1_end]);
        $sem1_lines = $stmt1->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $sem2_lines = [];
    if ($sem2_start && $sem2_end) {
        $stmt2 = $conn->prepare("SELECT student_id, SUM(total_baris) as total FROM tahfidz_memorization WHERE date BETWEEN :start AND :end GROUP BY student_id");
        $stmt2->execute([':start' => $sem2_start, ':end' => $sem2_end]);
        $sem2_lines = $stmt2->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $rankings = [];

    foreach ($all_students as $student) {
        $sid = $student['id'];
        
        // Resolve Class number
        $kelas_num = null;
        if (!empty($student['kelas']) && preg_match('/^(\d+)/', $student['kelas'], $matches)) {
            $kelas_num = (int)$matches[1];
        }

        // Resolve Unit (MTs = 5, MA = 6)
        $tingkat = strtoupper(trim($student['tingkat'] ?? ''));
        $unit_id = null;
        $unit_name = '';
        if (strpos($tingkat, 'MTS') !== false) {
            $unit_id = 5;
            $unit_name = 'MTs';
        } elseif (strpos($tingkat, 'MA') !== false) {
            $unit_id = 6;
            $unit_name = 'MA';
        }

        // Fallback for Unit ID based on Class Number if tingkat is empty
        if (!$unit_id && $kelas_num) {
            if ($kelas_num >= 7 && $kelas_num <= 9) {
                $unit_id = 5;
                $unit_name = 'MTs';
            } elseif ($kelas_num >= 10 && $kelas_num <= 12) {
                $unit_id = 6;
                $unit_name = 'MA';
            }
        }
        
        if (!$unit_id || !$kelas_num) continue;

        // Resolve Program
        $program = 'Fullday';
        if ($unit_id === 5) {
            $program = isset($boarding_student_set[$sid]) ? 'Boarding' : 'Fullday';
        }

        // Resolve Target
        $target_sem1 = null;
        if ($sem1_id) {
            $p_key = ($unit_id === 5) ? $program : 'NULL';
            if (isset($targets_map[$sem1_id][$unit_id][$p_key][$kelas_num])) {
                $target_sem1 = $targets_map[$sem1_id][$unit_id][$p_key][$kelas_num];
            }
        }
        
        $target_sem2 = null;
        if ($sem2_id) {
            $p_key = ($unit_id === 5) ? $program : 'NULL';
            if (isset($targets_map[$sem2_id][$unit_id][$p_key][$kelas_num])) {
                $target_sem2 = $targets_map[$sem2_id][$unit_id][$p_key][$kelas_num];
            }
        }

        $target_overall = null;
        if ($active_ay['semester'] === 'Ganjil') {
            $target_overall = $target_sem1;
        } else {
            $target_overall = $target_sem2;
        }

        if ($target_overall === null) continue;

        // Get actual lines
        $s1_count = $sem1_lines[$sid] ?? 0;
        $s2_count = $sem2_lines[$sid] ?? 0;
        $total_juz = ($s1_count + $s2_count) / 300;

        $is_achieved = ($total_juz >= $target_overall);
        if ($is_achieved) {
            $stats_achieved++;
        } else {
            $stats_not_achieved++;
        }

        // Aggregate class stats
        if (!isset($class_stats[$kelas_num])) {
            $class_stats[$kelas_num] = ['total' => 0, 'achieved' => 0];
        }
        $class_stats[$kelas_num]['total']++;
        if ($is_achieved) {
            $class_stats[$kelas_num]['achieved']++;
        }

        // Aggregate unit stats
        if (!isset($unit_stats[$unit_name])) {
            $unit_stats[$unit_name] = ['total' => 0, 'achieved' => 0];
        }
        $unit_stats[$unit_name]['total']++;
        if ($is_achieved) {
            $unit_stats[$unit_name]['achieved']++;
        }

        // Calculate progress percentage
        $progress = 0;
        if ($target_overall > 0) {
            $progress = ($total_juz / $target_overall) * 100;
        }

        $rankings[] = [
            'name' => $student['nama_siswa'],
            'class' => $student['kelas'] ?? '-',
            'unit' => $unit_name,
            'progress' => $progress,
            'hafalan' => $total_juz,
            'target' => $target_overall
        ];
    }

    // Sort rankings: progress DESC, then hafalan DESC
    usort($rankings, function($a, $b) {
        if (abs($a['progress'] - $b['progress']) < 0.0001) {
            return $b['hafalan'] <=> $a['hafalan'];
        }
        return $b['progress'] <=> $a['progress'];
    });

    $top_rankings = array_slice($rankings, 0, 10);
    ksort($class_stats); // Sort classes by number
}

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Dashboard Tahfidz</h1>
            <p class="text-slate-500 mt-1">Monitoring real-time aktivitas setoran hafalan santri <?php echo $is_today ? 'hari ini' : 'pada tanggal ' . date('d M Y', strtotime($selected_date)); ?>.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <div class="flex items-center gap-3 bg-white p-2 rounded-xl border border-slate-200 shadow-sm">
                <label class="text-xs font-bold text-slate-400 uppercase ml-2 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                    </svg>
                    Tanggal:
                </label>
                <input type="date" value="<?php echo $selected_date; ?>" 
                    max="<?php echo date('Y-m-d'); ?>"
                    onchange="window.location.href='?date=' + this.value"
                    class="border-none bg-transparent text-sm font-bold text-slate-700 focus:ring-0 cursor-pointer">
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Total Setoran Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Total Setoran <?php echo $is_today ? 'Hari Ini' : date('d M Y', strtotime($selected_date)); ?></p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($todayCount); ?></h3>
                    <span class="ml-2 text-sm text-green-600 font-medium">record</span>
                </div>
            </div>
        </div>

        <!-- Total Santri Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Santri Setor <?php echo $is_today ? 'Hari Ini' : date('d M Y', strtotime($selected_date)); ?></p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo number_format($todayStudentCount); ?></h3>
                    <span class="ml-2 text-sm text-cyan-600 font-medium">santri</span>
                </div>
            </div>
        </div>

        <!-- Mencapai Target Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Mencapai Target</p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $has_active_ay ? number_format($stats_achieved) : '-'; ?></h3>
                    <span class="ml-2 text-sm text-emerald-600 font-medium">santri</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1"><?php echo $has_active_ay ? htmlspecialchars($active_ay['name'] . ' - ' . $active_ay['semester']) : 'Tahun Ajaran Inaktif'; ?></p>
            </div>
        </div>

        <!-- Belum Mencapai Target Card -->
        <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow p-6 border border-slate-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                 <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-medium text-slate-500 uppercase tracking-wide">Belum Mencapai Target</p>
                <div class="mt-2 flex items-baseline">
                    <h3 class="text-3xl font-bold text-slate-800"><?php echo $has_active_ay ? number_format($stats_not_achieved) : '-'; ?></h3>
                    <span class="ml-2 text-sm text-rose-600 font-medium">santri</span>
                </div>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1"><?php echo $has_active_ay ? htmlspecialchars($active_ay['name'] . ' - ' . $active_ay['semester']) : 'Tahun Ajaran Inaktif'; ?></p>
            </div>
        </div>
    </div>

    <!-- Active Academic Year Insights -->
    <?php if (!$has_active_ay): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-700 text-sm font-semibold flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-500 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <span>Perhatian: Tidak ada Tahun Ajaran yang diatur sebagai Aktif saat ini. Pengukuran target hafalan dinonaktifkan. Silakan aktifkan tahun ajaran di halaman Kelola Tahun Ajaran.</span>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- Unit & Class Target Achievements -->
            <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="text-base font-bold text-slate-800">Ketercapaian Target Hafalan</h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Persentase Ketercapaian per Unit & Kelas</p>
                </div>
                <div class="p-6 space-y-6 flex-1 overflow-y-auto">
                    <!-- Unit Stats -->
                    <div class="space-y-4">
                        <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Berdasarkan Unit Pendidikan</h4>
                        <?php if (empty($unit_stats)): ?>
                            <p class="text-xs text-slate-400 italic">Belum ada data unit</p>
                        <?php else: ?>
                            <?php foreach ($unit_stats as $unit => $c): 
                                $pct = $c['total'] > 0 ? ($c['achieved'] / $c['total']) * 100 : 0;
                            ?>
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-xs font-bold">
                                        <span class="text-slate-700">Unit <?php echo htmlspecialchars($unit); ?></span>
                                        <span class="text-slate-500"><?php echo round($pct, 1); ?>% <span class="text-[10px] font-medium text-slate-400">(<?php echo $c['achieved']; ?>/<?php echo $c['total']; ?> santri)</span></span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden shadow-inner">
                                        <div class="bg-gradient-to-r from-cyan-500 to-blue-600 h-full rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Class Stats -->
                    <div class="space-y-4 pt-2">
                        <h4 class="text-xs font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Berdasarkan Kelas</h4>
                        <?php if (empty($class_stats)): ?>
                            <p class="text-xs text-slate-400 italic">Belum ada data kelas</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($class_stats as $kelas => $c): 
                                    $pct = $c['total'] > 0 ? ($c['achieved'] / $c['total']) * 100 : 0;
                                ?>
                                    <div class="space-y-1.5">
                                        <div class="flex justify-between text-xs font-bold">
                                            <span class="text-slate-700">Kelas <?php echo htmlspecialchars($kelas); ?></span>
                                            <span class="text-slate-500"><?php echo round($pct, 0); ?>% <span class="text-[9px] font-medium text-slate-400">(<?php echo $c['achieved']; ?>/<?php echo $c['total']; ?>)</span></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden shadow-inner">
                                            <div class="bg-gradient-to-r from-blue-400 to-indigo-500 h-full rounded-full transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Rankings (Top 10) -->
            <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Ranking Perkembangan Hafalan</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Top 10 Santri Berdasarkan Progress Capaian Target</p>
                    </div>
                    <span class="h-6 px-2.5 rounded-full bg-cyan-100 text-cyan-700 text-[10px] font-black uppercase tracking-wider flex items-center">TA Aktif</span>
                </div>
                <div class="overflow-x-auto flex-1">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3 text-center w-12">Rank</th>
                                <th class="px-4 py-3">Santri</th>
                                <th class="px-4 py-3 text-center">Hafalan</th>
                                <th class="px-4 py-3 text-center">Target</th>
                                <th class="px-4 py-3">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php if (empty($top_rankings)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-slate-400 italic">Belum ada data progress santri.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($top_rankings as $idx => $r): 
                                    $rank = $idx + 1;
                                    $badgeClass = 'bg-slate-100 text-slate-500';
                                    if ($rank === 1) $badgeClass = 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                                    elseif ($rank === 2) $badgeClass = 'bg-slate-200 text-slate-700 border border-slate-300';
                                    elseif ($rank === 3) $badgeClass = 'bg-amber-100 text-amber-800 border border-amber-250';
                                ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-2.5 text-center font-black">
                                            <span class="inline-flex items-center justify-center h-5 w-5 rounded-full text-[10px] <?php echo $badgeClass; ?>">
                                                <?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <p class="font-bold text-slate-800"><?php echo htmlspecialchars($r['name']); ?></p>
                                            <p class="text-[9px] text-slate-450 font-semibold">Kelas <?php echo htmlspecialchars($r['class']); ?> • <?php echo htmlspecialchars($r['unit']); ?></p>
                                        </td>
                                        <td class="px-4 py-2.5 text-center font-bold text-slate-900"><?php echo number_format($r['hafalan'], 1, ',', '.'); ?> Juz</td>
                                        <td class="px-4 py-2.5 text-center font-semibold text-slate-500"><?php echo number_format($r['target'], 1, ',', '.'); ?> Juz</td>
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-16 bg-slate-100 rounded-full h-1.5 overflow-hidden shadow-inner flex-shrink-0">
                                                    <div class="bg-gradient-to-r from-cyan-500 to-blue-500 h-full rounded-full" style="width: <?php echo min(100, $r['progress']); ?>%"></div>
                                                </div>
                                                <span class="font-black text-slate-700 text-[10px]"><?php echo round($r['progress'], 0); ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    <?php endif; ?>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
            <h3 class="text-lg font-bold text-slate-800">Aktivitas Terbaru</h3>
            <div class="flex space-x-2">
                 <!-- Actions -->
                 <button onclick="window.location.reload()" class="text-slate-400 hover:text-slate-600" title="Refresh Halaman">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                 </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Waktu</th>
                        <th class="px-6 py-3">Santri</th>
                        <th class="px-6 py-3">Setoran / Hafalan</th>
                        <th class="px-6 py-3">Catatan</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Pengampu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($activities) > 0): ?>
                        <?php foreach ($activities as $row): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                    <?php 
                                        // If 'created_at' exists use it, else just date 
                                        // db_schema suggests created_at typically exists, assuming timestamp
                                        // If query has no time column, we might just show Date.
                                        // But this is "Today", so time is valuable. 
                                        // Usually tables have created_at. Let's check schema visually if possible, but safe fallback.
                                        echo isset($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : '-'; 
                                    ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-cyan-100 flex items-center justify-center text-cyan-600 font-bold text-xs ring-2 ring-white">
                                            <?php echo substr($row['student_name'], 0, 1); ?>
                                        </div>
                                        <div class="ml-3">
                                            <p class="font-medium text-slate-800"><?php echo htmlspecialchars($row['student_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($row['student_class'] ?? '-'); ?> • <?php echo htmlspecialchars($row['student_level'] ?? ''); ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700 font-medium">
                                        <?php echo htmlspecialchars($row['surah_start']); ?> : <?php echo $row['ayat_start']; ?>
                                        <span class="mx-1 text-slate-300">s.d.</span>
                                        <?php echo htmlspecialchars($row['surah_end']); ?> : <?php echo $row['ayat_end']; ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-500 font-bold text-[10px] uppercase tracking-tight">
                                            Juz <?php echo htmlspecialchars($row['juz'] ?? '-'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 max-w-[150px] truncate">
                                    <?php echo htmlspecialchars($row['notes'] ?: '-'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $statusColor = 'bg-slate-100 text-slate-600';
                                        $s = strtolower($row['status']);
                                        if (strpos($s, 'lancar') !== false) $statusColor = 'bg-green-100 text-green-700';
                                        elseif (strpos($s, 'ulang') !== false) $statusColor = 'bg-red-100 text-red-700';
                                        elseif (strpos($s, 'kurang') !== false) $statusColor = 'bg-yellow-100 text-yellow-700';
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusColor; ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-500">
                                    <?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <p class="text-base font-medium">Belum ada data setoran <?php echo $is_today ? 'hari ini' : 'pada tanggal ' . date('d M Y', strtotime($selected_date)); ?></p>
                                    <p class="text-sm text-slate-400 mt-1">Data akan muncul setelah ada input dari aplikasi mobile</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
