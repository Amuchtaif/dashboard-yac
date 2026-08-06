<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$schedule = null;
$page_title = $id ? "Edit Jadwal Pelajaran" : "Tambah Jadwal Pelajaran";

// Capture filters
$filter_params = $_GET;
unset($filter_params['id'], $filter_params['success'], $filter_params['error']);
$query_string = http_build_query($filter_params);

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM class_schedules WHERE id = ?");
    $stmt->execute([$id]);
    $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch Master Data
$academic_years = $conn->query("SELECT id, name, semester, is_active FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);
$education_units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$current_sched_class_id = $schedule ? (int)$schedule['grade_level_id'] : 0;
$grade_levels = $conn->query("SELECT id, name, education_unit_id, is_active FROM grade_levels WHERE is_active = 1 OR id = $current_sched_class_id ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$employees = $conn->query("SELECT id, full_name FROM employees WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("SELECT id, name, code FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$lesson_periods = $conn->query("SELECT id, period_number, start_time, end_time, education_unit_id FROM lesson_periods ORDER BY education_unit_id ASC, period_number ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get current education_unit_id for edit mode
$current_unit_id = '';
if ($schedule) {
    foreach ($grade_levels as $gl) {
        if ($gl['id'] == $schedule['grade_level_id']) {
            $current_unit_id = $gl['education_unit_id'];
            break;
        }
    }
}

$selected_lps = [];
if ($schedule) {
    $start_lp = $schedule['lesson_period_id'];
    $end_lp = $schedule['end_lesson_period_id'] ?: $start_lp;
    
    // Find period_numbers for start and end
    $start_num = -1;
    $end_num = -1;
    foreach ($lesson_periods as $lp) {
        if ($lp['id'] == $start_lp) $start_num = $lp['period_number'];
        if ($lp['id'] == $end_lp) $end_num = $lp['period_number'];
    }
    
    if ($start_num != -1 && $end_num != -1) {
        foreach ($lesson_periods as $lp) {
            // Find all periods within same unit and between period numbers
            if ($lp['education_unit_id'] == $current_unit_id && $lp['period_number'] >= min($start_num, $end_num) && $lp['period_number'] <= max($start_num, $end_num)) {
                $selected_lps[] = $lp['id'];
            }
        }
    }
}

$days = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
];

include '../layouts/header.php';
?>

<div class="w-full pb-10">
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl"><?php echo $page_title; ?></h2>
            <p class="mt-1 text-sm text-slate-500">Definisikan guru, kelas, mata pelajaran, dan waktu.</p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="index.php?<?php echo $query_string; ?>"
                class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-lg shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors">Kembali</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm relative">
        <form action="../../logic/class_schedules/<?php echo $id ? 'update.php' : 'store.php'; ?>" method="POST"
            class="p-6 sm:p-8 space-y-6">
            <?php if ($id): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
            <input type="hidden" name="redirect_params" value="<?php echo htmlspecialchars($query_string); ?>">

            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-2">

                <!-- Tahun Akademik (Custom Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="academic_year_id" class="block text-sm font-semibold text-slate-700 mb-1">Tahun Akademik
                        <span class="text-red-500">*</span></label>
                    <div class="relative" id="container-academic_year_id">
                        <input type="hidden" name="academic_year_id" id="input-academic_year_id" value="<?php
                        if ($schedule) {
                            echo $schedule['academic_year_id'];
                        } else {
                            foreach ($academic_years as $ay) {
                                if ($ay['is_active'] == 1) {
                                    echo $ay['id'];
                                    break;
                                }
                            }
                        }
                        ?>">
                        <button type="button" onclick="toggleFormDropdown('academic_year_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-academic_year_id" class="block truncate">
                                <?php
                                $ayName = "Pilih Tahun Akademik...";
                                $current_ay_id = $schedule ? $schedule['academic_year_id'] : null;
                                if (!$current_ay_id) {
                                    foreach ($academic_years as $ay) {
                                        if ($ay['is_active'] == 1) {
                                            $current_ay_id = $ay['id'];
                                            break;
                                        }
                                    }
                                }
                                foreach ($academic_years as $ay) {
                                    if ($ay['id'] == $current_ay_id) {
                                        $ayName = $ay['name'] . ' - ' . $ay['semester'] . ($ay['is_active'] == 1 ? ' (Aktif)' : '');
                                        break;
                                    }
                                }
                                echo htmlspecialchars($ayName);
                                ?>
                            </span>
                            <i id="arrow-academic_year_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-academic_year_id"
                            class="hidden absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <ul class="py-1">
                                <?php foreach ($academic_years as $ay): ?>
                                    <li onclick="selectFormOption('academic_year_id', '<?php echo $ay['id']; ?>', '<?php echo htmlspecialchars(addslashes($ay['name'] . ' - ' . $ay['semester'] . ($ay['is_active'] == 1 ? ' (Aktif)' : '')), ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        <?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester']); ?>
                                        <?php echo $ay['is_active'] == 1 ? '(Aktif)' : ''; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Jenjang (Education Unit) (Custom Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="education_unit_id" class="block text-sm font-semibold text-slate-700 mb-1">Jenjang <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="container-education_unit_id" style="z-index: 40;">
                        <input type="hidden" name="education_unit_id" id="input-education_unit_id"
                            value="<?php echo $current_unit_id; ?>">
                        <button type="button" onclick="toggleFormDropdown('education_unit_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-education_unit_id" class="block truncate">
                                <?php
                                $unitName = "Pilih Jenjang...";
                                if ($current_unit_id) {
                                    foreach ($education_units as $unit) {
                                        if ($unit['id'] == $current_unit_id) {
                                            $unitName = $unit['name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($unitName);
                                ?>
                            </span>
                            <i id="arrow-education_unit_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-education_unit_id"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <ul class="py-1">
                                <?php foreach ($education_units as $unit): ?>
                                    <li onclick="selectFormOption('education_unit_id', '<?php echo $unit['id']; ?>', '<?php echo htmlspecialchars(addslashes($unit['name']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        <?php echo htmlspecialchars($unit['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Kelas (Custom Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="grade_level_id" class="block text-sm font-semibold text-slate-700 mb-1">Kelas <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="container-grade_level_id" style="z-index: 39;">
                        <input type="hidden" name="grade_level_id" id="input-grade_level_id"
                            value="<?php echo $schedule ? $schedule['grade_level_id'] : ''; ?>">
                        <button type="button" onclick="toggleFormDropdown('grade_level_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-grade_level_id" class="block truncate">
                                <?php
                                $glName = "Pilih Kelas...";
                                if ($schedule) {
                                    foreach ($grade_levels as $gl) {
                                        if ($gl['id'] == $schedule['grade_level_id']) {
                                            $glName = $gl['name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($glName);
                                ?>
                            </span>
                            <i id="arrow-grade_level_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-grade_level_id"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <div class="sticky top-0 z-50 bg-white px-2 py-1.5 border-b border-slate-100">
                                <input type="text" id="search-grade_level_id"
                                    onkeyup="filterDropdownSearch('grade_level_id')" placeholder="Cari kelas..."
                                    class="block w-full rounded-md border-slate-200 px-3 py-1.5 text-xs focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border">
                            </div>
                            <ul class="py-1" id="list-grade_level_id">
                                <li onclick="selectFormOption('grade_level_id', '', 'Pilih Kelas...')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-500 hover:bg-slate-50">
                                    Pilih Kelas...</li>
                                <?php foreach ($grade_levels as $gl): ?>
                                    <li onclick="selectFormOption('grade_level_id', '<?php echo $gl['id']; ?>', '<?php echo htmlspecialchars(addslashes($gl['name']), ENT_QUOTES); ?>')"
                                        data-unit="<?php echo $gl['education_unit_id']; ?>"
                                        class="grade-option cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700"
                                        style="<?php echo ($current_unit_id && $gl['education_unit_id'] != $current_unit_id) ? 'display: none;' : ''; ?>">
                                        <?php echo htmlspecialchars($gl['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Guru (Karyawan) (Custom Searchable Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="employee_id" class="block text-sm font-semibold text-slate-700 mb-1">Guru / Pengajar
                        <span class="text-red-500">*</span></label>
                    <div class="relative" id="container-employee_id" style="z-index: 38;">
                        <input type="hidden" name="employee_id" id="input-employee_id"
                            value="<?php echo $schedule ? $schedule['employee_id'] : ''; ?>">
                        <button type="button" onclick="toggleFormDropdown('employee_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-employee_id" class="block truncate">
                                <?php
                                $empName = "Pilih Guru...";
                                if ($schedule) {
                                    foreach ($employees as $emp) {
                                        if ($emp['id'] == $schedule['employee_id']) {
                                            $empName = $emp['full_name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($empName);
                                ?>
                            </span>
                            <i id="arrow-employee_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-employee_id"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <div class="sticky top-0 z-50 bg-white px-2 py-1.5 border-b border-slate-100">
                                <input type="text" id="search-employee_id" onkeyup="filterDropdownSearch('employee_id')"
                                    placeholder="Cari guru..."
                                    class="block w-full rounded-md border-slate-200 px-3 py-1.5 text-xs focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border">
                            </div>
                            <ul class="py-1" id="list-employee_id">
                                <li onclick="selectFormOption('employee_id', '', 'Pilih Guru...')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-500 hover:bg-slate-50">
                                    Pilih Guru...</li>
                                <?php foreach ($employees as $emp): ?>
                                    <li onclick="selectFormOption('employee_id', '<?php echo $emp['id']; ?>', '<?php echo htmlspecialchars(addslashes($emp['full_name']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Mata Pelajaran (Custom Searchable Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="subject_id" class="block text-sm font-semibold text-slate-700 mb-1">Mata Pelajaran <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="container-subject_id" style="z-index: 37;">
                        <input type="hidden" name="subject_id" id="input-subject_id"
                            value="<?php echo $schedule ? $schedule['subject_id'] : ''; ?>">
                        <button type="button" onclick="toggleFormDropdown('subject_id')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-subject_id" class="block truncate">
                                <?php
                                $subName = "Pilih Mata Pelajaran...";
                                if ($schedule) {
                                    foreach ($subjects as $sub) {
                                        if ($sub['id'] == $schedule['subject_id']) {
                                            $subName = "[" . $sub['code'] . "] " . $sub['name'];
                                            break;
                                        }
                                    }
                                }
                                echo htmlspecialchars($subName);
                                ?>
                            </span>
                            <i id="arrow-subject_id" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-subject_id"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <div class="sticky top-0 z-50 bg-white px-2 py-1.5 border-b border-slate-100">
                                <input type="text" id="search-subject_id" onkeyup="filterDropdownSearch('subject_id')"
                                    placeholder="Cari mata pelajaran..."
                                    class="block w-full rounded-md border-slate-200 px-3 py-1.5 text-xs focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border">
                            </div>
                            <ul class="py-1" id="list-subject_id">
                                <li onclick="selectFormOption('subject_id', '', 'Pilih Mata Pelajaran...')"
                                    class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-500 hover:bg-slate-50">
                                    Pilih Mata Pelajaran...</li>
                                <?php foreach ($subjects as $sub): ?>
                                    <li onclick="selectFormOption('subject_id', '<?php echo $sub['id']; ?>', '<?php echo htmlspecialchars(addslashes('[' . $sub['code'] . '] ' . $sub['name']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        [<?php echo htmlspecialchars($sub['code']); ?>]
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Hari (Custom Dropdown) -->
                <div class="sm:col-span-1">
                    <label for="day" class="block text-sm font-semibold text-slate-700 mb-1">Hari <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="container-day" style="z-index: 36;">
                        <input type="hidden" name="day" id="input-day"
                            value="<?php echo $schedule ? $schedule['day'] : 'Monday'; ?>">
                        <button type="button" onclick="toggleFormDropdown('day')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-day" class="block truncate">
                                <?php
                                $currentDay = $schedule ? $schedule['day'] : 'Monday';
                                echo isset($days[$currentDay]) ? $days[$currentDay] : 'Senin';
                                ?>
                            </span>
                            <i id="arrow-day" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-day"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                            <div class="sticky top-0 z-50 bg-white px-2 py-1.5 border-b border-slate-100">
                                <input type="text" id="search-day" onkeyup="filterDropdownSearch('day')"
                                    placeholder="Cari hari..."
                                    class="block w-full rounded-md border-slate-200 px-3 py-1.5 text-xs focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border">
                            </div>
                            <ul class="py-1" id="list-day">
                                <?php foreach ($days as $val => $label): ?>
                                    <li onclick="selectFormOption('day', '<?php echo $val; ?>', '<?php echo htmlspecialchars($label, ENT_QUOTES); ?>')"
                                        class="cursor-pointer select-none py-2 pl-3 pr-9 text-slate-900 hover:bg-cyan-50 hover:text-cyan-700">
                                        <?php echo $label; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Jam Pelajaran (Checkboxes) -->
                <div class="sm:col-span-1">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Jam Pelajaran <span
                            class="text-red-500">*</span></label>
                    <div class="relative" id="container-lesson_periods" style="z-index: 35;">
                        <button type="button" onclick="toggleFormDropdown('lesson_periods')"
                            class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-all">
                            <span id="text-lesson_periods" class="block truncate">Pilih Jam...</span>
                            <i id="arrow-lesson_periods" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                        </button>
                        <div id="menu-lesson_periods"
                            class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm border border-slate-200">
                            <ul class="py-1" id="list-lesson_periods">
                                <?php foreach ($lesson_periods as $lp): ?>
                                    <li data-unit="<?php echo $lp['education_unit_id']; ?>"
                                        class="lp-option hover:bg-cyan-50 transition-colors"
                                        style="<?php echo ($current_unit_id && $lp['education_unit_id'] != $current_unit_id) ? 'display: none;' : ''; ?>">
                                        <label class="flex items-center cursor-pointer px-4 py-2.5 w-full">
                                            <input type="checkbox" name="lesson_period_ids[]" value="<?php echo $lp['id']; ?>" 
                                            class="rounded border-slate-300 text-cyan-600 shadow-sm focus:border-cyan-300 focus:ring focus:ring-cyan-200 focus:ring-opacity-50 lp-checkbox w-4 h-4 mr-3" 
                                            onchange="updateLessonPeriodText()" <?php echo (in_array($lp['id'], $selected_lps)) ? 'checked' : ''; ?>>
                                            <span class="text-slate-700 whitespace-nowrap">Jam ke-<?php echo $lp['period_number']; ?> <span class="text-slate-400 text-xs ml-1">(<?php echo date('H:i', strtotime($lp['start_time'])); ?> - <?php echo date('H:i', strtotime($lp['end_time'])); ?>)</span></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-6 flex justify-end gap-3 border-t border-slate-100">
                <button type="submit"
                    class="inline-flex items-center px-6 py-2.5 rounded-lg shadow-sm text-sm font-bold text-white bg-cyan-600 hover:bg-cyan-700 focus:ring-2 focus:ring-cyan-500 transition-all">
                    Simpan Jadwal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleFormDropdown(id) {
        const menu = document.getElementById('menu-' + id);
        const arrow = document.getElementById('arrow-' + id);
        const isHidden = menu.classList.contains('hidden');

        // Tutup semua yang lain
        document.querySelectorAll('[id^="menu-"]').forEach(m => m.classList.add('hidden'));
        document.querySelectorAll('[id^="arrow-"]').forEach(a => a.classList.remove('rotate-180'));

        if (isHidden) {
            menu.classList.remove('hidden');
            arrow.classList.add('rotate-180');
            // Cek jika ada search input, fokus ke sana
            const searchInput = document.getElementById('search-' + id);
            if (searchInput) searchInput.focus();
        }
    }

    function selectFormOption(id, value, text) {
        document.getElementById('input-' + id).value = value;
        document.getElementById('text-' + id).innerText = text;
        document.getElementById('menu-' + id).classList.add('hidden');
        document.getElementById('arrow-' + id).classList.remove('rotate-180');

        // Logic khusus untuk filter Jenjang -> Kelas
        if (id === 'education_unit_id') {
            const classInput = document.getElementById('input-grade_level_id');
            const classText = document.getElementById('text-grade_level_id');
            classInput.value = '';
            classText.innerText = 'Pilih Kelas...';
            
            const classOptions = document.querySelectorAll('.grade-option');
            const lpOptions = document.querySelectorAll('.lp-option');
            // Reset Jam Pelajaran Selection
            const lpCheckboxes = document.querySelectorAll('.lp-checkbox');
            lpCheckboxes.forEach(cb => cb.checked = false);
            updateLessonPeriodText();
            lpOptions.forEach(opt => {
                opt.style.display = (value === '' || opt.getAttribute('data-unit') === value) ? 'block' : 'none';
            });
            classOptions.forEach(opt => {
                opt.style.display = (value === '' || opt.getAttribute('data-unit') === value) ? 'block' : 'none';
            });
        }
    }

    function updateLessonPeriodText() {
        const checkboxes = document.querySelectorAll('.lp-checkbox:checked');
        const textEl = document.getElementById('text-lesson_periods');
        if (checkboxes.length === 0) {
            textEl.innerText = 'Pilih Jam...';
        } else {
            textEl.innerText = checkboxes.length + ' Jam Terpilih';
        }
    }
    document.addEventListener('DOMContentLoaded', updateLessonPeriodText);

    function filterDropdownSearch(id) {
        const input = document.getElementById('search-' + id);
        const filter = input.value.toLowerCase();
        const list = document.getElementById('list-' + id);
        const li = list.getElementsByTagName('li');
        const unitId = document.getElementById('input-education_unit_id').value;

        for (let i = 0; i < li.length; i++) {
            const txtValue = li[i].textContent || li[i].innerText;
            const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;

            if (id === 'grade_level_id' || id === 'lesson_periods') {
                const itemUnit = li[i].getAttribute('data-unit');
                const matchesUnit = (unitId === '' || itemUnit === unitId || !itemUnit);
                li[i].style.display = (matchesSearch && matchesUnit) ? "" : "none";
            } else {
                li[i].style.display = matchesSearch ? "" : "none";
            }
        }
    }

    // Klik di luar untuk menutup
    window.addEventListener('click', function (e) {
        document.querySelectorAll('[id^="container-"]').forEach(container => {
            if (!container.contains(e.target)) {
                const id = container.id.replace('container-', '');
                document.getElementById('menu-' + id).classList.add('hidden');
                document.getElementById('arrow-' + id).classList.remove('rotate-180');
            }
        });
    });
</script>

<?php include '../layouts/footer.php'; ?>