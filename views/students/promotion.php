<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Kenaikan Kelas Massal";

$db = new Database();
$conn = $db->getConnection();

// 1. Fetch Units and Classes for Dropdowns
$units = $conn->query("SELECT id, name FROM education_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all classes first (could filter locally with JS or reload page)
$classes = $conn->query("SELECT id, name, education_unit_id, is_active FROM grade_levels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Academic Years from DB
$academic_years = $conn->query("SELECT id, name, semester FROM academic_years ORDER BY start_date DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Source Filter Parameters ---
// To load the list of students to promote
$source_unit_id = $_GET['source_unit_id'] ?? '';
$source_class_id = $_GET['source_class_id'] ?? '';
$source_year_id = $_GET['source_year_id'] ?? '';
$search = trim($_GET['search'] ?? '');

// Reusable Filter Query for redirect
$current_filters = $_GET;
unset($current_filters['success'], $current_filters['error']);
$filter_qs = http_build_query($current_filters);

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

// --- Fetch Students if Filtered or Searched ---
$students = [];
if ($source_class_id || $source_unit_id || $source_year_id || !empty($search)) {
    // Get active academic year id
    $active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $join_year_id = $source_year_id ?: $active_year_id;

    $where_clauses = ["s.status = 'Aktif'"];
    $params = [':join_year_id' => $join_year_id];
    
    if ($source_class_id) {
        $where_clauses[] = "sch.class_id = :class_id";
        $params[':class_id'] = $source_class_id;
    }
    if ($source_unit_id) {
        $where_clauses[] = "gl.education_unit_id = :unit_id";
        $params[':unit_id'] = $source_unit_id;
    }
    if (!empty($search)) {
        $where_clauses[] = "s.nama_siswa LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }
    
    $where_sql = implode(' AND ', $where_clauses);
    
    $sql = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk,
            s.status,
            gl.id as class_id,
            gl.name as class_name,
            ay.id as academic_year_id,
            ay.name as academic_year_name,
            ay.semester as academic_year_semester
        FROM students s
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :join_year_id
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        LEFT JOIN academic_years ay ON sch.academic_year_id = ay.id
        WHERE $where_sql
        ORDER BY s.nama_siswa ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
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
        <form id="source-form" method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">

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
                            <li onclick="selectFilterOption('source-class', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['name'] . ($c['is_active'] ? '' : ' (Non-aktif)')), ENT_QUOTES); ?>')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($source_class_id == $c['id']) ? 'bg-cyan-50 text-cyan-700' : ''; ?>">
                                <?php echo htmlspecialchars($c['name'] . ($c['is_active'] ? '' : ' (Non-aktif)')); ?>
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
                        $yearLabel = "-- Tahun Ajaran --";
                        if (!empty($academic_years)) {
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

            <!-- Search Input -->
            <div class="relative">
                <label for="search-input" class="block text-sm font-medium text-slate-700 mb-1">Cari Nama Siswa</label>
                <input type="text" name="search" id="search-input" value="<?php echo htmlspecialchars($search); ?>"
                    class="block w-full rounded-md border-slate-300 px-3 py-2 text-sm text-slate-600 bg-white placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 shadow-sm border"
                    placeholder="Nama siswa...">
            </div>

            <div>
                <button type="submit"
                    class="w-full inline-flex justify-center rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-800 transition-colors h-10 items-center">
                    Load Siswa
                </button>
            </div>
        </form>
    </div>

    <?php if ($source_class_id || $source_unit_id || $source_year_id || !empty($search)): ?>
        <form id="target-form" method="POST" action="<?php url('logic/students/promotion_process.php'); ?>">
            <!-- Pass student_ids is done via checkboxes below -->
            <input type="hidden" name="source_class_id" value="<?php echo htmlspecialchars($source_class_id); ?>">
            <input type="hidden" name="source_year_id" value="<?php echo htmlspecialchars($source_year_id); ?>">
            <input type="hidden" name="redirect_params" value="<?php echo htmlspecialchars($filter_qs); ?>">

            <!-- Step 2: Target Selection & Action -->
            <div class="bg-cyan-50 rounded-xl shadow-sm border border-cyan-100 p-6 mb-8">
                <h3 id="step-2-title" class="text-lg font-bold text-cyan-900 mb-4 border-b border-cyan-200 pb-2">2. Pilih Kelas Tujuan & Tahun Baru</h3>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-cyan-900 mb-2">Pilihan Tindakan</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center cursor-pointer bg-white px-4 py-2 rounded-lg border border-cyan-200 shadow-sm transition-all hover:bg-slate-50">
                            <input type="radio" name="action_type" value="promote" checked onchange="handleActionChange(this.value)" class="h-4 w-4 text-cyan-600 focus:ring-cyan-500 border-gray-300">
                            <span class="ml-2 text-sm font-medium text-slate-800">Kenaikan Kelas</span>
                        </label>
                        <label class="inline-flex items-center cursor-pointer bg-white px-4 py-2 rounded-lg border border-cyan-200 shadow-sm transition-all hover:bg-slate-50">
                            <input type="radio" name="action_type" value="graduate" onchange="handleActionChange(this.value)" class="h-4 w-4 text-cyan-600 focus:ring-cyan-500 border-gray-300">
                            <span class="ml-2 text-sm font-medium text-slate-800">Kelulusan Siswa</span>
                        </label>
                    </div>
                </div>

                <div id="target-selection-fields" class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Custom Target Unit Dropdown -->
                    <div class="relative group" id="target-unit-container">
                        <label class="block text-sm font-medium text-cyan-900 mb-1">Unit Tujuan</label>
                        <input type="hidden" id="target-unit-input" value="">
                        <button type="button" onclick="toggleDropdown('target-unit')"
                            class="inline-flex items-center justify-between w-full rounded-md border border-cyan-300 px-3 py-2 text-sm font-medium text-slate-600 bg-white hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm">
                            <span id="target-unit-text" class="truncate">
                                Pilih Unit Tujuan
                            </span>
                            <svg class="h-4 w-4 text-cyan-700 transition-transform duration-200" id="target-unit-arrow"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="target-unit-menu"
                            class="hidden absolute top-full left-0 mt-1 w-full origin-top-left rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                            <ul class="py-1">
                                <li onclick="selectTargetUnit('', 'Semua Unit Tujuan')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                    Pilih Unit Tujuan
                                </li>
                                <?php foreach ($units as $u): ?>
                                    <li onclick="selectTargetUnit('<?php echo $u['id']; ?>', '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                        class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

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
                                    <?php if ($c['is_active'] == 0) continue; // Skip inactive classes for target class ?>
                                    <li onclick="selectTargetOption('target-class', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['name']), ENT_QUOTES); ?>')"
                                        data-unit="<?php echo $c['education_unit_id']; ?>"
                                        class="target-class-option cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
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
                    <h3 id="step-3-title" class="text-base font-bold text-slate-800">3. Pilih Siswa untuk Dipromosikan</h3>
                    <div class="text-sm text-slate-500 flex items-center gap-4">
                        <span>Total Siswa Ditemukan: <span class="font-bold text-slate-900"><?php echo count($students); ?></span></span>
                        <span class="border-l border-slate-300 pl-4 font-semibold text-slate-600">Siswa Terpilih: <span id="selected-count-display" class="font-bold text-cyan-600">0</span></span>
                        <button type="button" onclick="clearAllSelections()" class="text-xs text-red-500 hover:text-red-700 underline font-medium">Clear All</button>
                    </div>
                </div>

                <?php if (count($students) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                    <th scope="col" class="px-6 py-3 text-left w-10">
                                        <input type="checkbox" id="select-all"
                                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left min-w-[200px]">
                                        Nama Siswa</th>
                                    <th scope="col" class="px-6 py-3 text-left min-w-[120px]">
                                        NISN</th>
                                    <th scope="col" class="px-6 py-3 text-left min-w-[120px]">
                                        Kelas Asal</th>
                                    <th scope="col" class="px-6 py-3 text-left min-w-[150px]">
                                        Tahun Ajaran Asal</th>
                                    <th scope="col" class="px-6 py-3 text-left min-w-[100px]">
                                        Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $s): ?>
                                    <tr class="student-row hover:bg-slate-50 cursor-pointer">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <input type="checkbox" value="<?php echo $s['id']; ?>"
                                                data-class-id="<?php echo $s['class_id'] ?? ''; ?>"
                                                data-year-id="<?php echo $s['academic_year_id'] ?? ''; ?>"
                                                class="student-checkbox h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer">
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($s['nama_siswa']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($s['nomor_induk']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($s['class_name'] ?? 'Belum ada kelas'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($s['academic_year_name'] ? $s['academic_year_name'] . ' - ' . $s['academic_year_semester'] : '-'); ?>
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
                        <button type="submit" id="submit-btn"
                            class="inline-flex justify-center rounded-lg bg-cyan-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-all transform active:scale-95">
                            Proses Kenaikan Kelas
                        </button>
                    </div>

                <?php else: ?>
                    <div class="p-12 text-center text-slate-500 italic">
                        Tidak ada siswa aktif ditemukan yang cocok dengan kriteria filter/pencarian Anda.
                    </div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Custom Graduation Confirmation Modal -->
<div id="graduateConfirmModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="graduate-modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div id="graduateModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>

    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <!-- Modal Panel -->
        <div id="graduateModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-cyan-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="graduate-modal-title">Konfirmasi Kelulusan Siswa</h3>
                        <div class="mt-2 text-sm text-slate-500" id="graduate-modal-message">
                            Apakah Anda yakin ingin meluluskan siswa-siswa yang dipilih? Status siswa akan diubah menjadi "Lulus".
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <button type="button" id="confirmGraduateBtn" class="inline-flex w-full justify-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 sm:w-auto transition-all transform active:scale-95">
                    Ya, Luluskan
                </button>
                <button type="button" onclick="closeGraduateModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<script>

    // --- Selections Management (sessionStorage) ---
    function getSelections() {
        try {
            return JSON.parse(sessionStorage.getItem('promotion_selections') || '{}');
        } catch(e) {
            return {};
        }
    }

    function saveSelections(selections) {
        sessionStorage.setItem('promotion_selections', JSON.stringify(selections));
    }

    function updateSelectedCountDisplay() {
        const selections = getSelections();
        const count = Object.keys(selections).length;
        const el = document.getElementById('selected-count-display');
        if (el) {
            el.textContent = count;
        }
        
        // Sync the select-all checkbox state
        const checkboxes = document.querySelectorAll('.student-checkbox');
        const selectAllInfo = document.getElementById('select-all');
        if (selectAllInfo && checkboxes.length > 0) {
            let allChecked = true;
            checkboxes.forEach(cb => {
                if (!cb.checked) allChecked = false;
            });
            selectAllInfo.checked = allChecked;
        }
    }

    function clearAllSelections() {
        saveSelections({});
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
        updateSelectedCountDisplay();
    }

    // Initialize selections on page load
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        // Clear selections if it was a success redirect
        if (urlParams.has('success')) {
            sessionStorage.removeItem('promotion_selections');
        }

        const sourceForm = document.getElementById('source-form');
        if (sourceForm) {
            sourceForm.addEventListener('submit', function() {
                sessionStorage.removeItem('promotion_selections');
            });
        }

        const selections = getSelections();
        const checkboxes = document.querySelectorAll('.student-checkbox');

        // Sync checkboxes with saved selections
        checkboxes.forEach(cb => {
            cb.checked = !!selections[cb.value];
        });

        updateSelectedCountDisplay();

        // Listen for individual checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('student-checkbox')) {
                const cb = e.target;
                const studentId = cb.value;
                const currentSelections = getSelections();
                if (cb.checked) {
                    currentSelections[studentId] = {
                        class_id: cb.dataset.classId || '',
                        year_id: cb.dataset.yearId || ''
                    };
                } else {
                    delete currentSelections[studentId];
                }
                saveSelections(currentSelections);
                updateSelectedCountDisplay();
            }
        });

        // Listen for row clicks to toggle checkbox
        document.querySelectorAll('.student-row').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.classList.contains('student-checkbox') || e.target.type === 'checkbox') {
                    return;
                }
                const cb = this.querySelector('.student-checkbox');
                if (cb) {
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });
    });

    // --- Filter Option Selection (Source Form) ---
    // Updates input and submits the Source Form to reload page
    function selectFilterOption(name, value, text) {
        sessionStorage.removeItem('promotion_selections');
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

    // --- Target Unit Dropdown Selection ---
    // Resets target class and filters class options by unit
    function selectTargetUnit(value, text) {
        document.getElementById('target-unit-input').value = value;
        document.getElementById('target-unit-text').innerText = text;
        
        // Close dropdown
        toggleDropdown('target-unit');
        
        // Reset target class selection
        document.getElementById('target-class-input').value = '';
        document.getElementById('target-class-text').innerText = '-- Pilih Kelas Tujuan --';

        // Filter the target class options in the dropdown
        const options = document.querySelectorAll('.target-class-option');
        options.forEach(opt => {
            if (!value || opt.dataset.unit == value) {
                opt.style.display = 'block';
            } else {
                opt.style.display = 'none';
            }
        });
    }

    // Handle change of Action (Kenaikan Kelas vs Kelulusan)
    function handleActionChange(action) {
        const targetFields = document.getElementById('target-selection-fields');
        const submitBtn = document.getElementById('submit-btn');
        const step2Title = document.getElementById('step-2-title');
        const step3Title = document.getElementById('step-3-title');
        
        if (action === 'promote') {
            targetFields.classList.remove('hidden');
            submitBtn.innerText = 'Proses Kenaikan Kelas';
            step2Title.innerText = '2. Pilih Kelas Tujuan & Tahun Baru';
            step3Title.innerText = '3. Pilih Siswa untuk Dipromosikan';
        } else {
            targetFields.classList.add('hidden');
            submitBtn.innerText = 'Proses Kelulusan Siswa';
            step2Title.innerText = '2. Pilihan Kelulusan';
            step3Title.innerText = '3. Pilih Siswa untuk Diluluskan';
        }
    }

    // Toggle All Checkboxes
    const selectAllInfo = document.getElementById('select-all');
    if (selectAllInfo) {
        selectAllInfo.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.student-checkbox');
            const selections = getSelections();
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                const studentId = cb.value;
                if (this.checked) {
                    selections[studentId] = {
                        class_id: cb.dataset.classId || '',
                        year_id: cb.dataset.yearId || ''
                    };
                } else {
                    delete selections[studentId];
                }
            });
            saveSelections(selections);
            updateSelectedCountDisplay();
        });
    }

    // Modal control functions
    function openGraduateModal(studentCount) {
        document.getElementById('graduate-modal-message').innerText = `Apakah Anda yakin ingin meluluskan ${studentCount} siswa yang dipilih? Tindakan ini akan mengubah status siswa menjadi "Lulus".`;
        
        const modal = document.getElementById('graduateConfirmModal');
        const backdrop = document.getElementById('graduateModalBackdrop');
        const panel = document.getElementById('graduateModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    // Helper to generate hidden fields right before submitting
    function prepareFormSubmission() {
        // Clear any existing hidden inputs we created
        document.querySelectorAll('.dynamic-student-id').forEach(el => el.remove());

        const selections = getSelections();
        const selectedIds = Object.keys(selections);

        selectedIds.forEach(id => {
            const sIdInput = document.createElement('input');
            sIdInput.type = 'hidden';
            sIdInput.name = 'student_ids[]';
            sIdInput.value = id;
            sIdInput.className = 'dynamic-student-id';
            targetForm.appendChild(sIdInput);

            const classIdInput = document.createElement('input');
            classIdInput.type = 'hidden';
            classIdInput.name = `student_source_class[${id}]`;
            classIdInput.value = selections[id].class_id || '';
            classIdInput.className = 'dynamic-student-id';
            targetForm.appendChild(classIdInput);

            const yearIdInput = document.createElement('input');
            yearIdInput.type = 'hidden';
            yearIdInput.name = `student_source_year[${id}]`;
            yearIdInput.value = selections[id].year_id || '';
            yearIdInput.className = 'dynamic-student-id';
            targetForm.appendChild(yearIdInput);
        });
    }

    function closeGraduateModal() {
        const modal = document.getElementById('graduateConfirmModal');
        const backdrop = document.getElementById('graduateModalBackdrop');
        const panel = document.getElementById('graduateModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Add form submit validation and confirmation interception
    let isConfirmed = false;
    const targetForm = document.getElementById('target-form');
    if (targetForm) {
        targetForm.addEventListener('submit', function (e) {
            const action = document.querySelector('input[name="action_type"]:checked').value;
            const selections = getSelections();
            const selectedIds = Object.keys(selections);
            
            if (selectedIds.length === 0) {
                alert('Pilih minimal satu siswa.');
                e.preventDefault();
                return false;
            }

            if (action === 'promote') {
                const targetClass = document.getElementById('target-class-input').value;
                const targetYear = document.getElementById('target-year-input').value;
                if (!targetClass) {
                    alert('Pilih kelas tujuan.');
                    e.preventDefault();
                    return false;
                }
                if (!targetYear) {
                    alert('Pilih tahun ajaran baru.');
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'graduate') {
                if (!isConfirmed) {
                    e.preventDefault();
                    openGraduateModal(selectedIds.length);
                    return false;
                }
            }

            prepareFormSubmission();
        });
    }

    // Listen to confirm button in Modal
    const confirmBtn = document.getElementById('confirmGraduateBtn');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function() {
            isConfirmed = true;
            closeGraduateModal();
            if (targetForm) {
                prepareFormSubmission();
                targetForm.submit();
            }
        });
    }
</script>

<?php include '../layouts/footer.php'; ?>