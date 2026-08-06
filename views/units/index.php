<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$page_title = "Unit Organisasi";

$db = new Database();
$conn = $db->getConnection();

// --- Pagination Logic ---
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
if (!in_array($limit, [10, 20, 50, 100]))
    $limit = 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;

$offset = ($page - 1) * $limit;

// Total Units
$total_units = $conn->query("SELECT COUNT(*) FROM units")->fetchColumn();
$total_pages = ceil($total_units / $limit);

// Fetch Units with Division Name and Employee Count
$query = "
    SELECT 
        u.id, 
        u.name, 
        d.name as division_name,
        ws.name as schedule_name,
        (SELECT COUNT(*) FROM employees e WHERE e.unit_id = u.id) as member_count
    FROM units u
    LEFT JOIN divisions d ON u.division_id = d.id
    LEFT JOIN work_schedules ws ON u.schedule_id = ws.id
    ORDER BY u.name ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Divisions for Dropdown
$divisions = $conn->query("SELECT id, name FROM divisions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Unit / Tim</h1>
            <p class="mt-2 text-sm text-slate-500">Kelola unit operasional dan tim di dalam bidang.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none flex justify-end">
            <a href="<?php url('views/units/create.php'); ?>"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:w-auto transition-colors">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Tambah Unit
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl bg-white p-4 shadow-sm border border-slate-100">
            <dt class="truncate text-sm font-medium text-slate-500">Total Unit</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                <?php echo $total_units; ?>
            </dd>
        </div>
    </div>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-xl bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-6 pr-3 text-left w-16">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[200px]">Nama Unit</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[180px]">Bidang</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[120px]">Anggota</th>
                        <th scope="col" class="px-3 py-3.5 text-left min-w-[150px]">Jadwal</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-6 text-right w-32 border-none">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                    <?php foreach ($units as $index => $unit): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm text-slate-500 font-medium">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-bold text-slate-900">
                                <?php echo htmlspecialchars($unit['name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-600 font-medium">
                                <?php echo htmlspecialchars($unit['division_name'] ?? 'Tidak Ada Bidang'); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span
                                    class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700 uppercase">
                                    <?php echo $unit['member_count']; ?> Personel
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <?php if (!empty($unit['schedule_name'])): ?>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md bg-cyan-50 px-2 py-1 text-[11px] font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10 uppercase">
                                        <?php echo htmlspecialchars($unit['schedule_name']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic text-xs">Default Bidang</span>
                                <?php endif; ?>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2 transition-opacity">
                                    <a href="<?php url('views/units/edit.php?id=' . $unit['id']); ?>"
                                        class="p-2 text-slate-400 hover:text-cyan-500 hover:bg-cyan-50 rounded-lg transition-all" title="Ubah">
                                        <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                                    </a>
                                    <button
                                        onclick="openDeleteModal('<?php url('logic/units/delete.php?id=' . $unit['id']); ?>')"
                                        class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" title="Hapus">
                                        <i class="fa-solid fa-trash w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

                    <!-- Pagination -->
                    <div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 bg-white px-4 py-4 md:py-3 sm:px-6 gap-4">
                        <!-- Mobile Pagination Info -->
                        <div class="flex sm:hidden flex-col items-center gap-2">
                            <p class="text-xs text-slate-500">
                                Menampilkan <span class="font-bold text-slate-900"><?php echo ($total_units > 0) ? $offset + 1 : 0; ?></span> - <span class="font-bold text-slate-900"><?php echo min($offset + $limit, $total_units); ?></span> dari <span class="font-bold text-slate-900"><?php echo $total_units; ?></span>
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
                                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-400">...</span>
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

            <!-- Add Unit Modal -->
            <div id="addModal" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeAddModal()"></div>
                <div class="fixed inset-0 z-10 overflow-y-auto">
                    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                        <div
                            class="relative transform overflow-hidden rounded-lg bg-white px-4 pt-5 pb-4 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                            <form action="<?php url('logic/units/store.php'); ?>" method="POST">
                                <div class="mb-4">
                                    <label for="name" class="block text-sm font-medium text-gray-700">Nama Unit</label>
                                    <input type="text" name="name" id="name" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm">
                                </div>
                                <div class="mb-4">
                                    <label for="division_id"
                                        class="block text-sm font-medium text-gray-700">Bidang</label>
                                    <select name="division_id" id="division_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-cyan-500 focus:ring-cyan-500 sm:text-sm">
                                        <option value="">Pilih Bidang</option>
                                        <?php foreach ($divisions as $div): ?>
                                            <option value="<?php echo $div['id']; ?>">
                                                <?php echo htmlspecialchars($div['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3">
                                    <button type="submit"
                                        class="inline-flex w-full justify-center rounded-md border border-transparent bg-cyan-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-cyan-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:text-sm">
                                        Simpan Unit
                                    </button>
                                    <button type="button" onclick="closeAddModal()"
                                        class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 sm:mt-0 sm:text-sm">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function openAddModal() {
                    document.getElementById('addModal').classList.remove('hidden');
                }
                function closeAddModal() {
                    document.getElementById('addModal').classList.add('hidden');
                }
            </script>

            <?php include '../layouts/footer.php'; ?>