<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
$page_title = "Absensi Santri Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Parameters ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$unit_filter = isset($_GET['unit']) ? $_GET['unit'] : '';

// --- Fetch Units for Filter ---
$units = $conn->query("SELECT DISTINCT tingkat FROM students WHERE tingkat IS NOT NULL AND tingkat != '' ORDER BY tingkat")->fetchAll(PDO::FETCH_COLUMN);

// --- Build Query ---
$query = "
    SELECT 
        ta.*,
        s.nama_siswa AS student_name,
        s.kelas AS student_class,
        s.tingkat AS student_level,
        e.full_name AS teacher_name
    FROM tahfidz_attendance ta
    LEFT JOIN students s ON ta.student_id = s.id
    LEFT JOIN employees e ON ta.teacher_id = e.id
    WHERE ta.date BETWEEN :start_date AND :end_date
";

$params = [
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

if (!empty($unit_filter)) {
    $query .= " AND s.tingkat = :unit";
    $params[':unit'] = $unit_filter;
}

$query .= " ORDER BY ta.date DESC, ta.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Absensi Santri</h1>
            <p class="text-slate-500 mt-1">Data kehadiran santri Tahfidz.</p>
        </div>
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Unit</label>
                <select name="unit" class="rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Semua</option>
                    <?php foreach ($units as $u): ?>
                        <option value="<?php echo htmlspecialchars($u); ?>" <?php echo ($unit_filter == $u) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                       class="rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                       class="rounded-lg border-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
                Filter
            </button>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4 w-16 text-center">No</th>
                        <th class="px-6 py-4">Tanggal</th>
                        <th class="px-6 py-4">Nama Santri</th>
                        <th class="px-6 py-4 text-center">Kelas</th>
                        <th class="px-6 py-4 text-center">Sesi</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4">Pencatat</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php 
                    $no = 1;
                    if (count($data) > 0): 
                        foreach ($data as $row): 
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-center text-slate-400"><?php echo $no++; ?></td>
                        <td class="px-6 py-4 text-slate-600">
                            <?php echo date('d/m/Y', strtotime($row['date'])); ?>
                        </td>
                        <td class="px-6 py-4 font-medium text-slate-800">
                            <?php echo htmlspecialchars($row['student_name']); ?>
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo htmlspecialchars($row['student_level']); ?> - <?php echo htmlspecialchars($row['student_class']); ?>
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo htmlspecialchars($row['session'] ?? '-'); ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                             <?php
                                $statusClass = 'bg-slate-100 text-slate-600';
                                $s = strtolower($row['status']);
                                if ($s == 'hadir') $statusClass = 'bg-green-100 text-green-700';
                                elseif ($s == 'sakit') $statusClass = 'bg-blue-100 text-blue-700';
                                elseif ($s == 'izin') $statusClass = 'bg-yellow-100 text-yellow-700';
                                elseif ($s == 'alpha') $statusClass = 'bg-red-100 text-red-700';
                            ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                        </td>
                         <td class="px-6 py-4 text-slate-500 italic">
                            <?php echo htmlspecialchars($row['teacher_name'] ?? '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-slate-500">
                            Tidak ada data absensi pada periode ini.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
