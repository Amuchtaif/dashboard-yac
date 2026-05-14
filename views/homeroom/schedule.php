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

$indo_days = [
    'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 
    'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Ahad'
];

// Fetch schedules for this class
// First, find the latest academic year that has schedules for this class
$stmt_year = $conn->prepare("
    SELECT academic_year_id 
    FROM class_schedules 
    WHERE grade_level_id = :grade_id 
    ORDER BY academic_year_id DESC 
    LIMIT 1
");
$stmt_year->execute([':grade_id' => $grade_id]);
$target_year_id = $stmt_year->fetchColumn();

if (!$target_year_id) {
    // Fallback to active year if no schedules at all
    $target_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
}

$sql = "
    SELECT cs.*, s.name as subject_name, e.full_name as teacher_name,
           lp.start_time, COALESCE(lp_end.end_time, lp.end_time) as end_time,
           lp.period_number as start_period, COALESCE(lp_end.period_number, lp.period_number) as end_period
    FROM class_schedules cs
    JOIN subjects s ON cs.subject_id = s.id
    JOIN employees e ON cs.employee_id = e.id
    LEFT JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    WHERE cs.grade_level_id = :grade_id AND cs.academic_year_id = :year_id
    ORDER BY FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), lp.start_time ASC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':grade_id' => $grade_id, ':year_id' => $target_year_id]);
$schedules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

$schedules_by_day = [];
foreach ($schedules_raw as $sch) {
    $schedules_by_day[$sch['day']][] = $sch;
}

$page_title = "Jadwal Pelajaran Kelas " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Jadwal Pelajaran Kelas</h1>
        <p class="text-sm text-slate-500 mt-1">Jadwal mingguan untuk kelas <?php echo htmlspecialchars($current_class['name']); ?>.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): 
            $day_name = $indo_days[$day];
            $day_schedules = $schedules_by_day[$day] ?? [];
        ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800"><?php echo $day_name; ?></h3>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo count($day_schedules); ?> Sesi</span>
                </div>
                <div class="p-4 flex-1">
                    <?php if (empty($day_schedules)): ?>
                        <div class="py-10 text-center text-slate-400 text-xs italic">Tidak ada jadwal.</div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($day_schedules as $sch): ?>
                                <div class="flex items-start gap-4 p-3 rounded-lg border border-slate-100 hover:border-cyan-200 hover:bg-cyan-50 transition-all group">
                                    <div class="text-center min-w-[50px]">
                                        <div class="text-xs font-black text-cyan-600"><?php echo date('H:i', strtotime($sch['start_time'])); ?></div>
                                        <div class="text-[9px] text-slate-400 mt-0.5">s/d <?php echo date('H:i', strtotime($sch['end_time'])); ?></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-sm font-bold text-slate-900 group-hover:text-cyan-700 transition-colors"><?php echo htmlspecialchars($sch['subject_name']); ?></div>
                                        <div class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-slate-300">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                            </svg>
                                            <?php echo htmlspecialchars($sch['teacher_name']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
