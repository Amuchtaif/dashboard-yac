<?php
// portal_wali/login.php
require_once __DIR__ . '/init.php';

// Jika sudah login, langsung ke beranda
if (!empty($_SESSION['guardian_id'])) {
    header("Location: " . portal_url('index.php'));
    exit;
}

$error = '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sesi keamanan formulir telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validasi hanya angka pada nomor WA
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    if (!empty($phone) && !ctype_digit($phone)) {
        $error = 'Nomor WhatsApp hanya boleh berisi angka (tanpa spasi, huruf, atau tanda khusus).';
    } elseif (empty($clean_phone) || empty($password)) {
        $error = 'Nomor WhatsApp dan kata sandi wajib diisi.';
    } elseif (strlen($clean_phone) < 9) {
        $error = 'Nomor WhatsApp minimal 9 digit angka.';
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        if (strpos($clean_phone, '62') === 0) {
            $clean_phone = '0' . substr($clean_phone, 2);
        }

        // Cari akun wali murid berdasarkan nomor WA (cek format 08... dan 62...)
        $alt_phone = (strpos($clean_phone, '0') === 0) ? '62' . substr($clean_phone, 1) : '0' . substr($clean_phone, 2);
        
        $stmt = $conn->prepare("
            SELECT * FROM guardians 
            WHERE (phone_number = :phone1 OR phone_number = :phone2)
            LIMIT 1
        ");
        $stmt->execute([':phone1' => $clean_phone, ':phone2' => $alt_phone]);
        $guardian = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($guardian && password_verify($password, $guardian['password_hash'])) {
            if ($guardian['status'] === 'inactive') {
                $error = 'Akses akun Anda telah dinonaktifkan oleh administrator sekolah. Silakan hubungi bagian Tata Usaha Yayasan Assunnah.';
            } else {
                // Login Berhasil - Regenerasi ID sesi untuk mencegah fixation
                session_regenerate_id(true);
                $_SESSION['guardian_id'] = (int)$guardian['id'];
                $_SESSION['guardian_name'] = $guardian['full_name'];
                $_SESSION['guardian_phone'] = $guardian['phone_number'];
                $_SESSION['guardian_relationship'] = $guardian['relationship'];

                // Update last login
                $upd = $conn->prepare("UPDATE guardians SET last_login_at = NOW() WHERE id = :id");
                $upd->execute([':id' => $guardian['id']]);

                // Set anak aktif default
                $children = get_guardian_children($guardian['id']);
                if (!empty($children)) {
                    $_SESSION['active_student_id'] = (int)$children[0]['student_id'];
                }

                header("Location: " . portal_url('index.php'));
                exit;
            }
        } else {
            $error = 'Nomor WhatsApp atau kata sandi tidak cocok.';
        }
    }
    }
}

$page_title = "Masuk";
require_once __DIR__ . '/layouts/header.php';
?>

<div class="px-5 pt-8 pb-10 flex flex-col justify-between min-h-[90vh]">
    <div>
        <!-- Hero Header -->
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white rounded-3xl p-3 shadow-md mx-auto mb-4 border border-slate-100 flex items-center justify-center">
                <img src="assets/logo.png" alt="Yayasan Assunnah Cirebon" class="w-full h-full object-contain">
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Portal Wali Santri</h1>
            <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto">
                Sistem informasi terintegrasi pemantauan akademik, hafalan Al-Qur'an, dan kegiatan santri.
            </p>
        </div>

        <!-- Alert Error / Success -->
        <?php if (!empty($error)): ?>
            <div class="mb-5 p-3.5 rounded-2xl bg-rose-50 border border-rose-200/80 text-rose-700 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-rose-500 shrink-0 text-sm"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-5 p-3.5 rounded-2xl bg-brand-50 border border-brand-200/80 text-brand-800 text-xs flex items-center gap-2.5">
                <i class="fa-solid fa-circle-check text-[#448AFF] shrink-0 text-sm"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="login.php" class="space-y-4" onsubmit="return validateLoginForm()">
            <?php echo csrf_field(); ?>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Nomor WhatsApp
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-brands fa-whatsapp text-base text-[#448AFF]"></i>
                    </div>
                    <input type="tel" name="phone" id="inputPhone" required placeholder="Contoh: 081234567890"
                           inputmode="numeric" pattern="[0-9]*" maxlength="16"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                           class="w-full pl-10 pr-3.5 py-3 rounded-2xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs">
                </div>
                <span class="text-[10px] text-slate-400 mt-1 block">Gunakan hanya angka nomor WhatsApp (contoh: 08xxxxxxxxxx).</span>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Kata Sandi / PIN
                    </label>
                </div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-solid fa-lock text-sm"></i>
                    </div>
                    <input type="password" name="password" id="inputPassword" required placeholder="Masukkan kata sandi / PIN"
                           class="w-full pl-10 pr-11 py-3 rounded-2xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs">
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-eye text-sm" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" 
                    class="w-full py-3.5 bg-[#448AFF] hover:bg-brand-600 text-white rounded-2xl font-bold text-sm shadow-md shadow-brand-500/25 transition-all active:scale-[0.98] mt-2 flex items-center justify-center gap-2">
                <span>Masuk ke Portal</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <!-- Divider -->
        <div class="relative my-7">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-200"></div>
            </div>
            <div class="relative flex justify-center text-[11px] uppercase">
                <span class="bg-slate-50 px-3 text-slate-400 font-semibold tracking-wider">Belum Memiliki Akun?</span>
            </div>
        </div>

        <!-- Tombol Aktivasi Mandiri -->
        <a href="aktivasi.php" 
           class="w-full py-3 bg-white border border-slate-300 hover:bg-slate-100/80 text-slate-700 rounded-2xl font-bold text-xs shadow-xs transition-all flex items-center justify-center gap-2">
            <i class="fa-solid fa-user-plus text-[#448AFF]"></i>
            <span>Aktivasi Akun Wali Santri Baru</span>
        </a>
    </div>

    <!-- Footer Copyright -->
    <div class="text-center pt-8">
        <p class="text-[11px] text-slate-400">
            &copy; <?php echo date('Y'); ?> Yayasan Assunnah Cirebon.<br>Portal Khusus Wali Santri & Orang Tua.
        </p>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
function validateLoginForm() {
    const phoneInput = document.getElementById('inputPhone');
    const phoneVal = phoneInput.value.trim();
    if (!/^[0-9]+$/.test(phoneVal)) {
        alert('Nomor WhatsApp hanya boleh berisi angka.');
        phoneInput.focus();
        return false;
    }
    if (phoneVal.length < 9) {
        alert('Nomor WhatsApp minimal 9 digit angka.');
        phoneInput.focus();
        return false;
    }
    return true;
}

function togglePasswordVisibility() {
    const input = document.getElementById('inputPassword');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>

