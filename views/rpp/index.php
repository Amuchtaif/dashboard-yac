<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

$db = new Database();
$conn = $db->getConnection();

$page_title = "Rencana Pelaksanaan Pembelajaran (RPP)";

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$is_draft = isset($_GET['draft']) ? (int)$_GET['draft'] : 0;
$search = $_GET['search'] ?? '';

$is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');

$where = "WHERE r.is_draft = :draft";
$params = [':draft' => $is_draft];

if (!$is_admin) {
    $where .= " AND r.employee_id = :current_user_id";
    $params[':current_user_id'] = $_SESSION['user_id'];
}

if ($search) {
    $where .= " AND (r.title LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count total records
$count_query = "SELECT COUNT(*) FROM rpp r JOIN subjects s ON r.subject_id = s.id $where";
$stmt_count = $conn->prepare($count_query);
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch RPPs
$query = "
    SELECT r.*, s.name as subject_name, gl.name as grade_name, ay.name as academic_year_name, e.full_name as teacher_name
    FROM rpp r
    JOIN subjects s ON r.subject_id = s.id
    JOIN grade_levels gl ON r.grade_level_id = gl.id
    JOIN academic_years ay ON r.academic_year_id = ay.id
    JOIN employees e ON r.employee_id = e.id
    $where
    ORDER BY r.created_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$rpp_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>

<div class="space-y-6 pb-12">
    <!-- Header -->
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800"><?php echo $page_title; ?></h1>
            <p class="text-slate-500 mt-1">Kelola dokumen rencana mengajar guru.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="create.php" 
                class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-cyan-700 transition-all">
                <i class="fa-solid fa-plus -ml-1 mr-2 h-4 w-4"></i>
                Buat RPP Baru
            </a>
        </div>
    </div>

    <!-- Tabs & Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex bg-slate-100 p-1 rounded-xl w-fit">
            <a href="?draft=0" class="px-4 py-2 text-sm font-bold rounded-lg transition-all <?php echo !$is_draft ? 'bg-white text-cyan-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'; ?>">
                Daftar Terbit
            </a>
            <a href="?draft=1" class="px-4 py-2 text-sm font-bold rounded-lg transition-all <?php echo $is_draft ? 'bg-white text-cyan-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'; ?>">
                Draft
                <?php if ($is_draft): ?>
                    <span class="ml-1 px-1.5 py-0.5 text-[10px] bg-cyan-100 text-cyan-700 rounded-full"><?php echo $total_records; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <form method="GET" class="relative group max-w-sm w-full">
            <input type="hidden" name="draft" value="<?php echo $is_draft; ?>">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass h-4 w-4 text-slate-400 group-focus-within:text-cyan-500 transition-colors"></i>
            </div>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                placeholder="Cari judul atau mapel..." 
                class="block w-full pl-10 pr-3 py-2 border border-slate-200 rounded-xl text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 transition-all bg-white">
        </form>
    </div>

    <!-- Content Table -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase">
                        <th class="px-6 py-4">No.</th>
                        <th class="px-6 py-4">Informasi RPP</th>
                        <th class="px-6 py-4">Periode</th>
                        <th class="px-6 py-4">Guru Pengampu</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (count($rpp_list) > 0): ?>
                        <?php foreach ($rpp_list as $index => $r): ?>
                            <tr class="hover:bg-slate-50/50 transition-all">
                                <td class="px-6 py-4 text-slate-400 font-medium"><?php echo $offset + $index + 1; ?>.</td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800 line-clamp-1"><?php echo htmlspecialchars($r['title']); ?></div>
                                    <div class="text-xs text-slate-500 mt-0.5 italic"><?php echo htmlspecialchars($r['subject_name']); ?> • Kelas <?php echo htmlspecialchars($r['grade_name']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-700"><?php echo htmlspecialchars($r['academic_year_name']); ?></div>
                                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"><?php echo $r['semester']; ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-600"><?php echo htmlspecialchars($r['teacher_name']); ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($r['is_draft']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">Draft</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">Terbit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button onclick="viewRPP(<?php echo $r['id']; ?>)" class="p-2 text-slate-400 hover:text-cyan-600 transition-colors" title="Lihat">
                                            <i class="fa-solid fa-eye h-5 w-5"></i>
                                        </button>
                                        <a href="<?php url('views/rpp/print.php?id=' . $r['id']); ?>" target="_blank" class="p-2 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-xl transition-all" title="Cetak RPP">
                                            <i class="fa-solid fa-print h-5 w-5"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $r['id']; ?>" class="p-2 text-slate-400 hover:text-cyan-600 transition-colors" title="Ubah">
                                            <i class="fa-solid fa-pen-to-square h-5 w-5"></i>
                                        </a>
                                        <button onclick="openDeleteModal('delete.php?id=<?php echo $r['id']; ?>')" class="p-2 text-slate-400 hover:text-rose-600 transition-colors" title="Hapus">
                                            <i class="fa-solid fa-trash h-5 w-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="px-6 py-20 text-center text-slate-400 italic">Belum ada dokumen RPP yang ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
            <p class="text-xs text-slate-500">
                Menampilkan <span class="font-bold"><?php echo count($rpp_list); ?></span> dari <span class="font-bold"><?php echo $total_records; ?></span> data
            </p>
            <div class="flex gap-2">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&draft=<?php echo $is_draft; ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-3 py-1 text-xs font-bold rounded-lg border <?php echo $i == $page ? 'bg-cyan-600 text-white border-cyan-600' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'; ?> transition-all">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail View Modal -->
<div id="viewModal" class="fixed inset-0 z-50 invisible transition-all duration-300 pointer-events-none" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Overlay -->
    <div id="viewModalOverlay" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="closeViewModal()"></div>
    
    <div class="flex items-center justify-center min-h-screen p-4 sm:p-6">
        <!-- Modal Content -->
        <div id="viewModalContent" class="relative bg-white rounded-[2rem] text-left shadow-2xl transform opacity-0 scale-95 transition-all duration-300 sm:max-w-4xl w-full border border-white/20 overflow-hidden">
            <div class="bg-white">
                <!-- Modal Head -->
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-cyan-600 flex items-center justify-center text-white shadow-lg shadow-cyan-600/20">
                            <i class="fa-solid fa-file-lines w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-slate-800 leading-tight" id="modal-title-text">Detail RPP</h3>
                            <p class="text-xs text-slate-500 mt-1 font-medium flex items-center gap-1.5" id="modal-subtitle-text"></p>
                        </div>
                    </div>
                    <button onclick="closeViewModal()" class="group p-2 rounded-xl bg-slate-100 text-slate-400 hover:text-slate-600 hover:bg-slate-200 transition-all active:scale-90">
                        <i class="fa-solid fa-xmark h-6 w-6"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="px-8 py-8 max-h-[65vh] overflow-y-auto custom-scrollbar space-y-8" id="modal-content">
                    <!-- Loading State -->
                    <div class="flex flex-col items-center justify-center py-20 gap-4">
                        <div class="relative w-12 h-12">
                            <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                            <div class="absolute inset-0 rounded-full border-4 border-cyan-600 border-t-transparent animate-spin"></div>
                        </div>
                        <p class="text-sm font-bold text-slate-400 animate-pulse">Mengambil data RPP...</p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-8 py-6 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-end gap-3">
                    <button onclick="closeViewModal()" class="px-6 py-2.5 text-sm font-bold text-slate-500 hover:text-slate-700 transition-colors">Tutup</button>
                    <a id="modal-print-btn" href="#" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 transition-all active:scale-95">
                        <i class="fa-solid fa-print w-4 h-4"></i>
                        Cetak Sekarang
                    </a>
                    <a id="modal-edit-btn" href="#" class="inline-flex items-center justify-center gap-2 rounded-xl bg-cyan-600 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-700 transition-all active:scale-95">
                        <i class="fa-solid fa-pen-to-square w-4 h-4"></i>
                        Edit RPP
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
</style>

<script>
async function viewRPP(id) {
    const modal = document.getElementById('viewModal');
    const overlay = document.getElementById('viewModalOverlay');
    const modalContent = document.getElementById('viewModalContent');
    const content = document.getElementById('modal-content');
    const title = document.getElementById('modal-title-text');
    const subtitle = document.getElementById('modal-subtitle-text');
    
    // Show modal with animation
    modal.classList.remove('invisible', 'pointer-events-none');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        modalContent.classList.remove('opacity-0', 'scale-95');
    }, 10);

    // Initial loading HTML
    content.innerHTML = `
        <div class="flex flex-col items-center justify-center py-20 gap-4">
            <div class="relative w-12 h-12">
                <div class="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                <div class="absolute inset-0 rounded-full border-4 border-cyan-600 border-t-transparent animate-spin"></div>
            </div>
            <p class="text-sm font-bold text-slate-400 animate-pulse">Mengambil data RPP...</p>
        </div>
    `;

    try {
        const response = await fetch(`../../api/rpp/get_detail.php?rpp_id=${id}`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;
            title.textContent = data.title;
            subtitle.innerHTML = `
                <i class="fa-solid fa-graduation-cap h-3 w-3 text-cyan-500"></i>
                ${data.subject_name} <span class="text-slate-300 mx-1">•</span> Kelas ${data.grade_name} <span class="text-slate-300 mx-1">•</span> ${data.teacher_name}
            `;
            
            document.getElementById('modal-print-btn').href = `<?php url('views/rpp/print.php?id='); ?>${id}`;
            document.getElementById('modal-edit-btn').href = `<?php url('views/rpp/edit.php?id='); ?>${id}`;

            content.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-100 shadow-inner">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm">
                            <i class="fa-solid fa-calendar-days w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-0.5">Tahun Ajaran / Sem</div>
                            <div class="text-sm font-black text-slate-800">${data.academic_year} (${data.semester})</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm">
                            <i class="fa-solid fa-clock w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-0.5">Alokasi Waktu</div>
                            <div class="text-sm font-black text-slate-800">${data.allocation || '-' }</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-slate-400 border border-slate-100 shadow-sm">
                            <i class="fa-solid fa-hashtag w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-slate-400 uppercase mb-0.5">Pertemuan Ke-</div>
                            <div class="text-sm font-black text-slate-800">${data.session_no || '-' }</div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                    ${renderSection('A', 'Capaian Pembelajaran (CP)', data.content_cp, 'bg-cyan-50 text-cyan-600')}
                    ${renderSection('B', 'Alur Tujuan Pembelajaran (ATP)', data.content_atp, 'bg-indigo-50 text-indigo-600')}
                    ${renderSection('C', 'Pertanyaan Pemantik', data.content_pertanyaan_pemantik, 'bg-amber-50 text-amber-600')}
                    ${renderSection('D', 'Tujuan Pembelajaran (TP)', data.learning_goal, 'bg-emerald-50 text-emerald-600')}
                    ${renderSection('E', 'Materi Ajar', data.teaching_material, 'bg-rose-50 text-rose-600')}
                    ${renderSection('F', 'Profil Pelajar Pancasila', data.teaching_profil_pancasila, 'bg-violet-50 text-violet-600')}
                    <div class="md:col-span-2">
                        ${renderSection('G', 'Kegiatan Pembelajaran', data.content_steps, 'bg-slate-800 text-white', 'grid grid-cols-1')}
                    </div>
                    ${renderSection('H', 'Media & Sumber Belajar', data.content_summary, 'bg-slate-100 text-slate-600')}
                    ${renderSection('I', 'Asesmen', data.assessment, 'bg-green-50 text-green-600')}
                </div>
            `;
        }
    } catch (e) {
        content.innerHTML = `<div class="text-center py-10 text-rose-500 font-bold">Terjadi Kesalahan Sistem.</div>`;
    }
}

function renderSection(number, title, content, badgeStyle, gridClass = '') {
    if(!content || content.trim() === '') return '';
    return `
        <section class="${gridClass}">
            <div class="flex items-center gap-3 mb-3">
                <span class="flex-shrink-0 w-6 h-6 rounded-lg ${badgeStyle} flex items-center justify-center text-[10px] font-black">${number}</span>
                <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-widest">${title}</h4>
            </div>
            <div class="text-sm text-slate-600 leading-relaxed pl-9 border-l-2 border-slate-50 whitespace-pre-line">${content}</div>
        </section>
    `;
}

function closeViewModal() {
    const modal = document.getElementById('viewModal');
    const overlay = document.getElementById('viewModalOverlay');
    const modalContent = document.getElementById('viewModalContent');
    
    overlay.classList.add('opacity-0');
    modalContent.classList.add('opacity-0', 'scale-95');
    
    setTimeout(() => {
        modal.classList.add('invisible', 'pointer-events-none');
    }, 300);
}

function openDeleteModal(url) {
    if (confirm('Apakah Anda yakin ingin menghapus RPP ini?')) {
        window.location.href = url;
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
