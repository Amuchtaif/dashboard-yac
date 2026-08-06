<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_tahfidz');
$page_title = "Laporan Hafalan Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Parameters ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$unit_filter = isset($_GET['unit']) ? $_GET['unit'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Fetch Active Academic Year ---
$active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year_id = $active_year_stmt->fetchColumn();
if (!$active_year_id) {
    $active_year_id = 1;
}

// --- Fetch Units for Filter ---
$units_stmt = $conn->prepare("
    SELECT DISTINCT s.tingkat 
    FROM students s
    JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
    WHERE s.status = 'Aktif' AND s.tingkat IS NOT NULL AND s.tingkat != '' 
    ORDER BY s.tingkat
");
$units_stmt->execute([':active_year_id' => $active_year_id]);
$units = $units_stmt->fetchAll(PDO::FETCH_COLUMN);

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// --- Build Query ---
$query = "
    SELECT 
        tm.id, tm.student_id, tm.teacher_id, tm.date, tm.surah_start, tm.start_ayah AS ayat_start, tm.surah_end, tm.end_ayah AS ayat_end, tm.juz, tm.total_baris, tm.status, tm.entry_type, tm.notes, tm.created_at,
        s.nama_siswa AS student_name,
        COALESCE(gl.name, s.kelas) AS student_class,
        s.tingkat AS student_level,
        e.full_name AS teacher_name
    FROM memorization_entries tm
    LEFT JOIN students s ON tm.student_id = s.id
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN employees e ON tm.teacher_id = e.id
    WHERE tm.date BETWEEN :start_date AND :end_date
";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date,
    ':active_year_id' => $active_year_id
];

if (!$is_admin) {
    $query .= " AND tm.teacher_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
}

if (!empty($unit_filter)) {
    $query .= " AND s.tingkat = :unit";
    $params[':unit'] = $unit_filter;
}

