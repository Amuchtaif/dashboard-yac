<?php
// portal_wali/tahfidz.php
// Halaman Rincian & Progres Tahfidz Al-Qur'an Santri
require_once __DIR__ . '/init.php';
check_guardian_auth();

$active_child = get_active_child();
$db = new Database();
$conn = $db->getConnection();

$entries = [];
$stats = [
    'total' => 0,
    'lancar' => 0,
    'ziyadah' => 0,
    'murajaah' => 0,
    'juz_latest' => '-'
];
$student_progress = null;

if ($active_child) {
    $sid = (int)$active_child['student_id'];
    $filter_status = $_GET['status'] ?? 'all';

    // 1. Ambil Progres Akademik & Target dari ProgressService
    try {
        require_once __DIR__ . '/../app/Services/Tahfidz/ProgressService.php';
        $ps = new ProgressService();
        $student_progress = $ps->getStudentProgress($sid);
    } catch (\Throwable $e) {
        error_log("ProgressService error in tahfidz.php: " . $e->getMessage());
    }

    // 2. Filter Status / Jenis Setoran
    $where_filter = "";
    $params = [':sid1' => $sid, ':sid2' => $sid];

    if ($filter_status === 'ziyadah') {
        $where_filter = "AND (t.entry_type = 'HAFALAN_BARU' OR t.status = 'Ziyadah')";
    } elseif ($filter_status === 'murajaah') {
        $where_filter = "AND (t.entry_type = 'MUROJAAH' OR t.status = 'Murajaah')";
    }

    // 3. Query Riwayat Setoran (Unified: memorization_entries + tahfidz_memorization fallback)
    $sql = "
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
        WHERE 1=1 $where_filter
        ORDER BY t.date DESC, t.id DESC
        LIMIT 100
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Hitung Statistik Riwayat
    $stmt_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status IN ('Lancar', 'Ziyadah') THEN 1 END) as lancar_count,
            COUNT(CASE WHEN entry_type = 'HAFALAN_BARU' OR status = 'Ziyadah' THEN 1 END) as ziyadah_count,
            COUNT(CASE WHEN entry_type = 'MUROJAAH' OR status = 'Murajaah' THEN 1 END) as murajaah_count,
            MAX(juz) as max_juz
        FROM (
            SELECT me.id, me.student_id, me.entry_type, me.juz, me.status
            FROM memorization_entries me
            WHERE me.student_id = :sid1

            UNION ALL

            SELECT tm.id, tm.student_id,
                CASE WHEN tm.status = 'Murajaah' THEN 'MUROJAAH' ELSE 'HAFALAN_BARU' END as entry_type,
                tm.juz, tm.status
            FROM tahfidz_memorization tm
            WHERE tm.student_id = :sid2
              AND NOT EXISTS (
                  SELECT 1 FROM memorization_entries me2 
                  WHERE me2.student_id = tm.student_id AND me2.date = tm.date
              )
        ) t
    ");
    $stmt_stats->execute([':sid1' => $sid, ':sid2' => $sid]);
    $res_stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    if ($res_stats) {
        $stats['total'] = (int)($res_stats['total'] ?? 0);
        $stats['lancar'] = (int)($res_stats['lancar_count'] ?? 0);
        $stats['ziyadah'] = (int)($res_stats['ziyadah_count'] ?? 0);
        $stats['murajaah'] = (int)($res_stats['murajaah_count'] ?? 0);
    }

    // 5. Tentukan Juz Aktif Santri
    if (!empty($entries)) {
        $first_entry = $entries[0];
        $detected_juz = !empty($first_entry['juz']) ? $first_entry['juz'] : get_quran_juz($first_entry['surah_start'], $first_entry['start_ayah']);
        if ($detected_juz) {
            $stats['juz_latest'] = 'Juz ' . $detected_juz;
        }
    }
    if ($stats['juz_latest'] === '-' && !empty($student_progress['baseline_juz'])) {
        $stats['juz_latest'] = $student_progress['baseline_juz'] . ' Juz';
    }
}

