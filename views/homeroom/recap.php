<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Get the class where this user is Wali Kelas
$stmt_class = $conn->prepare("
    SELECT gl.id, gl.name, gl.education_unit_id, eu.name as unit_name
    FROM grade_levels gl
    JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE gl.teacher_id = :uid
");
$stmt_class->execute([':uid' => $user_id]);
$my_classes = $stmt_class->fetchAll(PDO::FETCH_ASSOC);

if (empty($my_classes)) {
    $page_title = "Akses Ditolak";
    $error_message = "Anda bukan Wali Kelas";
    include '../layouts/header.php';
    include '../layouts/no_access.php';
    include '../layouts/footer.php';
    exit;
}

$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : $my_classes[0]['id'];

// Verify access
$has_access = false;
foreach ($my_classes as $mc) {
    if ($mc['id'] == $grade_id) {
        $has_access = true;
        $current_class = $mc;
        break;
    }
}

if (!$has_access) {
    $page_title = "Akses Ditolak";
    $error_message = "Akses Kelas Ditolak";
    include '../layouts/header.php';
    include '../layouts/no_access.php';
    include '../layouts/footer.php';
    exit;
}

// Fetch active academic year
$stmt_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
$active_ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);
$ay_id = $active_ay['id'];

// Date range for recap (default to academic year dates)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ($active_ay['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : ($active_ay['end_date'] ?? date('Y-m-d'));

// Fetch students
$stmt_students = $conn->prepare("
    SELECT s.id, s.nama_siswa, s.nomor_induk
    FROM students s
    JOIN student_class_history sch ON s.id = sch.student_id
    WHERE sch.class_id = :grade_id AND sch.academic_year_id = :ay_id AND s.status = 'Aktif'
    ORDER BY s.nama_siswa ASC
");
$stmt_students->execute([':grade_id' => $grade_id, ':ay_id' => $ay_id]);
$students = $stmt_students->fetchAll(PDO::FETCH_ASSOC);

// Fetch recap totals
$sql_recap = "
    SELECT 
        student_id,
        SUM(CASE WHEN status = 'H' THEN 1 ELSE 0 END) as total_h,
        SUM(CASE WHEN status = 'S' THEN 1 ELSE 0 END) as total_s,
        SUM(CASE WHEN status = 'I' THEN 1 ELSE 0 END) as total_i,
        SUM(CASE WHEN status = 'A' THEN 1 ELSE 0 END) as total_a,
        SUM(CASE WHEN status = 'T' THEN 1 ELSE 0 END) as total_t,
        COUNT(*) as total_days
    FROM daily_student_attendances
    WHERE grade_level_id = :grade_id AND date BETWEEN :start AND :end
    GROUP BY student_id
";
$stmt_recap = $conn->prepare($sql_recap);
$stmt_recap->execute([':grade_id' => $grade_id, ':start' => $start_date, ':end' => $end_date]);
$recap_data_raw = $stmt_recap->fetchAll(PDO::FETCH_ASSOC);
$recap_data = [];
foreach ($recap_data_raw as $row) {
    $recap_data[$row['student_id']] = $row;
}

$page_title = "Rekap Absensi Semester - " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rekap Absensi Satu Semester</h1>
            <p class="text-sm text-slate-500 mt-1">Rekapitulasi kehadiran siswa kelas <?php echo htmlspecialchars($current_class['name']); ?> periode <?php echo htmlspecialchars($active_ay['name']); ?> (<?php echo htmlspecialchars($active_ay['semester']); ?>).</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 mb-8">
        <form action="" method="GET" class="flex flex-col md:flex-row items-end gap-4">
            <input type="hidden" name="grade_id" value="<?php echo $grade_id; ?>">
            <div class="flex-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Rentang Tanggal</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                    <span class="text-slate-400">s/d</span>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                </div>
            </div>
            <?php if (count($my_classes) > 1): ?>
                <div class="w-full md:w-48">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Pilih Kelas</label>
                    <select name="grade_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                        <?php foreach ($my_classes as $mc): ?>
                            <option value="<?php echo $mc['id']; ?>" <?php echo $mc['id'] == $grade_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mc['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit" class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/10">
                Filter Rekap
            </button>
            <button type="button" onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2 font-bold text-sm">
                <i class="fa-solid fa-file-arrow-down w-5 h-5 text-rose-600"></i>
                Export PDF
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">
                    <th class="px-6 py-4 w-16">No.</th>
                    <th class="px-6 py-4 text-left">Nama Siswa</th>
                    <th class="px-6 py-4 bg-emerald-50 text-emerald-700">Hadir</th>
                    <th class="px-6 py-4 bg-blue-50 text-blue-700">Sakit</th>
                    <th class="px-6 py-4 bg-amber-50 text-amber-700">Izin</th>
                    <th class="px-6 py-4 bg-rose-50 text-rose-700">Alpha</th>
                    <th class="px-6 py-4 bg-orange-50 text-orange-700">Telat</th>
                    <th class="px-6 py-4 border-l border-slate-200 font-black text-slate-800">Total Hari</th>
                    <th class="px-6 py-4">% Kehadiran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($students)): ?>
                    <tr><td colspan="9" class="px-6 py-10 text-center text-slate-400 italic">Data tidak ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $index => $s): 
                        $rd = $recap_data[$s['id']] ?? ['total_h' => 0, 'total_s' => 0, 'total_i' => 0, 'total_a' => 0, 'total_t' => 0, 'total_days' => 0];
                        $percent = $rd['total_days'] > 0 ? ($rd['total_h'] / $rd['total_days']) * 100 : 0;
                    ?>
                        <tr class="hover:bg-slate-50/50 transition-colors text-center text-sm">
                            <td class="px-6 py-4 text-slate-400 text-xs"><?php echo $index + 1; ?>.</td>
                            <td class="px-6 py-4 text-left font-bold text-slate-900">
                                <?php echo htmlspecialchars($s['nama_siswa']); ?>
                                <div class="text-[9px] text-slate-400 font-medium">NIS: <?php echo htmlspecialchars($s['nomor_induk'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 font-bold text-emerald-600"><?php echo $rd['total_h']; ?></td>
                            <td class="px-6 py-4 font-bold text-blue-600"><?php echo $rd['total_s']; ?></td>
                            <td class="px-6 py-4 font-bold text-amber-600"><?php echo $rd['total_i']; ?></td>
                            <td class="px-6 py-4 font-bold text-rose-600"><?php echo $rd['total_a']; ?></td>
                            <td class="px-6 py-4 font-bold text-orange-600"><?php echo $rd['total_t']; ?></td>
                            <td class="px-6 py-4 border-l border-slate-100 font-black text-slate-800"><?php echo $rd['total_days']; ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-center gap-2">
                                    <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                        <div class="h-full bg-cyan-500" style="width: <?php echo $percent; ?>%"></div>
                                    </div>
                                    <span class="font-bold text-[10px] text-slate-600"><?php echo round($percent); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
@media print {
    body { background: white !important; }
    #main-sidebar, header, .pb-10 > div:first-child, .bg-white.p-6.rounded-2xl { display: none !important; }
    .pb-10 { padding: 0 !important; margin: 0 !important; }
    .bg-white.rounded-2xl { border: none !important; box-shadow: none !important; }
    .min-w-full { width: 100% !important; border: 1px solid #e2e8f0 !important; }
    th, td { border: 1px solid #e2e8f0 !important; }
}
</style>

<?php include '../layouts/footer.php'; ?>
