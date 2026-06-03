<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$target_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

// Build WHERE Clause (Base Employees)
$where_emp = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params_emp = [':target_date' => $target_date];

if ($division_id) {
    $where_emp .= " AND e.division_id = :division_id ";
    $params_emp[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_emp .= " AND e.unit_id = :unit_id ";
    $params_emp[':unit_id'] = $unit_id;
}

// 1. Query Belum Absen
$query_absent = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where_emp
    AND e.id NOT IN (SELECT user_id FROM attendances WHERE date = :target_date)
    ORDER BY e.full_name ASC
";

$stmt_absent = $conn->prepare($query_absent);
foreach ($params_emp as $key => $val) {
    $stmt_absent->bindValue($key, $val);
}
$stmt_absent->execute();
$absent_employees = $stmt_absent->fetchAll(PDO::FETCH_ASSOC);

// 2. Query Telat
$query_late = "
    SELECT 
        e.id, 
        e.full_name, 
        u.name as unit_name, 
        d.name as division_name,
        a.time_in as check_in_time,
        a.status as attendance_status
    FROM attendances a
    JOIN employees e ON a.user_id = e.id
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    " . str_replace('e.', 'e.', $where_emp) . "
    AND a.date = :target_date
    AND a.status IN ('Telat', 'Late')
    ORDER BY a.time_in ASC
";

$stmt_late = $conn->prepare($query_late);
foreach ($params_emp as $key => $val) {
    $stmt_late->bindValue($key, $val);
}
$stmt_late->execute();
$late_employees = $stmt_late->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Harian Absensi - <?php echo date('d M Y', strtotime($target_date)); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            @page { size: A4 portrait; margin: 1.5cm; }
        }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="p-8 bg-white text-slate-900" onload="window.print()">
    <div class="mb-10 border-b-2 border-slate-200 pb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tight text-slate-800">Rekap Harian Absensi</h1>
            <p class="text-sm text-slate-500 mt-2 font-medium">Tanggal: <span class="text-slate-900 font-bold"><?php echo date('d F Y', strtotime($target_date)); ?></span></p>
        </div>
        <div class="text-right">
            <h2 class="font-black text-xl text-cyan-700 tracking-tighter">DASHBOARD YAC</h2>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Laporan Ketidakhadiran & Keterlambatan</p>
        </div>
    </div>

    <!-- Section 1: Belum Absen -->
    <div class="mb-10">
        <h3 class="text-sm font-black text-rose-600 uppercase tracking-[0.2em] mb-4 flex items-center">
            <span class="w-4 h-1 bg-rose-600 mr-2"></span>
            Pegawai Belum Absen (<?php echo count($absent_employees); ?>)
        </h3>
        <table class="min-w-full text-xs border-separate border-spacing-0 border border-slate-200 rounded-lg overflow-hidden">
            <thead>
                <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-widest border-b border-slate-200">
                    <th class="py-3 px-3 text-center border-b border-slate-200 w-12">No.</th>
                    <th class="py-3 px-3 text-left border-b border-slate-200">Nama Pegawai</th>
                    <th class="py-3 px-3 text-left border-b border-slate-200">Unit Kerja</th>
                    <th class="py-3 px-3 text-left border-b border-slate-200">Bidang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($absent_employees) > 0): ?>
                    <?php foreach ($absent_employees as $index => $row): ?>
                        <tr>
                            <td class="py-3 px-3 text-center font-bold text-slate-400"><?php echo $index + 1; ?>.</td>
                            <td class="py-3 px-3 font-bold text-slate-800"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="py-3 px-3 text-slate-600 uppercase tracking-tight"><?php echo htmlspecialchars($row['unit_name'] ?: '-'); ?></td>
                            <td class="py-3 px-3 text-slate-600 uppercase tracking-tight"><?php echo htmlspecialchars($row['division_name'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-400 italic font-medium">Semua pegawai sudah absen.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Section 2: Telat -->
    <div>
        <h3 class="text-sm font-black text-amber-600 uppercase tracking-[0.2em] mb-4 flex items-center">
            <span class="w-4 h-1 bg-amber-600 mr-2"></span>
            Pegawai Terlambat (<?php echo count($late_employees); ?>)
        </h3>
        <table class="min-w-full text-xs border-separate border-spacing-0 border border-slate-200 rounded-lg overflow-hidden">
            <thead>
                <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-widest border-b border-slate-200">
                    <th class="py-3 px-3 text-center border-b border-slate-200 w-12">No.</th>
                    <th class="py-3 px-3 text-left border-b border-slate-200">Nama Pegawai</th>
                    <th class="py-3 px-3 text-left border-b border-slate-200">Unit Kerja</th>
                    <th class="py-3 px-3 text-center border-b border-slate-200 w-32">Waktu Absen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (count($late_employees) > 0): ?>
                    <?php foreach ($late_employees as $index => $row): ?>
                        <tr>
                            <td class="py-3 px-3 text-center font-bold text-slate-400"><?php echo $index + 1; ?>.</td>
                            <td class="py-3 px-3 font-bold text-slate-800"><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td class="py-3 px-3 text-slate-600 uppercase tracking-tight"><?php echo htmlspecialchars($row['unit_name'] ?: '-'); ?></td>
                            <td class="py-3 px-3 text-center font-black text-rose-600 text-sm"><?php echo date('H:i:s', strtotime($row['check_in_time'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="py-8 text-center text-slate-400 italic font-medium">Tidak ada pegawai yang terlambat.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-12 flex justify-between items-start">
        <div class="text-[10px] text-slate-400 italic">
            * Laporan ini dihasilkan secara otomatis oleh sistem.
        </div>
        <div class="text-center w-64 border-t border-slate-200 pt-4">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Dicetak Pada</p>
            <p class="text-xs font-bold text-slate-800"><?php echo date('d F Y, H:i'); ?></p>
        </div>
    </div>
</body>
</html>
