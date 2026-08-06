<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Manajemen Kelas";

$db = new Database();
$conn = $db->getConnection();

// Fetch Education Units for Filter
// Custom Order as requested previously
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'Idad Lughoh', 'MA', 'Mahad Aly') ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Filter Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

$where_clauses = [];
$params = [];

// Reusable Filter Query for CRUD redirects
$current_filters = $_GET;
unset($current_filters['success'], $current_filters['error'], $current_filters['id']);
$filter_qs = http_build_query($current_filters);

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Fetch Logged-in User Info for Role-based Scoping ---
$user_stmt = $conn->prepare("
    SELECT e.unit_id, p.level, u.name as unit_name
    FROM employees e 
    LEFT JOIN positions p ON e.position_id = p.id 
    LEFT JOIN units u ON e.unit_id = u.id
    WHERE e.id = :user_id LIMIT 1
");
$user_stmt->execute([':user_id' => $_SESSION['user_id']]);
$user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
$user_level = $user_data ? (int)$user_data['level'] : 5;
$user_unit_name = $user_data ? $user_data['unit_name'] : '';

$mapped_education_unit_ids = [];
if (!empty($user_unit_name)) {
    $clean_unit_name = str_replace(["'", " "], ["", ""], strtolower($user_unit_name));
    $edu_stmt = $conn->query("SELECT id, name FROM education_units");
    while ($edu_row = $edu_stmt->fetch(PDO::FETCH_ASSOC)) {
        $clean_edu_name = str_replace(["'", " "], ["", ""], strtolower($edu_row['name']));
        if (strpos($clean_unit_name, $clean_edu_name) !== false || strpos($clean_edu_name, $clean_unit_name) !== false) {
            $mapped_education_unit_ids[] = (int)$edu_row['id'];
        }
    }
}

if (!$is_admin && $user_level > 2) {
    if (!empty($mapped_education_unit_ids)) {
        // Scoped to their unit
        $where_clauses[] = "gl.education_unit_id IN (" . implode(',', $mapped_education_unit_ids) . ")";
    } else {
        // Teachers only see classes they are wali kelas for OR classes they have a schedule in
        $where_clauses[] = "(gl.teacher_id = :teacher_id_filter OR gl.id IN (SELECT grade_level_id FROM class_schedules WHERE employee_id = :employee_id_filter))";
        $params[':teacher_id_filter'] = $_SESSION['user_id'];
        $params[':employee_id_filter'] = $_SESSION['user_id'];
    }
}

if (!empty($search)) {
    $where_clauses[] = "gl.name LIKE :search";
    $params[':search'] = "%$search%";
}

if (!empty($unit_id)) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$where_sql = count($where_clauses) > 0 ? implode(' AND ', $where_clauses) : "1=1";

// Pagination Logic
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

// Get Total Count
$count_query = "SELECT COUNT(*) as total FROM grade_levels gl WHERE $where_sql";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute($params);
$total_results = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_results / $limit);

// Fetch Grade Levels (Classes) with Joins
// "Tingkat" column removed from SELECT if not needed, but good to keep in query just in case, removed from HTML table.
$query = "SELECT gl.*, eu.name as unit_name, e.full_name as teacher_name 
          FROM grade_levels gl 
          LEFT JOIN education_units eu ON gl.education_unit_id = eu.id 
          LEFT JOIN employees e ON gl.teacher_id = e.id 
          WHERE $where_sql 
          ORDER BY eu.name ASC, gl.level ASC, gl.name ASC 
          LIMIT :start, :limit";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper for pagination links
