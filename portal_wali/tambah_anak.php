<?php
// portal_wali/tambah_anak.php
// Menghubungkan santri lain ke akun orang tua yang sedang login
require_once __DIR__ . '/init.php';
check_guardian_auth();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sesi keamanan formulir telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $nisn = trim($_POST['nisn'] ?? '');
        $tgl_lahir = trim($_POST['tgl_lahir'] ?? '');
        $relationship = trim($_POST['relationship'] ?? $_SESSION['guardian_relationship'] ?? 'Ayah');

        if (empty($nisn) || empty($tgl_lahir)) {
            $error = 'NISN dan Tanggal Lahir santri wajib diisi.';
        } else {
            $db = new Database();
            $conn = $db->getConnection();

            // Verifikasi santri (Hanya Santri Aktif)
            $stmt = $conn->prepare("SELECT * FROM students WHERE nomor_induk = :nisn AND tanggal_lahir = :tgl_lahir AND status = 'Aktif' LIMIT 1");
            $stmt->execute([':nisn' => $nisn, ':tgl_lahir' => $tgl_lahir]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $error = 'Data santri aktif tidak ditemukan. Pastikan NISN dan tanggal lahir santri benar serta berstatus aktif.';
            } else {
                // Hubungkan ke guardian_students
                $stmt_link = $conn->prepare("
                    INSERT INTO guardian_students (guardian_id, student_id, relationship, is_primary, is_verified, created_at)
                    VALUES (:gid, :sid, :rel, 1, 1, NOW())
                    ON DUPLICATE KEY UPDATE is_verified = 1
                ");
                $stmt_link->execute([
                    ':gid' => $_SESSION['guardian_id'],
                    ':sid' => $student['id'],
                    ':rel' => in_array($relationship, ['Ayah', 'Ibu', 'Wali']) ? $relationship : 'Ayah'
                ]);

                $_SESSION['active_student_id'] = (int)$student['id'];
                header("Location: index.php?success=" . urlencode('Santri ' . $student['nama_siswa'] . ' berhasil ditambahkan ke akun Anda.'));
                exit;
            }
        }
    }
}

$page_title = "Tambah Data Anak";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-5 pt-5 pb-12">
    <div class="mb-4">
        <a href="index.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali ke Beranda</span>
        </a>
    </div>

    <div class="mb-5">
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Hubungkan Santri Baru</h1>
        <p class="text-xs text-slate-500 mt-0.5">
            Tambahkan anak kandung lainnya yang bersekolah di Yayasan Assunnah ke dalam akun Anda.
        </p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 p-3.5 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-700 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-500 shrink-0 text-sm"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" action="tambah_anak.php" class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
        <?php echo csrf_field(); ?>
        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">
                NISN Santri <span class="text-rose-500">*</span>
            </label>
            <input type="text" name="nisn" required placeholder="10 Digit NISN Anak"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">
                Tanggal Lahir Santri <span class="text-rose-500">*</span>
            </label>
            <input type="date" name="tgl_lahir" required
                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1">
                Hubungan dengan Santri
            </label>
            <select name="relationship" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                <option value="Ayah">Ayah</option>
                <option value="Ibu">Ibu</option>
                <option value="Wali">Wali</option>
            </select>
        </div>

        <button type="submit" 
                class="w-full py-3 bg-teal-700 hover:bg-teal-800 text-white rounded-xl font-bold text-xs shadow-md transition-all flex items-center justify-center gap-2 mt-2">
            <i class="fa-solid fa-link"></i>
            <span>Verifikasi & Hubungkan Santri</span>
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