$page_title = "Tahfidz Al-Qur'an";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-4 pt-4 space-y-4 pb-20">
    <!-- Header Page Info -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-lg font-bold text-slate-900">Buku Setoran Tahfidz</h1>
            <p class="text-xs text-slate-500">Progres hafalan santri: <strong><?php echo htmlspecialchars($active_child['nama_siswa'] ?? ''); ?></strong></p>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-lg shadow-xs">
            <i class="fa-solid fa-book-quran"></i>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="grid grid-cols-3 gap-2.5">
        <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs text-center">
            <span class="text-[10px] text-slate-400 font-semibold uppercase block">Total Setor</span>
            <span class="text-lg font-extrabold text-slate-900 mt-0.5 block"><?php echo number_format($stats['total']); ?></span>
            <span class="text-[9px] text-teal-700 font-medium">Sesi</span>
        </div>

        <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs text-center">
            <span class="text-[10px] text-slate-400 font-semibold uppercase block">Ziyadah</span>
            <span class="text-lg font-extrabold text-emerald-600 mt-0.5 block"><?php echo number_format($stats['ziyadah']); ?></span>
            <span class="text-[9px] text-slate-400 font-medium">Hafalan Baru</span>
        </div>

        <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs text-center">
            <span class="text-[10px] text-slate-400 font-semibold uppercase block">Juz Aktif</span>
            <span class="text-lg font-extrabold text-teal-700 mt-0.5 block font-arabic"><?php echo $stats['juz_latest']; ?></span>
            <span class="text-[9px] text-slate-400 font-medium">Target / Berjalan</span>
        </div>
    </div>

    <!-- Target & Capaian Semester Progress Card (Jika tersedia target/baseline) -->
    <?php if ($student_progress && ($student_progress['target_juz'] > 0 || $student_progress['total_juz'] > 0)): ?>
        <div class="bg-gradient-to-br from-teal-900 via-teal-800 to-emerald-900 rounded-2xl p-4 text-white shadow-xs relative overflow-hidden">
            <div class="absolute -right-6 -bottom-6 text-7xl text-white/5 pointer-events-none">
                <i class="fa-solid fa-kaaba"></i>
            </div>
            
            <div class="flex items-center justify-between mb-3 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center text-xs text-teal-200">
                        <i class="fa-solid fa-award"></i>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider text-teal-100">Progres Semester Berjalan</span>
                </div>
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-white/20 text-white backdrop-blur-xs">
                    <?php echo round($student_progress['progress_percentage']); ?>% Tercapai
                </span>
            </div>

            <!-- Progress Bar -->
            <div class="relative z-10">
                <div class="w-full bg-black/25 rounded-full h-2.5 mb-3 overflow-hidden p-0.5 border border-white/10">
                    <div class="bg-gradient-to-r from-teal-300 to-emerald-400 h-1.5 rounded-full transition-all duration-500" 
                         style="width: <?php echo min(100, max(0, $student_progress['progress_percentage'])); ?>%"></div>
                </div>

                <div class="grid grid-cols-3 gap-2 pt-2 border-t border-white/10 text-center">
                    <div>
                        <span class="text-[10px] text-teal-200/80 block">Target Semester</span>
                        <span class="text-xs font-extrabold text-white mt-0.5 block"><?php echo $student_progress['target_juz']; ?> Juz</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-teal-200/80 block">Hafalan Baru</span>
                        <span class="text-xs font-extrabold text-emerald-300 mt-0.5 block"><?php echo $student_progress['memorized_juz_semester']; ?> Juz</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-teal-200/80 block">Total Hafalan</span>
                        <span class="text-xs font-extrabold text-teal-200 mt-0.5 block"><?php echo $student_progress['total_juz']; ?> Juz</span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter Tab Bar -->
    <div class="flex items-center bg-slate-200/70 p-1 rounded-xl text-xs">
        <a href="tahfidz.php?status=all" 
           class="flex-1 py-1.5 text-center rounded-lg font-semibold transition-all <?php echo (!isset($_GET['status']) || $_GET['status'] === 'all') ? 'bg-white text-teal-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">
            Semua (<?php echo $stats['total']; ?>)
        </a>
        <a href="tahfidz.php?status=ziyadah" 
           class="flex-1 py-1.5 text-center rounded-lg font-semibold transition-all <?php echo (($_GET['status'] ?? '') === 'ziyadah') ? 'bg-white text-teal-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">
            Ziyadah (<?php echo $stats['ziyadah']; ?>)
        </a>
        <a href="tahfidz.php?status=murajaah" 
           class="flex-1 py-1.5 text-center rounded-lg font-semibold transition-all <?php echo (($_GET['status'] ?? '') === 'murajaah') ? 'bg-white text-teal-800 shadow-xs' : 'text-slate-600 hover:text-slate-900'; ?>">
            Muraja'ah (<?php echo $stats['murajaah']; ?>)
        </a>
    </div>

    <!-- Timeline List of Entries -->
    <div class="space-y-3">
        <?php if (empty($entries)): ?>
            <div class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 shadow-xs">
                <i class="fa-solid fa-book-open text-slate-300 text-3xl mb-2"></i>
                <p class="text-xs text-slate-500 font-medium">Belum ada riwayat setoran pada kategori ini.</p>
            </div>
        <?php else: ?>
            <?php foreach ($entries as $row): 
                $isMurajaah = ($row['entry_type'] === 'MUROJAAH' || strcasecmp($row['status'] ?? '', 'Murajaah') === 0);
                $isLancar = in_array($row['status'], ['Lancar', 'Ziyadah']);
                $badgeColor = $isLancar ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                
                $ayahStart = !empty($row['start_ayah']) ? (int)$row['start_ayah'] : (!empty($row['ayat_start']) ? (int)$row['ayat_start'] : null);
                $ayahEnd = !empty($row['end_ayah']) ? (int)$row['end_ayah'] : (!empty($row['ayat_end']) ? (int)$row['ayat_end'] : null);
                
                $juzNumber = !empty($row['juz']) ? $row['juz'] : get_quran_juz($row['surah_start'], $ayahStart ?: 1);

                $surahLabel = htmlspecialchars($row['surah_start'] ?? '');
                if (!empty($row['surah_end']) && strcasecmp($row['surah_start'] ?? '', $row['surah_end']) !== 0) {
                    $surahLabel .= ' - ' . htmlspecialchars($row['surah_end']);
                }
            ?>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs relative">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[11px] font-semibold text-slate-500 flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar text-teal-600"></i>
                            <?php echo date('d M Y', strtotime($row['date'])); ?>
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
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-baseline justify-between">
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">
                                <?php echo $surahLabel; ?>
                                <?php if ($ayahStart !== null): ?>
                                    <span class="text-xs font-semibold text-slate-600">: Ayat <?php echo $ayahStart; ?><?php if ($ayahEnd && $ayahEnd != $ayahStart): ?> - <?php echo $ayahEnd; ?><?php endif; ?></span>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($row['total_baris'])): ?>
                                <p class="text-[11px] text-slate-400 mt-0.5"><?php echo $row['total_baris']; ?> Baris hafalan</p>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($juzNumber)): ?>
                            <div class="text-right">
                                <span class="text-[9px] uppercase font-bold text-slate-400 block">Juz</span>
                                <span class="text-base font-black text-teal-700 font-arabic"><?php echo $juzNumber; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($row['notes'])): ?>
                        <div class="mt-2.5 p-2 rounded-xl bg-slate-50 border border-slate-100 text-[11px] text-slate-600 italic">
                            "<?php echo htmlspecialchars($row['notes']); ?>"
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($row['teacher_name'])): ?>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400">
                            <span>Pengampu Halaqah:</span>
                            <span class="text-slate-700 font-semibold"><?php echo htmlspecialchars($row['teacher_name']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