function get_query_params($page)
{
    $params = $_GET;
    $params['page'] = $page;
    return http_build_query($params);
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
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Manajemen Kelas</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Manajemen Kelas</h2>
            <p class="mt-2 text-sm text-slate-500">Kelola data kelas dan wali kelas unit pendidikan.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0 gap-3">
            <button type="button" id="bulkDeleteBtn" onclick="confirmBulkDelete()"
                class="hidden inline-flex items-center rounded-lg border border-red-600 bg-white px-4 py-2 text-sm font-semibold text-red-600 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors">
                <i class="fa-solid fa-trash -ml-1 mr-2 h-4 w-4"></i>
                Hapus Terpilih (<span id="selectedCount">0</span>)
            </button>
            <a href="<?php url('views/grade_levels/create.php' . (!empty($filter_qs) ? '?' . $filter_qs : '')); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <i class="fa-solid fa-plus w-5 h-5 mr-2"></i>
                Tambah Kelas
            </a>
        </div>
    </div>

    <!-- Improved Filters (Like Student Page) -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">

        <form id="filter-form" action="" method="GET" class="flex flex-col md:flex-row gap-4 w-full justify-between items-start md:items-center">
            <div class="flex flex-col md:flex-row gap-4 items-center w-full md:w-auto">
                <!-- Search -->
                <div class="relative w-full md:w-96">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass h-5 w-5 text-gray-400"></i>
                    </div>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border placeholder:text-slate-400 text-slate-600 shadow-sm"
                        placeholder="Cari nama kelas..." onchange="this.form.submit()">
                </div>

                <!-- Unit Filter (Dropdown) -->
                <div class="relative group" id="filter-unit-container">
                    <input type="hidden" name="unit_id" id="filter-unit-input" value="<?php echo $unit_id; ?>">
                    <button type="button" onclick="toggleDropdown('filter-unit')"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-48 shadow-sm">
                        <span id="filter-unit-text" class="truncate">
                            <?php
                            $unitLabel = "Unit: Semua";
                            if (!empty($unit_id)) {
                                foreach ($units as $u) {
                                    if ($u['id'] == $unit_id) {
                                        $unitLabel = $u['name'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars($unitLabel);
                            ?>
                        </span>
                        <i id="filter-unit-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="filter-unit-menu"
                        class="hidden absolute top-full left-0 mt-1 w-48 origin-top-left rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                        <ul class="py-1">
                            <li onclick="selectFilterOption('unit', '', 'Unit: Semua')"
                                class="cursor-pointer px-4 py-2 text-sm text-slate-500 hover:bg-slate-50 hover:text-cyan-700">
                                Unit: Semua</li>
                            <?php foreach ($units as $u): ?>
                                <li onclick="selectFilterOption('unit', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars(addslashes($u['name']), ENT_QUOTES); ?>')"
                                    class="cursor-pointer px-4 py-2 text-sm text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo ($unit_id == $u['id']) ? 'bg-cyan-50 text-cyan-700' : ''; ?>">
                                    <?php echo htmlspecialchars($u['name']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Reset Button -->
                <?php if (!empty($search) || !empty($unit_id)): ?>
                    <a href="<?php url('views/grade_levels/index.php'); ?>"
                        class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors shadow-sm"
                        title="Reset Filters">
                        <i class="fa-solid fa-xmark h-4 w-4"></i>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Limit Selector (Rows Per Page) -->
            <div class="flex items-center gap-2 self-end md:self-auto">
                <span class="text-xs font-medium text-slate-600">Tampilkan:</span>
                <div class="relative" id="limit-dropdown-container">
                    <button type="button" onclick="toggleDropdown('limit-dropdown')"
                        class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors min-w-[100px] shadow-sm">
                        <span id="limit-text"><?php echo $limit; ?> baris</span>
                        <i id="limit-dropdown-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                    </button>
                    <div id="limit-dropdown-menu"
                        class="hidden absolute right-0 top-full mt-1 w-28 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                        <ul class="py-1">
                            <?php foreach ([10, 20, 50, 100] as $opt): ?>
                                <li onclick="selectLimit(<?php echo $opt; ?>)"
                                    class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors <?php echo $limit == $opt ? 'bg-slate-50 font-semibold' : ''; ?>">
                                    <?php echo $opt; ?> baris
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <!-- Hidden Input to store value -->
                    <input type="hidden" name="limit" id="limit-input" value="<?php echo $limit; ?>">
                </div>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left w-12 sm:pl-6 text-center border-none">
                        <input type="checkbox" id="selectAll" onclick="toggleAllCheckboxes(this)" class="custom-checkbox">
                    </th>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left w-16 text-center">No.</th>
                    <th scope="col" class="py-3.5 pl-4 pr-3 text-left min-w-[150px]">Nama Kelas</th>
                    <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Unit</th>
                    <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Wali Kelas</th>
                    <th scope="col" class="px-3 py-3.5 text-left min-w-[100px]">Status</th>
                    <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right w-32 border-none">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php foreach ($levels as $index => $level): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 sm:pl-6 text-center">
                            <input type="checkbox" value="<?php echo $level['id']; ?>" class="row-checkbox custom-checkbox" onchange="updateBulkDeleteBtn()">
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 text-center">
                            <?php echo $start + $index + 1; ?>.
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($level['name']); ?>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <span
                                class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                <?php echo htmlspecialchars($level['unit_name'] ?? '-'); ?>
                            </span>
                        </td>
                        <!-- "Tingkat" Column REMOVED here too -->
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($level['teacher_name'])): ?>
                                    <div
                                        class="h-6 w-6 rounded-full bg-cyan-100 flex items-center justify-center text-xs font-medium text-cyan-600">
                                        <?php echo substr($level['teacher_name'], 0, 1); ?>
                                    </div>
                                    <span><?php echo htmlspecialchars($level['teacher_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-xs">Belum ditentukan</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                            <span class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium <?php echo $level['is_active'] ? 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20' : 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20'; ?>">
                                <?php echo $level['is_active'] ? 'Aktif' : 'Non-aktif'; ?>
                            </span>
                        </td>
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex justify-end gap-2">
                                <button type="button"
                                    onclick="openConfirmModal('<?php url('logic/grade_levels/toggle_status.php?id=' . $level['id'] . (!empty($filter_qs) ? '&' . $filter_qs : '')); ?>', '<?php echo $level['is_active'] ? 'Nonaktifkan Kelas' : 'Aktifkan Kelas'; ?>', 'Apakah Anda yakin ingin <?php echo $level['is_active'] ? 'menonaktifkan' : 'mengaktifkan'; ?> kelas &quot;<?php echo htmlspecialchars($level['name']); ?>&quot;?', '<?php echo $level['is_active'] ? 'amber' : 'emerald'; ?>')"
                                    class="<?php echo $level['is_active'] ? 'text-amber-600 hover:text-amber-900 hover:bg-amber-50' : 'text-emerald-600 hover:text-emerald-900 hover:bg-emerald-50'; ?> p-1 rounded transition-colors"
                                    title="<?php echo $level['is_active'] ? 'Nonaktifkan Kelas' : 'Aktifkan Kelas'; ?>">
                                    <?php if ($level['is_active']): ?>
                                        <i class="fa-solid fa-eye-slash w-5 h-5"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-eye w-5 h-5"></i>
                                    <?php endif; ?>
                                </button>
                                <a href="<?php url('views/grade_levels/edit.php?id=' . $level['id'] . (!empty($filter_qs) ? '&' . $filter_qs : '')); ?>"
                                    class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                                    title="Edit">
                                    <i class="fa-solid fa-pen-to-square w-5 h-5"></i>
                                </a>
                                <button
                                    onclick="openDeleteModal('<?php url('logic/grade_levels/delete.php?id=' . $level['id'] . (!empty($filter_qs) ? '&' . $filter_qs : '')); ?>')"
                                    class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition-colors"
                                    title="Delete">
                                    <i class="fa-solid fa-trash w-5 h-5"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($levels)): ?>
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-sm text-slate-500 text-center italic">
                            <div class="flex flex-col items-center justify-center">
                                <i class="fa-solid fa-bars w-10 h-10 text-slate-300 mb-2"></i>
                                Tidak ada data kelas ditemukan.
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div
            class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6 mt-4 rounded-lg shadow-sm">
            <div class="flex flex-1 justify-between sm:hidden">
                <a href="<?php url('views/grade_levels/index.php?' . get_query_params(max(1, $page - 1))); ?>"
                    class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Sebelumnya</a>
                <a href="<?php url('views/grade_levels/index.php?' . get_query_params(min($total_pages, $page + 1))); ?>"
                    class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Selanjutnya</a>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Menampilkan
                        <span class="font-medium"><?php echo ($total_results > 0) ? $start + 1 : 0; ?></span>
                        sampai
                        <span class="font-medium"><?php echo min($start + $limit, $total_results); ?></span>
                        dari
                        <span class="font-medium"><?php echo $total_results; ?></span>
                        hasil
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        <a href="<?php url('views/grade_levels/index.php?' . get_query_params(max(1, $page - 1))); ?>"
                            class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Sebelumnya</span>
                            <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                        </a>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php url('views/grade_levels/index.php?' . get_query_params($i)); ?>"
                                aria-current="<?php echo ($page === $i) ? 'page' : 'false'; ?>"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($page === $i) ? 'bg-cyan-600 text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600' : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        <a href="<?php url('views/grade_levels/index.php?' . get_query_params(min($total_pages, $page + 1))); ?>"
                            class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0">
                            <span class="sr-only">Selanjutnya</span>
                            <i class="fa-solid fa-chevron-right h-5 w-5"></i>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div id="bulkDeleteModal" class="fixed inset-0 z-[60] hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div id="bulkDeleteModalBackdrop" class="fixed inset-0 bg-slate-900/50 transition-opacity duration-300 opacity-0 backdrop-blur-sm"></div>
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div id="bulkDeleteModalPanel" class="relative transform overflow-hidden rounded-xl bg-white text-left shadow-2xl transition-all duration-300 opacity-0 scale-95 sm:my-8 sm:w-full sm:max-w-lg">
            <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <i class="fa-solid fa-triangle-exclamation h-6 w-6 text-red-600"></i>
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-lg font-bold leading-6 text-slate-900" id="modal-title">Hapus Kelas Terpilih</h3>
                        <div class="mt-2 text-sm text-slate-500">
                            Apakah Anda yakin ingin menghapus <span id="bulkDeleteCount" class="font-bold text-slate-700"></span> kelas terpilih? Tindakan ini tidak dapat dibatalkan.
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2">
                <button type="button" onclick="submitBulkDelete()" class="inline-flex w-full justify-center rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:w-auto transition-all transform active:scale-95">
                    Hapus Terpilih
                </button>
                <button type="button" onclick="closeBulkDeleteModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-all">
                    Batal
                </button>
            </div>
        </div>
    </div>
</div>

<form id="hiddenBulkDeleteForm" method="POST" action="<?php url('logic/grade_levels/bulk_delete.php?' . http_build_query($_GET)); ?>" class="hidden">
</form>

<script>
    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        // Submit immediately
        document.getElementById('filter-form').submit();
    }

    // --- Limit Option Selection ---
    function selectLimit(value) {
        document.getElementById('limit-input').value = value;
        document.getElementById('filter-form').submit();
    }

    // --- Bulk Delete Operations ---
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => cb.checked = source.checked);
        updateBulkDeleteBtn();
    }

    function updateBulkDeleteBtn() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const btn = document.getElementById('bulkDeleteBtn');
        const countSpan = document.getElementById('selectedCount');
        const selectAllCb = document.getElementById('selectAll');
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        
        if (checkboxes.length > 0) {
            btn.classList.remove('hidden');
            countSpan.textContent = checkboxes.length;
        } else {
            btn.classList.add('hidden');
        }

        if (allCheckboxes.length > 0 && selectAllCb) {
            selectAllCb.checked = (checkboxes.length === allCheckboxes.length);
        }
    }

    function openBulkDeleteModal(count) {
        document.getElementById('bulkDeleteCount').textContent = count;
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        modal.classList.remove('hidden');
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            panel.classList.remove('opacity-0', 'scale-95');
        }, 10);
    }

    function closeBulkDeleteModal() {
        const modal = document.getElementById('bulkDeleteModal');
        const backdrop = document.getElementById('bulkDeleteModalBackdrop');
        const panel = document.getElementById('bulkDeleteModalPanel');

        backdrop.classList.add('opacity-0');
        panel.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // Close modal on escape key or clicking outside the panel
    window.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeBulkDeleteModal();
        }
    });

    function confirmBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        openBulkDeleteModal(checkboxes.length);
    }

    function submitBulkDelete() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;
        
        const form = document.getElementById('hiddenBulkDeleteForm');
        form.innerHTML = ''; // clear previous elements
        checkboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = cb.value;
            form.appendChild(input);
        });
        
        // Hide modal and submit
        closeBulkDeleteModal();
        form.submit();
    }
</script>

<?php include '../layouts/footer.php'; ?>