<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Manajemen Kelas";

$db = new Database();
$conn = $db->getConnection();

// Fetch Education Units for Filter
// Custom Order as requested previously
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'MA', 'Ma''had Aly')")->fetchAll(PDO::FETCH_ASSOC);

// Filter Inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

$where_clauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "gl.name LIKE :search";
    $params[':search'] = "%$search%";
}

if (!empty($unit_id)) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination Logic
$limit = 10;
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
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
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="<?php url('views/grade_levels/create.php'); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan-600 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-5 h-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Kelas
            </a>
        </div>
    </div>

    <!-- Improved Filters (Like Student Page) -->
    <div class="mb-6 flex flex-col md:flex-row gap-4 items-center justify-between">

        <form id="filter-form" action="" method="GET" class="flex flex-col md:flex-row gap-4 w-full">
            <!-- Search -->
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
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
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col"
                        class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-16">
                        No.</th>
                    <th scope="col"
                        class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Nama Kelas</th>
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Unit</th>
                    <!-- "Tingkat" Column REMOVED as requested -->
                    <th scope="col"
                        class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Wali Kelas</th>
                    <th scope="col"
                        class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right font-semibold text-xs uppercase tracking-wide text-gray-500">
                        Aksi
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php foreach ($levels as $index => $level): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
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
                        <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                            <div class="flex justify-end gap-2">
                                <a href="<?php url('views/grade_levels/edit.php?id=' . $level['id']); ?>"
                                    class="text-indigo-600 hover:text-indigo-900 p-1 hover:bg-indigo-50 rounded transition-colors"
                                    title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                </a>
                                <button
                                    onclick="openDeleteModal('<?php url('logic/grade_levels/delete.php?id=' . $level['id']); ?>')"
                                    class="text-red-500 hover:text-red-700 p-1 hover:bg-red-50 rounded transition-colors"
                                    title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($levels)): ?>
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-sm text-slate-500 text-center italic">
                            <div class="flex flex-col items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                    stroke="currentColor" class="w-10 h-10 text-slate-300 mb-2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
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
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                    clip-rule="evenodd" />
                            </svg>
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
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                    clip-rule="evenodd" />
                            </svg>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    // --- Filter Option Selection ---
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        // Submit immediately
        document.getElementById('filter-form').submit();
    }
</script>

<?php include '../layouts/footer.php'; ?>