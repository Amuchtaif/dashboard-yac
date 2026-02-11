<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
$page_title = "Laporan Hafalan Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Parameters ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$unit_filter = isset($_GET['unit']) ? $_GET['unit'] : '';

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
    <!-- Header & Filter -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 filter-section">
        <form method="GET" class="flex flex-col lg:flex-row gap-4 lg:items-end">
            <div class="w-full lg:w-1/4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Unit Pendidikan</label>
                <select name="unit" class="w-full rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-sm">
                    <option value="">Semua Unit</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo ($unit_filter == $u) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="w-full lg:w-1/4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                       class="w-full rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>

            <div class="w-full lg:w-1/4">
                <label class="block text-sm font-medium text-slate-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                       class="w-full rounded-lg border-slate-200 focus:border-blue-500 focus:ring-blue-500 text-sm">
            </div>

            <div class="w-full lg:w-auto flex gap-2">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                    Tampilkan
                </button>
                <button type="button" onclick="window.print()" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Cetak PDF
                </button>
            </div>
        </form>
    </div>

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
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-700 uppercase tracking-wider">
                        <th class="px-6 py-3 w-16 text-center">No</th>
                        <th class="px-6 py-3">Tanggal Waktu</th>
                        <th class="px-6 py-3">Nama Santri</th>
                        <th class="px-6 py-3">Unit / Kelas</th>
                        <th class="px-6 py-3">Capaian Hafalan</th>
                        <th class="px-6 py-3 text-center">Juz</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Pengampu</th>
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

<?php include '../layouts/footer.php'; ?>
