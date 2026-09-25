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

// Employee & Attendance Rates
$active_percent = $emp_count > 0 ? round(($active_count / $emp_count) * 100, 1) : 0;
$inactive_percent = $emp_count > 0 ? round(($inactive_count / $emp_count) * 100, 1) : 0;
$present_rate = $active_count > 0 ? round(($present_count / $active_count) * 100, 1) : 0;
$absent_rate = $active_count > 0 ? round(($absent_count / $active_count) * 100, 1) : 0;
$late_rate = $present_count > 0 ? round(($late_count / $present_count) * 100, 1) : 0;

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
// Union query to get both Absen Masuk and Absen Keluar events ordered by time
$activity_query = "
    SELECT a.user_id, e.full_name, a.time_in as time, 'Absen Masuk' as event_type, a.status as status_label, a.status as status_code
    FROM attendances a 
    JOIN employees e ON a.user_id = e.id 
    WHERE a.date = '$today'
    UNION
    SELECT a.user_id, e.full_name, a.time_out as time, 'Absen Keluar' as event_type, a.status_out as status_label, a.status_out as status_code
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

// --- Employee Attendance Trend Bar Chart Data (Last 7 Days) ---
$bar_labels = [];
$bar_full_dates = [];
$bar_hadir_pct = [];
$bar_izin_pct = [];
$bar_alpa_pct = [];
$bar_hadir_count = [];
$bar_izin_count = [];
$bar_alpa_count = [];

$day_names_id = [
    'Sun' => 'Ahad', 'Mon' => 'Senin', 'Tue' => 'Selasa', 'Wed' => 'Rabu',
    'Thu' => 'Kamis', 'Fri' => 'Jumat', 'Sat' => 'Sabtu'
];

$month_names_id = [
    '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
    '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agt',
    '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
];

$bar_late_count = [];

for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $short_day = date('D', strtotime($d));
    $label_date = date('d/m', strtotime($d));
    $day_str = ($day_names_id[$short_day] ?? $short_day) . ' (' . $label_date . ')';
    $full_date_str = ($day_names_id[$short_day] ?? $short_day) . ', ' . date('d', strtotime($d)) . ' ' . ($month_names_id[date('m', strtotime($d))] ?? date('M', strtotime($d))) . ' ' . date('Y', strtotime($d));

    $d_hadir = (int)$conn->query("SELECT COUNT(DISTINCT user_id) FROM attendances WHERE date = '$d' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')")->fetchColumn();
    $d_late = (int)$conn->query("SELECT COUNT(DISTINCT user_id) FROM attendances WHERE date = '$d' AND status IN ('Late', 'Telat')")->fetchColumn();
    $d_izin = (int)$conn->query("SELECT COUNT(DISTINCT employee_id) FROM permits WHERE status = 'Approved' AND start_date <= '$d' AND end_date >= '$d'")->fetchColumn();
    $d_alpa = max(0, (int)$active_count - $d_hadir - $d_izin);

    $h_pct = $active_count > 0 ? round(($d_hadir / $active_count) * 100, 1) : 0;
    $i_pct = $active_count > 0 ? round(($d_izin / $active_count) * 100, 1) : 0;
    $a_pct = $active_count > 0 ? round(($d_alpa / $active_count) * 100, 1) : 0;

    $bar_labels[] = $day_str;
    $bar_full_dates[] = $full_date_str;
    $bar_hadir_pct[] = $h_pct;
    $bar_izin_pct[] = $i_pct;
    $bar_alpa_pct[] = $a_pct;
    $bar_hadir_count[] = $d_hadir;
    $bar_late_count[] = $d_late;
    $bar_izin_count[] = $d_izin;
    $bar_alpa_count[] = $d_alpa;
}

// Summary Averages for 7-day bar chart KPI cards
$avg_hadir_pct = count($bar_hadir_pct) > 0 ? round(array_sum($bar_hadir_pct) / count($bar_hadir_pct), 1) : 0;
$avg_izin_pct = count($bar_izin_pct) > 0 ? round(array_sum($bar_izin_pct) / count($bar_izin_pct), 1) : 0;
$avg_alpa_pct = count($bar_alpa_pct) > 0 ? round(array_sum($bar_alpa_pct) / count($bar_alpa_pct), 1) : 0;
$max_hadir_val = !empty($bar_hadir_pct) ? max($bar_hadir_pct) : 0;
$max_hadir_idx = !empty($bar_hadir_pct) ? array_search($max_hadir_val, $bar_hadir_pct) : false;
$best_day_label = ($max_hadir_idx !== false && isset($bar_labels[$max_hadir_idx])) ? $bar_labels[$max_hadir_idx] : '-';

// Backward compatibility alias if needed
$line_labels = &$bar_labels;
$line_hadir_pct = &$bar_hadir_pct;
$line_izin_pct = &$bar_izin_pct;
$line_alpa_pct = &$bar_alpa_pct;

/**
 * Generate smooth periodic cubic Bezier SVG paths for fluid summary card waves.
 * Each card gets a unique water level (based on rate/capacity), dynamic crests/troughs
 * from its 7-day trend, and distinct harmonic phase shift for organic motion.
 */
