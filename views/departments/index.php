<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Bidang";

$db = new Database();
$conn = $db->getConnection();

// --- Stats Calculations ---
// Total Divisions
$total_depts = $conn->query("SELECT COUNT(*) FROM divisions")->fetchColumn();

// Active Teams (Units)
$total_units = $conn->query("SELECT COUNT(*) FROM units")->fetchColumn();

// Total Headcount (Employees)
$total_employees = $conn->query("SELECT COUNT(*) FROM employees")->fetchColumn();

// --- Fetch Departments with Meta Data ---
// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// Total Departments (Already fetched above as $total_depts, use it)
$total_pages = ceil($total_depts / $limit);

// Fetch Divisions with Meta Data
// Getting Division Name, ID, Member Count, and Unit Count
$query = "
    SELECT 
        d.id, 
        d.name, 
        d.manager_id,
        m.full_name as manager_name,
        (SELECT COUNT(*) FROM employees e WHERE e.division_id = d.id) as member_count,
        (SELECT COUNT(*) FROM units u WHERE u.division_id = d.id) as unit_count
    FROM divisions d
    LEFT JOIN employees m ON d.manager_id = m.id
    ORDER BY d.id ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">

    <!-- Breadcrumbs -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>"
                    class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i>
                    Beranda
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Bidang</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Manajemen Bidang</h2>
            <p class="mt-1 text-sm text-slate-500">Atur struktur bidang yayasan.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <!-- Add Department Trigger -->
            <a href="<?php url('views/departments/form.php'); ?>"
                class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-all ml-auto">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Bidang
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Departments -->
        <div
            class="bg-white overflow-hidden rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500 truncate">Total Bidang</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo $total_depts; ?></p>
            </div>
            <div class="p-3 bg-blue-50 rounded-lg text-blue-600">
                <i class="fa-solid fa-building w-6 h-6"></i>
            </div>
        </div>

        <!-- Active Teams -->
        <div
            class="bg-white overflow-hidden rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500 truncate">Total Unit / Tim</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo $total_units; ?></p>
            </div>
            <div class="p-3 bg-purple-50 rounded-lg text-purple-600">
                <i class="fa-solid fa-users w-6 h-6"></i>
            </div>
        </div>

        <!-- Total Headcount -->
        <div
            class="bg-white overflow-hidden rounded-xl border border-slate-200 shadow-sm p-6 flex justify-between items-start">
            <div>
                <p class="text-sm font-medium text-slate-500 truncate">Total Karyawan</p>
                <p class="mt-2 text-3xl font-bold text-slate-900"><?php echo $total_employees; ?></p>
            </div>
            <div class="p-3 bg-green-50 rounded-lg text-green-600">
                <i class="fa-solid fa-file-lines w-6 h-6"></i>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div
        class="mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari bidang...">
        </div>

        <div class="flex gap-3">
            <button
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <i class="fa-solid fa-filter -ml-1 mr-2 h-4 w-4 text-slate-500"></i>
                Filter
            </button>
            <button
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <i class="fa-solid fa-download -ml-1 mr-2 h-4 w-4 text-slate-500"></i>
                Export
            </button>
        </div>
    </div>

    <!-- Departments Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50 border-b border-slate-100">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left min-w-[200px]">Bidang</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Kepala</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px] text-center">Unit/Tim</th>
                        <th scope="col" class="px-3 py-3.5 text-left w-28 text-center border-none">Status</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32 border-none">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($departments as $dept): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3">
                                <div class="flex items-center">
                                    <div
                                        class="h-10 w-10 flex-shrink-0 flex items-center justify-center bg-cyan-50 rounded-lg text-cyan-600 border border-cyan-100 shadow-sm">
                                        <i class="fa-solid fa-building-columns w-5 h-5"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="font-bold text-slate-900 text-sm tracking-tight">
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <div class="flex items-center">
                                    <?php if (!empty($dept['manager_name'])): ?>
                                        <img class="h-8 w-8 rounded-full border-2 border-slate-100 shadow-sm object-cover"
                                            src="https://ui-avatars.com/api/?name=<?php echo urlencode($dept['manager_name']); ?>&background=random"
                                            alt="">
                                        <div class="ml-3">
                                            <div class="text-[13px] font-bold text-slate-900 leading-tight">
                                                <?php echo htmlspecialchars($dept['manager_name']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-lg bg-slate-50 text-[10px] font-bold text-slate-400 border border-slate-100 uppercase">
                                            Kosong
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                <span
                                    class="inline-flex items-center rounded-md bg-purple-50 px-2 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">
                                    <?php echo $dept['unit_count']; ?> Tim
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4">
                                <span
                                    class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 border border-emerald-100 uppercase tracking-tighter">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                    Aktif
                                </span>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2 transition-opacity">
                                    <a href="<?php url('views/departments/form.php?id=' . $dept['id']); ?>"
                                        class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-all"
                                        title="Ubah">
                                        <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('logic/departments/delete.php?id=' . $dept['id']); ?>')"
                                        class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all"
                                        title="Hapus">
                                        <i class="fa-solid fa-trash w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <!-- Dynamic Pagination -->
            <!-- Pagination -->
            <div
                class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                <!-- Mobile Pagination Info -->
                <div class="flex sm:hidden flex-col items-center gap-2">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span
                            class="font-bold text-slate-900"><?php echo ($total_depts > 0) ? $offset + 1 : 0; ?></span>
                        - <span
                            class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_depts); ?></span>
                        dari <span class="font-bold text-slate-900"><?php echo $total_depts; ?></span>
                    </p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <select onchange="window.location.href='?page=1&limit='+this.value"
                            class="block rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-slate-900 ring-1 ring-inset ring-slate-100 focus:ring-2 focus:ring-cyan-600 sm:text-xs">
                            <?php foreach ([10, 20, 50, 100] as $val): ?>
                                <option value="<?php echo $val; ?>" <?php echo $limit == $val ? 'selected' : ''; ?>>
                                    <?php echo $val; ?> per hal
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden"
                            aria-label="Pagination">
                            <!-- Prev -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                    class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-left h-5 w-5"></i>
                                </a>
                            <?php endif; ?>

                            <?php
                            $range = 1;
                            for ($i = 1; $i <= $total_pages; $i++):
                                if ($i == 1 || $i == $total_pages || ($i >= $page - $range && $i <= $page + $range)): ?>
                                    <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?php echo ($i == $page) ? 'bg-cyan-600 text-white' : 'text-slate-900 hover:bg-slate-50'; ?> border-x border-slate-100 transition-colors">
                                        <?php echo $i; ?>
                                    </a>
                                <?php elseif ($i == 2 || $i == $total_pages - 1): ?>
                                    <span
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                                <?php endif;
                            endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                    class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-chevron-right h-5 w-5"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../layouts/footer.php'; ?>