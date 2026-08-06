<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

if (empty($student_id) || empty($start_date) || empty($end_date)) {
    die("Parameter tidak lengkap. Silakan kembali.");
}

// Get active academic year
$active_year_id = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn();

// Fetch student details
$stmt_student = $conn->prepare("
    SELECT 
        s.id, 
        s.nama_siswa, 
        s.nomor_induk,
        s.foto,
        g.name as grade_name,
        eu.name as unit_name
    FROM students s
    JOIN student_class_history sch ON s.id = sch.student_id
    JOIN grade_levels g ON sch.class_id = g.id
    JOIN education_units eu ON g.education_unit_id = eu.id
    WHERE s.id = :student_id AND sch.academic_year_id = :year_id
    LIMIT 1
");
$stmt_student->execute([':student_id' => $student_id, ':year_id' => $active_year_id]);
$student = $stmt_student->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Siswa tidak ditemukan.");
}

// Fetch academic year details
$ay_stmt = $conn->prepare("
    SELECT name, semester 
    FROM academic_years 
    WHERE (start_date <= :start_date AND end_date >= :end_date) OR id = :active_year_id 
    ORDER BY (start_date <= :start_date AND end_date >= :end_date) DESC 
    LIMIT 1
");
$ay_stmt->execute([
    ':start_date' => $start_date,
    ':end_date' => $end_date,
    ':active_year_id' => $active_year_id
]);
$ay = $ay_stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

// Fetch subjects scheduled for this grade
$where_sub = "WHERE sch.student_id = :student_id AND sch.academic_year_id = :year_id";
$params_sub = [':student_id' => $student_id, ':year_id' => $active_year_id];

if (!$is_admin) {
    $where_sub .= " AND cs.employee_id = :current_user_id";
    $params_sub[':current_user_id'] = $_SESSION['user_id'];
}

$sub_stmt = $conn->prepare("
    SELECT DISTINCT sub.id, sub.name
    FROM class_schedules cs
    JOIN subjects sub ON cs.subject_id = sub.id
    JOIN student_class_history sch ON cs.grade_level_id = sch.class_id
    $where_sub
    ORDER BY sub.name ASC
");
$sub_stmt->execute($params_sub);
$subjects = $sub_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch aggregate attendance counts for each subject
$where_recap = "WHERE sa.student_id = :student_id AND cj.date BETWEEN :start_date AND :end_date";
$params_recap = [
    ':student_id' => $student_id,
    ':start_date' => $start_date,
    ':end_date' => $end_date
];

if (!$is_admin) {
    $where_recap .= " AND cs.employee_id = :current_user_id";
    $params_recap[':current_user_id'] = $_SESSION['user_id'];
}

$recap_stmt = $conn->prepare("
    SELECT 
        cs.subject_id,
        SUM(CASE WHEN LOWER(sa.status) = 'present' THEN 1 ELSE 0 END) as count_present,
        SUM(CASE WHEN LOWER(sa.status) = 'sick' THEN 1 ELSE 0 END) as count_sick,
        SUM(CASE WHEN LOWER(sa.status) = 'permit' THEN 1 ELSE 0 END) as count_permit,
        SUM(CASE WHEN LOWER(sa.status) = 'absent' THEN 1 ELSE 0 END) as count_absent,
        SUM(CASE WHEN LOWER(sa.status) = 'late' THEN 1 ELSE 0 END) as count_late,
        COUNT(sa.id) as total_attendance
    FROM student_attendances sa
    JOIN class_journals cj ON sa.class_journal_id = cj.id
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    $where_recap
    GROUP BY cs.subject_id
");
$recap_stmt->execute($params_recap);
$recap_raw = $recap_stmt->fetchAll(PDO::FETCH_ASSOC);

$recap_map = [];
foreach ($recap_raw as $row) {
    $recap_map[$row['subject_id']] = [
        'present' => $row['count_present'],
        'sick' => $row['count_sick'],
        'permit' => $row['count_permit'],
        'absent' => $row['count_absent'],
        'late' => $row['count_late'],
        'total' => $row['total_attendance']
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raport Absensi - <?php echo htmlspecialchars($student['nama_siswa']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; margin: 0; background: white; }
            .print-container { border: none; box-shadow: none; width: 100%; max-width: 100%; margin: 0; padding: 0; }
        }
        body { background-color: #f1f5f9; font-family: 'Outfit', sans-serif; }
    </style>
</head>
<body class="p-8">
    <div class="no-print mb-8 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-cyan-600 hover:bg-cyan-700 text-white px-6 py-2.5 rounded-lg font-bold shadow-lg transition-all active:scale-95 flex items-center gap-2">
            <i class="fa-solid fa-calendar-days w-4 h-4"></i>
            Cetak Sekarang
        </button>
        <button onclick="window.close()" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-6 py-2.5 rounded-lg font-bold transition-all active:scale-95">Tutup Halaman</button>
    </div>

    <div class="print-container bg-white max-w-[210mm] mx-auto p-[15mm] border border-slate-200 shadow-xl min-h-[297mm] rounded-2xl flex flex-col justify-between">
        <div>
            <!-- Kop Surat / Header -->
            <div class="flex items-center justify-between border-b-4 border-slate-800 pb-5 mb-6">
                <div class="flex items-center gap-4">
                    <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-16 h-16 object-contain" onerror="this.src='https://ui-avatars.com/api/?name=Assunnah&background=0284c7&color=fff&size=64&bold=true'">
                    <div class="text-left">
                        <h1 class="text-xl font-bold uppercase leading-tight text-slate-800">
                            PESANTREN ASSUNNAH CIREBON
                        </h1>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">
                            UNIT PENDIDIKAN: <?php echo htmlspecialchars($student['unit_name']); ?>
                        </p>
                        <p class="text-[10px] text-slate-400">
                            Jalan Kalitanjung No. 11B, Kel. Karyamulya, Kec. Kesambi, Kota Cirebon, Jawa Barat
                        </p>
                    </div>
                </div>
                <div class="text-right">
                    <h2 class="text-lg font-black text-cyan-600 uppercase tracking-wider">RAPORT PRESENSI</h2>
                    <span class="text-[10px] bg-cyan-50 text-cyan-700 px-2 py-0.5 rounded font-bold uppercase border border-cyan-100">Semester <?php echo htmlspecialchars($ay['semester'] ?? ''); ?></span>
                </div>
            </div>

            <!-- Title -->
            <div class="text-center mb-6">
                <h2 class="text-base font-bold uppercase tracking-wide text-slate-800">LAPORAN REKAPITULASI KEHADIRAN SISWA</h2>
                <p class="text-xs text-slate-500">Rentang Tanggal: <?php echo date('d-m-Y', strtotime($start_date)); ?> s/d <?php echo date('d-m-Y', strtotime($end_date)); ?></p>
            </div>

            <!-- Identitas Siswa -->
            <div class="grid grid-cols-2 gap-x-8 gap-y-2 mb-8 bg-slate-50 p-4 rounded-xl border border-slate-100 text-sm">
                <div class="flex">
                    <span class="w-32 font-medium text-slate-500">Nama Siswa</span>
                    <span class="mr-2 text-slate-400">:</span>
                    <span class="font-bold text-slate-800"><?php echo htmlspecialchars($student['nama_siswa']); ?></span>
                </div>
                <div class="flex">
                    <span class="w-32 font-medium text-slate-500">Tahun Ajaran</span>
                    <span class="mr-2 text-slate-400">:</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($ay['name'] ?? '-'); ?></span>
                </div>
                <div class="flex">
                    <span class="w-32 font-medium text-slate-500">Nomor Induk (NIS)</span>
                    <span class="mr-2 text-slate-400">:</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($student['nomor_induk'] ?: '-'); ?></span>
                </div>
                <div class="flex">
                    <span class="w-32 font-medium text-slate-500">Semester</span>
                    <span class="mr-2 text-slate-400">:</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($ay['semester'] ?? '-'); ?></span>
                </div>
                <div class="flex">
                    <span class="w-32 font-medium text-slate-500">Kelas</span>
                    <span class="mr-2 text-slate-400">:</span>
                    <span class="font-semibold text-slate-700"><?php echo htmlspecialchars($student['grade_name']); ?></span>
                </div>
            </div>

            <!-- Table Kehadiran -->
            <table class="w-full border-collapse border border-slate-300 text-sm rounded-xl overflow-hidden shadow-sm">
                <thead>
                    <tr class="bg-slate-100/80 text-slate-700 font-bold uppercase tracking-wider text-xs">
                        <th class="border border-slate-300 px-3 py-3 text-center w-12">No</th>
                        <th class="border border-slate-300 px-4 py-3 text-left">Mata Pelajaran</th>
                        <th class="border border-slate-300 px-3 py-3 text-center w-20 text-green-700">Hadir</th>
                        <th class="border border-slate-300 px-3 py-3 text-center w-20 text-blue-700">Sakit</th>
                        <th class="border border-slate-300 px-3 py-3 text-center w-20 text-yellow-700">Izin</th>
                        <th class="border border-slate-300 px-3 py-3 text-center w-20 text-red-700">Alpa</th>
                        <th class="border border-slate-300 px-3 py-3 text-center w-20 text-orange-700">Terlambat</th>
                        <th class="border border-slate-300 px-4 py-3 text-center w-28">Persentase</th>
                    </tr>
                </thead>
                <tbody class="text-slate-700">
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="8" class="border border-slate-300 px-4 py-8 text-center text-slate-400 italic">Belum ada data presensi/jadwal pelajaran.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $index => $sub): ?>
                            <?php
                            $sub_id = $sub['id'];
                            $stats = isset($recap_map[$sub_id]) ? $recap_map[$sub_id] : [
                                'present' => 0, 'sick' => 0, 'permit' => 0, 'absent' => 0, 'late' => 0, 'total' => 0
                            ];
                            $present_total = $stats['present'] + $stats['late'];
                            $pct = $stats['total'] > 0 ? round(($present_total / $stats['total']) * 100) : 0;
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="border border-slate-300 px-3 py-2 text-center font-medium"><?php echo $index + 1; ?></td>
                                <td class="border border-slate-300 px-4 py-2 font-semibold"><?php echo htmlspecialchars($sub['name']); ?></td>
                                <td class="border border-slate-300 px-3 py-2 text-center font-bold text-green-600 bg-green-50/10"><?php echo $stats['present']; ?></td>
                                <td class="border border-slate-300 px-3 py-2 text-center font-bold text-blue-600 bg-blue-50/10"><?php echo $stats['sick']; ?></td>
                                <td class="border border-slate-300 px-3 py-2 text-center font-bold text-yellow-600 bg-yellow-50/10"><?php echo $stats['permit']; ?></td>
                                <td class="border border-slate-300 px-3 py-2 text-center font-bold text-red-600 bg-red-50/10"><?php echo $stats['absent']; ?></td>
                                <td class="border border-slate-300 px-3 py-2 text-center font-bold text-orange-600 bg-orange-50/10"><?php echo $stats['late']; ?></td>
                                <td class="border border-slate-300 px-4 py-2 text-center font-extrabold <?php echo $pct >= 90 ? 'text-green-600' : ($pct >= 75 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo $pct; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tanda Tangan Section -->
        <div class="mt-16 text-sm">
            <div class="grid grid-cols-2 text-center items-end">
                <div>
                    <p class="mb-20 font-medium">Mengetahui,<br>Orang Tua / Wali Siswa</p>
                    <div class="w-48 border-b-2 border-slate-400 mx-auto"></div>
                </div>
                <div>
                    <p class="mb-20 font-medium">Cirebon, <?php echo date('d F Y'); ?><br>Wali Kelas</p>
                    <div class="w-48 border-b-2 border-slate-400 mx-auto"></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
