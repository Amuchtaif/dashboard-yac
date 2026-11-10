<?php
// portal_wali/index.php
// Beranda Utama Portal Wali Santri
require_once __DIR__ . '/init.php';
check_guardian_auth();

$active_child = get_active_child();
$db = new Database();
$conn = $db->getConnection();

$today = date('Y-m-d');
$tahfidz_latest = null;
$attendance_today = null;
$boarding_info = null;
$recent_permit = null;
$announcements = [];

if ($active_child) {
    $sid = (int)$active_child['student_id'];

    // 1. Data Setoran Tahfidz Terakhir (Unified: memorization_entries + tahfidz_memorization fallback)
    $stmt_tahfidz = $conn->prepare("
        SELECT t.*, e.full_name as teacher_name
        FROM (
            SELECT 
                me.id, me.student_id, me.date, me.entry_type,
                me.surah_start, me.start_ayah, me.surah_end, me.end_ayah,
                COALESCE(me.line_count, me.total_baris, 0) as total_baris,
                me.juz, me.status, me.notes, me.teacher_id, me.created_at
            FROM memorization_entries me
            WHERE me.student_id = :sid1

            UNION ALL

            SELECT 
                tm.id, tm.student_id, tm.date,
                CASE WHEN tm.status = 'Murajaah' THEN 'MUROJAAH' ELSE 'HAFALAN_BARU' END as entry_type,
                tm.surah_start, tm.ayat_start as start_ayah, tm.surah_end, tm.ayat_end as end_ayah,
                tm.total_baris, tm.juz, tm.status, tm.notes, tm.teacher_id, tm.created_at
            FROM tahfidz_memorization tm
            WHERE tm.student_id = :sid2
              AND NOT EXISTS (
                  SELECT 1 FROM memorization_entries me2 
                  WHERE me2.student_id = tm.student_id AND me2.date = tm.date
              )
        ) t
        LEFT JOIN employees e ON t.teacher_id = e.id
        ORDER BY t.date DESC, t.id DESC
        LIMIT 1
    ");
    $stmt_tahfidz->execute([':sid1' => $sid, ':sid2' => $sid]);
    $tahfidz_latest = $stmt_tahfidz->fetch(PDO::FETCH_ASSOC);

    if ($tahfidz_latest) {
        $tahfidz_latest['ayat_start'] = !empty($tahfidz_latest['start_ayah']) ? (int)$tahfidz_latest['start_ayah'] : (!empty($tahfidz_latest['ayat_start']) ? (int)$tahfidz_latest['ayat_start'] : null);
        $tahfidz_latest['ayat_end'] = !empty($tahfidz_latest['end_ayah']) ? (int)$tahfidz_latest['end_ayah'] : (!empty($tahfidz_latest['ayat_end']) ? (int)$tahfidz_latest['ayat_end'] : null);
        if (empty($tahfidz_latest['juz'])) {
            $tahfidz_latest['juz'] = get_quran_juz($tahfidz_latest['surah_start'], $tahfidz_latest['ayat_start'] ?: 1);
        }
    }

    // Hitung total setoran bulan ini
    $cur_month = date('Y-m');
    $stmt_count_tahfidz = $conn->prepare("
        SELECT COUNT(*) FROM (
            SELECT id, date FROM memorization_entries WHERE student_id = :sid1
            UNION ALL
            SELECT tm.id, tm.date FROM tahfidz_memorization tm
            WHERE tm.student_id = :sid2 AND NOT EXISTS (
                SELECT 1 FROM memorization_entries me2 WHERE me2.student_id = tm.student_id AND me2.date = tm.date
            )
        ) t
        WHERE DATE_FORMAT(t.date, '%Y-%m') = :month
    ");
    $stmt_count_tahfidz->execute([':sid1' => $sid, ':sid2' => $sid, ':month' => $cur_month]);
    $total_setoran_bulan_ini = (int)$stmt_count_tahfidz->fetchColumn();

    // Ambil capaian hafalan semester dari ProgressService
    $student_tahfidz_prog = null;
    try {
        require_once __DIR__ . '/../app/Services/Tahfidz/ProgressService.php';
        $ps = new ProgressService();
        $student_tahfidz_prog = $ps->getStudentProgress($sid);
    } catch (\Throwable $e) {
        // Silently fallback
    }

    // 2. Presensi Hari Ini & Terakhir
    $today = date('Y-m-d');
    
    // a. Cek presensi harian hari ini
    $stmt_att = $conn->prepare("
        SELECT status, notes FROM daily_student_attendances 
        WHERE student_id = :sid AND date = :today LIMIT 1
    ");
    $stmt_att->execute([':sid' => $sid, ':today' => $today]);
    $attendance_today = $stmt_att->fetch(PDO::FETCH_ASSOC);

    // b. Cek presensi mapel hari ini
    $stmt_subject_att = $conn->prepare("
        SELECT sa.status, sa.note, cj.date, s.name as subject_name
        FROM student_attendances sa
        JOIN class_journals cj ON sa.class_journal_id = cj.id
        LEFT JOIN class_schedules cs ON cj.class_schedule_id = cs.id
        LEFT JOIN subjects s ON cs.subject_id = s.id
        WHERE sa.student_id = :sid AND cj.date = :today
        ORDER BY sa.id DESC LIMIT 1
    ");
    $stmt_subject_att->execute([':sid' => $sid, ':today' => $today]);
    $subject_att_today = $stmt_subject_att->fetch(PDO::FETCH_ASSOC);

    // c. Presensi hari terakhir yang tercatat di kelas
    $stmt_last_day = $conn->prepare("
        SELECT 
            cj.date,
            COUNT(*) as total_mapel,
            COALESCE(SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END), 0) as present_cnt,
            COALESCE(SUM(CASE WHEN sa.status = 'sick' THEN 1 ELSE 0 END), 0) as sick_cnt,
            COALESCE(SUM(CASE WHEN sa.status = 'permit' THEN 1 ELSE 0 END), 0) as permit_cnt,
            COALESCE(SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END), 0) as absent_cnt
        FROM student_attendances sa
        JOIN class_journals cj ON sa.class_journal_id = cj.id
        WHERE sa.student_id = :sid
        GROUP BY cj.date
        ORDER BY cj.date DESC
        LIMIT 1
    ");
    $stmt_last_day->execute([':sid' => $sid]);
    $last_recorded_day = $stmt_last_day->fetch(PDO::FETCH_ASSOC);

    // 3. Info Asrama & Musrif
    $stmt_room = $conn->prepare("
        SELECT br.room_name, e.full_name as musrif_name, e.phone_number as musrif_phone
        FROM boarding_room_members brm
        JOIN boarding_rooms br ON brm.room_id = br.id
        LEFT JOIN employees e ON br.supervisor_id = e.id
        WHERE brm.student_id = :sid
        LIMIT 1
    ");
    $stmt_room->execute([':sid' => $sid]);
    $boarding_info = $stmt_room->fetch(PDO::FETCH_ASSOC);

    // 4. Perizinan Pulang Terakhir
    $stmt_permit = $conn->prepare("
        SELECT category, reason, start_date, end_date, status, created_at
        FROM boarding_permits
        WHERE student_id = :sid
        ORDER BY id DESC LIMIT 1
    ");
    $stmt_permit->execute([':sid' => $sid]);
    $recent_permit = $stmt_permit->fetch(PDO::FETCH_ASSOC);

    // 5. Berita / Pengumuman Terkini
    try {
        $stmt_news = $conn->query("SELECT id, title, content, created_at FROM news ORDER BY id DESC LIMIT 2");
        $announcements = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $announcements = [];
    }
}

$page_title = "Beranda";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-4 pt-4 space-y-4">

    <!-- Notifikasi Selamat Datang Baru -->
    <?php if (isset($_GET['first_login'])): ?>
        <div class="p-3.5 rounded-2xl bg-teal-50 border border-teal-200 text-teal-800 text-xs flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-teal-600 text-base"></i>
                <span>Ahlan wa Sahlan! Akun wali santri Anda telah aktif.</span>
            </div>
            <a href="index.php" class="text-teal-900 font-bold hover:underline">Tutup</a>
        </div>
    <?php endif; ?>

    <?php if (!$active_child): ?>
        <!-- State Belum Ada Santri Terhubung -->
        <div class="bg-white rounded-3xl p-6 text-center border border-slate-200 shadow-sm mt-6">
            <div class="w-16 h-16 rounded-full bg-teal-50 text-teal-700 flex items-center justify-center mx-auto mb-3 text-2xl">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2 class="text-base font-bold text-slate-800">Belum Ada Santri Terhubung</h2>
            <p class="text-xs text-slate-500 mt-1 mb-4 leading-relaxed">
                Akun Anda belum memiliki santri yang dihubungkan. Masukkan NISN dan tanggal lahir anak Anda untuk memulai pemantauan.
            </p>
            <a href="tambah_anak.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-700 hover:bg-teal-800 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                <i class="fa-solid fa-link"></i>
                <span>Hubungkan Santri Sekarang</span>
            </a>
        </div>
    <?php else: 
        $avatarSrc = !empty($active_child['foto']) && file_exists(__DIR__ . '/../uploads/students/' . $active_child['foto']) 
            ? '../uploads/students/' . $active_child['foto'] 
            : null;
    ?>
        <!-- 1. Hero Card Santri -->
        <div class="bg-gradient-to-br from-teal-800 via-teal-900 to-slate-900 rounded-3xl p-5 text-white shadow-lg relative overflow-hidden">
            <!-- Decorative Islamic Ornament Watermark -->
            <div class="absolute -right-6 -bottom-6 text-8xl text-white/5 pointer-events-none select-none">
                <i class="fa-solid fa-mosque"></i>
            </div>

            <div class="relative z-10 flex items-center gap-4">
                <!-- Avatar -->
                <div class="w-16 h-16 rounded-2xl border-2 border-teal-400/40 bg-white/10 overflow-hidden flex items-center justify-center shrink-0 shadow-md">
                    <?php if ($avatarSrc): ?>
                        <img src="<?php echo htmlspecialchars($avatarSrc); ?>" alt="Foto" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa-solid fa-user-graduate text-2xl text-teal-200"></i>
                    <?php endif; ?>
                </div>

                <!-- Bio -->
                <div class="min-w-0 flex-1">
                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-200 text-[10px] font-semibold mb-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal-300"></span>
                        <span><?php echo htmlspecialchars($active_child['unit_name'] ?? $active_child['tingkat'] ?? 'Assunnah'); ?></span>
                    </div>
                    <h2 class="text-base font-bold text-white truncate leading-snug"><?php echo htmlspecialchars($active_child['nama_siswa']); ?></h2>
                    <p class="text-xs text-teal-100/80 mt-0.5">
                        NISN: <span class="font-mono font-medium text-white"><?php echo htmlspecialchars($active_child['nomor_induk'] ?? '-'); ?></span> &bull; 
                        Kelas <?php echo htmlspecialchars($active_child['grade_name'] ?? $active_child['kelas'] ?? '-'); ?>
                    </p>
                </div>
            </div>

            <!-- Quick Sub-Bar (Status Kehadiran Hari ini / Terakhir) -->
            <div class="mt-4 pt-3.5 border-t border-white/10 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-teal-200/80">Kehadiran:</span>
                    <?php 
                    if ($attendance_today) {
                        $st = $attendance_today['status'];
                        $map = ['H' => ['Hadir Hari Ini', 'bg-emerald-500/20 text-emerald-300'], 'S' => ['Sakit', 'bg-amber-500/20 text-amber-300'], 'I' => ['Izin', 'bg-blue-500/20 text-blue-300'], 'A' => ['Alpa', 'bg-rose-500/20 text-rose-300'], 'T' => ['Terlambat', 'bg-amber-500/20 text-amber-300']];
                        $badge = $map[$st] ?? ['Tercatat', 'bg-teal-500/20 text-teal-300'];
                        echo '<span class="px-2 py-0.5 rounded-md font-bold text-[10px] ' . $badge[1] . '">' . $badge[0] . '</span>';
                    } elseif ($subject_att_today) {
                        $st = $subject_att_today['status'];
                        $map = ['present' => ['Hadir di Kelas', 'bg-emerald-500/20 text-emerald-300'], 'sick' => ['Sakit', 'bg-amber-500/20 text-amber-300'], 'permit' => ['Izin', 'bg-blue-500/20 text-blue-300'], 'absent' => ['Alpa', 'bg-rose-500/20 text-rose-300'], 'late' => ['Terlambat', 'bg-amber-500/20 text-amber-300']];
                        $badge = $map[$st] ?? ['Tercatat', 'bg-teal-500/20 text-teal-300'];
                        echo '<span class="px-2 py-0.5 rounded-md font-bold text-[10px] ' . $badge[1] . '">' . $badge[0] . '</span>';
                    } elseif ($last_recorded_day) {
                        $tot = (int)$last_recorded_day['total_mapel'];
                        $pCnt = (int)$last_recorded_day['present_cnt'];
                        if ($pCnt === $tot && $tot > 0) {
                            $lastStatus = 'Hadir Penuh';
                            $lastBadgeColor = 'bg-emerald-500/20 text-emerald-300';
                        } elseif ($pCnt > 0) {
                            $lastStatus = 'Hadir Sebagian';
                            $lastBadgeColor = 'bg-amber-500/20 text-amber-300';
                        } elseif ($last_recorded_day['sick_cnt'] > 0) {
                            $lastStatus = 'Sakit';
                            $lastBadgeColor = 'bg-amber-500/20 text-amber-300';
                        } elseif ($last_recorded_day['permit_cnt'] > 0) {
                            $lastStatus = 'Izin';
                            $lastBadgeColor = 'bg-blue-500/20 text-blue-300';
                        } else {
                            $lastStatus = 'Alpa';
                            $lastBadgeColor = 'bg-rose-500/20 text-rose-300';
                        }
                        $lastDateFmt = date('d M', strtotime($last_recorded_day['date']));
                        echo '<span class="px-2 py-0.5 rounded-md font-bold text-[10px] ' . $lastBadgeColor . '">Terakhir: ' . $lastStatus . ' (' . $lastDateFmt . ')</span>';
                    } else {
                        echo '<span class="text-[10px] text-teal-200/60 font-medium">Belum ada data presensi</span>';
                    }
                    ?>
                </div>

                <a href="presensi.php" class="text-[11px] text-teal-300 hover:text-white font-semibold flex items-center gap-1">
                    <span>Rincian</span>
                    <i class="fa-solid fa-chevron-right text-[9px]"></i>
                </a>
            </div>
        </div>

        <!-- 2. Tahfidz Progress Card -->
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs relative overflow-hidden group">
            <!-- Icon Background Watermark with Soft Animation -->
            <div class="absolute -right-3 -bottom-3 text-7xl text-teal-700/[0.06] group-hover:text-teal-700/[0.14] transform group-hover:scale-110 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                <i class="fa-solid fa-book-quran"></i>
            </div>

            <div class="flex items-center justify-between mb-3 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-book-open"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Perkembangan Tahfidz</h3>
                        <p class="text-[10px] text-slate-400">Setoran hafalan Al-Qur'an santri</p>
                    </div>
                </div>

                <a href="tahfidz.php" class="text-xs text-teal-700 hover:text-teal-800 font-bold flex items-center gap-1">
                    <span>Lihat Semua</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>

            <?php if ($tahfidz_latest): 
                $isMurajaah = ($tahfidz_latest['entry_type'] === 'MUROJAAH' || strcasecmp($tahfidz_latest['status'] ?? '', 'Murajaah') === 0);
                $isLancar = in_array($tahfidz_latest['status'], ['Lancar', 'Ziyadah']);
                $badgeColor = $isLancar ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200';
            ?>
                <div class="p-4 rounded-2xl bg-teal-50/50 border border-teal-100/80 relative z-10">
                    <div class="flex items-center justify-between text-[11px] text-slate-500 mb-1.5">
                        <span class="font-medium">
                            <i class="fa-regular fa-calendar mr-1 text-teal-600"></i>
                            <?php echo date('d M Y', strtotime($tahfidz_latest['date'])); ?>
                        </span>
                        
                        <div class="flex items-center gap-1.5">
                            <?php if ($isMurajaah): ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    Muraja'ah
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                    Ziyadah
                                </span>
                            <?php endif; ?>

                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border <?php echo $badgeColor; ?>">
                                <?php echo htmlspecialchars($tahfidz_latest['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between mt-2">
                        <div>
                            <div class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Hafalan Terakhir:</div>
                            <div class="text-base font-extrabold text-slate-900 mt-0.5">
                                <?php echo htmlspecialchars($tahfidz_latest['surah_start']); ?>
                                <?php if (!empty($tahfidz_latest['ayat_start'])): ?>
                                    <span class="text-xs font-semibold text-slate-600">: Ayat <?php echo $tahfidz_latest['ayat_start']; ?><?php if (!empty($tahfidz_latest['ayat_end']) && $tahfidz_latest['ayat_end'] != $tahfidz_latest['ayat_start']): ?> - <?php echo $tahfidz_latest['ayat_end']; ?><?php endif; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($tahfidz_latest['juz'])): ?>
                            <div class="text-right">
                                <span class="text-[10px] text-slate-400 uppercase font-bold">Juz</span>
                                <div class="text-xl font-black text-teal-700 font-arabic"><?php echo $tahfidz_latest['juz']; ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($student_tahfidz_prog && ($student_tahfidz_prog['total_juz'] > 0 || $student_tahfidz_prog['target_juz'] > 0)): ?>
                        <div class="mt-2.5 pt-2 border-t border-teal-200/50">
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="text-slate-500 font-medium">Progres Semester:</span>
                                <span class="text-teal-800 font-bold"><?php echo round($student_tahfidz_prog['progress_percentage']); ?>% (Target: <?php echo $student_tahfidz_prog['target_juz']; ?> Juz)</span>
                            </div>
                            <div class="w-full bg-slate-200/80 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-teal-600 h-1.5 rounded-full" style="width: <?php echo min(100, max(0, $student_tahfidz_prog['progress_percentage'])); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($tahfidz_latest['teacher_name'])): ?>
                        <div class="mt-2.5 pt-2 border-t border-teal-200/40 text-[11px] text-slate-500 flex items-center justify-between">
                            <span>Pengampu: <strong><?php echo htmlspecialchars($tahfidz_latest['teacher_name']); ?></strong></span>
                            <span class="text-teal-700 font-medium"><?php echo $total_setoran_bulan_ini; ?>x setoran bln ini</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 text-center relative z-10">
                    <p class="text-xs text-slate-500">Belum ada riwayat setoran tahfidz yang tercatat.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. Asrama & Musrif (Jika ada data asrama) -->
        <?php if ($boarding_info): ?>
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs relative overflow-hidden group">
                <div class="absolute -right-3 -bottom-3 text-7xl text-indigo-700/[0.05] group-hover:text-indigo-700/[0.12] transform group-hover:scale-110 group-hover:-rotate-6 transition-all duration-300 pointer-events-none select-none">
                    <i class="fa-solid fa-hotel"></i>
                </div>

                <div class="flex items-center justify-between mb-3 relative z-10">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-house-user"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Informasi Asrama</h3>
                            <p class="text-[10px] text-slate-400">Pembinaan kepesantrenan santri</p>
                        </div>
                    </div>

                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                        <?php echo htmlspecialchars($boarding_info['room_name']); ?>
                    </span>
                </div>

                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/70 flex items-center justify-between relative z-10">
                    <div>
                        <span class="text-[10px] text-slate-400 uppercase font-bold block">Musrif Pembina:</span>
                        <h4 class="text-xs font-bold text-slate-800 mt-0.5">
                            <?php echo htmlspecialchars($boarding_info['musrif_name'] ?? 'Ustadz Pembina Asrama'); ?>
                        </h4>
                    </div>

                    <?php if (!empty($boarding_info['musrif_phone'])): 
                        $waNum = preg_replace('/[^0-9]/', '', $boarding_info['musrif_phone']);
                        if (strpos($waNum, '0') === 0) $waNum = '62' . substr($waNum, 1);
                    ?>
                        <a href="https://wa.me/<?php echo $waNum; ?>?text=<?php echo urlencode('Assalamu\'alaikum Ustadz, saya wali dari ' . $active_child['nama_siswa']); ?>" 
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-colors">
                            <i class="fa-brands fa-whatsapp text-sm"></i>
                            <span>Chat WA</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Perizinan Pulang -->
                <?php if ($recent_permit): ?>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between text-xs relative z-10">
                        <span class="text-slate-500 text-[11px]">Izin Pulang Terakhir:</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $recent_permit['status'] === 'Disetujui' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600'; ?>">
                            <?php echo htmlspecialchars($recent_permit['status']); ?> (<?php echo htmlspecialchars($recent_permit['category']); ?>)
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- 4. Pengumuman / Agenda Yayasan -->
        <?php if (!empty($announcements)): ?>
            <div class="space-y-2.5">
                <div class="flex items-center justify-between px-1">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Informasi Yayasan</h3>
                    <span class="text-[10px] text-slate-400">Resmi</span>
                </div>

                <?php foreach ($announcements as $item): ?>
                    <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                        <span class="text-[10px] text-teal-700 font-semibold block mb-1">
                            <i class="fa-regular fa-clock mr-1"></i><?php echo date('d M Y', strtotime($item['created_at'])); ?>
                        </span>
                        <h4 class="text-xs font-bold text-slate-800 leading-snug"><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                            <?php echo strip_tags($item['content']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

