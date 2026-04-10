<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';

// Build WHERE Clause
$where = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where .= " AND e.unit_id = :unit_id ";
    $params[':unit_id'] = $unit_id;
}

// Query Rekapitulasi
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email,
        u.name as unit_name, 
        d.name as division_name, 
        (SELECT COUNT(id) FROM attendances WHERE user_id = e.id AND date BETWEEN :start_date AND :end_date) as total_attendance
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':start_date', $start_date);
$stmt->bindValue(':end_date', $end_date);
foreach ($params as $key => $val) {
    if ($key !== ':start_date' && $key !== ':end_date') {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi Pegawai - Export PDF</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; }
            @page { size: A4 landscape; margin: 1cm; }
        }
        table { page-break-inside: auto; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="p-8 bg-white text-slate-900" onload="window.print()">
    <div class="mb-10 border-b-2 border-slate-200 pb-6 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black uppercase tracking-tight text-slate-800">Rekap Absensi Pegawai</h1>
            <p class="text-sm text-slate-500 mt-2 font-medium">Periode: <span class="text-slate-900 font-bold"><?php echo date('d M Y', strtotime($start_date)); ?></span> - <span class="text-slate-900 font-bold"><?php echo date('d M Y', strtotime($end_date)); ?></span></p>
        </div>
        <div class="text-right">
            <h2 class="font-black text-xl text-cyan-700 tracking-tighter">DASHBOARD YAC</h2>
            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Laporan Kehadiran Karyawan</p>
        </div>
    </div>

    <table class="min-w-full text-xs border-separate border-spacing-0">
        <thead>
            <tr class="bg-slate-50 text-slate-500 font-bold uppercase tracking-widest border-b border-slate-300">
                <th class="py-4 px-3 text-center border-b border-slate-300 w-12">No.</th>
                <th class="py-4 px-3 text-left border-b border-slate-300">Nama Lengkap</th>
                <th class="py-4 px-3 text-left border-b border-slate-300">Unit Kerja</th>
                <th class="py-4 px-3 text-left border-b border-slate-300">Bidang</th>
                <th class="py-4 px-3 text-center border-b border-slate-300 w-32">Total Kehadiran</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php foreach ($summary as $index => $row): ?>
                <tr>
                    <td class="py-4 px-3 text-center font-bold text-slate-400"><?php echo $index + 1; ?>.</td>
                    <td class="py-4 px-3">
                        <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($row['full_name']); ?></div>
                        <div class="text-[10px] text-slate-400 font-medium"><?php echo htmlspecialchars($row['email']); ?></div>
                    </td>
                    <td class="py-4 px-3 text-slate-600 font-medium uppercase tracking-tight"><?php echo htmlspecialchars($row['unit_name'] ?: '-'); ?></td>
                    <td class="py-4 px-3 text-slate-600 font-medium uppercase tracking-tight"><?php echo htmlspecialchars($row['division_name'] ?: '-'); ?></td>
                    <td class="py-4 px-3 text-center font-black text-slate-900 text-sm"><?php echo $row['total_attendance']; ?> HARI</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="mt-12 flex justify-end">
        <div class="text-center w-64 border-t border-slate-200 pt-4">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Dicetak Pada</p>
            <p class="text-xs font-bold text-slate-800"><?php echo date('d F Y, H:i'); ?></p>
        </div>
    </div>
</body>
</html>
