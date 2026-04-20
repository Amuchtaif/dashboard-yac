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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd"
                            d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z"
                            clip-rule="evenodd" />
                    </svg>
                    Beranda
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m1 9 4-4-4-4" />
                    </svg>
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
                <svg class="-ml-1 mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path
                        d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                </svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                </svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div
        class="mb-6 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center">
        <!-- Search -->
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari bidang...">
        </div>

        <div class="flex gap-3">
            <button
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z"
                        clip-rule="evenodd" />
                </svg>
                Filter
            </button>
            <button
                class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4 text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                </svg>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                        </svg>
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
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('logic/departments/delete.php?id=' . $dept['id']); ?>')"
                                        class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all"
                                        title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
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
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                            clip-rule="evenodd" />
                                    </svg>
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
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../layouts/footer.php'; ?>