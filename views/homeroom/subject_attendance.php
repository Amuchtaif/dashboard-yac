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

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

if (empty($my_classes) && !$is_admin) {
    $page_title = "Akses Ditolak";
    $error_message = "Anda bukan Wali Kelas";
    include '../layouts/header.php';
    include '../layouts/no_access.php';
    include '../layouts/footer.php';
    exit;
}

// If admin and no my_classes, maybe they want to see everything? 
if ($is_admin && empty($my_classes)) {
    $stmt_all_classes = $conn->query("SELECT id, name FROM grade_levels ORDER BY name ASC");
    $my_classes = $stmt_all_classes->fetchAll(PDO::FETCH_ASSOC);
}

$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : ($my_classes[0]['id'] ?? '');
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Resolve Day Name
$day_map = [
    'Sunday' => 'Ahad',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$english_day = date('l', strtotime($date));
$idn_day = $day_map[$english_day];

// --- Fetch Schedules & Attendance for My Class ---
$sql = "
    SELECT 
        cs.id as schedule_id,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        cj.id as journal_id,
        cj.topic,
        cj.notes,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as late
    FROM class_schedules cs
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cs.employee_id = e.id
    LEFT JOIN class_journals cj ON cs.id = cj.class_schedule_id AND cj.date = :date
    WHERE cs.day = :day AND cs.grade_level_id = :grade_id
    ORDER BY lp.start_time ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute([':date' => $date, ':day' => $english_day, ':grade_id' => $grade_id]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_class_name = "";
foreach($my_classes as $mc) {
    if ($mc['id'] == $grade_id) $current_class_name = $mc['name'];
}

$page_title = "Absensi Per Mapel - " . $current_class_name;
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Absensi Per Mata Pelajaran</h1>
            <p class="text-sm text-slate-500 mt-1">Pantau kehadiran siswa per mata pelajaran untuk kelas <?php echo htmlspecialchars($current_class_name); ?>.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date" value="<?php echo $date; ?>"
                class="rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm"
                onchange="window.location.href='?grade_id=<?php echo $grade_id; ?>&date='+this.value">

            <?php if (count($my_classes) > 1): ?>
                <select onchange="window.location.href='?date=<?php echo $date; ?>&grade_id='+this.value"
                    class="rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                    <?php foreach ($my_classes as $mc): ?>
                        <option value="<?php echo $mc['id']; ?>" <?php echo $mc['id'] == $grade_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 text-left">Waktu</th>
                    <th class="px-6 py-4 text-left">Mata Pelajaran</th>
                    <th class="px-6 py-4 text-left">Guru</th>
                    <th class="px-6 py-4 text-center">Status Jurnal</th>
                    <th class="px-6 py-4 text-center">Kehadiran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($schedules)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <div class="bg-slate-50 p-4 rounded-full mb-4">
                                    <svg class="w-10 h-10 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="text-slate-500 font-medium">Tidak ada jadwal pelajaran.</p>
                                <p class="text-slate-400 text-sm mt-1">Hari <?php echo $idn_day; ?> tidak ditemukan jadwal untuk kelas ini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($schedules as $s): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 font-medium">
                                <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($s['subject_name']); ?></div>
                                <?php if ($s['topic']): ?>
                                    <div class="text-[11px] text-slate-400 truncate max-w-xs mt-0.5"><?php echo htmlspecialchars($s['topic']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600 font-medium"><?php echo htmlspecialchars($s['teacher_name']); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($s['journal_id']): ?>
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 uppercase tracking-tight">Terisi</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/10 uppercase tracking-tight">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($s['journal_id']): ?>
                                    <div class="flex justify-center items-center gap-3">
                                        <div class="flex flex-col items-center min-w-[24px]">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">H</span>
                                            <span class="text-sm font-black text-emerald-600"><?php echo $s['present']; ?></span>
                                        </div>
                                        <div class="flex flex-col items-center min-w-[24px]">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">S</span>
                                            <span class="text-sm font-black text-blue-600"><?php echo $s['sick']; ?></span>
                                        </div>
                                        <div class="flex flex-col items-center min-w-[24px]">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">I</span>
                                            <span class="text-sm font-black text-amber-600"><?php echo $s['permit']; ?></span>
                                        </div>
                                        <div class="flex flex-col items-center min-w-[24px]">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">A</span>
                                            <span class="text-sm font-black text-rose-600"><?php echo $s['absent']; ?></span>
                                        </div>
                                        <div class="flex flex-col items-center min-w-[24px]">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">T</span>
                                            <span class="text-sm font-black text-orange-600"><?php echo $s['late']; ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-slate-300 text-xs italic">Menunggu input guru</div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="mt-8 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
        <p class="text-xs text-slate-500 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Data absensi per mapel sinkron dengan Jurnal Kelas yang diisi oleh Guru.
        </p>
        <div class="flex gap-3">
            <a href="attendance.php?grade_id=<?php echo $grade_id; ?>&date=<?php echo $date; ?>" 
               class="bg-white px-4 py-2 rounded-lg border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-100 transition-all flex items-center gap-2 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 0118 0z" />
                </svg>
                Absensi Harian
            </a>
            <a href="recap.php?grade_id=<?php echo $grade_id; ?>" 
               class="bg-cyan-600 px-4 py-2 rounded-lg text-xs font-bold text-white hover:bg-cyan-700 transition-all flex items-center gap-2 shadow-md shadow-cyan-100">
                Rekap Semester
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </a>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