if (!empty($search)) {
    $query .= " AND (s.nama_siswa LIKE :search OR tm.surah_start LIKE :search OR tm.surah_end LIKE :search OR e.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY tm.date DESC, tm.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<!-- Print Styles -->
<style>
    @media print {
        aside, header, .filter-section, .no-print {
            display: none !important;
        }
        main {
            margin: 0 !important;
            padding: 0 !important;
        }
        body {
            background-color: white !important;
            font-size: 12px;
        }
        .print-header {
            display: block !important;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100% !important;
            border-collapse: collapse !important;
        }
        th, td {
            border: 1px solid #ddd !important;
            padding: 8px !important;
        }
        .status-badge {
            border: none;
            padding: 0;
            background: none;
            font-weight: bold;
        }
    }
    .print-header {
        display: none;
    }
</style>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between no-print">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Laporan Hafalan Santri</h1>
            <p class="text-slate-500 mt-1">Laporan pencapaian hafalan Quran santri.</p>
        </div>
        <div class="flex gap-2">
            <button type="button" onclick="window.print()" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                <i class="fa-solid fa-print h-4 w-4 text-slate-500"></i>
                Cetak PDF
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <form id="filter-form" method="GET" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center no-print">
        <!-- Search -->
        <div class="relative w-full sm:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                class="block w-full rounded-lg border-slate-200 pl-10 pt-2 pb-2 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-slate-50 border placeholder:text-slate-400 text-slate-600"
                placeholder="Cari santri, surah, atau pengampu..." onchange="this.form.submit()">
        </div>

        <!-- Right side filters -->
        <div class="flex gap-2 w-full sm:w-auto flex-wrap items-center">
            <!-- Unit Filter -->
            <div class="relative group" id="filter-unit-container">
                <input type="hidden" name="unit" id="filter-unit-input" value="<?php echo $unit_filter; ?>">
                <button type="button" onclick="toggleDropdown('filter-unit')"
                    class="inline-flex items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500 transition-colors w-40">
                    <span id="filter-unit-text" class="truncate">
                        <?php
                        $displayUnit = "Semua Unit";
                        if ($unit_filter) $displayUnit = "Unit: " . $unit_filter;
                        echo htmlspecialchars($displayUnit);
                        ?>
                    </span>
                    <i id="filter-unit-arrow" class="fa-solid fa-chevron-down h-4 w-4 text-slate-400 transition-transform duration-200"></i>
                </button>
                <div id="filter-unit-menu" class="hidden absolute top-full right-0 mt-1 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-50 max-h-60 overflow-y-auto">
                    <ul class="py-1">
                        <li onclick="selectFilterOption('unit', '', 'Semua Unit')" class="cursor-pointer px-4 py-2 text-xs text-slate-500 hover:bg-slate-50 hover:text-cyan-700">Semua Unit</li>
                        <?php foreach ($units as $u): ?>
                            <li onclick="selectFilterOption('unit', '<?php echo htmlspecialchars($u, ENT_QUOTES); ?>', 'Unit: <?php echo htmlspecialchars($u, ENT_QUOTES); ?>')" class="cursor-pointer px-4 py-2 text-xs text-slate-700 hover:bg-cyan-50 hover:text-cyan-700 transition-colors">
                                <?php echo htmlspecialchars($u); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Date Range -->
            <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-lg px-2 py-1">
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="bg-transparent border-none text-xs text-slate-600 focus:ring-0 p-1 w-32" onchange="this.form.submit()">
                <span class="text-slate-400 text-xs">-</span>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="bg-transparent border-none text-xs text-slate-600 focus:ring-0 p-1 w-32" onchange="this.form.submit()">
            </div>

            <a href="report.php" class="inline-flex items-center p-2 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-red-500 focus:outline-none transition-colors" title="Reset Filters">
                <i class="fa-solid fa-xmark h-4 w-4"></i>
            </a>
        </div>
    </form>

    <!-- Print Header -->
    <div class="print-header">
        <h2 class="text-xl font-bold">Laporan Hafalan Tahfidz</h2>
        <p class="text-sm">Periode: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
        <?php if($unit_filter): ?>
            <p class="text-sm font-bold">Unit: <?php echo htmlspecialchars($unit_filter); ?></p>
        <?php endif; ?>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th class="px-6 py-3 w-16 text-center">No</th>
                        <th class="px-6 py-3 min-w-[150px]">Tanggal Waktu</th>
                        <th class="px-6 py-3 min-w-[200px]">Nama Santri</th>
                        <th class="px-6 py-3 min-w-[220px]">Capaian Hafalan</th>
                        <th class="px-6 py-3 min-w-[180px]">Kategori & Baris</th>
                        <th class="px-6 py-3 min-w-[200px]">Kelancaran & Catatan</th>
                        <th class="px-6 py-3 min-w-[180px]">Pengampu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php 
                    $no = 1;
                    if (count($data) > 0): 
                        foreach ($data as $row): 
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-center text-slate-500"><?php echo $no++; ?></td>
                        <td class="px-6 py-4 text-slate-600">
                            <?php echo date('d/m/Y', strtotime($row['date'])); ?><br>
                            <span class="text-xs text-slate-400">
                                <?php echo isset($row['created_at']) ? date('H:i', strtotime($row['created_at'])) : '-'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-800"><?php echo htmlspecialchars($row['student_name']); ?></div>
                            <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tight mt-0.5">
                                <?php echo htmlspecialchars($row['student_level']); ?> • <?php echo htmlspecialchars($row['student_class']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-slate-700 font-medium whitespace-nowrap">
                                <?php echo htmlspecialchars($row['surah_start']); ?>:<?php echo $row['ayat_start']; ?>
                                <span class="mx-1 text-slate-300">s.d</span>
                                <?php echo htmlspecialchars($row['surah_end']); ?>:<?php echo $row['ayat_end']; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                                $categoryColor = 'text-slate-600';
                                $categoryText = $row['entry_type'] ?? '-';
                                if ($row['entry_type'] === 'HAFALAN_BARU') {
                                    $categoryColor = 'text-cyan-700';
                                    $categoryText = 'Hafalan Baru';
                                } elseif ($row['entry_type'] === 'MUROJAAH') {
                                    $categoryColor = 'text-indigo-700';
                                    $categoryText = 'Murojaah';
                                } elseif ($row['entry_type'] === 'TASMI') {
                                    $categoryColor = 'text-purple-700';
                                    $categoryText = 'Tasmi';
                                } elseif ($row['entry_type'] === 'UJIAN') {
                                    $categoryColor = 'text-pink-700';
                                    $categoryText = 'Ujian';
                                }
                            ?>
                            <div class="flex flex-col gap-1 items-start">
                                <span class="text-xs font-semibold <?php echo $categoryColor; ?>">
                                    <?php echo htmlspecialchars($categoryText); ?>
                                </span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight mt-0.5"><?php echo htmlspecialchars($row['total_baris'] ?? '0'); ?> Baris</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                                $statusColor = 'text-slate-600';
                                $s = strtolower($row['status'] ?? '');
                                if (strpos($s, 'kurang') !== false) $statusColor = 'text-amber-600';
                                elseif (strpos($s, 'lancar') !== false) $statusColor = 'text-green-650';
                                elseif (strpos($s, 'ulang') !== false || strpos($s, 'tidak') !== false) $statusColor = 'text-red-600';
                                elseif ($s !== '') $statusColor = 'text-slate-700';
                            ?>
                            <div class="flex flex-col gap-1 items-start">
                                <span class="text-xs font-medium <?php echo $statusColor; ?>">
                                    <?php echo htmlspecialchars($row['status'] ?: '-'); ?>
                                </span>
                                <?php if (!empty($row['notes']) && $row['notes'] !== '-'): ?>
                                    <span class="text-xs text-slate-500 italic mt-0.5"><?php echo htmlspecialchars($row['notes']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-xs">
                            <?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada data hafalan pada periode ini.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    function selectFilterOption(name, value, text) {
        document.getElementById('filter-' + name + '-input').value = value;
        document.getElementById('filter-form').submit();
    }
</script>

<?php include '../layouts/footer.php'; ?>
