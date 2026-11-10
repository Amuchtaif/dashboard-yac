<?php
// portal_wali/presensi.php
// Halaman Monitoring Presensi Pelajaran, Harian & Asrama Santri
require_once __DIR__ . '/init.php';
check_guardian_auth();

$active_child = get_active_child();
$db = new Database();
$conn = $db->getConnection();

$tab = $_GET['tab'] ?? 'attendance';
$permits = [];
$daily_map = [];
$available_months = [];
$att_summary = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0];

$month_names_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$day_names_id = [
    'Sunday' => 'Ahad', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$cur_calendar_month = date('Y-m');
$selected_month = $_GET['month'] ?? '';

if ($active_child) {
    $sid = (int)$active_child['student_id'];

    if ($tab === 'attendance') {
        // 1. Ambil daftar bulan yang memiliki riwayat presensi santri
        $stmt_months = $conn->prepare("
            SELECT DISTINCT ym FROM (
                SELECT DATE_FORMAT(cj.date, '%Y-%m') as ym
                FROM student_attendances sa
                JOIN class_journals cj ON sa.class_journal_id = cj.id
                WHERE sa.student_id = :sid1
                UNION
                SELECT DATE_FORMAT(date, '%Y-%m') as ym
                FROM daily_student_attendances
                WHERE student_id = :sid2
            ) t
            ORDER BY ym DESC
        ");
        $stmt_months->execute([':sid1' => $sid, ':sid2' => $sid]);
        $months_with_data = $stmt_months->fetchAll(PDO::FETCH_COLUMN);

        // Tentukan bulan terpilih:
        // Prioritas 1: Parameter URL 'month' jika ada
        // Prioritas 2: Bulan berjalan jika sudah memiliki data
        // Prioritas 3: Bulan terbaru yang memiliki catatan presensi
        // Prioritas 4: Bulan kalender saat ini
        if (!empty($_GET['month'])) {
            $selected_month = $_GET['month'];
        } elseif (in_array($cur_calendar_month, $months_with_data)) {
            $selected_month = $cur_calendar_month;
        } elseif (!empty($months_with_data)) {
            $selected_month = $months_with_data[0];
        } else {
            $selected_month = $cur_calendar_month;
        }

        // Susun daftar bulan untuk dropdown selector
        $available_months = $months_with_data;
        if (!in_array($cur_calendar_month, $available_months)) {
            array_unshift($available_months, $cur_calendar_month);
        }

        // 2. Query Presensi Per Mapel / Kelas (student_attendances + class_journals)
        $sql_class = "
            SELECT 
                sa.id as attendance_id,
                sa.status,
                sa.note as student_note,
                cj.id as journal_id,
                cj.date,
                cj.topic,
                cj.notes as journal_notes,
                sub.name as subject_name,
                e.full_name as teacher_name,
                lp.start_time,
                lp.end_time
            FROM student_attendances sa
            JOIN class_journals cj ON sa.class_journal_id = cj.id
            LEFT JOIN class_schedules cs ON cj.class_schedule_id = cs.id
            LEFT JOIN subjects sub ON cs.subject_id = sub.id
            LEFT JOIN employees e ON cs.employee_id = e.id
            LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
            WHERE sa.student_id = :sid AND DATE_FORMAT(cj.date, '%Y-%m') = :month
            ORDER BY cj.date DESC, lp.start_time ASC, sa.id ASC
        ";
        $stmt_class = $conn->prepare($sql_class);
        $stmt_class->execute([':sid' => $sid, ':month' => $selected_month]);
        $class_attendances = $stmt_class->fetchAll(PDO::FETCH_ASSOC);

        // 3. Query Presensi Harian Tambahan (daily_student_attendances bila ada)
        $stmt_daily = $conn->prepare("
            SELECT date, status, notes
            FROM daily_student_attendances
            WHERE student_id = :sid AND DATE_FORMAT(date, '%Y-%m') = :month
        ");
        $stmt_daily->execute([':sid' => $sid, ':month' => $selected_month]);
        $daily_attendances = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

        // 4. Agregasi Harian Presensi
        $att_summary = [
            'present' => 0, 'sick' => 0, 'permit' => 0, 'absent' => 0, 'late' => 0,
            'present_days' => 0, 'sick_days' => 0, 'permit_days' => 0, 'absent_days' => 0, 'late_days' => 0,
            'total_sessions' => 0, 'total_days' => 0
        ];

        foreach ($class_attendances as $row) {
            $d = $row['date'];
            if (!isset($daily_map[$d])) {
                $daily_map[$d] = [
                    'date' => $d,
                    'sessions' => [],
                    'present_count' => 0,
                    'sick_count' => 0,
                    'permit_count' => 0,
                    'absent_count' => 0,
                    'late_count' => 0,
                    'final_status' => 'H',
                    'notes' => ''
                ];
            }
            $daily_map[$d]['sessions'][] = $row;
            $st = strtolower($row['status'] ?? 'present');
            if ($st === 'present') { $daily_map[$d]['present_count']++; $att_summary['present']++; }
            elseif ($st === 'sick') { $daily_map[$d]['sick_count']++; $att_summary['sick']++; }
            elseif ($st === 'permit') { $daily_map[$d]['permit_count']++; $att_summary['permit']++; }
            elseif ($st === 'absent') { $daily_map[$d]['absent_count']++; $att_summary['absent']++; }
            elseif ($st === 'late') { $daily_map[$d]['late_count']++; $att_summary['late']++; }
            $att_summary['total_sessions']++;
        }

        // Merge daily_student_attendances (Wali kelas roll call jika ada)
        foreach ($daily_attendances as $dd) {
            $d = $dd['date'];
            $st_map = ['H' => 'present', 'S' => 'sick', 'I' => 'permit', 'A' => 'absent', 'T' => 'late'];
            $mapped_st = $st_map[$dd['status']] ?? 'present';

            if (!isset($daily_map[$d])) {
                $daily_map[$d] = [
                    'date' => $d,
                    'sessions' => [],
                    'present_count' => ($mapped_st === 'present' ? 1 : 0),
                    'sick_count' => ($mapped_st === 'sick' ? 1 : 0),
                    'permit_count' => ($mapped_st === 'permit' ? 1 : 0),
                    'absent_count' => ($mapped_st === 'absent' ? 1 : 0),
                    'late_count' => ($mapped_st === 'late' ? 1 : 0),
                    'final_status' => $dd['status'],
                    'notes' => $dd['notes'] ?? ''
                ];
                $att_summary[$mapped_st]++;
                $att_summary['total_sessions']++;
            } else {
                $daily_map[$d]['final_status'] = $dd['status'];
                if (!empty($dd['notes'])) {
                    $daily_map[$d]['notes'] = $dd['notes'];
                }
            }
        }

        // Tentukan status harian final & badge serta hitung hari kehadiran
        $att_summary['total_days'] = count($daily_map);
        foreach ($daily_map as $d => &$day_data) {
            $p = $day_data['present_count'];
            $s = $day_data['sick_count'];
            $i = $day_data['permit_count'];
            $a = $day_data['absent_count'];
            $l = $day_data['late_count'];
            $total_s = count($day_data['sessions']);

            if ($p > 0) $att_summary['present_days']++;
            if ($s > 0) $att_summary['sick_days']++;
            if ($i > 0) $att_summary['permit_days']++;
            if ($a > 0) $att_summary['absent_days']++;
            if ($l > 0) $att_summary['late_days']++;

            if ($total_s === 0) {
                $map_direct = [
                    'H' => ['Hadir Penuh', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                    'S' => ['Sakit', 'bg-amber-50 text-amber-700 border-amber-200'],
                    'I' => ['Izin', 'bg-blue-50 text-blue-700 border-blue-200'],
                    'A' => ['Alpa', 'bg-rose-50 text-rose-700 border-rose-200'],
                    'T' => ['Terlambat', 'bg-amber-50 text-amber-700 border-amber-200']
                ];
                $day_data['badge_label'] = $map_direct[$day_data['final_status']][0] ?? 'Tercatat';
                $day_data['badge_color'] = $map_direct[$day_data['final_status']][1] ?? 'bg-slate-100 text-slate-700 border-slate-200';
            } elseif ($p === $total_s) {
                // Semua sesi hadir
                $day_data['badge_label'] = 'Hadir Penuh';
                $day_data['badge_color'] = 'bg-emerald-50 text-emerald-700 border-emerald-200';
            } elseif ($s === $total_s) {
                // Semua sesi sakit
                $day_data['badge_label'] = 'Sakit';
                $day_data['badge_color'] = 'bg-amber-50 text-amber-700 border-amber-200';
            } elseif ($i === $total_s) {
                // Semua sesi izin
                $day_data['badge_label'] = 'Izin';
                $day_data['badge_color'] = 'bg-blue-50 text-blue-700 border-blue-200';
            } elseif ($a === $total_s) {
                // Semua sesi alpa
                $day_data['badge_label'] = 'Alpa';
                $day_data['badge_color'] = 'bg-rose-50 text-rose-700 border-rose-200';
            } else {
                // Hadir sebagian dengan catatan sesi sakit / izin / alpa
                $parts = [];
                if ($s > 0) $parts[] = "$s Sakit";
                if ($i > 0) $parts[] = "$i Izin";
                if ($a > 0) $parts[] = "$a Alpa";
                if ($l > 0) $parts[] = "$l Telat";

                $prefix = ($p > 0) ? 'Hadir Sebagian' : 'Tidak Hadir';
                $day_data['badge_label'] = !empty($parts) ? ($prefix . ' (' . implode(', ', $parts) . ')') : 'Tercatat';
                $day_data['badge_color'] = ($a > 0) 
                    ? 'bg-rose-50 text-rose-800 border-rose-200' 
                    : 'bg-amber-50 text-amber-800 border-amber-200';
            }
        }
        unset($day_data);

        // Aliases for compatibility
        $att_summary['H'] = $att_summary['present'];
        $att_summary['S'] = $att_summary['sick'];
        $att_summary['I'] = $att_summary['permit'];
        $att_summary['A'] = $att_summary['absent'];

        // Urutkan tanggal dari yang paling baru
        krsort($daily_map);
    }

    // 5. Riwayat Izin Kepulangan Asrama (hanya dimuat jika tab perizinan dibuka)
    if ($tab === 'permits') {
        $stmt_permits = $conn->prepare("
            SELECT bp.*, e.full_name as approved_by_name
            FROM boarding_permits bp
            LEFT JOIN employees e ON bp.approved_by = e.id
            WHERE bp.student_id = :sid
            ORDER BY bp.id DESC
            LIMIT 20
        ");
        $stmt_permits->execute([':sid' => $sid]);
        $permits = $stmt_permits->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = "Kehadiran & Asrama";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-4 pt-4 space-y-4 pb-20">
    <!-- Header Page Info -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900">Kehadiran & Asrama</h1>
            <p class="text-xs text-slate-500">Santri: <strong><?php echo htmlspecialchars($active_child['nama_siswa'] ?? ''); ?></strong></p>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-lg shadow-xs">
            <i class="fa-solid fa-clipboard-check"></i>
        </div>
    </div>

    <!-- Switch Tab -->
    <div class="flex items-center bg-slate-200/70 p-1 rounded-xl text-xs">
        <a href="presensi.php?tab=attendance<?php echo !empty($selected_month) ? '&month=' . urlencode($selected_month) : ''; ?>" 
           class="flex-1 py-1.5 text-center rounded-lg font-semibold transition-all <?php echo $tab === 'attendance' ? 'bg-white text-teal-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">
            Presensi Sekolah
        </a>
        <a href="presensi.php?tab=permits" 
           class="flex-1 py-1.5 text-center rounded-lg font-semibold transition-all <?php echo $tab === 'permits' ? 'bg-white text-teal-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">
            Izin Pulang Asrama
        </a>
    </div>

    <?php if ($tab === 'attendance'): ?>
        <!-- Month Selector Toolbar -->
        <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-xs">
                    <i class="fa-regular fa-calendar-days"></i>
                </span>
                <span class="text-xs font-bold text-slate-700">Periode:</span>
            </div>

            <div class="relative flex-1 max-w-[200px]">
                <select onchange="window.location.href='presensi.php?tab=attendance&month=' + encodeURIComponent(this.value)" 
                        class="w-full appearance-none pl-3 pr-8 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 cursor-pointer">
                    <?php foreach ($available_months as $ym): 
                        $parts = explode('-', $ym);
                        $mName = $month_names_id[$parts[1]] ?? $parts[1];
                        $yName = $parts[0];
                        $label = "$mName $yName";
                    ?>
                        <option value="<?php echo $ym; ?>" <?php echo $selected_month === $ym ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </div>
            </div>
        </div>

        <!-- Quick Summary Bulan Ini -->
        <?php $is_period_based = ($att_summary['total_sessions'] > $att_summary['total_days']); ?>
        <div class="grid grid-cols-4 gap-2">
            <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 text-center shadow-xs">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">Hadir</span>
                <span class="text-base sm:text-lg font-black text-emerald-600 mt-0.5 block"><?php echo $att_summary['present']; ?></span>
                <span class="text-[9px] text-slate-400 block"><?php echo $is_period_based ? 'Pelajaran' : 'Hari'; ?></span>
                <?php if ($is_period_based): ?>
                    <span class="text-[8px] text-slate-400/80 block mt-0.5">(<?php echo $att_summary['present_days']; ?> Hari)</span>
                <?php endif; ?>
            </div>
            <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 text-center shadow-xs">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">Sakit</span>
                <span class="text-base sm:text-lg font-black text-amber-600 mt-0.5 block"><?php echo $att_summary['sick']; ?></span>
                <span class="text-[9px] text-slate-400 block"><?php echo $is_period_based ? 'Pelajaran' : 'Hari'; ?></span>
                <?php if ($is_period_based): ?>
                    <span class="text-[8px] text-slate-400/80 block mt-0.5">(<?php echo $att_summary['sick_days']; ?> Hari)</span>
                <?php endif; ?>
            </div>
            <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 text-center shadow-xs">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">Izin</span>
                <span class="text-base sm:text-lg font-black text-blue-600 mt-0.5 block"><?php echo $att_summary['permit']; ?></span>
                <span class="text-[9px] text-slate-400 block"><?php echo $is_period_based ? 'Pelajaran' : 'Hari'; ?></span>
                <?php if ($is_period_based): ?>
                    <span class="text-[8px] text-slate-400/80 block mt-0.5">(<?php echo $att_summary['permit_days']; ?> Hari)</span>
                <?php endif; ?>
            </div>
            <div class="bg-white p-2.5 rounded-2xl border border-slate-200/80 text-center shadow-xs">
                <span class="text-[10px] text-slate-400 font-semibold block uppercase">Alpa</span>
                <span class="text-base sm:text-lg font-black text-rose-600 mt-0.5 block"><?php echo $att_summary['absent']; ?></span>
                <span class="text-[9px] text-slate-400 block"><?php echo $is_period_based ? 'Pelajaran' : 'Hari'; ?></span>
                <?php if ($is_period_based): ?>
                    <span class="text-[8px] text-slate-400/80 block mt-0.5">(<?php echo $att_summary['absent_days']; ?> Hari)</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Persentase Kehadiran Bar -->
        <?php 
            $total_days = $att_summary['total_days'];
            $total_sessions = $att_summary['total_sessions'];
            $pct = $total_sessions > 0 ? round(($att_summary['present'] / $total_sessions) * 100) : ($total_days > 0 ? round(($att_summary['present_days'] / $total_days) * 100) : 0);
        ?>
        <?php if ($total_days > 0): ?>
            <div class="p-3.5 rounded-2xl bg-teal-50/70 border border-teal-100 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-teal-600 text-white flex items-center justify-center text-xs font-extrabold shadow-xs">
                        <?php echo $pct; ?>%
                    </span>
                    <div>
                        <span class="text-xs font-bold text-slate-800 block">Tingkat Kehadiran Pelajaran</span>
                        <span class="text-[10px] text-slate-500">
                            <?php if ($is_period_based): ?>
                                <?php echo $att_summary['present']; ?> dari <?php echo $total_sessions; ?> pelajaran diikuti (<?php echo $att_summary['present_days']; ?> dari <?php echo $total_days; ?> hari hadir)
                            <?php else: ?>
                                <?php echo $att_summary['present']; ?> dari <?php echo $total_days; ?> hari belajar tercatat
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo $pct >= 85 ? 'bg-emerald-100 text-emerald-800' : ($pct >= 70 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'); ?>">
                    <?php echo $pct >= 85 ? 'Sangat Baik' : ($pct >= 70 ? 'Cukup' : 'Perlu Perhatian'); ?>
                </span>
            </div>
        <?php endif; ?>

        <!-- Log List Kehadiran Harian & Rincian Mata Pelajaran -->
        <div class="space-y-3">
            <div class="flex items-center justify-between px-1">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                    Riwayat Presensi (<?php 
                        $parts = explode('-', $selected_month);
                        echo ($month_names_id[$parts[1]] ?? $parts[1]) . ' ' . $parts[0];
                    ?>)
                </h3>
                <span class="text-[11px] font-semibold text-slate-400"><?php echo $total_days; ?> Hari Belajar</span>
            </div>

            <?php if (empty($daily_map)): ?>
                <div class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 shadow-xs">
                    <i class="fa-regular fa-calendar-xmark text-slate-300 text-3xl mb-2"></i>
                    <p class="text-xs text-slate-500 font-medium">Belum ada catatan presensi pada periode bulan ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($daily_map as $d => $day): 
                    $day_eng = date('l', strtotime($d));
                    $day_idn = $day_names_id[$day_eng] ?? $day_eng;
                    $date_formatted = $day_idn . ', ' . date('d', strtotime($d)) . ' ' . ($month_names_id[date('m', strtotime($d))] ?? date('M', strtotime($d))) . ' ' . date('Y', strtotime($d));
                    $session_count = count($day['sessions']);
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                        <!-- Header Tanggal Hari -->
                        <div class="p-3.5 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-regular fa-calendar-check text-teal-600 text-xs"></i>
                                    <?php echo $date_formatted; ?>
                                </span>
                                <?php if ($session_count > 0): ?>
                                    <span class="text-[10px] text-slate-400 mt-0.5 block">
                                        <?php echo $session_count; ?> Pelajaran (<?php echo $day['present_count']; ?> Hadir<?php 
                                            $unp = [];
                                            if ($day['sick_count'] > 0) $unp[] = $day['sick_count'] . ' Sakit';
                                            if ($day['permit_count'] > 0) $unp[] = $day['permit_count'] . ' Izin';
                                            if ($day['absent_count'] > 0) $unp[] = $day['absent_count'] . ' Alpa';
                                            if (!empty($unp)) echo ', ' . implode(', ', $unp);
                                        ?>)
                                    </span>
                                <?php elseif (!empty($day['notes'])): ?>
                                    <span class="text-[10px] text-slate-500 italic mt-0.5 block">
                                        Catatan: <?php echo htmlspecialchars($day['notes']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?php echo $day['badge_color']; ?>">
                                <?php echo htmlspecialchars($day['badge_label']); ?>
                            </span>
                        </div>

                        <!-- Daftar Sesi Mata Pelajaran di Kelas -->
                        <?php if ($session_count > 0): ?>
                            <div class="divide-y divide-slate-100">
                                <?php foreach ($day['sessions'] as $session): 
                                    $map_subj = [
                                        'present' => ['Hadir', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                                        'sick'    => ['Sakit', 'bg-amber-50 text-amber-700 border-amber-200'],
                                        'permit'  => ['Izin', 'bg-blue-50 text-blue-700 border-blue-200'],
                                        'absent'  => ['Alpa', 'bg-rose-50 text-rose-700 border-rose-200'],
                                        'late'    => ['Terlambat', 'bg-amber-50 text-amber-700 border-amber-200']
                                    ];
                                    $sBadge = $map_subj[$session['status']] ?? ['Tercatat', 'bg-slate-100 text-slate-700 border-slate-200'];
                                    
                                    $time_text = '';
                                    if (!empty($session['start_time'])) {
                                        $time_text = substr($session['start_time'], 0, 5);
                                        if (!empty($session['end_time'])) {
                                            $time_text .= ' - ' . substr($session['end_time'], 0, 5);
                                        }
                                    }
                                ?>
                                    <div class="p-3.5 hover:bg-slate-50/50 transition-colors flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-slate-900 truncate">
                                                    <?php echo htmlspecialchars($session['subject_name'] ?? 'Mata Pelajaran'); ?>
                                                </span>
                                                <?php if (!empty($time_text)): ?>
                                                    <span class="px-1.5 py-0.2 rounded text-[9px] font-mono text-slate-500 bg-slate-100">
                                                        <?php echo $time_text; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($session['teacher_name'])): ?>
                                                <p class="text-[11px] text-slate-500 mt-0.5">
                                                    Pengajar: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($session['teacher_name']); ?></span>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($session['topic'])): ?>
                                                <p class="text-[10px] text-slate-400 mt-1 italic flex items-center gap-1">
                                                    <i class="fa-solid fa-book-open text-[9px] text-teal-600"></i>
                                                    <span class="truncate">Materi: "<?php echo htmlspecialchars($session['topic']); ?>"</span>
                                                </p>
                                            <?php endif; ?>

                                            <?php if (!empty($session['student_note'])): ?>
                                                <p class="text-[10px] text-amber-600 mt-0.5">
                                                    Ket: <?php echo htmlspecialchars($session['student_note']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>

                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border shrink-0 <?php echo $sBadge[1]; ?>">
                                            <?php echo $sBadge[0]; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- Tab Izin Pulang Asrama -->
        <div class="space-y-3">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider px-1">Riwayat Perizinan Pulang</h3>

            <?php if (empty($permits)): ?>
                <div class="bg-white rounded-2xl p-6 text-center border border-slate-200/80 shadow-xs">
                    <i class="fa-solid fa-hotel text-slate-300 text-3xl mb-2"></i>
                    <p class="text-xs text-slate-500">Belum ada riwayat izin kepulangan asrama.</p>
                </div>
            <?php else: ?>
                <?php foreach ($permits as $p): 
                    $badgeStatus = $p['status'] === 'Disetujui' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                                  ($p['status'] === 'Pending' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-100 text-slate-700 border-slate-200');
                ?>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                <?php echo htmlspecialchars($p['category']); ?>
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?php echo $badgeStatus; ?>">
                                <?php echo htmlspecialchars($p['status']); ?>
                            </span>
                        </div>

                        <div>
                            <div class="text-xs font-bold text-slate-800">
                                Alasan: <?php echo htmlspecialchars($p['reason']); ?>
                            </div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                <span>Periode: </span>
                                <strong><?php echo date('d M Y', strtotime($p['start_date'])); ?></strong> s/d <strong><?php echo date('d M Y', strtotime($p['end_date'])); ?></strong>
                            </div>
                        </div>

                        <?php if (!empty($p['approved_by_name'])): ?>
                            <div class="pt-2 border-t border-slate-100 text-[10px] text-slate-400 flex items-center justify-between">
                                <span>Disetujui oleh:</span>
                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($p['approved_by_name']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
