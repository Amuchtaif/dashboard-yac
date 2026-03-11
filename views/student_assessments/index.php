<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$page_title = "Data Penilaian Siswa";

$db = new Database();
$conn = $db->getConnection();

// --- Logika Paginasi ---
$limit = isset($_GET['limit']) && in_array($_GET['limit'], [10, 50, 100]) ? (int) $_GET['limit'] : 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// --- Logika Filter ---
$search = $_GET['search'] ?? '';
$unit_id = $_GET['unit_id'] ?? '';
$class_id = $_GET['class_id'] ?? '';
$subject_id = $_GET['subject_id'] ?? '';

$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(s.name LIKE :search OR at.name LIKE :search OR e.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "gl.education_unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($class_id) {
    $where_clauses[] = "sa.grade_level_id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($subject_id) {
    $where_clauses[] = "sa.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Total baris untuk paginasi
$count_query = "
    SELECT COUNT(*) 
    FROM student_assessments sa 
    JOIN subjects s ON sa.subject_id = s.id 
    JOIN grade_levels gl ON sa.grade_level_id = gl.id
    LEFT JOIN employees e ON sa.teacher_id = e.id
    JOIN assessment_types at ON sa.assessment_type_id = at.id
    $where_sql
";
$total_stmt = $conn->prepare($count_query);
$total_stmt->execute($params);
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Ambil data penilaian
$query = "
    SELECT 
        sa.*, 
        s.name as subject_name, 
        gl.name as class_name, 
        gl.education_unit_id,
        at.name as assessment_type_name,
        e.full_name as teacher_name,
        (SELECT AVG(score) FROM student_assessment_details WHERE assessment_id = sa.id) as avg_score,
        (SELECT COUNT(*) FROM student_assessment_details WHERE assessment_id = sa.id) as student_count
    FROM student_assessments sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN grade_levels gl ON sa.grade_level_id = gl.id
    JOIN assessment_types at ON sa.assessment_type_id = at.id
    LEFT JOIN employees e ON sa.teacher_id = e.id
    $where_sql
    ORDER BY sa.assessment_date DESC, sa.created_at DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Data untuk filter dropdown
$units = $conn->query("SELECT id, name FROM education_units ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$classes = $conn->query("SELECT id, name, education_unit_id FROM grade_levels ORDER BY level_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("SELECT id, name FROM subjects ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="pb-10 space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Pantau riwayat perolehan nilai siswa secara real-time.</p>
        </div>
    </div>

    <!-- Filter Bar (Custom Searchable Dropdowns Like Jurnal Kelas) -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" id="filterForm" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <input type="hidden" name="limit" value="<?php echo $limit; ?>">
            
            <!-- Search -->
            <div class="relative group">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                        class="block w-full rounded-xl border-slate-300 pl-10 pr-3 py-2 text-sm focus:border-cyan-500 focus:ring-4 focus:ring-cyan-500/5 bg-slate-50 transition-all outline-none h-[42px]" 
                        placeholder="Cari guru/mapel/jenis...">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400 group-focus-within:text-cyan-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                </div>
            </div>

            <!-- Custom Jenjang Dropdown -->
            <div class="relative" id="container-unit_id">
                <input type="hidden" name="unit_id" id="input-unit_id" value="<?php echo $unit_id; ?>">
                <button type="button" onclick="toggleFormDropdown('unit_id')"
                    class="flex h-[42px] w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all">
                    <span id="text-unit_id" class="block truncate font-medium">
                        <?php 
                        $unitTitle = "Semua Jenjang";
                        foreach($units as $u) if((string)$u['id'] === (string)$unit_id) $unitTitle = $u['name'];
                        echo htmlspecialchars($unitTitle);
                        ?>
                    </span>
                    <svg id="arrow-unit_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-unit_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-unit_id" onkeyup="filterDropdownSearch('unit_id')" placeholder="Cari unit..." class="block w-full rounded-lg border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 outline-none">
                    </div>
                    <ul id="list-unit_id">
                        <li onclick="selectFilterOption('unit_id', '', 'Semua Jenjang')" class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium">Semua Jenjang</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit_id', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium">
                                <?php echo htmlspecialchars($u['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Kelas Dropdown -->
            <div class="relative" id="container-class_id">
                <input type="hidden" name="class_id" id="input-class_id" value="<?php echo $class_id; ?>">
                <button type="button" onclick="toggleFormDropdown('class_id')"
                    class="flex h-[42px] w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all">
                    <span id="text-class_id" class="block truncate font-medium">
                        <?php 
                        $classTitle = "Semua Kelas";
                        foreach($classes as $c) if((string)$c['id'] === (string)$class_id) $classTitle = $c['name'];
                        echo htmlspecialchars($classTitle);
                        ?>
                    </span>
                    <svg id="arrow-class_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-class_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-class_id" onkeyup="filterDropdownSearch('class_id')" placeholder="Cari kelas..." class="block w-full rounded-lg border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 outline-none">
                    </div>
                    <ul id="list-class_id">
                        <li onclick="selectFilterOption('class_id', '', 'Semua Kelas')" class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium">Semua Kelas</li>
                        <?php foreach ($classes as $c): ?>
                            <li onclick="selectFilterOption('class_id', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>')" 
                                data-unit="<?php echo $c['education_unit_id']; ?>"
                                class="class-option relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium"
                                <?php echo ($unit_id && $c['education_unit_id'] != $unit_id) ? 'style="display:none"' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Custom Mapel Dropdown -->
            <div class="relative" id="container-subject_id">
                <input type="hidden" name="subject_id" id="input-subject_id" value="<?php echo $subject_id; ?>">
                <button type="button" onclick="toggleFormDropdown('subject_id')"
                    class="flex h-[42px] w-full items-center justify-between rounded-xl border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all">
                    <span id="text-subject_id" class="block truncate font-medium">
                        <?php 
                        $subjTitle = "Semua Mapel";
                        foreach($subjects as $s) if((string)$s['id'] === (string)$subject_id) $subjTitle = $s['name'];
                        echo htmlspecialchars($subjTitle);
                        ?>
                    </span>
                    <svg id="arrow-subject_id" class="h-4 w-4 text-slate-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div id="menu-subject_id" class="hidden absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-xl bg-white py-1 text-base shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm right-0">
                    <div class="sticky top-0 z-10 bg-white px-2 py-1.5 border-b border-slate-100">
                        <input type="text" id="search-subject_id" onkeyup="filterDropdownSearch('subject_id')" placeholder="Cari mapel..." class="block w-full rounded-lg border-slate-200 py-1.5 pl-3 text-sm focus:border-cyan-500 focus:ring-cyan-500 outline-none">
                    </div>
                    <ul id="list-subject_id">
                        <li onclick="selectFilterOption('subject_id', '', 'Semua Mapel')" class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium">Semua Mapel</li>
                        <?php foreach ($subjects as $s): ?>
                            <li onclick="selectFilterOption('subject_id', '<?php echo $s['id']; ?>', '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>')" class="relative cursor-pointer select-none py-2.5 pl-3 pr-9 text-slate-600 hover:bg-cyan-50 hover:text-cyan-700 transition-colors font-medium">
                                <?php echo htmlspecialchars($s['name']); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-cyan-600 text-white rounded-xl px-4 py-2.5 text-sm font-bold shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 active:scale-95 transition-all h-[42px]">
                    Filter
                </button>
                <a href="index.php?limit=<?php echo $limit; ?>" class="bg-white border border-slate-200 text-slate-500 rounded-xl px-4 py-2.5 text-sm font-bold hover:bg-slate-50 hover:text-slate-800 transition-all flex items-center justify-center shadow-sm h-[42px]" title="Reset Filter">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                </a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50/50">
                    <tr>
                        <th class="px-6 py-5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Tanggal</th>
                        <th class="px-6 py-5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Kelas & Mata Pelajaran</th>
                        <th class="px-6 py-5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Nama Guru</th>
                        <th class="px-6 py-5 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Jenis</th>
                        <th class="px-6 py-5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Rerata</th>
                        <th class="px-6 py-5 text-center text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Siswa</th>
                        <th class="px-6 py-5 text-right text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">Opsi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (empty($assessments)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-20 text-center text-slate-400">
                                <div class="flex flex-col items-center">
                                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    </div>
                                    <p class="text-sm font-medium">Data penilaian tidak ditemukan.</p>
                                    <p class="text-[10px] mt-1 italic">Gunakan filter lain atau reset pencarian Anda.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($assessments as $a): ?>
                        <tr class="hover:bg-slate-50/50 transition-all group">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-slate-700"><?php echo date('d M Y', strtotime($a['assessment_date'])); ?></div>
                                <div class="text-[10px] text-slate-400 font-medium"><?php echo date('H:i', strtotime($a['created_at'])); ?> WIB</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($a['subject_name']); ?></div>
                                <div class="text-[11px] text-cyan-600 font-bold bg-cyan-50 px-2 py-0.5 rounded-lg w-fit mt-1"><?php echo htmlspecialchars($a['class_name'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-slate-600 capitalize"><?php echo htmlspecialchars(strtolower($a['teacher_name'] ?? '-')); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-500 border border-slate-200">
                                    <?php echo htmlspecialchars($a['assessment_type_name']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="inline-flex flex-col">
                                    <span class="text-base font-black <?php echo $a['avg_score'] >= 75 ? 'text-emerald-500' : 'text-amber-500'; ?>">
                                        <?php echo number_format($a['avg_score'], 1); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-xl text-[10px] font-bold border border-indigo-100"><?php echo $a['student_count']; ?> Siswa</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="viewDetail(<?php echo $a['id']; ?>)" class="p-2.5 bg-white border border-slate-200 text-slate-400 rounded-xl hover:bg-cyan-50 hover:text-cyan-600 hover:border-cyan-200 transition-all shadow-sm active:scale-95" title="Lihat Detail">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </button>
                                    <a href="print.php?id=<?php echo $a['id']; ?>" target="_blank" class="p-2.5 bg-white border border-slate-200 text-slate-400 rounded-xl hover:bg-indigo-50 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm active:scale-95" title="Cetak">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 0): ?>
            <div class="px-6 py-6 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <p class="text-xs text-slate-400 font-medium">Halaman <span class="text-slate-700"><?php echo $page; ?></span> dari <span class="text-slate-700"><?php echo max(1, $total_pages); ?></span> • <span class="text-slate-700"><?php echo $total_rows; ?></span> data ditemukan.</p>
                    <div class="flex items-center gap-3">
                        <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tampilkan</label>
                        <select onchange="window.location.href = updateQueryStringParameter(window.location.href, 'limit', this.value)" class="bg-white border border-slate-200 rounded-lg px-2 py-1 text-[11px] font-bold text-slate-700 outline-none focus:ring-4 focus:ring-cyan-500/5 transition-all cursor-pointer h-8">
                            <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-1.5 font-bold">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo $unit_id; ?>&class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 hover:bg-slate-50 active:scale-95 transition-all shadow-sm">Previous</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&limit=<?php echo $limit; ?>&search=<?php echo urlencode($search); ?>&unit_id=<?php echo $unit_id; ?>&class_id=<?php echo $class_id; ?>&subject_id=<?php echo $subject_id; ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-600 hover:bg-slate-50 active:scale-95 transition-all shadow-sm">Next Page</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Modal -->
<div id="detailModal" class="fixed inset-0 z-50 invisible transition-all duration-300 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div id="modalOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeModal()"></div>
        
        <div id="modalContent" class="relative bg-white rounded-3xl shadow-2xl transform opacity-0 scale-95 transition-all duration-300 w-full max-w-2xl border border-slate-100 overflow-hidden">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div>
                    <h3 class="text-lg font-black text-slate-800" id="modal-title">Detail Nilai Siswa</h3>
                    <p class="text-[11px] text-cyan-600 font-bold uppercase tracking-wider mt-1" id="modal-subtitle"></p>
                </div>
                <button type="button" onclick="closeModal()" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-slate-600 shadow-sm transition-all active:rotate-90">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="px-8 py-8 h-[450px] overflow-y-auto custom-scrollbar" id="modal-body">
                <div class="flex items-center justify-center h-full">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div>
                </div>
            </div>

            <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex items-center justify-end">
                <button type="button" onclick="closeModal()" class="px-8 py-3 bg-white border border-slate-200 rounded-2xl text-sm font-bold text-slate-500 hover:text-slate-800 transition-all active:scale-95 shadow-sm">
                    Tutup Jendela
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<script>
// Query String Helper
function updateQueryStringParameter(uri, key, value) {
    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
    if (uri.match(re)) {
        return uri.replace(re, '$1' + key + "=" + value + '$2');
    }
    else {
        return uri + separator + key + "=" + value;
    }
}

// Dropdown Toggle Logic (Like Class Journals)
function toggleFormDropdown(id) {
    const container = document.getElementById('container-' + id);
    const menu = document.getElementById('menu-' + id);
    const arrow = document.getElementById('arrow-' + id);
    
    // Close other menus
    document.querySelectorAll('[id^="menu-"]').forEach(m => {
        if (m !== menu) m.classList.add('hidden');
    });
    document.querySelectorAll('[id^="arrow-"]').forEach(a => {
        if (a !== arrow) a.classList.remove('rotate-180');
    });

    menu.classList.toggle('hidden');
    arrow.classList.toggle('rotate-180');
    
    // Focus search if opened
    if (!menu.classList.contains('hidden')) {
        const searchInput = document.getElementById('search-' + id);
        if (searchInput) searchInput.focus();
        
        // If opening class_id, apply unit filter immediately
        if (id === 'class_id') {
            filterDropdownSearch('class_id');
        }
    }
}

// Select Filter Option
function selectFilterOption(id, value, text) {
    document.getElementById('input-' + id).value = value;
    document.getElementById('text-' + id).innerText = text;
    document.getElementById('menu-' + id).classList.add('hidden');
    document.getElementById('arrow-' + id).classList.remove('rotate-180');

    // Cascading logic for Unit -> Class
    if (id === 'unit_id') {
        document.getElementById('input-class_id').value = '';
        document.getElementById('text-class_id').innerText = 'Semua Kelas';
    }

    document.getElementById('filterForm').submit();
}

// Filter Search inside Dropdown
function filterDropdownSearch(id) {
    const input = document.getElementById('search-' + id);
    const filter = input.value.toLowerCase();
    const list = document.getElementById('list-' + id);
    const li = list.getElementsByTagName('li');
    const unitId = document.getElementById('input-unit_id').value;

    for (let i = 0; i < li.length; i++) {
        const txtValue = li[i].textContent || li[i].innerText;
        const matchesSearch = txtValue.toLowerCase().indexOf(filter) > -1;

        if (id === 'class_id') {
            const itemUnit = li[i].getAttribute('data-unit');
            const matchesUnit = (!unitId || itemUnit === unitId || !li[i].hasAttribute('data-unit'));
            li[i].style.display = (matchesSearch && matchesUnit) ? "" : "none";
        } else {
            li[i].style.display = matchesSearch ? "" : "none";
        }
    }
}

// Close dropdowns on outside click
window.addEventListener('click', function (e) {
    document.querySelectorAll('[id^="container-"]').forEach(container => {
        if (!container.contains(e.target)) {
            const id = container.id.replace('container-', '');
            const menu = document.getElementById('menu-' + id);
            if (menu) menu.classList.add('hidden');
            const arrow = document.getElementById('arrow-' + id);
            if (arrow) arrow.classList.remove('rotate-180');
        }
    });
});

// Initialize filter on page load (legacy but keeps it stable)
window.addEventListener('load', () => {
    // Optional: add any onload specific logic here
});

async function viewDetail(id) {
    const modal = document.getElementById('detailModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    const body = document.getElementById('modal-body');
    const subtitle = document.getElementById('modal-subtitle');

    modal.classList.remove('invisible');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        modalContent.classList.remove('opacity-0', 'scale-95');
    }, 10);

    body.innerHTML = `
        <div class="flex items-center justify-center h-full">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div>
        </div>
    `;

    try {
        const response = await fetch(`../../api/grading/get_detail.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            subtitle.innerText = `${data.subject_name} • ${data.class_name} • ${data.assessment_type_name}`;
            
            let html = `
                <div class="space-y-4">
                    <table class="min-w-full">
                        <thead class="sticky top-0 bg-white shadow-sm z-10 border-b-2 border-slate-100">
                            <tr>
                                <th class="px-4 py-4 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Identitas Siswa</th>
                                <th class="px-4 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Skor</th>
                                <th class="px-4 py-4 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
            `;
            
            data.details.forEach(detail => {
                const statusColor = detail.score >= 75 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-rose-50 text-rose-600 border-rose-100';
                const statusText = detail.score >= 75 ? 'Tuntas' : 'Remedial';
                
                html += `
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-4 py-4">
                            <div class="text-[13px] font-bold text-slate-700">${detail.nama_siswa}</div>
                            <div class="text-[10px] text-slate-400 font-medium">NIS: ${detail.nomor_induk || '-'}</div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <div class="text-sm font-black ${detail.score >= 75 ? 'text-slate-800' : 'text-rose-600'}">${detail.score}</div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg border ${statusColor} text-[10px] font-black uppercase tracking-tighter">${statusText}</span>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            body.innerHTML = html;
        } else {
            body.innerHTML = `<div class="p-8 text-center text-red-500 font-bold">${result.message}</div>`;
        }
    } catch (e) {
        body.innerHTML = `<div class="p-8 text-center text-red-400 italic">Terjadi kesalahan saat mengambil detail data.</div>`;
    }
}

function closeModal() {
    const modal = document.getElementById('detailModal');
    const overlay = document.getElementById('modalOverlay');
    const modalContent = document.getElementById('modalContent');
    
    overlay.classList.add('opacity-0');
    modalContent.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('invisible');
    }, 300);
}
</script>

<?php include '../layouts/footer.php'; ?>
