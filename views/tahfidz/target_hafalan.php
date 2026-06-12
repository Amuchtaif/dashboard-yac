<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');

$page_title = "Master Target Hafalan";

$db = new Database();
$conn = $db->getConnection();

// --- Fetch Academic Years ---
$academic_years = $conn->query("SELECT * FROM academic_years ORDER BY name DESC, semester DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Education Units ---
$units = $conn->query("SELECT * FROM education_units WHERE name IN ('MTs', 'MA') OR id IN (5, 6) ORDER BY name DESC")->fetchAll(PDO::FETCH_ASSOC);

// --- Filters ---
$filter_ay = isset($_GET['filter_ay']) ? $_GET['filter_ay'] : '';
$filter_unit = isset($_GET['filter_unit']) ? $_GET['filter_unit'] : '';
$filter_program = isset($_GET['filter_program']) ? $_GET['filter_program'] : '';
$filter_kelas = isset($_GET['filter_kelas']) ? $_GET['filter_kelas'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

// --- Pagination Logic ---
$limit = isset($_GET['limit']) && in_array((int) $_GET['limit'], [10, 20, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Build Query ---
$where_clauses = ["1=1"];
$params = [];

if ($filter_ay !== '') {
    $where_clauses[] = "th.tahun_ajaran_id = :filter_ay";
    $params[':filter_ay'] = $filter_ay;
}
if ($filter_unit !== '') {
    $where_clauses[] = "th.unit_id = :filter_unit";
    $params[':filter_unit'] = $filter_unit;
}
if ($filter_program !== '') {
    if ($filter_program === 'NULL') {
        $where_clauses[] = "th.program_id IS NULL";
    } else {
        $where_clauses[] = "th.program_id = :filter_program";
        $params[':filter_program'] = $filter_program;
    }
}
if ($filter_kelas !== '') {
    $where_clauses[] = "th.kelas_id = :filter_kelas";
    $params[':filter_kelas'] = $filter_kelas;
}
if ($filter_status !== '') {
    $where_clauses[] = "th.status_aktif = :filter_status";
    $params[':filter_status'] = $filter_status;
}

$where_str = implode(" AND ", $where_clauses);

// Count total rows
$count_query = "SELECT COUNT(*) FROM target_hafalan th WHERE $where_str";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Fetch target_hafalan records
$query = "SELECT th.*, ay.name as tahun_ajaran_name, ay.semester as tahun_ajaran_semester, eu.name as unit_name 
          FROM target_hafalan th
          JOIN academic_years ay ON th.tahun_ajaran_id = ay.id
          JOIN education_units eu ON th.unit_id = eu.id
          WHERE $where_str
          ORDER BY ay.name DESC, ay.semester DESC, eu.name ASC, th.kelas_id ASC, th.program_id ASC
          LIMIT :limit OFFSET :offset";

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="sm:flex sm:items-center justify-between">
        <div class="sm:flex-auto">
            <h1 class="text-2xl font-bold text-slate-800">Master Target Hafalan</h1>
            <p class="mt-2 text-sm text-slate-500 font-medium">Atur target hafalan (juz) santri secara dinamis berdasarkan tahun ajaran, unit pendidikan, program, dan kelas.</p>
        </div>
        <div class="mt-4 sm:mt-0 sm:flex-none">
            <button onclick="openFormModal()"
                class="inline-flex items-center justify-center rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 px-5 py-3 text-sm font-bold text-white shadow-md hover:from-cyan-600 hover:to-blue-700 transition-all active:scale-95">
                <svg class="-ml-1 mr-2 h-4 w-4 text-cyan-200" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Target Hafalan
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="mt-8 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tahun Ajaran</label>
                <select name="filter_ay" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-650 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all">
                    <option value="">Semua Tahun Ajaran</option>
                    <?php foreach ($academic_years as $ay): ?>
                        <option value="<?php echo $ay['id']; ?>" <?php echo $filter_ay == $ay['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester']); ?> <?php echo $ay['is_active'] ? '(Aktif)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Unit Pendidikan</label>
                <select name="filter_unit" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-650 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filter_unit == $u['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Program (Khusus MTs)</label>
                <select name="filter_program" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-650 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all">
                    <option value="">Semua Program</option>
                    <option value="Fullday" <?php echo $filter_program === 'Fullday' ? 'selected' : ''; ?>>Fullday</option>
                    <option value="Boarding" <?php echo $filter_program === 'Boarding' ? 'selected' : ''; ?>>Boarding</option>
                    <option value="NULL" <?php echo $filter_program === 'NULL' ? 'selected' : ''; ?>>Bukan MTs / Non-Program</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Kelas</label>
                <select name="filter_kelas" class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-650 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all">
                    <option value="">Semua Kelas</option>
                    <?php for ($k = 7; $k <= 12; $k++): ?>
                        <option value="<?php echo $k; ?>" <?php echo $filter_kelas == $k ? 'selected' : ''; ?>>Kelas <?php echo $k; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-gradient-to-br from-cyan-500 to-blue-600 text-white px-4 py-2.5 rounded-xl text-xs font-bold hover:from-cyan-600 hover:to-blue-700 transition-all active:scale-[0.98]">
                    Filter
                </button>
                <a href="target_hafalan.php" class="bg-white border border-slate-200 text-slate-500 p-2.5 rounded-xl hover:bg-slate-50 transition-all flex items-center justify-center shadow-sm" title="Reset Filter">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="mt-6 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden text-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-4 w-20 text-center">No</th>
                        <th class="px-6 py-4">Tahun Ajaran</th>
                        <th class="px-6 py-4">Unit Pendidikan</th>
                        <th class="px-6 py-4">Program</th>
                        <th class="px-6 py-4">Kelas</th>
                        <th class="px-6 py-4 text-center">Target Hafalan</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4">Keterangan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($data)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-20 text-center text-slate-400 font-medium bg-slate-50/20">Belum ada data target hafalan. Silakan tambah data baru.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($data as $index => $item): ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-450 font-bold"><?php echo $offset + $index + 1; ?>.</td>
                            <td class="px-6 py-4 text-slate-800 font-bold">
                                <?php echo htmlspecialchars($item['tahun_ajaran_name'] . ' (' . $item['tahun_ajaran_semester'] . ')'); ?>
                            </td>
                            <td class="px-6 py-4 font-semibold text-slate-700">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg <?php echo $item['unit_name'] === 'MTs' ? 'bg-cyan-50 text-cyan-700 border border-cyan-100' : 'bg-indigo-50 text-indigo-700 border border-indigo-100'; ?>">
                                    <?php echo htmlspecialchars($item['unit_name']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 font-medium">
                                <?php if ($item['program_id']): ?>
                                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg <?php echo $item['program_id'] === 'Boarding' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100'; ?>">
                                        <?php echo htmlspecialchars($item['program_id']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-400 italic font-normal">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-black text-slate-800 text-base">Kelas <?php echo htmlspecialchars($item['kelas_id']); ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="text-slate-900 font-black text-lg"><?php echo htmlspecialchars($item['target_juz']); ?></span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider ml-0.5">Juz</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <?php if ($item['status_aktif'] === 'Aktif'): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-250">Aktif</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-55 px-3 py-1 text-xs font-bold text-slate-500 border border-slate-200">Tidak Aktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-slate-500 text-xs italic max-w-xs truncate">
                                <?php echo htmlspecialchars($item['keterangan'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button onclick='openFormModal(<?php echo json_encode($item); ?>)'
                                        class="p-2 text-slate-300 hover:text-cyan-600 hover:bg-cyan-50 rounded-xl transition-all" title="Edit">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                                        </svg>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $item['id']; ?>)"
                                        class="p-2 text-slate-300 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all" title="Hapus">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <p class="text-[12px] text-slate-500 font-medium">
                    Menampilkan <span class="text-slate-800 font-bold"><?php echo $offset + 1; ?></span> - <span class="text-slate-800 font-bold"><?php echo min($offset + $limit, $total_rows); ?></span> dari <span class="text-slate-800 font-bold"><?php echo $total_rows; ?></span> data
                </p>
                <div class="flex gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&filter_ay=<?php echo $filter_ay; ?>&filter_unit=<?php echo $filter_unit; ?>&filter_program=<?php echo $filter_program; ?>&filter_kelas=<?php echo $filter_kelas; ?>&filter_status=<?php echo $filter_status; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_loop = max(1, $page - 2);
                    $end_loop = min($total_pages, $page + 2);
                    for ($i = $start_loop; $i <= $end_loop; $i++): 
                    ?>
                        <a href="?page=<?php echo $i; ?>&limit=<?php echo $limit; ?>&filter_ay=<?php echo $filter_ay; ?>&filter_unit=<?php echo $filter_unit; ?>&filter_program=<?php echo $filter_program; ?>&filter_kelas=<?php echo $filter_kelas; ?>&filter_status=<?php echo $filter_status; ?>" 
                           class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all <?php echo ($i == $page) ? 'bg-cyan-600 text-white shadow-md shadow-cyan-600/20' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&filter_ay=<?php echo $filter_ay; ?>&filter_unit=<?php echo $filter_unit; ?>&filter_program=<?php echo $filter_program; ?>&filter_kelas=<?php echo $filter_kelas; ?>&filter_status=<?php echo $filter_status; ?>" class="p-2 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 shadow-sm transition-all">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Form -->
<div id="formModal" class="fixed inset-0 z-[60] invisible transition-all duration-300 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeFormModal()"></div>
        
        <div id="modalContent" class="relative bg-white rounded-2xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-lg border border-slate-100 overflow-hidden">
            <form id="targetForm" onsubmit="submitForm(event)">
                <input type="hidden" name="id" id="form-id">
                
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800" id="modal-title">Tambah Target Hafalan</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">Pengelolaan Target Hafalan Dinamis</p>
                    </div>
                    <button type="button" onclick="closeFormModal()" class="p-2 rounded-lg border border-slate-200 text-slate-400 hover:text-rose-600 shadow-sm transition-all group">
                        <svg class="h-5 w-5 transform group-hover:rotate-90 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="px-6 py-6 space-y-5">
                    <!-- Tahun Ajaran -->
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Tahun Ajaran <span class="text-rose-500">*</span></label>
                        <select name="tahun_ajaran_id" id="form-tahun_ajaran_id" required 
                            class="hybrid-select block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer">
                            <option value="">Pilih Tahun Ajaran...</option>
                            <?php foreach ($academic_years as $ay): ?>
                                <option value="<?php echo $ay['id']; ?>">
                                    <?php echo htmlspecialchars($ay['name'] . ' - ' . $ay['semester']); ?> <?php echo $ay['is_active'] ? '(Aktif)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Unit Pendidikan -->
                        <div class="space-y-1">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Unit Pendidikan <span class="text-rose-500">*</span></label>
                            <select name="unit_id" id="form-unit_id" required onchange="handleUnitChange()"
                                class="hybrid-select block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer" data-searchable="false">
                                <option value="">Pilih Unit...</option>
                                <?php foreach ($units as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" data-name="<?php echo htmlspecialchars($u['name']); ?>">
                                        <?php echo htmlspecialchars($u['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Program (Conditional) -->
                        <div class="space-y-1" id="program-field-wrapper">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Program MTs <span class="text-rose-500">*</span></label>
                            <select name="program_id" id="form-program_id"
                                class="hybrid-select block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer" data-searchable="false">
                                <option value="">Pilih Program...</option>
                                <option value="Fullday">Fullday</option>
                                <option value="Boarding">Boarding</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Kelas -->
                        <div class="space-y-1">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Kelas <span class="text-rose-500">*</span></label>
                            <select name="kelas_id" id="form-kelas_id" required
                                class="hybrid-select block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer" data-searchable="false">
                                <option value="">Pilih Kelas (Pilih unit dahulu)...</option>
                            </select>
                        </div>

                        <!-- Target Juz -->
                        <div class="space-y-1">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Target Hafalan (Juz) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.01" min="0.01" max="30" name="target_juz" id="form-target_juz" required placeholder="masukkan target"
                                class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all placeholder:text-slate-350">
                        </div>
                    </div>

                    <!-- Status Aktif -->
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Status</label>
                        <select name="status_aktif" id="form-status_aktif" class="hybrid-select block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 focus:outline-none transition-all cursor-pointer" data-searchable="false">
                            <option value="Aktif">Aktif</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>

                    <!-- Keterangan -->
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1">Keterangan</label>
                        <textarea name="keterangan" id="form-keterangan" rows="3" placeholder="Tambahkan catatan jika diperlukan..."
                            class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 focus:bg-white focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 outline-none transition-all resize-none placeholder:text-slate-350"></textarea>
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex flex-col sm:flex-row-reverse gap-3">
                    <button type="submit" class="sm:flex-1 h-12 rounded-xl bg-gradient-to-br from-cyan-500 to-blue-600 text-white font-bold text-sm shadow-md hover:from-cyan-600 hover:to-blue-700 transition-all active:scale-[0.98]">
                        Simpan Target
                    </button>
                    <button type="button" onclick="closeFormModal()" class="sm:flex-1 h-12 rounded-xl border border-slate-200 bg-white text-sm font-bold text-slate-500 hover:bg-slate-50 transition-colors">
                        Batalkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function handleUnitChange(selectedKelas = null) {
    const unitSelect = document.getElementById('form-unit_id');
    const programWrapper = document.getElementById('program-field-wrapper');
    const programSelect = document.getElementById('form-program_id');
    const kelasSelect = document.getElementById('form-kelas_id');
    
    const selectedOption = unitSelect.options[unitSelect.selectedIndex];
    const unitName = selectedOption ? selectedOption.getAttribute('data-name') : '';
    
    // Clear kelas select options
    kelasSelect.innerHTML = '<option value="">Pilih Kelas...</option>';
    
    if (unitName === 'MTs') {
        // Show Program MTs and make it required
        programWrapper.style.display = 'block';
        programSelect.required = true;
        
        // Add kelas 7, 8, 9
        for (let i = 7; i <= 9; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = 'Kelas ' + i;
            if (selectedKelas && selectedKelas == i) opt.selected = true;
            kelasSelect.appendChild(opt);
        }
    } else if (unitName === 'MA') {
        // Hide Program MTs and disable required
        programWrapper.style.display = 'none';
        programSelect.required = false;
        programSelect.value = '';
        
        // Add kelas 10, 11, 12
        for (let i = 10; i <= 12; i++) {
            const opt = document.createElement('option');
            opt.value = i;
            opt.textContent = 'Kelas ' + i;
            if (selectedKelas && selectedKelas == i) opt.selected = true;
            kelasSelect.appendChild(opt);
        }
    } else {
        programWrapper.style.display = 'none';
        programSelect.required = false;
        programSelect.value = '';
        kelasSelect.innerHTML = '<option value="">Pilih unit dahulu...</option>';
    }

    // Refresh hybrid select UI for Kelas and Program
    setTimeout(() => {
        refreshHybridSelect('form-kelas_id');
        refreshHybridSelect('form-program_id');
    }, 10);
}

function openFormModal(data = null) {
    const modal = document.getElementById('formModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const form = document.getElementById('targetForm');
    const title = document.getElementById('modal-title');
    
    form.reset();
    document.getElementById('form-id').value = '';
    document.getElementById('form-status_aktif').value = 'Aktif';
    
    // Reset fields visibility
    document.getElementById('program-field-wrapper').style.display = 'none';
    document.getElementById('form-program_id').required = false;
    document.getElementById('form-kelas_id').innerHTML = '<option value="">Pilih unit dahulu...</option>';

    if (data) {
        title.textContent = 'Edit Target Hafalan';
        document.getElementById('form-id').value = data.id;
        document.getElementById('form-tahun_ajaran_id').value = data.tahun_ajaran_id;
        document.getElementById('form-unit_id').value = data.unit_id;
        
        // Trigger unit change event to render appropriate class options
        handleUnitChange(data.kelas_id);
        
        if (data.program_id) {
            document.getElementById('form-program_id').value = data.program_id;
        }
        
        document.getElementById('form-target_juz').value = data.target_juz;
        document.getElementById('form-status_aktif').value = data.status_aktif;
        document.getElementById('form-keterangan').value = data.keterangan || '';

        // Refresh all hybrid selects
        setTimeout(() => {
            refreshHybridSelect('form-tahun_ajaran_id');
            refreshHybridSelect('form-unit_id');
            refreshHybridSelect('form-program_id');
            refreshHybridSelect('form-kelas_id');
            refreshHybridSelect('form-status_aktif');
        }, 20);
    } else {
        title.textContent = 'Tambah Target Hafalan';

        // Reset hybrid selects placeholder & options list
        setTimeout(() => {
            refreshHybridSelect('form-tahun_ajaran_id');
            refreshHybridSelect('form-unit_id');
            refreshHybridSelect('form-program_id');
            refreshHybridSelect('form-kelas_id');
            refreshHybridSelect('form-status_aktif');
        }, 20);
    }

    modal.classList.remove('invisible');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        modalContent.classList.remove('opacity-0', 'scale-95');
    }, 10);
}

function closeFormModal() {
    const modal = document.getElementById('formModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    
    overlay.classList.add('opacity-0');
    modalContent.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('invisible');
    }, 300);
}

async function submitForm(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData.entries());

    try {
        const response = await fetch('../../api/tahfidz/save_target_hafalan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        if (response.ok && result.success) {
            const url = new URL(window.location.href);
            url.searchParams.set('success', result.message);
            window.location.href = url.toString();
        } else {
            showToast(result.message || 'Terjadi kesalahan.', 'error');
        }
    } catch (e) {
        showToast('Terjadi kesalahan saat memproses data.', 'error');
    }
}

function confirmDelete(id) {
    Swal.fire({
        title: 'Hapus Target Hafalan?',
        text: "Data yang dihapus tidak dapat dikembalikan dan akan memengaruhi perhitungan progress rekap santri!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        borderRadius: '20px'
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch('../../api/tahfidz/delete_target_hafalan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const result = await response.json();
                if (result.success) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('success', result.message);
                    window.location.href = url.toString();
                } else {
                    showToast(result.message || 'Gagal menghapus.', 'error');
                }
            } catch (e) {
                showToast('Gagal menghubungi server.', 'error');
            }
        }
    });
}
function refreshHybridSelect(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return;
    
    // Find the hybrid container, which is the previous sibling
    const container = select.previousElementSibling;
    if (!container || !container.classList.contains('hybrid-select-container')) return;
    
    // Get currently selected option text
    const currentText = select.options[select.selectedIndex]?.text || 'Pilih...';
    
    // Update placeholder
    const mainInput = container.querySelector('.hybrid-search-input');
    if (mainInput) {
        mainInput.placeholder = currentText;
        mainInput.value = '';
    }
    
    // Re-render options in the dropdown list
    const optionsList = container.querySelector('.options-list');
    const dropdown = container.querySelector('.hybrid-select-dropdown');
    const arrow = container.querySelector('svg');
    if (optionsList) {
        optionsList.innerHTML = '';
        Array.from(select.options).forEach(opt => {
            const div = document.createElement('div');
            div.className = `hybrid-option ${opt.selected ? 'selected' : ''}`;
            div.textContent = opt.text;
            div.onclick = () => {
                select.value = opt.value;
                select.dispatchEvent(new Event('change'));
                mainInput.placeholder = opt.text;
                mainInput.value = '';
                dropdown.classList.remove('active');
                if (arrow) arrow.classList.remove('rotate-180');
            };
            optionsList.appendChild(div);
        });
        if (optionsList.innerHTML === '') {
            optionsList.innerHTML = '<div class="p-8 text-center text-xs text-slate-405 font-bold uppercase tracking-widest">Tidak ditemukan</div>';
        }
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
