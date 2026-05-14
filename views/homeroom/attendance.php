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
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

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

// Fetch attendance data for the selected date
$stmt_att = $conn->prepare("
    SELECT s.nama_siswa, s.nomor_induk, dsa.status, dsa.notes, dsa.updated_at
    FROM daily_student_attendances dsa
    JOIN students s ON dsa.student_id = s.id
    WHERE dsa.grade_level_id = :grade_id AND dsa.date = :date
    ORDER BY s.nama_siswa ASC
");
$stmt_att->execute([':grade_id' => $grade_id, ':date' => $date]);
$attendance_data = $stmt_att->fetchAll(PDO::FETCH_ASSOC);

// Status labels
$status_map = [
    'H' => ['label' => 'Hadir', 'color' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
    'S' => ['label' => 'Sakit', 'color' => 'bg-blue-50 text-blue-700 ring-blue-600/20'],
    'I' => ['label' => 'Izin', 'color' => 'bg-amber-50 text-amber-700 ring-amber-600/20'],
    'A' => ['label' => 'Alpha', 'color' => 'bg-rose-50 text-rose-700 ring-rose-600/20'],
    'T' => ['label' => 'Telat', 'color' => 'bg-orange-50 text-orange-700 ring-orange-600/20']
];

$page_title = "Data Absensi Harian - " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Riwayat Absensi Siswa</h1>
            <p class="text-sm text-slate-500 mt-1">Data absensi harian yang telah diinput via Mobile/Dashboard untuk
                kelas <?php echo htmlspecialchars($current_class['name']); ?>.</p>
        </div>
        <div class="flex items-center gap-3">
            <input type="date" value="<?php echo $date; ?>"
                class="rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm"
                onchange="window.location.href='?grade_id=<?php echo $grade_id; ?>&date='+this.value">

            <?php if (count($my_classes) > 1): ?>
                <select onchange="window.location.href='?date=<?php echo $date; ?>&grade_id='+this.value"
                    class="rounded-lg border-slate-200 text-sm focus:border-cyan-500 focus:ring-cyan-500 bg-white border py-2 px-4 shadow-sm">
                    <?php foreach ($my_classes as $mc): ?>
                        <option value="<?php echo $mc['id']; ?>" <?php echo $mc['id'] == $grade_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats -->
    <?php if (!empty($attendance_data)): ?>
        <?php
        $stats = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0, 'T' => 0];
        foreach ($attendance_data as $row) {
            $stats[$row['status']]++;
        }
        ?>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <?php foreach ($status_map as $key => $meta): ?>
                <div class="bg-white p-4 rounded-xl border border-slate-100 shadow-sm flex flex-col items-center">
                    <span
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1"><?php echo $meta['label']; ?></span>
                    <span class="text-2xl font-black text-slate-800"><?php echo $stats[$key]; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 text-center w-16">No.</th>
                    <th class="px-6 py-4 text-left">Nama Siswa</th>
                    <th class="px-6 py-4 text-center">Status</th>
                    <th class="px-6 py-4 text-left">Keterangan</th>
                    <th class="px-6 py-4 text-right">Terakhir Update</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($attendance_data)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-20 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-slate-200 mb-3" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-slate-400 italic">Belum ada data absensi yang masuk untuk tanggal ini.</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendance_data as $index => $row):
                        $meta = $status_map[$row['status']] ?? $status_map['H'];
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 text-center text-slate-400 text-xs"><?php echo $index + 1; ?>.</td>
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 text-sm">
                                    <?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                <div class="text-[10px] text-slate-400">NIS:
                                    <?php echo htmlspecialchars($row['nomor_induk'] ?? '-'); ?></div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ring-1 ring-inset <?php echo $meta['color']; ?>">
                                    <?php echo $meta['label']; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-600">
                                <?php echo htmlspecialchars($row['notes'] ?: '-'); ?>
                            </td>
                            <td class="px-6 py-4 text-right text-[10px] text-slate-400 font-medium">
                                <?php echo date('d/m/Y H:i', strtotime($row['updated_at'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-6 flex justify-between items-center">
        <p class="text-xs text-slate-400 italic">* Data sinkron otomatis dengan input dari aplikasi.</p>
        <a href="recap.php?grade_id=<?php echo $grade_id; ?>"
            class="text-cyan-600 hover:text-cyan-700 text-sm font-bold flex items-center gap-2">
            Lihat Rekap Semester
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
            </svg>
        </a>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>