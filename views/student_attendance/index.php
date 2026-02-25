<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Absensi Siswa";
$db = new Database();
$conn = $db->getConnection();

// --- Filters ---
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';

// Resolve Day Name
$day_map = [
    'Sunday' => 'Ahad', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$english_day = date('l', strtotime($date));
$idn_day = $day_map[$english_day];

// --- Data Master --
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Schedules & Attendance ---
$sql = "
    SELECT 
        cs.id as schedule_id,
        lp.start_time,
        lp.end_time,
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
    JOIN employees e ON cs.employee_id = e.id
    LEFT JOIN class_journals cj ON cs.id = cj.class_schedule_id AND cj.date = :date
    WHERE cs.day = :day
";

$params = [':date' => $date, ':day' => $english_day];

if ($unit_id) {
    $sql .= " AND gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($grade_id) {
    $sql .= " AND cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}

$sql .= " ORDER BY lp.start_time ASC, gl.name ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Absensi Siswa</h1>
            <p class="mt-2 text-sm text-slate-500">Pantau kehadiran siswa per mata pelajaran.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4" method="GET">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Date -->
            <div>
                <label for="date" class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" 
                    onchange="this.form.submit()"
                    class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5">
                <p class="mt-1 text-xs text-slate-500"><?php echo $idn_day; ?></p>
            </div>

            <!-- Unit -->
            <div>
                <label for="unit_id" class="block text-sm font-medium text-slate-700 mb-1">Unit</label>
                <select name="unit_id" id="unit_id" onchange="filterGrades(); this.form.submit()"
                    class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Grade -->
            <div>
                <label for="grade_id" class="block text-sm font-medium text-slate-700 mb-1">Kelas</label>
                <select name="grade_id" id="grade_id" onchange="this.form.submit()"
                    class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?php echo $g['id']; ?>" 
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                <?php echo $grade_id == $g['id'] ? 'selected' : ''; ?>
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function filterGrades() {
        const unitId = document.getElementById('unit_id').value;
        const gradeSelect = document.getElementById('grade_id');
        const options = gradeSelect.options;
        gradeSelect.value = "";
        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            const optUnitId = opt.getAttribute('data-unit');
            if (!unitId || !optUnitId || optUnitId === unitId) {
                opt.style.display = "";
            } else {
                opt.style.display = "none";
            }
        }
    }
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-24">Waktu</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 w-32">Kelas</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Mata Pelajaran</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Guru</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500 w-24">Status</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">Tidak ada jadwal pelajaran pada hari ini.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $s): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo date('H:i', strtotime($s['start_time'])); ?> - <?php echo date('H:i', strtotime($s['end_time'])); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($s['class_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($s['subject_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($s['teacher_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center">
                                <?php if ($s['journal_id']): ?>
                                    <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Sudah</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/10">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-center">
                                <?php if ($s['journal_id']): ?>
                                    <div class="flex justify-center space-x-2 text-xs">
                                        <span class="text-green-600 font-bold" title="Hadir">H: <?php echo $s['present']; ?></span>
                                        <span class="text-red-600 font-bold" title="Alpha">A: <?php echo $s['absent']; ?></span>
                                        <span class="text-yellow-600 font-bold" title="Sakit">S: <?php echo $s['sick']; ?></span>
                                        <span class="text-blue-600 font-bold" title="Izin">I: <?php echo $s['permit']; ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-slate-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
