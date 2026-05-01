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
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch students for the filtered class
$students = [];
if ($grade_id) {
    // Get students in this class for the active academic year
    $active_year = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    
    $stmt = $conn->prepare("
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk,
            s.foto
        FROM students s
        JOIN student_class_history sch ON s.id = sch.student_id
        WHERE sch.class_id = :grade_id AND sch.academic_year_id = :year_id AND s.status = 'Aktif'
        ORDER BY s.nama_siswa ASC
    ");
    $stmt->execute([':grade_id' => $grade_id, ':year_id' => $active_year]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
    
    // Get attendance records for these students on this date across all subjects
    // We'll aggregate them into a list of subjects and statuses per student
    $where_att = "WHERE cj.date = :date AND cs.grade_level_id = :grade_id";
    $params_att = [':date' => $date, ':grade_id' => $grade_id];

    if (!$is_admin) {
        $where_att .= " AND cs.employee_id = :current_user_id";
        $params_att[':current_user_id'] = $_SESSION['user_id'];
    }

    $att_stmt = $conn->prepare("
        SELECT 
            sa.student_id,
            sa.status,
            sub.name as subject_name,
            lp.start_time
        FROM student_attendances sa
        JOIN class_journals cj ON sa.class_journal_id = cj.id
        JOIN class_schedules cs ON cj.class_schedule_id = cs.id
        JOIN subjects sub ON cs.subject_id = sub.id
        JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
        $where_att
        ORDER BY lp.start_time ASC
    ");
    $att_stmt->execute($params_att);
    $attendance_raw = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

    $attendance_map = [];
    foreach ($attendance_raw as $row) {
        $attendance_map[$row['student_id']][] = [
            'subject' => $row['subject_name'],
            'status' => $row['status'],
            'time' => $row['start_time']
        ];
    }
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Absensi Siswa</h1>
            <p class="mt-2 text-sm text-slate-500">Lihat status kehadiran masing-masing siswa berdasarkan jadwal mata pelajaran.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm" method="GET">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Date -->
            <div class="space-y-1.5">
                <label for="date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Tanggal</label>
                <div class="relative">
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" 
                        onchange="this.form.submit()"
                        class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border text-slate-600 py-2.5 px-4 h-[45px]">
                </div>
            </div>

            <!-- Unit Filter -->
            <div class="relative space-y-1.5" id="filter-unit-container">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Unit</label>
                <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full h-[45px]">
                    <span id="filter-unit-text" class="truncate">
                        <?php
                        $unit_name = "Semua Unit";
                        foreach ($units as $u) {
                            if ($u['id'] == $unit_id) $unit_name = $u['name'];
                        }
                        echo htmlspecialchars($unit_name);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Semua Unit')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Unit</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Grade Filter -->
            <div class="relative space-y-1.5" id="filter-grade-container">
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider">Kelas</label>
                <input type="hidden" name="grade_id" id="filter-grade-input" value="<?php echo $grade_id; ?>">
                <button type="button" onclick="toggleDropdown('filter-grade')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-full h-[45px]">
                    <span id="filter-grade-text" class="truncate">
                        <?php
                        $grade_name = "Semua Kelas";
                        foreach ($grades as $g) {
                            if ($g['id'] == $grade_id) $grade_name = $g['name'];
                        }
                        echo htmlspecialchars($grade_name);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-grade-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="filter-grade-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('grade', '', 'Semua Kelas')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Kelas</li>
                        <?php foreach ($grades as $g): ?>
                            <li onclick="selectFilterOption('grade', '<?php echo $g['id']; ?>', '<?php echo htmlspecialchars(addslashes($g['name']), ENT_QUOTES); ?>')"
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                class="grade-option cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <div>
                <a href="index.php" class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-red-600 transition-all w-full h-[45px] shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Reset
                </a>
            </div>
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function selectFilterOption(type, value, text) {
        document.getElementById('filter-' + type + '-input').value = value;
        document.getElementById('filterForm').submit();
    }
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="py-3.5 pl-4 pr-3 text-left w-16 sm:pl-6 text-center">No.</th>
                        <th class="px-3 py-3.5 text-left min-w-[250px]">Siswa</th>
                        <th class="px-3 py-3.5 text-left">Status Kehadiran per Mata Pelajaran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($grade_id)): ?>
                        <tr>
                            <td colspan="3" class="py-10 text-center text-sm text-slate-500 italic">Silakan pilih unit dan kelas terlebih dahulu.</td>
                        </tr>
                    <?php elseif (empty($students)): ?>
                        <tr>
                            <td colspan="3" class="py-10 text-center text-sm text-slate-500 italic">Tidak ada siswa aktif di kelas ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $index => $s): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-400 sm:pl-6 text-center">
                                    <?php echo $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <?php
                                            $avatarPath = "https://ui-avatars.com/api/?name=" . urlencode($s['nama_siswa']) . "&background=random&color=fff&bold=true";
                                            if (!empty($s['foto']) && file_exists("../../uploads/students/" . $s['foto'])) {
                                                $avatarPath = "../../uploads/students/" . $s['foto'];
                                            }
                                            ?>
                                            <img class="h-10 w-10 rounded-full object-cover border border-slate-100" src="<?php echo $avatarPath; ?>" alt="">
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-bold text-slate-900 text-sm"><?php echo htmlspecialchars($s['nama_siswa']); ?></div>
                                            <div class="text-[11px] text-slate-400 font-medium uppercase tracking-tight">NIS: <?php echo htmlspecialchars($s['nomor_induk'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        <?php if (isset($attendance_map[$s['id']])): ?>
                                            <?php foreach ($attendance_map[$s['id']] as $att): ?>
                                                <?php
                                                $statusClass = 'bg-slate-100 text-slate-600';
                                                $st = strtolower($att['status']);
                                                if ($st == 'present') $statusClass = 'bg-green-100 text-green-700';
                                                elseif ($st == 'absent') $statusClass = 'bg-red-100 text-red-700';
                                                elseif ($st == 'sick') $statusClass = 'bg-blue-100 text-blue-700';
                                                elseif ($st == 'permit') $statusClass = 'bg-yellow-100 text-yellow-700';
                                                elseif ($st == 'late') $statusClass = 'bg-orange-100 text-orange-700';
                                                
                                                $statusTextMap = [
                                                    'present' => 'H', 'absent' => 'A', 'sick' => 'S', 'permit' => 'I', 'late' => 'T'
                                                ];
                                                $text = $statusTextMap[$st] ?? $att['status'];
                                                ?>
                                                <div class="flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-100 bg-white shadow-sm" title="<?php echo htmlspecialchars($att['subject'] . ' (' . date('H:i', strtotime($att['time'])) . ')'); ?>">
                                                    <span class="text-[10px] font-bold text-slate-400 uppercase"><?php echo htmlspecialchars($att['subject']); ?>:</span>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black <?php echo $statusClass; ?>">
                                                        <?php echo $text; ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs italic">Belum ada data presensi hari ini.</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
