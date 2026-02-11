<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Kenaikan Kelas Massal";

$db = new Database();
$conn = $db->getConnection();

// 1. Fetch Units and Classes for Dropdowns
$units = $conn->query("SELECT id, name FROM education_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all classes first (could filter locally with JS or reload page)
$classes = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Academic Years from DB
$academic_years = $conn->query("SELECT id, name, semester FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Source Filter Parameters ---
// To load the list of students to promote
$source_unit_id = $_GET['source_unit_id'] ?? '';
$source_class_id = $_GET['source_class_id'] ?? '';
$source_year_id = $_GET['source_year_id'] ?? '';

// --- Filtered Classes for Source Dropdown ---
$source_classes = [];
if ($source_unit_id) {
    foreach ($classes as $c) {
        if ($c['education_unit_id'] == $source_unit_id)
            $source_classes[] = $c;
    }
} else {
    $source_classes = $classes;
}

// --- Fetch Students if Source Class & Year Selected ---
$students = [];
if ($source_class_id && $source_year_id) {
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk,
            s.status
        FROM students s
        JOIN student_class_history sch ON s.id = sch.student_id
        WHERE sch.class_id = :class_id 
          AND sch.academic_year_id = :year_id
          AND s.status = 'Aktif'
        ORDER BY s.nama_siswa ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':class_id' => $source_class_id, ':year_id' => $source_year_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <a href="<?php url('views/students/index.php'); ?>"
                        class="ml-1 text-slate-500 hover:text-slate-800">Manajemen Siswa</a>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 font-medium text-slate-800">Kenaikan Kelas</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Kenaikan Kelas Massal
            </h2>
            <p class="mt-2 text-sm text-slate-500">Promosikan siswa dari satu kelas ke kelas berikutnya untuk tahun
                ajaran baru.</p>
        </div>
    </div>

    <!-- Alert Messages -->

    <!-- Step 1: Source Selection Form -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-8">
        <h3 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">1. Pilih Kelas Asal (Sumber)</h3>
        <form id="source-form" method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">

            <!-- Custom Source Unit Dropdown -->
            <div class="relative group" id="source-unit-container">
                <label class="block text-sm font-medium text-slate-700 mb-1">Unit Pendidikan</label>
                <input type="hidden" name="source_unit_id" id="source-unit-input"
                    value="<?php echo $source_unit_id; ?>">
                <button type="button" onclick="toggleDropdown('source-unit')"
                    class="inline-flex items-center justify-between w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                    <span id="source-unit-text" class="truncate">
                        <?php
                        $unitLabel = "Semua Unit";
                        foreach ($units as $u) {
                            if ($u['id'] == $source_unit_id) {
                                $unitLabel = $u['name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($unitLabel);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="source-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="source-unit-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('source-unit', '', 'Semua Unit')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            Semua Unit
                        </li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('source-unit', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($source_unit_id == $u['id']) ? 'bg-cyan-50 text-cyan-700' : ''; ?>">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Source Class Dropdown -->
            <div class="relative group" id="source-class-container">
                <label class="block text-sm font-medium text-slate-700 mb-1">Kelas Asal</label>
                <input type="hidden" name="source_class_id" id="source-class-input"
                    value="<?php echo $source_class_id; ?>">
                <button type="button" onclick="toggleDropdown('source-class')"
                    class="inline-flex items-center justify-between w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                    <span id="source-class-text" class="truncate">
                        <?php
                        $classLabel = "-- Pilih Kelas --";
                        foreach ($source_classes as $c) {
                            if ($c['id'] == $source_class_id) {
                                $classLabel = $c['name'];
                                break;
                            }
                        }
                        echo htmlspecialchars($classLabel);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="source-class-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="source-class-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('source-class', '', '-- Pilih Kelas --')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            -- Pilih Kelas --
                        </li>
                        <?php foreach ($source_classes as $c): ?>
                            <li onclick="selectFilterOption('source-class', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['name']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($source_class_id == $c['id']) ? 'bg-cyan-50 text-cyan-700' : ''; ?>">
                                <?php echo htmlspecialchars($c['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Source Year Dropdown -->
            <div class="relative group" id="source-year-container">
                <label class="block text-sm font-medium text-slate-700 mb-1">Tahun Ajaran Asal</label>
                <input type="hidden" name="source_year_id" id="source-year-input"
                    value="<?php echo $source_year_id; ?>">
                <button type="button" onclick="toggleDropdown('source-year')"
                    class="inline-flex items-center justify-between w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                    <span id="source-year-text" class="truncate">
                        <?php
                        if (!empty($academic_years)) {
                            // First, check if there's a match
                            foreach ($academic_years as $y) {
                                if ($source_year_id == $y['id']) {
                                    $yearLabel = $y['name'] . ' - ' . $y['semester'];
                                    break;
                                }
                            }
                        }
                        echo htmlspecialchars($yearLabel);
                        ?>
                    </span>
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="source-year-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="source-year-menu"
                    class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('source-year', '', '-- Tahun Ajaran --')"
                            class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                            -- Tahun Ajaran --
                        </li>
                        <?php foreach ($academic_years as $y): ?>
                            <li onclick="selectFilterOption('source-year', '<?php echo $y['id']; ?>', '<?php echo htmlspecialchars(addslashes($y['name'] . ' - ' . $y['semester']), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($source_year_id == $y['id']) ? 'bg-cyan-50 text-cyan-700' : ''; ?>">
                                <?php echo htmlspecialchars($y['name'] . ' - ' . $y['semester']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-800 transition-colors h-10 items-center">
                    Load Siswa
                </button>
            </div>
        </form>
    </div>

    <?php if ($source_class_id && $source_year_id): ?>
        <form id="target-form" method="POST" action="<?php url('logic/students/promotion_process.php'); ?>">
            <!-- Pass student_ids is done via checkboxes below -->

            <!-- Step 2: Target Selection -->
            <div class="bg-cyan-50 rounded-xl shadow-sm border border-cyan-100 p-6 mb-8">
                <h3 class="text-lg font-bold text-cyan-900 mb-4 border-b border-cyan-200 pb-2">2. Pilih Kelas Tujuan & Tahun
                    Baru</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Custom Target Class Dropdown -->
                    <div class="relative group" id="target-class-container">
                        <label class="block text-sm font-medium text-cyan-900 mb-1">Kelas Tujuan (Promosi Ke)</label>
                        <input type="hidden" name="target_class_id" id="target-class-input" value="">
                        <button type="button" onclick="toggleDropdown('target-class')"
                            class="inline-flex items-center justify-between w-full rounded-md border border-cyan-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                            <span id="target-class-text" class="truncate">
                                -- Pilih Kelas Tujuan --
                            </span>
                            <svg class="h-4 w-4 text-cyan-700 transition-transform duration-200" id="target-class-arrow"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="target-class-menu"
                            class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                            <ul class="py-1">
                                <li onclick="selectTargetOption('target-class', '', '-- Pilih Kelas Tujuan --')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                    -- Pilih Kelas Tujuan --
                                </li>
                                <?php foreach ($classes as $c): ?>
                                    <li onclick="selectTargetOption('target-class', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['name']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        <?php echo htmlspecialchars($c['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Custom Target Year Dropdown -->
                    <div class="relative group" id="target-year-container">
                        <label class="block text-sm font-medium text-cyan-900 mb-1">Tahun Ajaran Baru</label>
                        <input type="hidden" name="target_year_id" id="target-year-input" value="">
                        <button type="button" onclick="toggleDropdown('target-year')"
                            class="inline-flex items-center justify-between w-full rounded-md border border-cyan-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                            <span id="target-year-text" class="truncate">
                                -- Pilih Tahun Baru --
                            </span>
                            <svg class="h-4 w-4 text-cyan-700 transition-transform duration-200" id="target-year-arrow"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="target-year-menu"
                            class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                            <ul class="py-1">
                                <?php foreach ($academic_years as $y): ?>
                                    <li onclick="selectTargetOption('target-year', '<?php echo $y['id']; ?>', '<?php echo htmlspecialchars(addslashes($y['name'] . ' - ' . $y['semester']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        <?php echo htmlspecialchars($y['name'] . ' - ' . $y['semester']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Student Selection Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center bg-gray-50">
                    <h3 class="text-base font-bold text-slate-800">3. Pilih Siswa untuk Dipromosikan</h3>
                    <div class="text-sm text-slate-500">
                        Total Siswa Ditemukan: <span class="font-bold text-slate-900">
                            <?php echo count($students); ?>
                        </span>
                    </div>
                </div>

                <?php if (count($students) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                                        <input type="checkbox" id="select-all" checked
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Nama Siswa</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        NISN</th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status Saat Ini</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $s): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" name="student_ids[]" value="<?php echo $s['id']; ?>" checked
                                                class="student-checkbox h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($s['nama_siswa']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($s['nomor_induk']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <span
                                                class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">
                                                <?php echo htmlspecialchars($s['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-slate-200 flex justify-end">
                        <button type="submit"
                            class="inline-flex justify-center rounded-lg bg-cyan-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all transform active:scale-95">
                            Proses Kenaikan Kelas
                        </button>
                    </div>

                <?php else: ?>
                    <div class="p-12 text-center text-slate-500 italic">
                        Tidak ada siswa aktif ditemukan di kelas dan tahun ajaran ini.
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>

    // --- Filter Option Selection (Source Form) ---
    // Updates input and submits the Source Form to reload page
    function selectFilterOption(name, value, text) {
        document.getElementById(name + '-input').value = value;

        // Update Label
        document.getElementById(name + '-text').innerText = text;

        // Submit form
        document.getElementById('source-form').submit();
    }

    // --- Target Option Selection (Target Form) ---
    // Updates input ONLY (No submit)
    function selectTargetOption(name, value, text) {
        document.getElementById(name + '-input').value = value;
        document.getElementById(name + '-text').innerText = text;

        // Close dropdown
        toggleDropdown(name);
    }


    // Toggle All Checkboxes
    const selectAllInfo = document.getElementById('select-all');
    if (selectAllInfo) {
        selectAllInfo.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    }
</script>

<?php include '../layouts/footer.php'; ?>