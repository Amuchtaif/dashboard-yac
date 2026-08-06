<?php
// views/documents/disposition.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('document.disposition');

$page_title = "Disposisi Masuk";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Inline Status Update Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $disp_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $new_status = isset($_POST['status']) ? $_POST['status'] : '';

    if ($disp_id > 0 && in_array($new_status, ['pending', 'in_progress', 'completed'])) {
        try {
            // Verify this disposition belongs to the user
            $stmtCheck = $conn->prepare("SELECT id FROM document_dispositions WHERE id = ? AND to_user_id = ?");
            $stmtCheck->execute([$disp_id, $user_id]);
            if ($stmtCheck->fetchColumn()) {
                $stmtUpdate = $conn->prepare("UPDATE document_dispositions SET status = ? WHERE id = ?");
                $stmtUpdate->execute([$new_status, $disp_id]);
                
                Logger::activity('Dokumen', 'UPDATE_DISPOSITION_STATUS', 'Mengubah status disposisi menjadi: ' . $new_status, ['id' => $disp_id]);
                redirect("views/documents/disposition.php?success=Status+disposisi+berhasil+diperbarui");
            } else {
                redirect("views/documents/disposition.php?error=Akses+ditolak");
            }
        } catch (PDOException $e) {
            redirect("views/documents/disposition.php?error=Gagal+memperbarui+status");
        }
    }
}

