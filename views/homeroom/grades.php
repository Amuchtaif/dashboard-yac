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

// Fetch assessments for this class
$sql = "
    SELECT 
        sa.id, sa.assessment_date, at.name as assessment_type, 
        s.name as subject_name, e.full_name as teacher_name,
        (SELECT AVG(score) FROM student_assessment_details WHERE assessment_id = sa.id) as avg_score,
        (SELECT COUNT(*) FROM student_assessment_details WHERE assessment_id = sa.id) as student_count
    FROM student_assessments sa
    JOIN assessment_types at ON sa.assessment_type_id = at.id
    JOIN subjects s ON sa.subject_id = s.id
    JOIN employees e ON sa.teacher_id = e.id
    WHERE sa.grade_level_id = :grade_id
    ORDER BY sa.assessment_date DESC, sa.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([':grade_id' => $grade_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Data Nilai Kelas " . $current_class['name'];
include '../layouts/header.php';
?>

<div class="pb-10">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-900">Data Nilai Siswa</h1>
        <p class="text-sm text-slate-500 mt-1">Daftar penilaian yang telah diinput oleh guru untuk kelas <?php echo htmlspecialchars($current_class['name']); ?>.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    <th class="px-6 py-4 text-left">Tanggal</th>
                    <th class="px-6 py-4 text-left">Mata Pelajaran</th>
                    <th class="px-6 py-4 text-left">Jenis Penilaian</th>
                    <th class="px-6 py-4 text-left">Guru Pengampu</th>
                    <th class="px-6 py-4 text-center">Rata-rata</th>
                    <th class="px-6 py-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                <?php if (empty($assessments)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-slate-500 italic">Belum ada data nilai untuk kelas ini.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assessments as $a): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                <div class="font-bold text-slate-900"><?php echo date('d M Y', strtotime($a['assessment_date'])); ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-cyan-600">
                                <?php echo htmlspecialchars($a['subject_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-bold uppercase border border-slate-200">
                                    <?php echo htmlspecialchars($a['assessment_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">
                                <?php echo htmlspecialchars($a['teacher_name']); ?>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="text-sm font-black <?php echo $a['avg_score'] >= 75 ? 'text-emerald-500' : 'text-amber-500'; ?>">
                                    <?php echo number_format($a['avg_score'], 1); ?>
                                </div>
                                <div class="text-[9px] text-slate-400"><?php echo $a['student_count']; ?> Siswa</div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="viewDetail(<?php echo $a['id']; ?>)" 
                                    class="text-cyan-600 hover:text-cyan-900 font-semibold text-xs bg-cyan-50 px-3 py-1.5 rounded-lg transition-colors">
                                    Lihat Detail
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Detail Modal (Same as student_assessments/index.php but simplified) -->
<div id="detailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-2xl w-full overflow-hidden transition-all transform scale-100">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">Detail Nilai Siswa</h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-6 max-h-[500px] overflow-y-auto" id="modal-body">
                <!-- Content loaded via JS -->
            </div>
        </div>
    </div>
</div>

<script>
async function viewDetail(id) {
    const modal = document.getElementById('detailModal');
    const body = document.getElementById('modal-body');
    modal.classList.remove('hidden');
    body.innerHTML = '<div class="flex justify-center p-10"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div></div>';

    try {
        const response = await fetch(`../../api/grading/get_detail.php?id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            let html = '<table class="min-w-full divide-y divide-slate-100"><thead><tr class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"><th class="text-left py-2">Siswa</th><th class="text-center py-2">Nilai</th><th class="text-center py-2">Status</th></tr></thead><tbody class="divide-y divide-slate-50">';
            result.data.details.forEach(d => {
                const statusColor = d.score >= 75 ? 'text-emerald-500 bg-emerald-50' : 'text-rose-500 bg-rose-50';
                const statusText = d.score >= 75 ? 'Lulus' : 'Remedi';
                html += `<tr><td class="py-3 text-sm font-medium text-slate-700">${d.nama_siswa}</td><td class="py-3 text-center font-bold text-slate-900">${d.score}</td><td class="py-3 text-center"><span class="px-2 py-0.5 rounded text-[10px] font-bold ${statusColor}">${statusText}</span></td></tr>`;
            });
            html += '</tbody></table>';
            body.innerHTML = html;
        } else {
            body.innerHTML = '<div class="text-center text-red-500 py-10">' + result.message + '</div>';
        }
    } catch (e) {
        body.innerHTML = '<div class="text-center text-red-500 py-10">Terjadi kesalahan koneksi.</div>';
    }
}

function closeModal() {
    document.getElementById('detailModal').classList.add('hidden');
}
</script>

<?php include '../layouts/footer.php'; ?>
