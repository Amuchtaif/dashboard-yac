<?php
// views/documents/approval.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('document.approve');

$page_title = "Persetujuan Surat";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch documents awaiting approval by this user
$stmtPending = $conn->prepare("
    SELECT d.id, d.title, d.document_number, d.created_at, e.full_name as creator_name, da.stage_order
    FROM document_approvals da
    JOIN documents d ON da.document_id = d.id
    JOIN employees e ON d.creator_id = e.id
    WHERE da.approver_id = ? AND da.status = 'pending'
    ORDER BY d.created_at DESC
");
$stmtPending->execute([$user_id]);
$pending_list = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

// Fetch approval history by this user
$stmtHistory = $conn->prepare("
    SELECT d.id, d.title, d.document_number, da.status, da.approved_at, da.notes, e.full_name as creator_name
    FROM document_approvals da
    JOIN documents d ON da.document_id = d.id
    JOIN employees e ON d.creator_id = e.id
    WHERE da.approver_id = ? AND da.status != 'pending'
    ORDER BY da.approved_at DESC
    LIMIT 10
");
$stmtHistory->execute([$user_id]);
$history_list = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Persetujuan & Verifikasi Dokumen</h1>
        <p class="mt-1 text-sm text-slate-500">Tinjau dan bubuhkan persetujuan atau penolakan pada surat dinas yayasan.</p>
    </div>

    <!-- PENDING LIST -->
    <div class="mt-8">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Menunggu Persetujuan Anda</h2>
        
        <div class="mt-4 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Nomor Draf</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Perihal / Judul</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Diajukan Oleh</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tahap Alur</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tanggal Masuk</th>
                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi Tinjauan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (count($pending_list) > 0): ?>
                        <?php foreach ($pending_list as $index => $item): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                    <?php echo $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                    <?php echo htmlspecialchars($item['document_number']); ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 font-medium">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <?php echo htmlspecialchars($item['creator_name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-center text-slate-500">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">Tahap <?php echo $item['stage_order']; ?></span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                    <?php echo date('d M Y, H:i', strtotime($item['created_at'])); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium space-x-2">
                                    <!-- View/Review button triggers modal -->
                                    <button type="button" onclick="openReviewModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['title'])); ?>')"
                                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[10px] font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                        Tinjau Dokumen
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-slate-500 italic">Tidak ada persetujuan masuk. Kerja bagus!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RECENT HISTORY -->
    <div class="mt-10">
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Riwayat Persetujuan Anda (Terbaru)</h2>
        
        <div class="mt-4 overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Nomor Surat</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Perihal / Judul</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Diajukan Oleh</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Keputusan</th>
                        <th scope="col" class="px-3 py-3.5 text-left">Catatan / Komentar</th>
                        <th scope="col" class="px-3 py-3.5 text-center">Tanggal Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (count($history_list) > 0): ?>
                        <?php foreach ($history_list as $index => $hist): 
                            $status = $hist['status'];
                            $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
                            if ($status === 'rejected') $badge_class = 'bg-rose-50 text-rose-700 border-rose-200/50';
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                    <?php echo $index + 1; ?>.
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                    <?php echo htmlspecialchars($hist['document_number'] ?: '-'); ?>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-600 font-medium">
                                    <?php echo htmlspecialchars($hist['title']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-500">
                                    <?php echo htmlspecialchars($hist['creator_name']); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-center">
                                    <span class="inline-flex items-center rounded-xl border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                        <?php echo $status === 'approved' ? 'Disetujui' : 'Ditolak'; ?>
                                    </span>
                                </td>
                                <td class="px-3 py-4 text-xs text-slate-500 italic max-w-xs truncate">
                                    <?php echo htmlspecialchars($hist['notes'] ?: '-'); ?>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                    <?php echo date('d/m/Y H:i', strtotime($hist['approved_at'])); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-10 text-center text-sm text-slate-500 italic">Belum ada riwayat persetujuan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div id="reviewModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Backdrop -->
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-slate-900 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeReviewModal()"></div>

        <!-- Position modal in center -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-2xl bg-white text-left align-middle shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-4xl">
            <!-- Header -->
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider" id="modal-title-label">Tinjauan Surat</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5" id="modal-subtitle-label">Pratinjau konten draf secara utuh</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition-colors" onclick="closeReviewModal()">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="px-6 py-6 space-y-6">
                <!-- Iframe containing document view -->
                <div class="border border-slate-200 rounded-xl bg-slate-50 p-6 min-h-[300px] max-h-[450px] overflow-y-auto">
                    <div id="document-preview-container" class="prose max-w-none text-slate-800 text-xs" style="font-family: serif;">
                        <!-- Content injected dynamically -->
                        Memuat berkas draf...
                    </div>
                </div>

                <!-- Approval Form -->
                <form action="<?php url('logic/documents/approve_document.php'); ?>" method="POST" class="space-y-4 border-t border-slate-100 pt-5">
                    <input type="hidden" name="id" id="modal-doc-id" value="">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">Catatan / Alasan (Opsional untuk Setuju, Wajib untuk Tolak)</label>
                        <textarea name="notes" id="modal-notes" rows="3"
                                  class="mt-2 block w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 text-xs bg-slate-50 border p-3 text-slate-700 placeholder:text-slate-400"
                                  placeholder="Ketik catatan persetujuan atau alasan detail penolakan surat di sini..."></textarea>
                    </div>

                    <div class="flex justify-between items-center">
                        <button type="button" onclick="closeReviewModal()" class="inline-flex items-center justify-center rounded-xl bg-white border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                            Tutup
                        </button>
                        
                        <div class="flex gap-2">
                            <!-- Reject -->
                            <button type="submit" name="status" value="rejected" onclick="return validateReject()"
                                    class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition-colors">
                                Tolak Draf
                            </button>
                            <!-- Approve -->
                            <button type="submit" name="status" value="approved"
                                    class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                Setujui & Lanjutkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openReviewModal(id, title) {
    document.getElementById('modal-doc-id').value = id;
    document.getElementById('modal-title-label').innerText = 'Tinjauan: ' + title;
    document.getElementById('document-preview-container').innerHTML = 'Memuat berkas draf...';
    
    // Fetch document content dynamically
    fetch('<?php url("views/documents/outgoing.php?action=view&id="); ?>' + id)
        .then(response => response.text())
        .then(html => {
            // Parse and extract the document content div
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const content = doc.querySelector('.prose').innerHTML;
            document.getElementById('document-preview-container').innerHTML = content;
        })
        .catch(err => {
            document.getElementById('document-preview-container').innerHTML = '<span class="text-rose-600 font-bold">Gagal memuat pratinjau surat.</span>';
        });

    document.getElementById('reviewModal').classList.remove('hidden');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.getElementById('modal-notes').value = '';
}

function validateReject() {
    const notes = document.getElementById('modal-notes').value.trim();
    if (notes.length === 0) {
        alert('Anda wajib menuliskan alasan penolakan pada kolom Catatan/Alasan.');
        return false;
    }
    return confirm('Apakah Anda yakin ingin menolak draft surat ini?');
}
</script>

<?php include '../layouts/footer.php'; ?>