function generate_card_wave_paths($values, $percent, $phase_shift = 0, $width = 300, $height = 60) {
    $clamped_pct = max(0, min(100, (float)$percent));
    $base_y = 50 - ($clamped_pct / 100) * 32; // range [18, 50]
    
    $val_count = count($values);
    $min_val = $val_count > 0 ? min($values) : 0;
    $max_val = $val_count > 0 ? max($values) : 0;
    $range = $max_val - $min_val;
    $avg = $val_count > 0 ? array_sum($values) / $val_count : 0;
    
    $num_points = 5;
    $step_x = $width / $num_points;
    
    $front_pts = [];
    $back_pts = [];
    
    for ($i = 0; $i <= $num_points; $i++) {
        $x = $i * $step_x;
        
        $val_idx = $i % $num_points;
        $mapped_val = $val_count > 0 ? ($values[$val_idx % $val_count] ?? $avg) : 0;
        
        $deviation = $range > 0 ? (($mapped_val - $avg) / $range) : 0;
        
        $angle = $i * (2 * M_PI / $num_points);
        $harmonic_front = sin($angle + $phase_shift) * 4.2;
        $harmonic_back = cos($angle + $phase_shift + 0.8) * 3.5;
        
        $trend_front = -$deviation * 6.5;
        $trend_back = $deviation * 4.5;
        
        $y_front = round(max(15, min(54, $base_y + $trend_front + $harmonic_front)), 1);
        $y_back = round(max(18, min(55, $base_y + 4 + $trend_back + $harmonic_back)), 1);
        
        $front_pts[] = ['x' => $x, 'y' => $y_front];
        $back_pts[] = ['x' => $x, 'y' => $y_back];
    }
    
    // Ensure 100% seamless periodic closure between x=0 and x=300
    $front_pts[$num_points]['y'] = $front_pts[0]['y'];
    $back_pts[$num_points]['y'] = $back_pts[0]['y'];
    
    $build_bezier = function($pts) use ($num_points, $width, $height) {
        $slopes = [];
        $dx = $pts[1]['x'] - $pts[0]['x'];
        for ($i = 0; $i < $num_points; $i++) {
            $prev = ($i - 1 + $num_points) % $num_points;
            $next = ($i + 1) % $num_points;
            $slopes[$i] = ($pts[$next]['y'] - $pts[$prev]['y']) / (2 * $dx);
        }
        $slopes[$num_points] = $slopes[0];
        
        $stroke_cmd = "M 0 " . $pts[0]['y'];
        $tension = 0.35;
        
        for ($i = 0; $i < $num_points; $i++) {
            $p0 = $pts[$i];
            $p1 = $pts[$i + 1];
            $cp1_x = round($p0['x'] + $dx * $tension, 1);
            $cp1_y = round($p0['y'] + $slopes[$i] * $dx * $tension, 1);
            $cp2_x = round($p1['x'] - $dx * $tension, 1);
            $cp2_y = round($p1['y'] - $slopes[$i + 1] * $dx * $tension, 1);
            
            $stroke_cmd .= " C $cp1_x $cp1_y, $cp2_x $cp2_y, {$p1['x']} {$p1['y']}";
        }
        
        $fill_cmd = $stroke_cmd . " L $width $height L 0 $height Z";
        return ['stroke' => $stroke_cmd, 'fill' => $fill_cmd];
    };
    
    $front_paths = $build_bezier($front_pts);
    $back_paths = $build_bezier($back_pts);
    
    return [
        'front_fill' => $front_paths['fill'],
        'front_stroke' => $front_paths['stroke'],
        'back_fill' => $back_paths['fill'],
        'back_stroke' => $back_paths['stroke'],
        'baseline_y' => $base_y
    ];
}

// Generate unique, data-driven wave paths for each of the 6 summary cards
$wave_total = generate_card_wave_paths(array_fill(0, 7, (int)$emp_count), 100, 0.0);
$wave_active = generate_card_wave_paths(array_fill(0, 7, (int)$active_count), $active_percent, 1.2);
$wave_inactive = generate_card_wave_paths(array_fill(0, 7, (int)$inactive_count), $inactive_percent, 2.4);
$wave_present = generate_card_wave_paths($bar_hadir_count, $present_rate, 0.6);
$wave_late = generate_card_wave_paths($bar_late_count, $late_rate, 1.8);
$wave_absent = generate_card_wave_paths($bar_alpa_count, $absent_rate, 3.0);

// --- Recent Permits (5 entries, current month only) ---
$current_month_str = date('Y-m');
$recent_permits_query = "
    SELECT p.*, e.full_name, pos.name as position_name
    FROM permits p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    WHERE DATE_FORMAT(p.created_at, '%Y-%m') = :current_month
    ORDER BY p.created_at DESC
    LIMIT 5
";
$stmt_permits = $conn->prepare($recent_permits_query);
$stmt_permits->execute([':current_month' => $current_month_str]);
$recent_permits = $stmt_permits->fetchAll(PDO::FETCH_ASSOC);

// Map status to Indonesian labels
$statusTextMap = [
    'Pending' => 'Menunggu',
    'Approved' => 'Disetujui',
    'Rejected' => 'Ditolak'
];

