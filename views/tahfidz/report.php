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

// --- Fetch Units for Filter ---
$units = $conn->query("SELECT DISTINCT tingkat FROM students WHERE tingkat IS NOT NULL AND tingkat != '' ORDER BY tingkat")->fetchAll(PDO::FETCH_COLUMN);

// --- Build Query ---
$query = "
    SELECT 
        tm.*,
        s.nama_siswa AS student_name,
        s.kelas AS student_class,
        s.tingkat AS student_level,
        e.full_name AS teacher_name
    FROM tahfidz_memorization tm
    LEFT JOIN students s ON tm.student_id = s.id
    LEFT JOIN employees e ON tm.teacher_id = e.id
    WHERE tm.date BETWEEN :start_date AND :end_date
";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak PDF
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <form id="filter-form" method="GET" class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 justify-between items-center no-print">
        <!-- Search -->
        <div class="relative w-full sm:w-80">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
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
                    <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" id="filter-unit-arrow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
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
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
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
                        <th class="px-6 py-3 min-w-[150px]">Unit / Kelas</th>
                        <th class="px-6 py-3 min-w-[250px]">Capaian Hafalan</th>
                        <th class="px-6 py-3 text-center min-w-[80px]">Juz</th>
                        <th class="px-6 py-3 min-w-[120px]">Status</th>
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
                        <td class="px-6 py-4 font-medium text-slate-800">
                            <?php echo htmlspecialchars($row['student_name']); ?>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <?php echo htmlspecialchars($row['student_level']); ?> - <?php echo htmlspecialchars($row['student_class']); ?>
                        </td>
                        <td class="px-6 py-4 text-slate-700">
                            <?php echo htmlspecialchars($row['surah_start']); ?>:<?php echo $row['ayat_start']; ?>
                            <span class="mx-1 text-slate-300">s.d</span>
                            <?php echo htmlspecialchars($row['surah_end']); ?>:<?php echo $row['ayat_end']; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs font-bold ring-1 ring-slate-200">
                                <?php echo $row['juz']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php
                                $statusClass = 'bg-slate-100 text-slate-600';
                                $s = strtolower($row['status']);
                                if (strpos($s, 'lancar') !== false) $statusClass = 'bg-green-100 text-green-700';
                                elseif (strpos($s, 'ulang') !== false) $statusClass = 'bg-red-100 text-red-700';
                                elseif (strpos($s, 'kurang') !== false) $statusClass = 'bg-yellow-100 text-yellow-700';
                            ?>
                            <div class="status-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-500 text-xs">
                            <?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-slate-500">
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
