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
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

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

// Fetch journals for this class
$sql = "
    SELECT 
        cj.id, cj.date, lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time,
        s.name as subject_name, e.full_name as teacher_name, cj.topic, cj.notes,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id) as total_students,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as count_present
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cj.teacher_id = e.id
    WHERE cs.grade_level_id = :grade_id AND cj.date = :date
    ORDER BY lp.start_time ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':grade_id' => $grade_id, ':date' => $date]);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Jurnal Kelas " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Jurnal Kelas</h1>
            <p class="text-sm text-slate-500 mt-1">Kumpulan jurnal harian untuk kelas <?php echo htmlspecialchars($current_class['name']); ?>.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date" value="<?php echo $date; ?>" 
                class="rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm"
                onchange="window.location.href='?grade_id=<?php echo $grade_id; ?>&date='+this.value">
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 text-left">Waktu</th>
                    <th class="px-6 py-4 text-left">Mata Pelajaran</th>
                    <th class="px-6 py-4 text-left">Guru</th>
                    <th class="px-6 py-4 text-left">Materi / Catatan</th>
                    <th class="px-6 py-4 text-center">Kehadiran</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($journals)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-slate-500 italic">Belum ada jurnal yang diinput untuk tanggal ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($journals as $j): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <div class="font-bold text-slate-900"><?php echo date('H:i', strtotime($j['start_time'])); ?> - <?php echo date('H:i', strtotime($j['end_time'])); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-cyan-600">
                                <?php echo htmlspecialchars($j['subject_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <?php echo htmlspecialchars($j['teacher_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600 max-w-xs">
                                <div class="font-medium text-slate-800"><?php echo htmlspecialchars($j['topic'] ?: '-'); ?></div>
                                <div class="text-xs italic text-slate-400 mt-1"><?php echo htmlspecialchars($j['notes'] ?: ''); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                    <?php echo $j['count_present']; ?> / <?php echo $j['total_students']; ?> Hadir
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-6 flex justify-end">
        <a href="journal_recap.php?grade_id=<?php echo $grade_id; ?>"
            class="text-cyan-600 hover:text-cyan-700 text-sm font-bold flex items-center gap-2">
            Lihat Rekap Semester
            <i class="fa-solid fa-arrow-right w-4 h-4"></i>
        </a>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
