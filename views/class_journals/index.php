<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Jurnal Kelas";
$db = new Database();
$conn = $db->getConnection();

// --- Filters ---
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : '';

$where_clauses = ["cj.date = :date"];
$params = [':date' => $date];

if ($unit_id) {
    // Requires join with grades
}

if ($grade_id) {
    $where_clauses[] = "cs.grade_level_id = :grade_id";
    $params[':grade_id'] = $grade_id;
}

if ($employee_id) {
    $where_clauses[] = "cj.teacher_id = :employee_id";
    $params[':employee_id'] = $employee_id;
}
// Note: Joining required for filters

$where_sql = "WHERE " . implode(" AND ", $where_clauses);

// --- Data Master --
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$grades = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$employees = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Data ---
$sql = "
    SELECT 
        cj.id,
        cj.date,
        cs.day,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        cj.topic,
        cj.notes,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'present') as count_present,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'absent') as count_absent,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'sick') as count_sick,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'permit') as count_permit,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id AND sa.status = 'late') as count_late,
        (SELECT COUNT(*) FROM student_attendances sa WHERE sa.class_journal_id = cj.id) as total_attendance
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cj.teacher_id = e.id
    $where_sql
";

if ($unit_id) {
    $sql .= " AND gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$sql .= " ORDER BY cj.date DESC, lp.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Jurnal Kelas</h1>
            <p class="mt-2 text-sm text-slate-500">Rekapitulasi aktivitas pembelajaran harian.</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form id="filterForm" class="mt-8 bg-white p-6 rounded-xl border border-slate-200 shadow-sm" method="GET">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <!-- Date -->
            <div class="space-y-1.5">
                <div class="relative">
                    <input type="date" id="input-date" name="date" value="<?php echo htmlspecialchars($date); ?>" 
                        onchange="this.form.submit()"
                        class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all bg-slate-50 h-[42px]">
                </div>
            </div>

            <!-- Custom Education Unit Dropdown -->
            <div class="relative" id="container-unit_id">
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-unit_id" class="block truncate">
                        <?php 
                        $unitTitle = "Semua Jenjang";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <svg id="arrow-unit_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari jenjang..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Semua Jenjang')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Jenjang</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Grade Level Dropdown -->
            <div class="relative" id="container-grade_id">
                <input type="hidden" name="grade_id" id="input-grade_id" value="<?php echo $grade_id; ?>">
                <button type="button" onclick="toggleFormDropdown('grade_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-grade_id" class="block truncate">
                        <?php 
                        $gradeTitle = "Semua Kelas";
                        foreach($grades as $g) if($g['id'] == $grade_id) $gradeTitle = $g['name'];
                        echo htmlspecialchars($gradeTitle);
                        ?>
                    </span>
                    <svg id="arrow-grade_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-grade_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-grade_id" onkeyup="filterDropdownSearch('grade_id')" placeholder="Cari kelas..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-grade_id">
                        <li onclick="selectFilterOption('grade_id', '', 'Semua Kelas')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Kelas</li>
                        <?php foreach ($grades as $g): ?>
                            <li onclick="selectFilterOption('grade_id', '<?php echo $g['id']; ?>', '<?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?>')" 
                                data-unit="<?php echo $g['education_unit_id']; ?>"
                                class="grade-option relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors"
                                <?php echo ($unit_id && $g['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($g['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Employee (Guru) Dropdown -->
            <div class="relative" id="container-employee_id">
                <input type="hidden" name="employee_id" id="input-employee_id" value="<?php echo $employee_id; ?>">
                <button type="button" onclick="toggleFormDropdown('employee_id')"
                    class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <span id="text-employee_id" class="block truncate">
                        <?php 
                        $empTitle = "Semua Guru";
                        foreach($employees as $emp) if((string)$emp['id'] === (string)$employee_id) $empTitle = $emp['full_name'];
                        echo htmlspecialchars($empTitle);
                        ?>
                    </span>
                    <svg id="arrow-employee_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-employee_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm right-0">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-employee_id" onkeyup="filterDropdownSearch('employee_id')" placeholder="Cari guru..." class="block w-full rounded-md border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                    </div>
                    <ul id="list-employee_id">
                        <li onclick="selectFilterOption('employee_id', '', 'Semua Guru')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">Semua Guru</li>
                        <?php foreach ($employees as $emp): ?>
                            <li onclick="selectFilterOption('employee_id', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($emp['full_name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php if (!empty($unit_id) || !empty($grade_id) || !empty($employee_id) || $date != date('Y-m-d')): ?>
            <div class="flex items-end h-[42px] mt-auto">
                <a href="?" class="inline-flex w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all h-[42px]">
                    <svg class="h-4 w-4 mr-2 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Reset Filter
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <button type="submit" class="hidden">Filter</button>
    </form>

    <script>
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        const allArrows = document.querySelectorAll('[id^="arrow-"]');
        
        allMenus.forEach(m => { if(m !== menu) m.classList.add('hidden'); });
        allArrows.forEach(a => { if(a !== arrow) a.classList.remove('rotate-180'); });

        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
        
        if (!menu.classList.contains('hidden') && document.getElementById('search-' + id)) {
            document.getElementById('search-' + id).focus();
        }
    }

    function selectFilterOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).innerText = text;
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

        if (id === 'unit_id') {
            document.getElementById('input-grade_id').value = '';
            document.getElementById('text-grade_id').innerText = 'Semua Kelas';
        }

        document.getElementById('filterForm').submit();
    }

    function filterDropdownSearch(id) {
        const input = document.getElementById('search-' + id);
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-' + id);
        const li = list.getElementsByTagName('li');
        const unitId = document.getElementById('input-unit_id').value;

        for (let i = 0; i < li.length; i++) {
            const txtValue = li[i].textContent || li[i].innerText;
            const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;

            if (id === 'grade_id') {
                const itemUnit = li[i].getAttribute('data-unit');
                const matchesUnit = (!unitId || itemUnit === unitId || !itemUnit);
                li[i].style.display = (matchesSearch && matchesUnit) ? "" : "none";
            } else {
                li[i].style.display = matchesSearch ? "" : "none";
            }
        }
    }

    // Close on click outside
    window.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="container-"]').forEach(container => {
            if (!container.contains(e.target)) {
                const id = container.id.replace('container-', '');
                const menu = document.getElementById('menu-' + id);
                if(menu) menu.classList.add('hidden');
                const arrow = document.getElementById('arrow-' + id);
                if(arrow) arrow.classList.remove('rotate-180');
            }
        });
    });
    </script>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6">Waktu</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Kelas / Mapel</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Guru</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Materi / Catatan</th>
                        <th class="px-3 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">Kehadiran</th>
                        <th class="px-3 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($journals)): ?>
                        <tr>
                            <td colspan="6" class="py-10 text-center text-sm text-slate-500">Belum ada jurnal kelas pada tanggal ini.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($journals as $j): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6 align-top">
                                <div class="font-medium text-slate-900"><?php echo date('d M Y', strtotime($j['date'])); ?></div>
                                <div class="text-xs text-slate-500 mt-1"><?php echo date('H:i', strtotime($j['start_time'])); ?> - <?php echo date('H:i', strtotime($j['end_time'])); ?></div>
                            </td>
                            <td class="px-3 py-4 text-sm align-top">
                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($j['class_name']); ?></div>
                                <div class="text-cyan-600 mt-1"><?php echo htmlspecialchars($j['subject_name']); ?></div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 align-top">
                                <?php echo htmlspecialchars($j['teacher_name']); ?>
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-600 align-top max-w-xs">
                                <?php if ($j['topic']): ?>
                                    <div class="font-medium text-slate-800 mb-1">Materi: <?php echo htmlspecialchars($j['topic']); ?></div>
                                <?php endif; ?>
                                <?php if ($j['notes']): ?>
                                    <div class="italic text-slate-500 text-xs">"<?php echo htmlspecialchars($j['notes']); ?>"</div>
                                <?php endif; ?>
                                <?php if (!$j['topic'] && !$j['notes']) echo '<span class="text-slate-400">-</span>'; ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-center align-top">
                                <span class="inline-flex items-center rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                    <?php echo $j['total_attendance']; ?> Siswa
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-right align-top">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" 
                                        onclick="openViewModal({
                                            date: '<?php echo date('d M Y', strtotime($j['date'])); ?>',
                                            time: '<?php echo date('H:i', strtotime($j['start_time'])) . ' - ' . date('H:i', strtotime($j['end_time'])); ?>',
                                            class: '<?php echo htmlspecialchars(addslashes($j['class_name']), ENT_QUOTES); ?>',
                                            subject: '<?php echo htmlspecialchars(addslashes($j['subject_name']), ENT_QUOTES); ?>',
                                            teacher: '<?php echo htmlspecialchars(addslashes($j['teacher_name']), ENT_QUOTES); ?>',
                                            topic: '<?php echo htmlspecialchars(addslashes($j['topic'] ?? '-'), ENT_QUOTES); ?>',
                                            notes: '<?php echo htmlspecialchars(addslashes(preg_replace('/\r|\n/', ' ', $j['notes'] ?? '-')), ENT_QUOTES); ?>',
                                            present: <?php echo $j['count_present']; ?>,
                                            absent: <?php echo $j['count_absent']; ?>,
                                            sick: <?php echo $j['count_sick']; ?>,
                                            permit: <?php echo $j['count_permit']; ?>,
                                            late: <?php echo $j['count_late']; ?>
                                        })"
                                        class="inline-flex items-center p-1.5 text-cyan-600 hover:text-cyan-900 bg-cyan-50 hover:bg-cyan-100 rounded-lg transition-colors" title="Lihat Jurnal">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <a href="print.php?id=<?php echo $j['id']; ?>" target="_blank"
                                        class="inline-flex items-center p-1.5 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors" title="Cetak/Print">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closeViewModal()"></div>

        <!-- Modal panel -->
        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                        <h3 class="text-lg font-semibold leading-6 text-slate-900" id="modal-title">Detail Jurnal Kelas</h3>
                        <div class="mt-4 space-y-4">
                            <!-- Info Grid -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Tanggal & Waktu</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-datetime"></p>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Kelas / Mapel</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-class-subject"></p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Guru</p>
                                    <p class="mt-1 font-medium text-slate-900" id="v-teacher"></p>
                                </div>
                            </div>
                            
                            <hr class="border-slate-100">

                            <!-- Content -->
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Materi</p>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-700" id="v-topic"></div>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1">Catatan Tambahan</p>
                                <div class="bg-slate-50 rounded-lg p-3 text-sm text-slate-700 italic" id="v-notes"></div>
                            </div>
                            
                            <hr class="border-slate-100">

                            <!-- Attendance Stats -->
                            <div>
                                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Rekap Kehadiran Siswa</p>
                                <div class="grid grid-cols-5 gap-2 text-center text-xs">
                                    <div class="bg-green-50 rounded-lg p-2 border border-green-100">
                                        <div class="font-bold text-green-700 text-lg" id="v-present">0</div>
                                        <div class="text-green-600 mt-0.5">Hadir</div>
                                    </div>
                                    <div class="bg-red-50 rounded-lg p-2 border border-red-100">
                                        <div class="font-bold text-red-700 text-lg" id="v-absent">0</div>
                                        <div class="text-red-600 mt-0.5">Alpa</div>
                                    </div>
                                    <div class="bg-yellow-50 rounded-lg p-2 border border-yellow-100">
                                        <div class="font-bold text-yellow-700 text-lg" id="v-sick">0</div>
                                        <div class="text-yellow-600 mt-0.5">Sakit</div>
                                    </div>
                                    <div class="bg-blue-50 rounded-lg p-2 border border-blue-100">
                                        <div class="font-bold text-blue-700 text-lg" id="v-permit">0</div>
                                        <div class="text-blue-600 mt-0.5">Izin</div>
                                    </div>
                                    <div class="bg-orange-50 rounded-lg p-2 border border-orange-100">
                                        <div class="font-bold text-orange-700 text-lg" id="v-late">0</div>
                                        <div class="text-orange-600 mt-0.5">Telat</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <button type="button" onclick="closeViewModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openViewModal(data) {
        document.getElementById('v-datetime').innerText = data.date + ' (' + data.time + ')';
        document.getElementById('v-class-subject').innerText = data.class + ' - ' + data.subject;
        document.getElementById('v-teacher').innerText = data.teacher;
        document.getElementById('v-topic').innerText = data.topic;
        document.getElementById('v-notes').innerText = data.notes;
        
        document.getElementById('v-present').innerText = data.present;
        document.getElementById('v-absent').innerText = data.absent;
        document.getElementById('v-sick').innerText = data.sick;
        document.getElementById('v-permit').innerText = data.permit;
        document.getElementById('v-late').innerText = data.late;
        
        const modal = document.getElementById('viewModal');
        modal.classList.remove('hidden');
    }

    function closeViewModal() {
        const modal = document.getElementById('viewModal');
        modal.classList.add('hidden');
    }
</script>

<?php include '../layouts/footer.php'; ?>
