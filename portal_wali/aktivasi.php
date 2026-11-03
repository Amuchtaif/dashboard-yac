<?php
// portal_wali/aktivasi.php
// Halaman Aktivasi Akun Mandiri Wali Santri (Verifikasi NISN + Tanggal Lahir Anak)
require_once __DIR__ . '/init.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn = trim($_POST['nisn'] ?? '');
    $tgl_lahir = trim($_POST['tgl_lahir'] ?? '');
    $parent_name = trim($_POST['parent_name'] ?? '');
    $relationship = trim($_POST['relationship'] ?? 'Ayah');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Normalisasi & Validasi Nomor WhatsApp
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (!empty($phone) && !ctype_digit($phone)) {
        $error = 'Nomor WhatsApp hanya boleh berisi angka (tanpa huruf, spasi, atau tanda khusus).';
    } elseif (empty($nisn) || empty($tgl_lahir) || empty($parent_name) || empty($clean_phone) || empty($password)) {
        $error = 'Semua formulir wajib diisi dengan benar.';
    } elseif (strlen($clean_phone) < 9) {
        $error = 'Nomor WhatsApp minimal 9 digit angka.';
    } elseif (strlen($password) < 6) {
        $error = 'Kata sandi / PIN minimal 6 karakter.';
    } else {
        if (strpos($clean_phone, '62') === 0) {
            $clean_phone = '0' . substr($clean_phone, 2);
        }
        $db = new Database();
        $conn = $db->getConnection();

        // 1. Verifikasi Kecocokan Data Santri (Hanya Santri Aktif)
        $stmt_student = $conn->prepare("
            SELECT * FROM students 
            WHERE nomor_induk = :nisn AND tanggal_lahir = :tgl_lahir AND status = 'Aktif'
            LIMIT 1
        ");
        $stmt_student->execute([
            ':nisn' => $nisn,
            ':tgl_lahir' => $tgl_lahir
        ]);
        $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $error = 'Data santri aktif tidak ditemukan! Pastikan NISN dan tanggal lahir santri sesuai serta berstatus aktif.';
        } else {
            // 2. Cek apakah akun wali dengan nomor WA ini sudah pernah dibuat sebelumnya
            $stmt_guard = $conn->prepare("SELECT * FROM guardians WHERE phone_number = :phone LIMIT 1");
            $stmt_guard->execute([':phone' => $clean_phone]);
            $guardian = $stmt_guard->fetch(PDO::FETCH_ASSOC);

            if ($guardian) {
                $guardian_id = (int)$guardian['id'];
                // Update password baru jika diperlukan
                $upd_pwd = $conn->prepare("UPDATE guardians SET password_hash = :pwd, full_name = :fn WHERE id = :id");
                $upd_pwd->execute([
                    ':pwd' => password_hash($password, PASSWORD_BCRYPT),
                    ':fn' => $parent_name,
                    ':id' => $guardian_id
                ]);
            } else {
                // Buat akun baru di tabel guardians
                $stmt_new = $conn->prepare("
                    INSERT INTO guardians (phone_number, full_name, password_hash, relationship, status, created_at)
                    VALUES (:phone, :full_name, :password_hash, :relationship, 'active', NOW())
                ");
                $stmt_new->execute([
                    ':phone' => $clean_phone,
                    ':full_name' => $parent_name,
                    ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    ':relationship' => in_array($relationship, ['Ayah', 'Ibu', 'Wali']) ? $relationship : 'Ayah'
                ]);
                $guardian_id = (int)$conn->lastInsertId();
            }

            // 3. Hubungkan akun wali dengan santri di tabel guardian_students
            $stmt_link = $conn->prepare("
                INSERT INTO guardian_students (guardian_id, student_id, relationship, is_primary, is_verified, created_at)
                VALUES (:gid, :sid, :rel, 1, 1, NOW())
                ON DUPLICATE KEY UPDATE relationship = :rel_upd, is_verified = 1
            ");
            $stmt_link->execute([
                ':gid' => $guardian_id,
                ':sid' => $student['id'],
                ':rel' => $relationship,
                ':rel_upd' => $relationship
            ]);

            // 4. Auto Login & Redirect
            $_SESSION['guardian_id'] = $guardian_id;
            $_SESSION['guardian_name'] = $parent_name;
            $_SESSION['guardian_phone'] = $clean_phone;
            $_SESSION['guardian_relationship'] = $relationship;
            $_SESSION['active_student_id'] = (int)$student['id'];

            header("Location: " . portal_url('index.php?first_login=1'));
            exit;
        }
    }
}

$page_title = "Aktivasi Akun Wali";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-5 pt-6 pb-12">
    <!-- Top Back Navigation -->
    <div class="mb-5">
        <a href="login.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Kembali ke Halaman Masuk</span>
        </a>
    </div>

    <!-- Title Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Aktivasi Akun Wali</h1>
        <p class="text-xs text-slate-500 mt-1">
            Verifikasi identitas santri untuk mendaftarkan akun orang tua ke sistem portal Yayasan Assunnah.
        </p>
    </div>

    <!-- Alert Error -->
    <?php if (!empty($error)): ?>
        <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-700 text-xs flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-rose-500 shrink-0 text-sm"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Step Information Card -->
    <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-800 text-xs mb-6 flex items-start gap-3">
        <i class="fa-solid fa-shield-halved text-amber-600 mt-0.5 shrink-0 text-sm"></i>
        <div class="leading-relaxed">
            <strong>Verifikasi Keamanan:</strong> Masukkan <strong>NISN</strong> dan <strong>Tanggal Lahir</strong> santri sesuai data rapor / akta pendaftaran sekolah untuk memvalidasi kepemilikan data anak.
        </div>
    </div>

    <!-- Form Aktivasi -->
    <form method="POST" action="aktivasi.php" class="space-y-4">
        <!-- Section: Data Santri -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3.5">
            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-xs font-bold">1</span>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Identitas Santri</h3>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Nomor Induk / NISN Santri <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="nisn" required placeholder="Contoh: 0081234567"
                       value="<?php echo htmlspecialchars($_POST['nisn'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none transition-all shadow-xs">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Tanggal Lahir Santri <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="tgl_lahir" required
                       value="<?php echo htmlspecialchars($_POST['tgl_lahir'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none transition-all shadow-xs">
                <span class="text-[10px] text-slate-400 mt-1 block">Sesuai data pendaftaran di sekolah</span>
            </div>
        </div>

        <!-- Section: Data Akun Orang Tua -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3.5">
            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-xs font-bold">2</span>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Data Akun Orang Tua</h3>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Nama Lengkap Orang Tua / Wali <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="parent_name" required placeholder="Contoh: H. Ahmad Subagio"
                       value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none transition-all shadow-xs capitalize">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Hubungan <span class="text-rose-500">*</span>
                    </label>
                    <select name="relationship" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none shadow-xs">
                        <option value="Ayah" <?php echo (($_POST['relationship'] ?? '') === 'Ayah') ? 'selected' : ''; ?>>Ayah</option>
                        <option value="Ibu" <?php echo (($_POST['relationship'] ?? '') === 'Ibu') ? 'selected' : ''; ?>>Ibu</option>
                        <option value="Wali" <?php echo (($_POST['relationship'] ?? '') === 'Wali') ? 'selected' : ''; ?>>Wali</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        No. WhatsApp <span class="text-rose-500">*</span>
                    </label>
                    <input type="tel" name="phone" id="inputPhone" required placeholder="08xxxxxxxxxx"
                           inputmode="numeric" pattern="[0-9]*" maxlength="16"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none shadow-xs">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Hanya angka (contoh: 08xxxxxxxxxx).</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Buat Kata Sandi / PIN Baru <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none transition-all shadow-xs">
                <span class="text-[10px] text-slate-400 mt-1 block">Kata sandi ini digunakan untuk masuk ke portal berikutnya</span>
            </div>
        </div>

        <button type="submit" 
                class="w-full py-3.5 bg-gradient-to-r from-teal-700 to-teal-800 hover:from-teal-800 hover:to-teal-900 text-white rounded-2xl font-bold text-sm shadow-md shadow-teal-700/20 transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-4">
            <i class="fa-solid fa-check"></i>
            <span>Verifikasi & Aktifkan Akun</span>
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

