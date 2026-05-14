<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Get the class where this user is Wali Kelas
$stmt_class = $conn->prepare("
    SELECT gl.id, gl.name, gl.education_unit_id, eu.name as unit_name
    FROM grade_levels gl
    JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE gl.teacher_id = :uid
");
$stmt_class->execute([':uid' => $user_id]);
$my_classes = $stmt_class->fetchAll(PDO::FETCH_ASSOC);

if (empty($my_classes)) {
    $page_title = "Akses Ditolak";
    $error_message = "Anda bukan Wali Kelas";
    include '../layouts/header.php';
    include '../layouts/no_access.php';
    include '../layouts/footer.php';
    exit;
}

$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : $my_classes[0]['id'];

// Verify access
$has_access = false;
foreach ($my_classes as $mc) {
    if ($mc['id'] == $grade_id) {
        $has_access = true;
        $current_class = $mc;
        break;
    }
}

if (!$has_access) {
    $page_title = "Akses Ditolak";
    $error_message = "Akses Kelas Ditolak";
    include '../layouts/header.php';
    include '../layouts/no_access.php';
    include '../layouts/footer.php';
    exit;
}

// Fetch active academic year
$stmt_ay = $conn->query("SELECT * FROM academic_years WHERE is_active = 1 LIMIT 1");
$active_ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);

// Date range for recap
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ($active_ay['start_date'] ?? date('Y-m-01'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : ($active_ay['end_date'] ?? date('Y-m-d'));
$subject_id = isset($_GET['subject_id']) ? $_GET['subject_id'] : '';

// Fetch subjects for this class
$stmt_subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.name 
    FROM class_schedules cs
    JOIN subjects s ON cs.subject_id = s.id
    WHERE cs.grade_level_id = :grade_id
    ORDER BY s.name ASC
");
$stmt_subjects->execute([':grade_id' => $grade_id]);
$subjects = $stmt_subjects->fetchAll(PDO::FETCH_ASSOC);

// Fetch journals
$query = "
    SELECT cj.*, s.name as subject_name, e.full_name as teacher_name
    FROM class_journals cj
    JOIN class_schedules cs ON cj.class_schedule_id = cs.id
    JOIN subjects s ON cs.subject_id = s.id
    JOIN employees e ON cj.teacher_id = e.id
    WHERE cs.grade_level_id = :grade_id AND cj.date BETWEEN :start AND :end
";
$params = [':grade_id' => $grade_id, ':start' => $start_date, ':end' => $end_date];

if ($subject_id) {
    $query .= " AND cs.subject_id = :subject_id";
    $params[':subject_id'] = $subject_id;
}

$query .= " ORDER BY cj.date DESC, cj.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Rekap Jurnal Kelas - " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rekap Jurnal Kelas</h1>
            <p class="text-sm text-slate-500 mt-1">Kumpulan jurnal kegiatan belajar mengajar untuk kelas <?php echo htmlspecialchars($current_class['name']); ?>.</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 mb-8">
        <form action="" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <input type="hidden" name="grade_id" value="<?php echo $grade_id; ?>">
            
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Rentang Tanggal</label>
                <div class="flex items-center gap-2">
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                    <span class="text-slate-400 text-xs">s/d</span>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                        class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Mata Pelajaran</label>
                <select name="subject_id" class="w-full rounded-xl border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                    <option value="">Semua Mapel</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo $subject_id == $s['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-800 transition-all shadow-lg shadow-slate-900/10">
                    Filter
                </button>
                <button type="button" onclick="window.print()" class="bg-white border border-slate-200 text-slate-600 px-4 py-2.5 rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2 font-bold text-sm">
                    <svg class="w-5 h-5 text-rose-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Export PDF
                </button>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        <?php if (empty($journals)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-20 text-center">
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                    <p class="text-slate-400 italic">Tidak ada catatan jurnal dalam periode ini.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th class="px-6 py-4 text-left w-32">Tanggal</th>
                            <th class="px-6 py-4 text-left w-48">Mata Pelajaran</th>
                            <th class="px-6 py-4 text-left">Materi & Kegiatan</th>
                            <th class="px-6 py-4 text-left w-48">Guru Pengampu</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        <?php foreach ($journals as $j): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors align-top">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-slate-900"><?php echo date('d M Y', strtotime($j['date'])); ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium"><?php echo date('H:i', strtotime($j['created_at'])); ?> WIB</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full bg-cyan-50 px-2 py-1 text-[10px] font-bold text-cyan-700 ring-1 ring-inset ring-cyan-700/10">
                                        <?php echo htmlspecialchars($j['subject_name']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($j['topic']); ?></div>
                                    <div class="text-xs text-slate-500 leading-relaxed"><?php echo nl2br(htmlspecialchars($j['notes'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-bold text-slate-500">
                                            <?php echo substr($j['teacher_name'], 0, 1); ?>
                                        </div>
                                        <div class="text-xs font-semibold text-slate-700"><?php echo htmlspecialchars($j['teacher_name']); ?></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
@media print {
    #main-sidebar, header, .bg-white.p-6.rounded-2xl { display: none !important; }
    .pb-10 { padding: 0 !important; }
    .bg-white.rounded-2xl { border: none !important; box-shadow: none !important; }
    table { width: 100% !important; border-collapse: collapse !important; }
    th, td { border: 1px solid #e2e8f0 !important; padding: 8px !important; }
    body { background: white !important; }
}
</style>

<?php include '../layouts/footer.php'; ?>
