<?php
// views/documents/signature.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('document.sign');

$page_title = "Tanda Tangan Saya";

$db = new Database();
$conn = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Retrieve current employee's signature path
$stmtEmp = $conn->prepare("SELECT signature_path, full_name FROM employees WHERE id = ?");
$stmtEmp->execute([$user_id]);
$employee = $stmtEmp->fetch(PDO::FETCH_ASSOC);

$signature_path = $employee['signature_path'] ?? '';
$has_sig = (!empty($signature_path) && file_exists(BASE_PATH . '/' . $signature_path));

include '../layouts/header.php';
?>
<!-- Font Awesome CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="pb-10">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-5">
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Pengaturan Tanda Tangan Digital</h1>
        <p class="mt-1 text-sm text-slate-500">Unggah berkas tanda tangan fisik Anda untuk disematkan secara elektronik saat menyetujui surat dinas.</p>
    </div>

    <!-- MAIN GRID -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
        <!-- Left: Upload Form -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Unggah Tanda Tangan Baru</h3>
                <p class="text-xs text-slate-400 mt-1">Harap perhatikan ketentuan gambar untuk kejelasan cetak surat dinas.</p>

                <!-- Guidelines Alert -->
                <div class="mt-4 bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-xs text-indigo-800 leading-relaxed font-medium">
                    <p class="font-bold text-indigo-900 mb-1">Ketentuan Berkas:</p>
                    <ul class="list-disc pl-4 space-y-1 mt-1.5">
                        <li>Format gambar wajib berupa <span class="font-bold">PNG</span>.</li>
                        <li>Direkomendasikan berlatar belakang <span class="font-bold">transparan (transparent background)</span> agar tidak menutupi teks surat.</li>
                        <li>Goresan pulpen berwarna hitam atau biru tua tebal.</li>
                        <li>Ukuran berkas maksimal 2 MB.</li>
                    </ul>
                </div>

                <form action="<?php url('logic/documents/upload_signature.php'); ?>" method="POST" enctype="multipart/form-data" class="mt-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Pilih File Tanda Tangan</label>
                        <input type="file" name="signature" required accept="image/png"
                               class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer">
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                            Simpan & Terapkan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Current Active Signature preview -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm flex flex-col justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Tanda Tangan Aktif Saat Ini</h3>
                <p class="text-xs text-slate-400 mt-1">Pratinjau gambar tanda tangan digital Anda yang tersimpan di server.</p>

                <div class="mt-6 border border-dashed border-slate-300 rounded-2xl bg-slate-50/50 p-8 flex items-center justify-center min-h-[180px]">
                    <?php if ($has_sig): ?>
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($signature_path); ?>" 
                             alt="Tanda Tangan <?php echo htmlspecialchars($employee['full_name']); ?>"
                             class="max-h-[140px] max-w-full object-contain mix-blend-multiply">
                    <?php else: ?>
                        <div class="text-center text-slate-400 text-xs flex flex-col items-center">
                            <i class="fa-solid fa-file-signature text-4xl text-slate-300 mb-2"></i>
                            <p class="font-bold">Belum Ada Gambar</p>
                            <p class="text-[10px] text-slate-400 mt-0.5">Unggah berkas PNG di panel sebelah kiri.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 text-[10px] text-slate-400 text-center">
                <p>Pegawai bertanggung jawab penuh atas keabsahan gambar tanda tangan digital yang disimpan di sistem ini.</p>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