// Greeting Logic
$hour = (int)date('H');
if ($hour >= 4 && $hour < 11) {
    $greeting = "Selamat Pagi";
} elseif ($hour >= 11 && $hour < 15) {
    $greeting = "Selamat Siang";
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = "Selamat Sore";
} else {
    $greeting = "Selamat Malam";
}
$user_name = $_SESSION['user_name'] ?? 'User';

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Greeting Card -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border-l-4 border-[#2B3990] relative overflow-hidden transition-all duration-300 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- Decorative subtle pattern/background shape -->
        <div class="absolute right-0 bottom-0 opacity-[0.03] translate-x-10 translate-y-10 pointer-events-none">
            <i class="fa-solid fa-user text-[#2B3990]" style="font-size: 16rem;"></i>
        </div>
        
        <div class="relative z-10">
            <p class="text-xs font-bold text-[#2B3990] uppercase tracking-wider mb-1">Beranda Dashboard</p>
            <h1 class="text-xl md:text-2xl font-bold text-slate-800">
                <?php echo $greeting; ?>, <span class="text-[#2B3990]"><?php echo htmlspecialchars($user_name); ?></span>!
            </h1>
            <p class="text-slate-500 text-sm mt-1.5 max-w-xl leading-relaxed">
                Ahlan wa sahlan di sistem pengelolaan dashboard administrasi Yayasan Assunnah Cirebon.
            </p>
        </div>
        <div class="relative z-10 shrink-0 flex items-center gap-3 bg-slate-50 rounded-xl p-3 border border-slate-100 self-start md:self-auto">
            <div class="p-2 bg-indigo-50 text-[#2B3990] rounded-lg flex items-center justify-center">
                <i class="fa-solid fa-calendar-days text-base"></i>
            </div>
            <div class="text-left">
                <p class="text-[10px] text-slate-400 uppercase font-semibold">Hari Ini</p>
                <p class="text-xs font-bold text-slate-700"><?php echo date('d M Y'); ?></p>
            </div>
        </div>
    </div>

    <style>
    @keyframes waveFlowFront {
        0% { transform: translate3d(0, 0, 0); }
        100% { transform: translate3d(-50%, 0, 0); }
    }
    @keyframes waveFlowBack {
        0% { transform: translate3d(0, 0, 0); }
        100% { transform: translate3d(-50%, 0, 0); }
    }
    .wave-anim-front {
        animation: waveFlowFront 8s linear infinite;
        will-change: transform;
    }
    .wave-anim-back {
        animation: waveFlowBack 14s linear infinite;
        will-change: transform;
    }
    @media (prefers-reduced-motion: reduce) {
        .wave-anim-front, .wave-anim-back {
            animation: none !important;
        }
    }
    </style>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

        <!-- 1. Total Employees Card -->
        <a href="<?php url('views/employees/index.php'); ?>"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-indigo-500/10 hover:border-indigo-200 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-indigo-50/70 rounded-full blur-2xl group-hover:bg-indigo-100/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-indigo-50 text-[#2B3990] group-hover:scale-110 group-hover:bg-[#2B3990] group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-users text-lg"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200/80 group-hover:border-indigo-200 transition-colors">
                        Pegawai
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Total Pegawai</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$emp_count; ?>">
                            <?php echo number_format($emp_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <span>Status Keaktifan</span>
                        <span class="text-blue-600 font-bold"><?php echo $active_percent; ?>% Aktif</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Status Keaktifan: <?php echo $active_percent; ?>% Aktif | Kapasitas: 100%">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-total-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#60a5fa" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#60a5fa" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_total['back_fill']; ?>" fill="url(#wave-grad-total-back)" />
                                <path d="<?php echo $wave_total['back_stroke']; ?>" stroke="#93c5fd" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_total['back_fill']; ?>" fill="url(#wave-grad-total-back)" />
                                <path d="<?php echo $wave_total['back_stroke']; ?>" stroke="#93c5fd" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-total-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_total['front_fill']; ?>" fill="url(#wave-grad-total-front)" />
                                <path d="<?php echo $wave_total['front_stroke']; ?>" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_total['front_fill']; ?>" fill="url(#wave-grad-total-front)" />
                                <path d="<?php echo $wave_total['front_stroke']; ?>" stroke="#3b82f6" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-blue-600 relative z-10">
                <span>Kelola Data Pegawai</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>

        <!-- 2. Active Employees Card -->
        <a href="<?php url('views/employees/index.php'); ?>?status=active"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-emerald-500/10 hover:border-emerald-200 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-emerald-50/70 rounded-full blur-2xl group-hover:bg-emerald-100/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-user-check text-lg"></i>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Aktif
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Pegawai Aktif</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$active_count; ?>">
                            <?php echo number_format($active_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <span>Rasio Aktif</span>
                        <span class="text-emerald-600 font-bold"><?php echo $active_percent; ?>%</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Rasio Pegawai Aktif: <?php echo $active_percent; ?>%">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-active-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#34d399" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#34d399" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_active['back_fill']; ?>" fill="url(#wave-grad-active-back)" />
                                <path d="<?php echo $wave_active['back_stroke']; ?>" stroke="#6ee7b7" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_active['back_fill']; ?>" fill="url(#wave-grad-active-back)" />
                                <path d="<?php echo $wave_active['back_stroke']; ?>" stroke="#6ee7b7" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-active-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_active['front_fill']; ?>" fill="url(#wave-grad-active-front)" />
                                <path d="<?php echo $wave_active['front_stroke']; ?>" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_active['front_fill']; ?>" fill="url(#wave-grad-active-front)" />
                                <path d="<?php echo $wave_active['front_stroke']; ?>" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-emerald-600 relative z-10">
                <span>Filter Pegawai Aktif</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>

        <!-- 3. Inactive Employees Card -->
        <a href="<?php url('views/employees/index.php'); ?>?status=inactive"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-slate-500/10 hover:border-slate-300 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-slate-100/70 rounded-full blur-2xl group-hover:bg-slate-200/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-slate-100 text-slate-600 group-hover:scale-110 group-hover:bg-slate-700 group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-user-slash text-lg"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-600 border border-slate-200/80">
                        Nonaktif
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Pegawai Nonaktif</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$inactive_count; ?>">
                            <?php echo number_format($inactive_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <span>Persentase Nonaktif</span>
                        <span class="text-slate-600 font-bold"><?php echo $inactive_percent; ?>%</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Rasio Pegawai Nonaktif: <?php echo $inactive_percent; ?>%">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-inactive-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#94a3b8" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#94a3b8" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_inactive['back_fill']; ?>" fill="url(#wave-grad-inactive-back)" />
                                <path d="<?php echo $wave_inactive['back_stroke']; ?>" stroke="#cbd5e1" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_inactive['back_fill']; ?>" fill="url(#wave-grad-inactive-back)" />
                                <path d="<?php echo $wave_inactive['back_stroke']; ?>" stroke="#cbd5e1" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-inactive-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#64748b" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#64748b" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_inactive['front_fill']; ?>" fill="url(#wave-grad-inactive-front)" />
                                <path d="<?php echo $wave_inactive['front_stroke']; ?>" stroke="#64748b" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_inactive['front_fill']; ?>" fill="url(#wave-grad-inactive-front)" />
                                <path d="<?php echo $wave_inactive['front_stroke']; ?>" stroke="#64748b" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-slate-600 relative z-10">
                <span>Kelola Pegawai Nonaktif</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>

        <!-- 4. Present Today Card -->
        <a href="<?php url('views/attendance/daily_recap.php'); ?>?date=<?php echo $today; ?>"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-teal-500/10 hover:border-teal-200 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-teal-50/70 rounded-full blur-2xl group-hover:bg-teal-100/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-teal-50 text-teal-600 group-hover:scale-110 group-hover:bg-teal-600 group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-clipboard-user text-lg"></i>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-500"></span>
                        Hari Ini
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Hadir Hari Ini</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$present_count; ?>">
                            <?php echo number_format($present_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <span>Tingkat Kehadiran</span>
                        <span class="text-teal-600 font-bold"><?php echo $present_rate; ?>%</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Tingkat Kehadiran: <?php echo $present_rate; ?>% | Tren Hadir 7 Hari: <?php echo implode(', ', $bar_hadir_count); ?>">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-present-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#2dd4bf" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#2dd4bf" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_present['back_fill']; ?>" fill="url(#wave-grad-present-back)" />
                                <path d="<?php echo $wave_present['back_stroke']; ?>" stroke="#5eead4" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_present['back_fill']; ?>" fill="url(#wave-grad-present-back)" />
                                <path d="<?php echo $wave_present['back_stroke']; ?>" stroke="#5eead4" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-present-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#0d9488" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#0d9488" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_present['front_fill']; ?>" fill="url(#wave-grad-present-front)" />
                                <path d="<?php echo $wave_present['front_stroke']; ?>" stroke="#0d9488" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_present['front_fill']; ?>" fill="url(#wave-grad-present-front)" />
                                <path d="<?php echo $wave_present['front_stroke']; ?>" stroke="#0d9488" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-teal-600 relative z-10">
                <span>Buka Rekap Harian</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>

        <!-- 5. Late Arrivals Card -->
        <a href="<?php url('views/attendance/daily_recap.php'); ?>?tab=late&date=<?php echo $today; ?>"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-amber-500/10 hover:border-amber-200 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-amber-50/70 rounded-full blur-2xl group-hover:bg-amber-100/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-amber-50 text-amber-600 group-hover:scale-110 group-hover:bg-amber-600 group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-business-time text-lg"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200/60">
                        Terlambat
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Datang Terlambat</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$late_count; ?>">
                            <?php echo number_format($late_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <span>Rasio Keterlambatan</span>
                        <span class="text-amber-600 font-bold"><?php echo $late_rate; ?>%</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Rasio Terlambat: <?php echo $late_rate; ?>% | Tren Telat 7 Hari: <?php echo implode(', ', $bar_late_count); ?>">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-late-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#fbbf24" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#fbbf24" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_late['back_fill']; ?>" fill="url(#wave-grad-late-back)" />
                                <path d="<?php echo $wave_late['back_stroke']; ?>" stroke="#fcd34d" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_late['back_fill']; ?>" fill="url(#wave-grad-late-back)" />
                                <path d="<?php echo $wave_late['back_stroke']; ?>" stroke="#fcd34d" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-late-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#f59e0b" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_late['front_fill']; ?>" fill="url(#wave-grad-late-front)" />
                                <path d="<?php echo $wave_late['front_stroke']; ?>" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_late['front_fill']; ?>" fill="url(#wave-grad-late-front)" />
                                <path d="<?php echo $wave_late['front_stroke']; ?>" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-amber-600 relative z-10">
                <span>Lihat Log Keterlambatan</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>

        <!-- 6. Absent Today Card -->
        <a href="<?php url('views/attendance/daily_recap.php'); ?>?tab=absenteeism&date=<?php echo $today; ?>"
            class="group bg-white rounded-2xl p-5 border border-slate-100 shadow-sm transition-all duration-300 hover:-translate-y-1.5 hover:shadow-xl hover:shadow-rose-500/10 hover:border-rose-200 relative overflow-hidden flex flex-col justify-between cursor-pointer">
            <!-- Subtle Hover Ambient Glow -->
            <div class="absolute -right-12 -top-12 w-32 h-32 bg-rose-50/70 rounded-full blur-2xl group-hover:bg-rose-100/80 transition-all duration-500 pointer-events-none"></div>

            <div>
                <div class="flex justify-between items-start relative z-10">
                    <div class="flex items-center justify-center h-11 w-11 rounded-xl bg-rose-50 text-rose-600 group-hover:scale-110 group-hover:bg-rose-600 group-hover:text-white transition-all duration-300 shadow-xs">
                        <i class="fa-solid fa-user-xmark text-lg"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200/60">
                        Tidak Hadir
                    </span>
                </div>

                <div class="mt-4 relative z-10">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-slate-500 transition-colors">Tidak Hadir</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-800 tracking-tight counter-val" data-target="<?php echo (int)$absent_count; ?>">
                            <?php echo number_format($absent_count); ?>
                        </span>
                        <span class="text-xs font-medium text-slate-400">Pegawai</span>
                    </div>
                </div>

                <!-- Context & Wave Line -->
                <div class="mt-3 relative z-10">
                    <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 mb-0.5">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200/60">
                                Izin: <?php echo (int)$permits_today_count; ?>
                            </span>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200/60">
                                Tanpa Ket: <?php echo max(0, (int)$absent_count - (int)$permits_today_count); ?>
                            </span>
                        </div>
                        <span class="text-rose-600 font-bold"><?php echo $absent_rate; ?>%</span>
                    </div>
                    <div class="w-full h-11 overflow-hidden relative -mb-1 group-hover:scale-y-110 transition-transform duration-500 origin-bottom pointer-events-none" title="Tingkat Tidak Hadir: <?php echo $absent_rate; ?>% | Tren Tidak Hadir 7 Hari: <?php echo implode(', ', $bar_alpa_count); ?>">
                        <!-- Layer 1: Back secondary wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-back">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-absent-back" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#fb7185" stop-opacity="0.22" />
                                        <stop offset="100%" stop-color="#fb7185" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_absent['back_fill']; ?>" fill="url(#wave-grad-absent-back)" />
                                <path d="<?php echo $wave_absent['back_stroke']; ?>" stroke="#fda4af" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_absent['back_fill']; ?>" fill="url(#wave-grad-absent-back)" />
                                <path d="<?php echo $wave_absent['back_stroke']; ?>" stroke="#fda4af" stroke-width="1.2" stroke-linecap="round" />
                            </svg>
                        </div>
                        <!-- Layer 2: Front main wave (animated) -->
                        <div class="absolute inset-0 flex w-[200%] wave-anim-front">
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <defs>
                                    <linearGradient id="wave-grad-absent-front" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.32" />
                                        <stop offset="100%" stop-color="#f43f5e" stop-opacity="0.0" />
                                    </linearGradient>
                                </defs>
                                <path d="<?php echo $wave_absent['front_fill']; ?>" fill="url(#wave-grad-absent-front)" />
                                <path d="<?php echo $wave_absent['front_stroke']; ?>" stroke="#f43f5e" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <svg class="w-1/2 h-full shrink-0" viewBox="0 0 300 60" preserveAspectRatio="none" fill="none">
                                <path d="<?php echo $wave_absent['front_fill']; ?>" fill="url(#wave-grad-absent-front)" />
                                <path d="<?php echo $wave_absent['front_stroke']; ?>" stroke="#f43f5e" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs font-semibold text-rose-600 relative z-10">
                <span>Rincian Ketidakhadiran</span>
                <i class="fa-solid fa-arrow-right text-[11px] transform group-hover:translate-x-1.5 transition-transform duration-200"></i>
            </div>
        </a>
    </div>

    <!-- Attendance Trend Bar Chart Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6 transition-all duration-300 hover:shadow-md">
        <!-- Top Header: Title, Description & Action Controls -->
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between pb-4 border-b border-slate-100 mb-5 gap-4">
            <div class="flex items-start gap-3">
                <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-gradient-to-br from-[#2B3990] to-indigo-600 text-white shadow-sm shrink-0">
                    <i class="fa-solid fa-chart-simple text-lg"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base font-bold text-slate-800">
                            Tren & Persentase Kehadiran Pegawai
                        </h3>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-[#2B3990] border border-indigo-100">
                            <i class="fa-solid fa-calendar-week text-[10px]"></i> 7 Hari Terakhir
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Komparasi tingkat kehadiran harian (Hadir, Izin, dan Alpa) terhadap total pegawai aktif (<?php echo number_format($active_count); ?> orang)</p>
                </div>
            </div>

            <!-- View Switcher & Interactive Legends -->
            <div class="flex items-center gap-3 flex-wrap self-start xl:self-auto">
                <!-- Grouped / Stacked View Switcher -->
                <div class="bg-slate-100 p-1 rounded-xl flex items-center border border-slate-200/80 text-xs font-semibold">
                    <button type="button" id="btnChartGrouped" onclick="setChartBarMode('grouped')" 
                        class="chart-mode-btn px-3 py-1.5 rounded-lg text-slate-700 bg-white shadow-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-chart-column text-indigo-600 text-[11px]"></i>
                        <span>Berdampingan</span>
                    </button>
                    <button type="button" id="btnChartStacked" onclick="setChartBarMode('stacked')" 
                        class="chart-mode-btn px-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-800 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-bars-staggered text-[11px]"></i>
                        <span>Akumulasi (100%)</span>
                    </button>
                </div>

                <!-- Interactive Legend Buttons -->
                <div class="flex items-center gap-1.5 text-xs font-semibold">
                    <button type="button" onclick="toggleDataset(0)" id="legendBtn0" title="Klik untuk sembunyikan/tampilkan Hadir"
                        class="legend-filter-btn inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50/80 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-all shadow-2xs cursor-pointer select-none">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-200"></span>
                        <span>Hadir</span>
                    </button>
                    <button type="button" onclick="toggleDataset(1)" id="legendBtn1" title="Klik untuk sembunyikan/tampilkan Izin"
                        class="legend-filter-btn inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-amber-50/80 text-amber-700 border border-amber-200 hover:bg-amber-100 transition-all shadow-2xs cursor-pointer select-none">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500 ring-2 ring-amber-200"></span>
                        <span>Izin</span>
                    </button>
                    <button type="button" onclick="toggleDataset(2)" id="legendBtn2" title="Klik untuk sembunyikan/tampilkan Tidak Hadir"
                        class="legend-filter-btn inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-rose-50/80 text-rose-700 border border-rose-200 hover:bg-rose-100 transition-all shadow-2xs cursor-pointer select-none">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 ring-2 ring-rose-200"></span>
                        <span>Tidak Hadir</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Metric KPI Cards: 7-Day Performance Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="bg-gradient-to-br from-white to-emerald-50/40 rounded-xl p-3 border border-emerald-100/90 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Rata-rata Hadir</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span class="text-xl font-extrabold text-emerald-700"><?php echo $avg_hadir_pct; ?>%</span>
                        <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-100/70 px-1.5 py-0.5 rounded-full">7 Hari</span>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-100/70 text-emerald-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-white to-amber-50/40 rounded-xl p-3 border border-amber-100/90 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Rata-rata Izin</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span class="text-xl font-extrabold text-amber-700"><?php echo $avg_izin_pct; ?>%</span>
                        <span class="text-[10px] font-semibold text-amber-600 bg-amber-100/70 px-1.5 py-0.5 rounded-full">Disetujui</span>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-lg bg-amber-100/70 text-amber-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-envelope-open-text"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-white to-rose-50/40 rounded-xl p-3 border border-rose-100/90 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Rata-rata Alpa</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span class="text-xl font-extrabold text-rose-700"><?php echo $avg_alpa_pct; ?>%</span>
                        <span class="text-[10px] font-semibold text-rose-600 bg-rose-100/70 px-1.5 py-0.5 rounded-full">Tanpa Ket.</span>
                    </div>
                </div>
                <div class="w-8 h-8 rounded-lg bg-rose-100/70 text-rose-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
            </div>

            <div class="bg-gradient-to-br from-white to-indigo-50/40 rounded-xl p-3 border border-indigo-100/90 shadow-2xs flex items-center justify-between">
                <div>
                    <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Puncak Kehadiran</span>
                    <div class="flex items-baseline gap-1.5 mt-0.5">
                        <span class="text-base font-extrabold text-[#2B3990] truncate max-w-[130px]" title="<?php echo htmlspecialchars($best_day_label); ?>"><?php echo $best_day_label; ?></span>
                    </div>
                    <span class="text-[11px] font-bold text-indigo-600"><?php echo $max_hadir_val; ?>% Hadir</span>
                </div>
                <div class="w-8 h-8 rounded-lg bg-indigo-100/70 text-[#2B3990] flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-award"></i>
                </div>
            </div>
        </div>

        <!-- Canvas Chart Container -->
        <div class="relative h-72 sm:h-80 lg:h-84 w-full">
            <canvas id="attendanceBarChart"></canvas>
        </div>

        <!-- Bottom Guidance / Deep Dive Action -->
        <div class="mt-4 pt-3 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-2">
            <div class="flex items-center gap-1.5">
                <i class="fa-solid fa-circle-info text-slate-400 text-xs"></i>
                <span>Tip: Klik legenda di atas untuk memfilter data. Arahkan kursor pada diagram untuk melihat detail per hari.</span>
            </div>
            <a href="<?php url('views/attendance/daily_recap.php'); ?>" class="font-semibold text-[#2B3990] hover:underline flex items-center gap-1 text-xs shrink-0">
                <span>Buka Rekap Harian Absensi</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Attendance Ratio Pie Chart -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 sm:p-6 transition-all duration-300 hover:shadow-md flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-indigo-50 text-[#2B3990] flex items-center justify-center text-sm shadow-xs shrink-0">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-800">Rasio Kehadiran</h3>
                            <p class="text-xs text-slate-400">Komposisi kehadiran hari ini</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <?php echo $present_rate; ?>% Hadir
                    </span>
                </div>
                
                <!-- Doughnut Canvas with Center Percentage Overlay -->
                <div class="relative h-56 sm:h-60 w-full flex items-center justify-center my-2">
                    <canvas id="attendancePieChart"></canvas>
                    
                    <!-- Center Overlay Stat -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center select-none">
                        <div class="flex items-baseline justify-center">
                            <span id="pieCenterValue" class="text-3xl sm:text-4xl font-extrabold text-slate-800 tracking-tight leading-none"><?php echo $sudah_percent; ?></span>
                            <span id="pieCenterUnit" class="text-lg font-bold text-emerald-600 ml-0.5">%</span>
                        </div>
                        <span id="pieCenterLabel" class="text-[11px] font-semibold text-slate-400 mt-1 uppercase tracking-wider">Sudah Hadir</span>
                        <span id="pieCenterSub" class="text-[10px] text-slate-400 font-medium"><?php echo $sudah_absen; ?> dari <?php echo $total_for_pie; ?> Pegawai</span>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Recent Permissions Card -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 sm:p-6">
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
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 sm:p-6">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-base font-bold text-slate-800">Aktivitas Terkini</h3>
                <a href="<?php url('views/attendance/index.php'); ?>" class="text-xs font-semibold text-cyan-600 hover:text-cyan-700">Lihat Semua</a>
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

                                if ($activity['event_type'] == 'Absen Masuk') {
                                    if (in_array(strtolower($activity['status_code']), ['hadir', 'present'])) {
                                        $statusColor = 'bg-green-100 text-green-700'; // On Time
                                    } elseif (in_array(strtolower($activity['status_code']), ['late', 'telat'])) {
                                        $statusColor = 'bg-red-100 text-red-700'; // Late
                                    }
                                } elseif ($activity['event_type'] == 'Absen Keluar') {
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
    // Doughnut Pie Chart (Modern Interactive Center Stats)
    const pieCanvas = document.getElementById('attendancePieChart');
    if (pieCanvas) {
        const pieCtx = pieCanvas.getContext('2d');
        const pieData = [<?php echo $sudah_absen; ?>, <?php echo $tidak_absen; ?>, <?php echo $belum_absen; ?>];
        const piePcts = [<?php echo $sudah_percent; ?>, <?php echo $tidak_percent; ?>, <?php echo $belum_percent; ?>];
        const pieLabels = ['Sudah Hadir', 'Izin / Sakit', 'Belum Absen'];
        const pieColorClasses = ['text-emerald-600', 'text-amber-600', 'text-slate-500'];

        new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Sudah Absen', 'Izin / Sakit', 'Belum Absen'],
                datasets: [{
                    data: pieData,
                    backgroundColor: ['#10b981', '#f59e0b', '#e2e8f0'],
                    hoverBackgroundColor: ['#059669', '#d97706', '#cbd5e1'],
                    borderColor: '#ffffff',
                    borderWidth: 3,
                    borderRadius: 4,
                    cutout: '74%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    animateRotate: true,
                    animateScale: true
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#ffffff',
                        titleFont: { size: 13, weight: '700', family: "'Outfit', sans-serif" },
                        bodyFont: { size: 12, weight: '500', family: "'Outfit', sans-serif" },
                        padding: 12,
                        cornerRadius: 10,
                        boxPadding: 6,
                        usePointStyle: true,
                        borderColor: 'rgba(255, 255, 255, 0.08)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const val = context.parsed;
                                const idx = context.dataIndex;
                                const pct = piePcts[idx] !== undefined ? piePcts[idx] : 0;
                                return ' ' + context.label + ': ' + pct + '% (' + val + ' Pegawai)';
                            }
                        }
                    }
                },
                onHover: (event, elements) => {
                    const centerVal = document.getElementById('pieCenterValue');
                    const centerUnit = document.getElementById('pieCenterUnit');
                    const centerLabel = document.getElementById('pieCenterLabel');
                    const centerSub = document.getElementById('pieCenterSub');
                    if (!centerVal) return;

                    if (elements && elements.length > 0) {
                        const idx = elements[0].index;
                        centerVal.textContent = piePcts[idx];
                        centerUnit.className = 'text-lg font-bold ml-0.5 ' + pieColorClasses[idx];
                        centerLabel.textContent = pieLabels[idx];
                        centerSub.textContent = pieData[idx] + ' Pegawai';
                    } else {
                        centerVal.textContent = '<?php echo $sudah_percent; ?>';
                        centerUnit.className = 'text-lg font-bold ml-0.5 text-emerald-600';
                        centerLabel.textContent = 'Sudah Hadir';
                        centerSub.textContent = '<?php echo $sudah_absen; ?> dari <?php echo $total_for_pie; ?> Pegawai';
                    }
                }
            }
        });
    }

    // Attendance Trend Bar Chart (Modern Enterprise Edition)
    const barChartEl = document.getElementById('attendanceBarChart');
    let attendanceChartInstance = null;

    if (barChartEl) {
        const barCtx = barChartEl.getContext('2d');

        // Dynamic vertical gradient generator
        function createBarGradient(ctx, topColor, bottomColor) {
            const grad = ctx.createLinearGradient(0, 0, 0, 320);
            grad.addColorStop(0, topColor);
            grad.addColorStop(1, bottomColor);
            return grad;
        }

        const hadirGrad = createBarGradient(barCtx, 'rgba(16, 185, 129, 0.95)', 'rgba(52, 211, 153, 0.35)');
        const hadirHoverGrad = createBarGradient(barCtx, 'rgba(5, 150, 105, 1)', 'rgba(16, 185, 129, 0.6)');

        const izinGrad = createBarGradient(barCtx, 'rgba(245, 158, 11, 0.95)', 'rgba(251, 191, 36, 0.35)');
        const izinHoverGrad = createBarGradient(barCtx, 'rgba(217, 119, 6, 1)', 'rgba(245, 158, 11, 0.6)');

        const alpaGrad = createBarGradient(barCtx, 'rgba(244, 63, 94, 0.95)', 'rgba(251, 113, 133, 0.35)');
        const alpaHoverGrad = createBarGradient(barCtx, 'rgba(225, 29, 72, 1)', 'rgba(244, 63, 94, 0.6)');

        const fullDates = <?php echo json_encode($bar_full_dates); ?>;
        const totalActive = <?php echo (int)$active_count; ?>;

        attendanceChartInstance = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($bar_labels); ?>,
                datasets: [
                    {
                        label: 'Hadir',
                        data: <?php echo json_encode($bar_hadir_pct); ?>,
                        counts: <?php echo json_encode($bar_hadir_count); ?>,
                        backgroundColor: hadirGrad,
                        hoverBackgroundColor: hadirHoverGrad,
                        borderColor: '#059669',
                        borderWidth: 1.5,
                        borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        maxBarThickness: 32,
                        categoryPercentage: 0.68,
                        barPercentage: 0.85
                    },
                    {
                        label: 'Izin',
                        data: <?php echo json_encode($bar_izin_pct); ?>,
                        counts: <?php echo json_encode($bar_izin_count); ?>,
                        backgroundColor: izinGrad,
                        hoverBackgroundColor: izinHoverGrad,
                        borderColor: '#d97706',
                        borderWidth: 1.5,
                        borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        maxBarThickness: 32,
                        categoryPercentage: 0.68,
                        barPercentage: 0.85
                    },
                    {
                        label: 'Tidak Hadir',
                        data: <?php echo json_encode($bar_alpa_pct); ?>,
                        counts: <?php echo json_encode($bar_alpa_count); ?>,
                        backgroundColor: alpaGrad,
                        hoverBackgroundColor: alpaHoverGrad,
                        borderColor: '#e11d48',
                        borderWidth: 1.5,
                        borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                        borderSkipped: false,
                        maxBarThickness: 32,
                        categoryPercentage: 0.68,
                        barPercentage: 0.85
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    duration: 800,
                    easing: 'easeOutQuart'
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        padding: 13,
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#ffffff',
                        titleFont: { size: 13, weight: '700', family: "'Outfit', sans-serif" },
                        bodyFont: { size: 12, weight: '500', family: "'Outfit', sans-serif" },
                        footerFont: { size: 11, weight: '400', family: "'Outfit', sans-serif" },
                        footerColor: '#94a3b8',
                        cornerRadius: 10,
                        boxPadding: 6,
                        usePointStyle: true,
                        borderColor: 'rgba(255, 255, 255, 0.08)',
                        borderWidth: 1,
                        callbacks: {
                            title: function(items) {
                                if (!items.length) return '';
                                const idx = items[0].dataIndex;
                                return (fullDates && fullDates[idx]) ? '📅 ' + fullDates[idx] : items[0].label;
                            },
                            label: function(context) {
                                const pct = context.parsed.y !== null ? context.parsed.y : 0;
                                const count = context.dataset.counts ? context.dataset.counts[context.dataIndex] : 0;
                                return ' ' + context.dataset.label + ': ' + pct + '% (' + count + ' Pegawai)';
                            },
                            footer: function() {
                                return '\nTotal Pegawai Aktif: ' + totalActive + ' orang';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20,
                            callback: function(value) {
                                return value + '%';
                            },
                            font: { size: 11, weight: '500', family: "'Outfit', sans-serif" },
                            color: '#64748b',
                            padding: 6
                        },
                        grid: {
                            color: 'rgba(226, 232, 240, 0.8)',
                            drawBorder: false,
                            borderDash: [4, 4]
                        },
                        border: {
                            display: false
                        }
                    },
                    x: {
                        stacked: false,
                        ticks: {
                            font: { size: 11, weight: '600', family: "'Outfit', sans-serif" },
                            color: '#475569',
                            padding: 6
                        },
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        border: {
                            display: false
                        }
                    }
                }
            }
        });

        // Global functions for interactive chart controls
        window.toggleDataset = function(index) {
            if (!attendanceChartInstance) return;
            const isVisible = attendanceChartInstance.isDatasetVisible(index);
            attendanceChartInstance.setDatasetVisibility(index, !isVisible);
            attendanceChartInstance.update();

            const btn = document.getElementById('legendBtn' + index);
            if (btn) {
                if (isVisible) {
                    btn.classList.add('opacity-40', 'line-through', 'grayscale');
                } else {
                    btn.classList.remove('opacity-40', 'line-through', 'grayscale');
                }
            }
        };

        window.setChartBarMode = function(mode) {
            if (!attendanceChartInstance) return;
            const isStacked = (mode === 'stacked');

            attendanceChartInstance.options.scales.x.stacked = isStacked;
            attendanceChartInstance.options.scales.y.stacked = isStacked;

            attendanceChartInstance.data.datasets.forEach((ds, idx) => {
                if (isStacked) {
                    ds.maxBarThickness = 40;
                    // Only round the top of the stack (dataset 2: Tidak Hadir) or if other datasets are hidden
                    ds.borderRadius = (idx === 2) ? { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 } : 0;
                } else {
                    ds.maxBarThickness = 32;
                    ds.borderRadius = { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 };
                }
            });

            attendanceChartInstance.update();

            const btnGrouped = document.getElementById('btnChartGrouped');
            const btnStacked = document.getElementById('btnChartStacked');
            if (isStacked) {
                btnStacked.classList.add('bg-white', 'text-slate-700', 'shadow-xs');
                btnStacked.classList.remove('text-slate-500');
                btnGrouped.classList.remove('bg-white', 'text-slate-700', 'shadow-xs');
                btnGrouped.classList.add('text-slate-500');
            } else {
                btnGrouped.classList.add('bg-white', 'text-slate-700', 'shadow-xs');
                btnGrouped.classList.remove('text-slate-500');
                btnStacked.classList.remove('bg-white', 'text-slate-700', 'shadow-xs');
                btnStacked.classList.add('text-slate-500');
            }
        };
    }

    // Number Counter Animation for Summary Cards
    const counterElements = document.querySelectorAll('.counter-val');
    counterElements.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-target'), 10);
        if (isNaN(target) || target === 0) return;
        
        const duration = 800;
        const startTime = performance.now();
        
        function update(now) {
            const progress = Math.min((now - startTime) / duration, 1);
            const ease = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            const current = Math.round(target * ease);
            counter.textContent = current.toLocaleString('id-ID');
            
            if (progress < 1) {
                requestAnimationFrame(update);
            } else {
                counter.textContent = target.toLocaleString('id-ID');
            }
        }
        requestAnimationFrame(update);
    });
});
</script>


<?php include '../layouts/footer.php'; ?>