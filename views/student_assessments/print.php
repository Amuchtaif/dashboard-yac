<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("ID Penilaian tidak valid.");
}

// Fetch Assessment Header
$query = "
    SELECT 
        sa.*, 
        s.name as subject_name, 
        gl.name as class_name, 
        at.name as assessment_type_name,
        e.full_name as teacher_name,
        eu.name as unit_name
    FROM student_assessments sa
    JOIN subjects s ON sa.subject_id = s.id
    JOIN grade_levels gl ON sa.grade_level_id = gl.id
    JOIN assessment_types at ON sa.assessment_type_id = at.id
    LEFT JOIN employees e ON sa.teacher_id = e.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE sa.id = ?
";
$stmt = $conn->prepare($query);
$stmt->execute([$id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    die("Data penilaian tidak ditemukan.");
}

// Fetch Details
$query_details = "
    SELECT sad.*, st.nama_siswa, st.nomor_induk
    FROM student_assessment_details sad
    JOIN students st ON sad.student_id = st.id
    WHERE sad.assessment_id = ?
    ORDER BY st.nama_siswa ASC
";
$stmt_details = $conn->prepare($query_details);
$stmt_details->execute([$id]);
$details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Penilaian - <?php echo htmlspecialchars($assessment['subject_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; background: white; }
            .print-container { border: none; box-shadow: none; width: 100%; max-width: 100%; margin: 0; }
        }
        body { background-color: #f1f5f9; font-family: 'Times New Roman', serif; }
    </style>
</head>
<body class="p-8">
    <div class="no-print mb-8 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-cyan-600 text-white px-6 py-2 rounded-lg font-bold shadow-lg hover:bg-cyan-700 transition-all">Cetak Sekarang</button>
        <button onclick="window.close()" class="bg-white border border-slate-200 px-6 py-2 rounded-lg font-bold text-slate-600 hover:bg-slate-50 transition-all">Tutup</button>
    </div>

    <div class="print-container bg-white max-w-[210mm] mx-auto p-[15mm] border border-slate-200 shadow-xl min-h-[297mm]">
        <!-- Header -->
        <div class="flex items-center justify-between border-b-2 border-slate-900 pb-4 mb-6">
            <div class="flex items-center gap-4">
                <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-16 h-16 object-contain">
                <div class="text-left">
                    <h1 class="text-xl font-bold uppercase leading-tight">
                        <?php echo htmlspecialchars($assessment['unit_name'] ?? ''); ?> Assunnah Cirebon
                    </h1>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-widest">
                        Yayasan Assunnah Cirebon
                    </p>
                </div>
            </div>
            <div class="text-right mr-2">
                <h2 class="text-xl font-black text-slate-300 uppercase tracking-tighter opacity-50">DAFTAR NILAI</h2>
            </div>
        </div>

        <div class="text-center mb-6">
            <h2 class="text-lg font-bold uppercase underline">DAFTAR NILAI SISWA</h2>
        </div>

        <!-- Identitas -->
        <div class="grid grid-cols-2 gap-x-8 gap-y-1 mb-6 text-sm">
            <div class="flex">
                <span class="w-32 font-bold">Mata Pelajaran</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($assessment['subject_name']); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Jenis Penilaian</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($assessment['assessment_type_name']); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Kelas</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($assessment['class_name'] ?? '-'); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Tanggal</span>
                <span class="mr-2">:</span>
                <span><?php echo date('d F Y', strtotime($assessment['assessment_date'])); ?></span>
            </div>
        </div>

        <!-- Table Nilai -->
        <table class="w-full border-collapse border border-slate-900 text-sm mb-12">
            <thead>
                <tr class="bg-slate-50">
                    <th class="border border-slate-900 px-2 py-2 w-12 text-center">No</th>
                    <th class="border border-slate-900 px-4 py-2 text-left">Nomor Induk</th>
                    <th class="border border-slate-900 px-4 py-2 text-left">Nama Siswa</th>
                    <th class="border border-slate-900 px-4 py-2 text-center w-24">Nilai</th>
                    <th class="border border-slate-900 px-4 py-2 text-center w-32">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $index => $d): ?>
                <tr>
                    <td class="border border-slate-900 px-2 py-2 text-center"><?php echo $index + 1; ?></td>
                    <td class="border border-slate-900 px-4 py-2"><?php echo htmlspecialchars($d['nomor_induk'] ?: '-'); ?></td>
                    <td class="border border-slate-900 px-4 py-2 font-medium"><?php echo htmlspecialchars($d['nama_siswa']); ?></td>
                    <td class="border border-slate-900 px-4 py-2 text-center font-bold"><?php echo $d['score']; ?></td>
                    <td class="border border-slate-900 px-4 py-2 text-center">
                        <?php echo $d['score'] >= 75 ? 'Tuntas' : 'Remedial'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Footer Tanda Tangan -->
        <div class="grid grid-cols-2 text-center text-sm">
            <div>
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="h-20"></div>
                <p class="font-bold underline">( ........................................ )</p>
            </div>
            <div>
                <p>Cirebon, <?php echo date('d F Y'); ?></p>
                <p>Guru Mata Pelajaran</p>
                <div class="h-20"></div>
                <p class="font-bold underline"><?php echo htmlspecialchars($assessment['teacher_name'] ?? '........................................'); ?></p>
            </div>
        </div>
    </div>
</body>
</html>
