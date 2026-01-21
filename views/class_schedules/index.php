<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$page_title = "Data Jadwal Pelajaran";

$db = new Database();
$conn = $db->getConnection();

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1)
    $page = 1;
$offset = ($page - 1) * $limit;

// --- Logika Filter ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR s.name LIKE :search OR gl.name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Total baris
$count_query = "
    SELECT COUNT(*) 
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    $where_sql
";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Ambil data
$query = "
    SELECT cs.*, e.full_name as teacher_name, s.name as subject_name, gl.name as class_name, ay.name as ay_name
    FROM class_schedules cs
    JOIN employees e ON cs.employee_id = e.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    JOIN academic_years ay ON cs.academic_year_id = ay.id
    $where_sql
    ORDER BY ay.name DESC, FIELD(cs.day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), cs.start_time ASC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Map Hari ke Bahasa Indonesia
$indo_days = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Ahad'
];

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center">
        <div class="sm:flex-auto">
            <h1 class="text-xl font-bold text-slate-900">Jadwal Pelajaran</h1>
            <p class="mt-2 text-sm text-slate-500">Atur siapa mengajar apa, di mana, dan kapan.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none">
            <a href="form.php"
                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-cyan-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-cyan-700 transition-colors">
                <svg class="-ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Jadwal
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <form
        class="mt-8 bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center"
        method="GET">
        <div class="relative w-full sm:w-96">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari guru, mapel, atau kelas...">
        </div>
        <button type="submit" class="hidden">Filter</button>
    </form>

    <!-- Table -->
    <div class="mt-8 flex flex-col">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="py-3.5 pl-4 pr-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 sm:pl-6 w-12">
                            No.</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Tahun Akademik</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Hari & Waktu</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Mata Pelajaran</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Kelas</th>
                        <th class="px-3 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Guru</th>
                        <th
                            class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-slate-500">Belum ada data jadwal.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($schedules as $index => $sch): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm text-gray-500 sm:pl-6">
                                <?php echo $offset + $index + 1; ?>.
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 font-medium">
                                <?php echo htmlspecialchars($sch['ay_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <span class="font-bold text-slate-900">
                                    <?php echo $indo_days[$sch['day']]; ?>
                                </span>
                                <div class="text-xs text-slate-500 mt-1">
                                    <?php echo date('H:i', strtotime($sch['start_time'])); ?> -
                                    <?php echo date('H:i', strtotime($sch['end_time'])); ?>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-cyan-600">
                                <?php echo htmlspecialchars($sch['subject_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                <?php echo htmlspecialchars($sch['class_name']); ?>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($sch['teacher_name']); ?>
                                </div>
                            </td>
                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                <div class="flex items-center justify-end gap-3 text-gray-400">
                                    <a href="form.php?id=<?php echo $sch['id']; ?>" class="hover:text-cyan-600"
                                        title="Edit"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path
                                                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg></a>
                                    <button
                                        onclick="openDeleteModal('../../logic/class_schedules/delete.php?id=<?php echo $sch['id']; ?>')"
                                        class="hover:text-red-600" title="Hapus"><svg class="w-5 h-5" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<?php include '../layouts/footer.php'; ?>