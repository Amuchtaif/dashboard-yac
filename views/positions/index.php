<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Data Jabatan";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// Total Positions
$total_rows = $conn->query("SELECT COUNT(*) FROM positions")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch Positions with Employee Count
// Assuming 'position_id' exists in employees table based on earlier check
$query = "
    SELECT 
        p.id, 
        p.name, 
        p.level,
        (SELECT COUNT(*) FROM employees e WHERE e.position_id = p.id) as member_count
    FROM positions p
    ORDER BY p.level ASC, p.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Data Jabatan</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola gelar pekerjaan karyawan dan tingkat hierarki.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex justify-end">
            <a href="<?php url('views/positions/form.php'); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                    stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jabatan
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Nama Jabatan</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px]">Level</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Jumlah Pegawai</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32 border-none">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php if (count($positions) > 0): ?>
                        <?php foreach ($positions as $index => $pos): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500 font-medium">
                                    <?php echo $offset + $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-slate-900">
                                    <?php echo htmlspecialchars($pos['name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-lg bg-slate-50 px-2.5 py-0.5 text-[11px] font-bold text-slate-600 ring-1 ring-inset ring-slate-500/10 uppercase">
                                        Tingkat <?php echo $pos['level']; ?>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span
                                        class="inline-flex items-center rounded-lg bg-blue-50 px-2.5 py-0.5 text-[11px] font-bold text-blue-700 uppercase">
                                        <?php echo $pos['member_count']; ?> Orang
                                    </span>
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2 transition-opacity">
                                        <a href="<?php url('views/positions/form.php?id=' . $pos['id']); ?>"
                                            class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-all" title="Ubah">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                            </svg>
                                        </a>
                                        <button
                                            onclick="openDeleteModal('<?php url('logic/positions/delete.php?id=' . $pos['id']); ?>')"
                                            class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" title="Hapus">
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
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-12 px-6 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="h-10 w-10 text-slate-200 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    <p class="text-sm text-slate-500 font-medium tracking-tight">Data jabatan tidak ditemukan.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                        <!-- Mobile Pagination Info -->
                        <div class="flex sm:hidden flex-col items-center gap-2">
                            <p class="text-xs text-slate-500">
                                Menampilkan <span class="font-bold text-slate-900"><?php echo ($total_rows > 0) ? $offset + 1 : 0; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_rows; ?></span>
                            </p>
                            <div class="flex gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Prev</a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50">Next</a>
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
                                <nav class="isolate inline-flex -space-x-px rounded-xl shadow-sm border border-slate-200 overflow-hidden" aria-label="Pagination">
                                    <!-- Prev -->
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" /></svg>
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
                                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
                                        <?php endif;
                                    endfor; ?>

                                    <!-- Next -->
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>"
                                            class="relative inline-flex items-center px-3 py-2 text-slate-400 hover:bg-slate-50 transition-colors">
                                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" /></svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include '../layouts/footer.php'; ?>