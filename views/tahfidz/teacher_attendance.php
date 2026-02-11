<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
$page_title = "Absensi Pengampu Tahfidz";

$db = new Database();
$conn = $db->getConnection();

// --- Filter Parameters ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d'); // Default today
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// --- Build Query ---
$query = "
    SELECT 
        ta.*,
        e.full_name AS teacher_name
    FROM tahfidz_teacher_attendance ta
    LEFT JOIN employees e ON ta.teacher_id = e.id
    WHERE ta.date BETWEEN :start_date AND :end_date
    ORDER BY ta.date DESC, ta.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6">
    <!-- Header & Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">Absensi Pengampu</h1>
            <p class="text-slate-500 mt-1">Data kehadiran guru Tahfidz.</p>
        </div>
        <form method="GET" class="flex flex-wrap gap-3 items-end">
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
                        <th class="px-6 py-4">Nama Pengampu</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Jam Masuk</th>
                        <th class="px-6 py-4 text-center">Jam Pulang</th>
                        <th class="px-6 py-4">Catatan</th>
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
                            <?php echo htmlspecialchars($row['teacher_name'] ?? 'Unknown'); ?>
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
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo $row['check_in_time'] ? date('H:i', strtotime($row['check_in_time'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-center text-slate-600">
                            <?php echo $row['check_out_time'] ? date('H:i', strtotime($row['check_out_time'])) : '-'; ?>
                        </td>
                        <td class="px-6 py-4 text-slate-500 italic">
                            <?php echo htmlspecialchars($row['notes'] ?? '-'); ?>
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
