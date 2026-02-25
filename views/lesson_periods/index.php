<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Pengaturan Jam Pelajaran";

$db = new Database();
$conn = $db->getConnection();

// Fetch Education Units for grouping/filtering
$units = $conn->query("SELECT id, name FROM education_units ORDER BY FIELD(name, 'Playgroup', 'TKIT', 'SDIT', 'MTs', 'MA', 'Ma''had Aly')")->fetchAll(PDO::FETCH_ASSOC);

$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

$where_clauses = ["1=1"];
$params = [];

if (!empty($unit_id)) {
    $where_clauses[] = "lp.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

$where_sql = implode(' AND ', $where_clauses);

$query = "SELECT lp.*, eu.name as unit_name 
          FROM lesson_periods lp 
          JOIN education_units eu ON lp.education_unit_id = eu.id 
          WHERE $where_sql 
          ORDER BY eu.name ASC, lp.period_number ASC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$periods = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <!-- Breadcrumb -->
    <nav class="flex mb-4" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                        <path fill-rule="evenodd" d="M9.293 2.293a1 1 0 011.414 0l7 7A1 1 0 0117 11h-1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-3a1 1 0 00-1-1H9a1 1 0 00-1 1v3a1 1 0 01-1 1H5a1 1 0 01-1-1v-6H3a1 1 0 01-.707-1.707l7-7z" clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </a>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <svg class="w-3 h-3 text-gray-400 mx-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4" />
                    </svg>
                    <span class="ml-1 text-slate-500 hover:text-slate-800">Jam Pelajaran</span>
                </div>
            </li>
        </ol>
    </nav>

    <div class="md:flex md:items-center md:justify-between mb-8">
        <div class="min-w-0 flex-1">
            <h2 class="text-3xl font-bold leading-7 text-slate-900 sm:truncate sm:tracking-tight">Jam Pelajaran</h2>
            <p class="mt-2 text-sm text-slate-500">Atur waktu mulai dan selesai sesi pelajaran per jenjang pendidikan.</p>
        </div>
        <div class="mt-4 flex md:ml-4 md:mt-0">
            <a href="<?php url('views/lesson_periods/form.php'); ?>" class="inline-flex items-center rounded-lg bg-cyan-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jam
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-6">
        <form action="" method="GET" class="flex flex-col md:flex-row gap-4 items-center">
            <div class="w-full md:w-64">
                <select name="unit_id" onchange="this.form.submit()" class="block w-full rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border p-2 text-slate-600 shadow-sm">
                    <option value="">Semua Jenjang</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo ($unit_id == $u['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (!empty($unit_id)): ?>
                <a href="<?php url('views/lesson_periods/index.php'); ?>" class="text-sm text-cyan-600 hover:text-cyan-700 font-medium">Reset Filter</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-16">No.</th>
                    <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jenjang</th>
                    <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Jam Ke</th>
                    <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Waktu Mulai</th>
                    <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Waktu Selesai</th>
                    <th class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right font-semibold text-xs uppercase tracking-wide text-gray-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (empty($periods)): ?>
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-sm text-slate-500 text-center italic">Tidak ada data jam pelajaran ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($periods as $index => $lp): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6"><?php echo $index + 1; ?>.</td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lp['unit_name']); ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 font-bold">Jam ke-<?php echo $lp['period_number']; ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600"><?php echo date('H:i', strtotime($lp['start_time'])); ?></td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600"><?php echo date('H:i', strtotime($lp['end_time'])); ?></td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex justify-end gap-2 text-gray-400">
                                    <a href="<?php url('views/lesson_periods/form.php?id=' . $lp['id']); ?>" class="hover:text-amber-600 transition-colors" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                    </a>
                                    <button onclick="openDeleteModal('<?php url('logic/lesson_periods/delete.php?id=' . $lp['id']); ?>')" class="hover:text-red-600 transition-colors" title="Hapus">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
