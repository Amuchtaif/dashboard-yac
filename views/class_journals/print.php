<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if (!isset($_GET['id'])) {
    die("ID required");
}

$id = $_GET['id'];
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT 
        cj.*,
        gl.name as class_name,
        s.name as subject_name,
        e.full_name as teacher_name,
        cs.day,
        lp.start_time,
        COALESCE(lp_end.end_time, lp.end_time) as end_time,
        eu.name as unit_name
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN grade_levels gl ON cs.grade_level_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
    LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
    JOIN employees e ON cj.teacher_id = e.id
    WHERE cj.id = :id
");
$stmt->execute([':id' => $id]);
$journal = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$journal) {
    die("Data not found");
}

// Fetch student attendance
$stmt = $conn->prepare("
    SELECT 
        sa.status,
        st.nama_siswa as student_name,
        st.nomor_induk as nis
    FROM student_attendances sa
    JOIN students st ON sa.student_id = st.id
    WHERE sa.class_journal_id = :journal_id
    ORDER BY st.nama_siswa ASC
");
$stmt->execute([':journal_id' => $id]);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// counts
$counts = ['present' => 0, 'absent' => 0, 'sick' => 0, 'permit' => 0, 'late' => 0];
foreach($attendances as $a) {
    $status = strtolower($a['status']);
    if(isset($counts[$status])) $counts[$status]++;
}

$days_id = [
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
    'Sunday' => 'Minggu'
];
$day_name = isset($days_id[$journal['day']]) ? $days_id[$journal['day']] : $journal['day'];

$months_id = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];
$date_parts = explode('-', $journal['date']);
$indonesian_date = $date_parts[2] . ' ' . $months_id[$date_parts[1]] . ' ' . $date_parts[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Jurnal - <?php echo htmlspecialchars($journal['class_name'] . ' - ' . $journal['subject_name']); ?></title>
    <!-- Use standard Tailwind via CDN for print -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { 
                print-color-adjust: exact; 
                -webkit-print-color-adjust: exact; 
                font-size: 11pt;
            }
            .no-print { display: none !important; }
            @page {
                margin: 0.5cm;
            }
        }
        body {
            font-family: Arial, sans-serif;
            color: #000;
        }
        .header-box { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .data-table th, .data-table td { border: 1px solid #000; padding: 6px; }
        .data-table th { background-color: #f3f4f6; font-weight: bold; }
    </style>
</head>
<body class="bg-white p-6 max-w-4xl mx-auto" onload="window.print()">
    <div class="no-print mb-4 flex justify-end">
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">Print</button>
        <button onclick="window.close()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded shadow ml-2 hover:bg-gray-300">Tutup</button>
    </div>

    <!-- Kop -->
    <div class="header-box flex items-center justify-between pb-4">
        <div class="w-32 flex justify-center">
            <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="h-16 object-contain">
        </div>
        <div class="flex-1 text-center pr-32">
            <h1 class="text-xl font-bold uppercase">Jurnal Kelas Harian</h1>
            <h2 class="text-lg font-bold mt-1"><?php echo htmlspecialchars($journal['unit_name']); ?></h2>
        </div>
    </div>

    <!-- Info -->
    <div class="grid grid-cols-2 gap-4 mb-6 text-sm">
        <div>
            <table class="w-full">
                <tr><td class="w-24 font-bold py-1">Kelas</td><td class="w-4">:</td><td><?php echo htmlspecialchars($journal['class_name']); ?></td></tr>
                <tr><td class="font-bold py-1">Mata Pelajaran</td><td>:</td><td><?php echo htmlspecialchars($journal['subject_name']); ?></td></tr>
                <tr><td class="font-bold py-1">Guru Pengajar</td><td>:</td><td><?php echo htmlspecialchars($journal['teacher_name']); ?></td></tr>
            </table>
        </div>
        <div>
            <table class="w-full">
                <tr><td class="w-28 font-bold py-1">Hari, Tanggal</td><td class="w-4">:</td><td><?php echo $day_name . ', ' . $indonesian_date; ?></td></tr>
                <tr><td class="font-bold py-1">Jam Ke</td><td>:</td><td><?php echo date('H:i', strtotime($journal['start_time'])) . ' - ' . date('H:i', strtotime($journal['end_time'])); ?></td></tr>
                <tr><td class="font-bold py-1">Total Siswa</td><td>:</td><td><?php echo count($attendances); ?> Orang</td></tr>
            </table>
        </div>
    </div>

    <!-- Materi & Catatan -->
    <div class="mb-6">
        <h3 class="font-bold text-sm mb-2">A. Materi Pembelajaran</h3>
        <div class="border border-black p-3 min-h-[60px] text-sm">
            <?php echo nl2br(htmlspecialchars($journal['topic'])); ?>
        </div>
        
        <h3 class="font-bold text-sm mt-4 mb-2">B. Catatan Tambahan</h3>
        <div class="border border-black p-3 min-h-[60px] text-sm">
            <?php echo nl2br(htmlspecialchars($journal['notes'])); ?>
        </div>
    </div>

    <!-- Rekapitulasi Kehadiran -->
    <div class="mb-6">
        <h3 class="font-bold text-sm mb-2">C. Rekapitulasi Kehadiran</h3>
        <table class="data-table w-full text-center text-sm">
            <thead>
                <tr>
                    <th class="w-1/5">Hadir</th>
                    <th class="w-1/5">Sakit</th>
                    <th class="w-1/5">Izin</th>
                    <th class="w-1/5">Alpa</th>
                    <th class="w-1/5">Terlambat</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo $counts['present']; ?></td>
                    <td><?php echo $counts['sick']; ?></td>
                    <td><?php echo $counts['permit']; ?></td>
                    <td><?php echo $counts['absent']; ?></td>
                    <td><?php echo $counts['late']; ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Tanda Tangan -->
    <div class="mt-12 flex justify-end">
        <div class="text-center">
            <p class="mb-16 text-sm">Cirebon, <?php echo $indonesian_date; ?></p>
            <p class="font-bold underline text-sm"><?php echo htmlspecialchars($journal['teacher_name']); ?></p>
            <p class="text-sm">Guru Pengajar</p>
        </div>
    </div>
</body>
</html>
