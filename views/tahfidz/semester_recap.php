<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Rekap Semester Tahfidz";

$db = new Database();
$conn = $db->getConnection();

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Academic Years for Filter ---
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$active_ay = null;
foreach ($academic_years as $ay) {
    if ($ay['is_active']) {
        $active_ay = $ay;
        break;
    }
}

// --- Filter Parameters ---
$ay_id = isset($_GET['ay_id']) ? $_GET['ay_id'] : ($active_ay ? $active_ay['id'] : '');
$group_id = isset($_GET['group_id']) ? $_GET['group_id'] : '';

// Get selected academic year details
$selected_ay = null;
if ($ay_id) {
    foreach ($academic_years as $ay) {
        if ($ay['id'] == $ay_id) {
            $selected_ay = $ay;
            break;
        }
    }
}

$start_date = $selected_ay ? $selected_ay['start_date'] : date('Y-m-01');
$end_date = $selected_ay ? $selected_ay['end_date'] : date('Y-m-d');

// --- Fetch Halaqah Groups for Filter ---
$where_groups = "1=1";
$params_groups = [];
if (!$is_admin) {
    $where_groups = "hg.teacher_id = :uid";
    $params_groups[':uid'] = $_SESSION['user_id'];
}

$groups_query = "
    SELECT hg.*, e.full_name as teacher_name
    FROM halaqah_groups hg
    JOIN employees e ON hg.teacher_id = e.id
    WHERE $where_groups
    ORDER BY hg.group_name ASC
";
$groups_stmt = $conn->prepare($groups_query);
$groups_stmt->execute($params_groups);
$groups = $groups_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no group_id is selected but groups exist, pick the first one
if (!$group_id && count($groups) > 0) {
    $group_id = $groups[0]['id'];
}

// --- Fetch Students and Memorization Recap ---
$students_recap = [];
if ($group_id) {
    // 1. Get students in this halaqah group
    $students_query = "
        SELECT s.id, s.nama_siswa, s.nomor_induk, s.kelas
        FROM students s
        JOIN halaqah_members hm ON s.id = hm.student_id
        WHERE hm.group_id = :group_id
        ORDER BY s.nama_siswa ASC
    ";
    $students_stmt = $conn->prepare($students_query);
    $students_stmt->execute([':group_id' => $group_id]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $student) {
        $sid = $student['id'];
        
        // 2. Get earliest memorization in period (Ziyadah/Setoran)
        $earliest_query = "
            SELECT surah_start, ayat_start, date
            FROM tahfidz_memorization
            WHERE student_id = :sid AND date BETWEEN :start AND :end
            ORDER BY date ASC, created_at ASC
            LIMIT 1
        ";
        $e_stmt = $conn->prepare($earliest_query);
        $e_stmt->execute([':sid' => $sid, ':start' => $start_date, ':end' => $end_date]);
        $earliest = $e_stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Get latest memorization in period
        $latest_query = "
            SELECT surah_end, ayat_end, date, juz
            FROM tahfidz_memorization
            WHERE student_id = :sid AND date BETWEEN :start AND :end
            ORDER BY date DESC, created_at DESC
            LIMIT 1
        ";
        $l_stmt = $conn->prepare($latest_query);
        $l_stmt->execute([':sid' => $sid, ':start' => $start_date, ':end' => $end_date]);
        $latest = $l_stmt->fetch(PDO::FETCH_ASSOC);

        // 4. Count total setoran entries
        $count_query = "
            SELECT COUNT(*) as total_count, 
                   SUM(CASE WHEN LOWER(status) LIKE '%lancar%' AND LOWER(status) NOT LIKE '%kurang%' THEN 1 ELSE 0 END) as fluent_count
            FROM tahfidz_memorization
            WHERE student_id = :sid AND date BETWEEN :start AND :end
        ";
        $c_stmt = $conn->prepare($count_query);
        $c_stmt->execute([':sid' => $sid, ':start' => $start_date, ':end' => $end_date]);
        $counts = $c_stmt->fetch(PDO::FETCH_ASSOC);

        $students_recap[] = [
            'id' => $student['id'],
            'name' => $student['nama_siswa'],
            'nis' => $student['nomor_induk'] ?? '-',
            'class' => $student['kelas'] ?? '-',
            'start_mem' => $earliest ? $earliest['surah_start'] . " ayat " . $earliest['ayat_start'] : '-',
            'end_mem' => $latest ? $latest['surah_end'] . " ayat " . $latest['ayat_end'] : '-',
            'last_juz' => ($latest && $latest['juz']) ? $latest['juz'] : '-',
            'total_entries' => $counts['total_count'],
            'fluent_entries' => $counts['fluent_count']
        ];
    }
}

