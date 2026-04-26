<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die("ID RPP tidak valid.");
}

// Fetch RPP Detail
$query = "
    SELECT r.*, s.name as subject_name, gl.name as grade_name, ay.name as academic_year_name, e.full_name as teacher_name, eu.name as unit_name
    FROM rpp r
    LEFT JOIN subjects s ON r.subject_id = s.id
    LEFT JOIN grade_levels gl ON r.grade_level_id = gl.id
    LEFT JOIN academic_years ay ON r.academic_year_id = ay.id
    LEFT JOIN employees e ON r.employee_id = e.id
    LEFT JOIN education_units eu ON r.education_unit_id = eu.id
    WHERE r.id = ?
";
$stmt = $conn->prepare($query);
$stmt->execute([$id]);
$rpp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rpp) {
    die("RPP tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak RPP - <?php echo htmlspecialchars($rpp['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; background: white; }
            .print-container { border: none; box-shadow: none; width: 100%; max-width: 100%; margin: 0; }
        }
        body { background-color: #f1f5f9; }
    </style>
</head>
<body class="p-8">
    <div class="no-print mb-8 flex justify-center gap-4">
        <button onclick="window.print()" class="bg-cyan-600 text-white px-6 py-2 rounded-lg font-bold shadow-lg hover:bg-cyan-700 transition-all">Cetak Sekarang</button>
        <button onclick="window.close()" class="bg-white border border-slate-200 px-6 py-2 rounded-lg font-bold text-slate-600 hover:bg-slate-50 transition-all">Tutup</button>
    </div>

    <div class="print-container bg-white max-w-[210mm] mx-auto p-[20mm] border border-slate-200 shadow-xl min-h-[297mm]">
        <!-- Header -->
        <div class="flex items-center justify-between border-b-2 border-slate-900 pb-4 mb-8">
            <div class="flex items-center gap-4">
                <img src="<?php echo url('public/images/logo.png'); ?>" alt="Logo" class="w-16 h-16 object-contain">
                <div class="text-left">
                    <h1 class="text-xl font-bold uppercase leading-tight">
                        <?php echo htmlspecialchars($rpp['unit_name'] ?? ''); ?> Assunnah Cirebon
                    </h1>
                    <p class="text-[10px] font-medium text-slate-500 uppercase tracking-widest">
                        Yayasan Assunnah Cirebon
                    </p>
                </div>
            </div>
            <div class="text-right mr-2">
                <h2 class="text-2xl font-black text-slate-300 uppercase tracking-tighter opacity-50">RPP</h2>
            </div>
        </div>

        <!-- Identitas -->
        <div class="grid grid-cols-2 gap-x-8 gap-y-2 mb-8 text-sm">
            <div class="flex">
                <span class="w-32 font-bold">Mata Pelajaran</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($rpp['subject_name']); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Tahun Pelajaran</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($rpp['academic_year_name']); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Kelas / Semester</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($rpp['grade_name']); ?> / <?php echo htmlspecialchars($rpp['semester']); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Alokasi Waktu</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($rpp['allocation'] ?: '-'); ?></span>
            </div>
            <div class="flex">
                <span class="w-32 font-bold">Pertemuan Ke</span>
                <span class="mr-2">:</span>
                <span><?php echo htmlspecialchars($rpp['session_no'] ?: '-'); ?></span>
            </div>
        </div>

        <!-- Judul -->
        <div class="mb-8">
            <h2 class="text-lg font-bold text-center underline decoration-2 underline-offset-4">
                <?php echo htmlspecialchars($rpp['title']); ?>
            </h2>
        </div>

        <!-- Konten Struktural -->
        <div class="space-y-6 text-sm leading-relaxed">
            <section>
                <h3 class="font-bold mb-1">A. Capaian Pembelajaran (CP)</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['content_cp'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">B. Alur Tujuan Pembelajaran (ATP)</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['content_atp'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">C. Pertanyaan Pemantik</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['content_pertanyaan_pemantik'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">D. Tujuan Pembelajaran (TP)</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['learning_goal'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">E. Materi Ajar</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['teaching_material'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">F. Profil Pelajar Pancasila</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['teaching_profil_pancasila'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">G. Kegiatan Pembelajaran</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['content_steps'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">H. Media dan Sumber Belajar</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['content_summary'] ?? '')); ?></div>
            </section>

            <section>
                <h3 class="font-bold mb-1">I. Asesmen</h3>
                <div class="whitespace-pre-line pl-5"><?php echo nl2br(htmlspecialchars($rpp['assessment'] ?? '')); ?></div>
            </section>
        </div>

        <!-- Tanda Tangan -->
        <div class="mt-16 grid grid-cols-2 text-center text-sm">
            <div>
                <p>Mengetahui,</p>
                <p>Kepala Sekolah</p>
                <div class="h-24"></div>
                <p class="font-bold underline">( ........................................ )</p>
            </div>
            <div>
                <p>Singaraja, <?php echo date('d F Y'); ?></p>
                <p>Guru Mata Pelajaran</p>
                <div class="h-24"></div>
                <p class="font-bold underline text-slate-900"><?php echo htmlspecialchars($rpp['teacher_name']); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Auto print trigger if needed
        // window.print();
    </script>
</body>
</html>