// Fetch dispositions received by the user
$stmtList = $conn->prepare("
    SELECT dd.id, dd.notes, dd.deadline, dd.status, dd.created_at,
           d.title, d.document_number, d.sender as original_sender, d.file_path,
           e_from.full_name as from_user_name
    FROM document_dispositions dd
    JOIN documents d ON dd.document_id = d.id
    JOIN employees e_from ON dd.from_user_id = e_from.id
    WHERE dd.to_user_id = ?
    ORDER BY dd.created_at DESC
");
$stmtList->execute([$user_id]);
$dispositions = $stmtList->fetchAll(PDO::FETCH_ASSOC);

// Fetch outgoing dispositions made by the user
$stmtListOut = $conn->prepare("
    SELECT dd.id, dd.notes, dd.deadline, dd.status, dd.created_at,
           d.title, d.document_number,
           e_to.full_name as to_user_name
    FROM document_dispositions dd
    JOIN documents d ON dd.document_id = d.id
    JOIN employees e_to ON dd.to_user_id = e_to.id
    WHERE dd.from_user_id = ?
    ORDER BY dd.created_at DESC
");
$stmtListOut->execute([$user_id]);
$my_dispositions = $stmtListOut->fetchAll(PDO::FETCH_ASSOC);

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Disposisi & Instruksi Surat</h1>
        <p class="mt-1 text-sm text-slate-500">Tindak lanjuti instruksi delegasi surat dinas dari pimpinan yayasan.</p>
    </div>

    <!-- TABS -->
    <div class="mt-8" x-data="{ activeTab: 'incoming' }">
        <div class="border-b border-slate-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button @click="activeTab = 'incoming'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'incoming', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeTab !== 'incoming' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm transition-colors duration-200">
                    Disposisi Masuk
                </button>
                <button @click="activeTab = 'outgoing'" :class="{ 'border-indigo-500 text-indigo-600': activeTab === 'outgoing', 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300': activeTab !== 'outgoing' }" class="whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm transition-colors duration-200">
                    Riwayat Disposisi Keluar
                </button>
            </nav>
        </div>

        <!-- TAB CONTENT: INCOMING -->
        <div x-show="activeTab === 'incoming'" class="mt-6">
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Pemberi Instruksi</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Perihal / Judul Surat</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Catatan Instruksi</th>
                            <th scope="col" class="px-3 py-3.5 class text-center">Batas Waktu</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Lampiran</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Status</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6 text-center">Aksi Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (count($dispositions) > 0): ?>
                            <?php foreach ($dispositions as $index => $disp): 
                                $status = $disp['status'];
                                $badge_class = 'bg-slate-100 text-slate-700 border-slate-200';
                                if ($status === 'in_progress') $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/50';
                                elseif ($status === 'completed') $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
                            ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                        <?php echo $index + 1; ?>.
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                        <?php echo htmlspecialchars($disp['from_user_name']); ?>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-slate-600 font-medium max-w-xs truncate">
                                        <div class="font-bold text-slate-700"><?php echo htmlspecialchars($disp['title']); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">No: <?php echo htmlspecialchars($disp['document_number']); ?></div>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-slate-500 italic max-w-sm">
                                        "<?php echo htmlspecialchars($disp['notes']); ?>"
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-center font-bold <?php echo ($disp['deadline'] && strtotime($disp['deadline']) < time()) ? 'text-rose-600' : 'text-slate-600'; ?>">
                                        <?php echo $disp['deadline'] ? date('d/m/Y', strtotime($disp['deadline'])) : '-'; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <?php if (!empty($disp['file_path'])): ?>
                                            <a href="<?php echo BASE_URL . '/' . htmlspecialchars($disp['file_path']); ?>" target="_blank" class="inline-flex items-center rounded-lg bg-indigo-50 px-2 py-1 text-[10px] font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                                Lihat File
                                            </a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-300 italic">No File</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <span class="inline-flex items-center rounded-xl border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                            <?php echo $status === 'in_progress' ? 'Dalam Proses' : ($status === 'completed' ? 'Selesai' : 'Baru (Pending)'); ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center text-xs font-medium">
                                        <form action="" method="POST" class="inline-flex gap-1">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="id" value="<?php echo $disp['id']; ?>">
                                            
                                            <?php if ($status !== 'in_progress' && $status !== 'completed'): ?>
                                                <button type="submit" name="status" value="in_progress" class="inline-flex items-center rounded-lg bg-amber-50 px-2 py-1 text-[9px] font-bold text-amber-700 hover:bg-amber-100 transition-colors">
                                                    Proses
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($status !== 'completed'): ?>
                                                <button type="submit" name="status" value="completed" class="inline-flex items-center rounded-lg bg-emerald-50 px-2 py-1 text-[9px] font-bold text-emerald-700 hover:bg-emerald-100 transition-colors">
                                                    Selesai
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="py-10 text-center text-sm text-slate-500 italic">Tidak ada disposisi masuk yang ditugaskan ke Anda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB CONTENT: OUTGOING -->
        <div x-show="activeTab === 'outgoing'" class="mt-6" style="display: none;">
            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-xl border border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left sm:pl-6 w-12">No.</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Penerima Disposisi</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Perihal / Judul Surat</th>
                            <th scope="col" class="px-3 py-3.5 text-left">Catatan Instruksi</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Batas Waktu</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Status</th>
                            <th scope="col" class="px-3 py-3.5 text-center">Tanggal Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (count($my_dispositions) > 0): ?>
                            <?php foreach ($my_dispositions as $index => $disp): 
                                $status = $disp['status'];
                                $badge_class = 'bg-slate-100 text-slate-700 border-slate-200';
                                if ($status === 'in_progress') $badge_class = 'bg-amber-50 text-amber-700 border-amber-200/50';
                                elseif ($status === 'completed') $badge_class = 'bg-emerald-50 text-emerald-700 border-emerald-200/50';
                            ?>
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs text-slate-400 sm:pl-6">
                                        <?php echo $index + 1; ?>.
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs font-bold text-slate-700">
                                        <?php echo htmlspecialchars($disp['to_user_name']); ?>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-slate-600 font-medium">
                                        <div class="font-bold text-slate-700"><?php echo htmlspecialchars($disp['title']); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">No: <?php echo htmlspecialchars($disp['document_number']); ?></div>
                                    </td>
                                    <td class="px-3 py-4 text-xs text-slate-500 italic max-w-sm">
                                        "<?php echo htmlspecialchars($disp['notes']); ?>"
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-center">
                                        <?php echo $disp['deadline'] ? date('d/m/Y', strtotime($disp['deadline'])) : '-'; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-center">
                                        <span class="inline-flex items-center rounded-xl border px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider <?php echo $badge_class; ?>">
                                            <?php echo $status === 'in_progress' ? 'Dalam Proses' : ($status === 'completed' ? 'Selesai' : 'Pending'); ?>
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4 text-xs text-slate-400 text-center">
                                        <?php echo date('d/m/Y H:i', strtotime($disp['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="py-10 text-center text-sm text-slate-500 italic">Belum ada riwayat disposisi keluar yang Anda kirimkan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Alpine JS for tabs -->
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

<?php include '../layouts/footer.php'; ?>