// Get group info for display
$selected_group = null;
foreach ($groups as $g) {
    if ($g['id'] == $group_id) {
        $selected_group = $g;
        break;
    }
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Header Section -->
    <div class="sm:flex sm:items-center justify-between mb-8 no-print">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight">Rekap Semester Tahfidz</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Rekapitulasi pencapaian hafalan santri per halaqah dalam satu semester.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="window.print()"
                class="inline-flex items-center justify-center rounded-lg bg-white border border-slate-300 px-6 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-all active:scale-95 group">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak Rekap
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm mb-8 no-print">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="space-y-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Kelompok Halaqah</label>
                <select name="group_id" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                    <option value="">Pilih Halaqah...</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?php echo $g['id']; ?>" <?php echo $group_id == $g['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['group_name']); ?> (<?php echo htmlspecialchars($g['teacher_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-2">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Semester / Tahun Ajaran</label>
                <select name="ay_id" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all appearance-none cursor-pointer" onchange="this.form.submit()">
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?php echo $ay['id']; ?>" <?php echo $ay_id == $ay['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ay['name']); ?> - <?php echo htmlspecialchars($ay['semester']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-slate-900 text-white px-6 py-3 rounded-xl text-sm font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/10">
                    Filter
                </button>
                <a href="semester_recap.php" class="bg-white border border-slate-200 text-slate-500 p-3 rounded-xl hover:bg-slate-50 transition-all" title="Reset Filter">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </form>
    </div>

    <!-- Info Card -->
    <?php if ($selected_group): ?>
        <!-- Print-only Header -->
        <div class="hidden print:block mb-8 text-center border-b-2 border-slate-900 pb-4">
            <h2 class="text-2xl font-black text-slate-900 uppercase">Rekapitulasi Hafalan Tahfidz</h2>
            <p class="text-sm font-bold text-slate-600 mt-1">
                Kelompok: <?php echo htmlspecialchars($selected_group['group_name']); ?> • 
                Pengampu: <?php echo htmlspecialchars($selected_group['teacher_name']); ?>
            </p>
            <p class="text-xs text-slate-500 mt-0.5">
                Periode: <?php echo htmlspecialchars($selected_ay['name']); ?> (<?php echo htmlspecialchars($selected_ay['semester']); ?>)
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 no-print">
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-cyan-50 flex items-center justify-center text-cyan-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Grup Halaqah</p>
                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($selected_group['group_name']); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pengampu</p>
                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($selected_group['teacher_name']); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Periode</p>
                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($selected_ay['name']); ?></h3>
                </div>
            </div>
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-4">
                <div class="h-12 w-12 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Semester</p>
                    <h3 class="font-bold text-slate-800"><?php echo htmlspecialchars($selected_ay['semester']); ?></h3>
                </div>
            </div>
        </div>

        <!-- Recap Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                        <th class="px-6 py-4 w-16">No.</th>
                        <th class="px-6 py-4 text-left">Santri</th>
                        <th class="px-6 py-4">Awal Semester</th>
                        <th class="px-6 py-4">Akhir Semester</th>
                        <th class="px-6 py-4">Capaian Juz</th>
                        <th class="px-6 py-4">Total Setoran</th>
                        <th class="px-6 py-4">Kualitas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (empty($students_recap)): ?>
                        <tr><td colspan="7" class="px-6 py-10 text-center text-slate-400 italic">Tidak ada santri dalam kelompok ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students_recap as $index => $s): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors text-center text-sm">
                                <td class="px-6 py-4 text-slate-400 text-xs"><?php echo $index + 1; ?>.</td>
                                <td class="px-6 py-4 text-left">
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($s['name']); ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium">NIS: <?php echo htmlspecialchars($s['nis']); ?> • <?php echo htmlspecialchars($s['class']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-slate-600 font-medium"><?php echo htmlspecialchars($s['start_mem']); ?></td>
                                <td class="px-6 py-4 text-slate-600 font-bold"><?php echo htmlspecialchars($s['end_mem']); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-cyan-50 text-cyan-700">
                                        Juz <?php echo htmlspecialchars($s['last_juz']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-black text-slate-800"><?php echo $s['total_entries']; ?> <span class="text-[10px] font-normal text-slate-400 italic">kali</span></td>
                                <td class="px-6 py-4">
                                    <?php 
                                    $percent = $s['total_entries'] > 0 ? ($s['fluent_entries'] / $s['total_entries']) * 100 : 0;
                                    $color = 'bg-slate-400';
                                    if ($percent >= 80) $color = 'bg-emerald-500';
                                    elseif ($percent >= 60) $color = 'bg-cyan-500';
                                    elseif ($percent >= 40) $color = 'bg-amber-500';
                                    else $color = 'bg-rose-500';
                                    ?>
                                    <div class="flex flex-col items-center gap-1">
                                        <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                            <div class="h-full <?php echo $color; ?>" style="width: <?php echo $percent; ?>%"></div>
                                        </div>
                                        <span class="text-[10px] font-bold text-slate-500"><?php echo round($percent); ?>% Lancar</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="py-20 bg-white rounded-2xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 text-center px-6 shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mb-4 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
            <p class="text-lg font-bold text-slate-600">Pilih Halaqah dan Semester</p>
            <p class="text-sm mt-1 max-w-xs">Silakan pilih kelompok halaqah dan periode semester untuk melihat rekapitulasi hafalan santri.</p>
        </div>
    <?php endif; ?>
</div>

<style>
@media print {
    @page { margin: 1cm; }
    body { background: white !important; font-size: 10pt; }
    #main-sidebar, header, .no-print { display: none !important; }
    .pb-10 { padding: 0 !important; margin: 0 !important; }
    .bg-white.rounded-2xl { border: none !important; box-shadow: none !important; }
    .min-w-full { width: 100% !important; border-collapse: collapse !important; border: 1px solid #000 !important; }
    th, td { border: 1px solid #000 !important; padding: 8px !important; }
    th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
}
</style>

<?php include '../layouts/footer.php'; ?>
