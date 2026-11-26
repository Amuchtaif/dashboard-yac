<?php
// portal_wali/aktivasi.php
// Halaman Aktivasi Akun Mandiri Wali Santri (Verifikasi Santri + Tanggal Lahir Anak)
require_once __DIR__ . '/init.php';

// -----------------------------------------------------------------------------
// AJAX Endpoint: Ambil Daftar Kelas & Santri (Tanpa membocorkan data sensitif)
// -----------------------------------------------------------------------------
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $action = $_GET['ajax'];

    try {
        $db = new Database();
        $conn = $db->getConnection();

        if ($action === 'get_classes') {
            $unit_id = (int)($_GET['unit_id'] ?? 0);
            if ($unit_id <= 0) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT gl.id, gl.name
                FROM grade_levels gl
                WHERE gl.education_unit_id = :uid AND gl.is_active = 1
                ORDER BY LENGTH(gl.name) ASC, gl.name ASC
            ");
            $stmt->execute([':uid' => $unit_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        if ($action === 'get_students') {
            $class_id = (int)($_GET['class_id'] ?? 0);
            if ($class_id <= 0) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $stmt = $conn->prepare("
                SELECT DISTINCT s.id, s.nama_siswa
                FROM students s
                JOIN student_class_history sch ON s.id = sch.student_id
                JOIN academic_years ay ON sch.academic_year_id = ay.id AND ay.is_active = 1
                WHERE sch.class_id = :cid
                  AND sch.status = 'ACTIVE'
                  AND s.status = 'Aktif'
                ORDER BY s.nama_siswa ASC
            ");
            $stmt->execute([':cid' => $class_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Action tidak valid']);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memuat data']);
        exit;
    }
}

$db = new Database();
$conn = $db->getConnection();

$error = '';
$success = '';

$identity_mode = ($_POST['identity_mode'] ?? 'picker') === 'nisn' ? 'nisn' : 'picker';
$selected_unit_id = (int)($_POST['unit_id'] ?? 0);
$selected_class_id = (int)($_POST['class_id'] ?? 0);
$selected_student_id = (int)($_POST['student_id'] ?? 0);

// Ambil tahun pelajaran yang sedang aktif dan daftar unit pendidikan
$active_year = null;
$units = [];
try {
    $active_year = $conn->query("SELECT id, name, semester FROM academic_years WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $units = $conn->query("SELECT id, name FROM education_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Preload kelas & santri jika user baru saja submit dan terjadi error validasi
$preloaded_classes = [];
$preloaded_students = [];
if ($selected_unit_id > 0) {
    try {
        $stmtCls = $conn->prepare("
            SELECT id, name FROM grade_levels
            WHERE education_unit_id = :uid AND is_active = 1
            ORDER BY LENGTH(name) ASC, name ASC
        ");
        $stmtCls->execute([':uid' => $selected_unit_id]);
        $preloaded_classes = $stmtCls->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}
if ($selected_class_id > 0) {
    try {
        $stmtStu = $conn->prepare("
            SELECT DISTINCT s.id, s.nama_siswa
            FROM students s
            JOIN student_class_history sch ON s.id = sch.student_id
            JOIN academic_years ay ON sch.academic_year_id = ay.id AND ay.is_active = 1
            WHERE sch.class_id = :cid AND sch.status = 'ACTIVE' AND s.status = 'Aktif'
            ORDER BY s.nama_siswa ASC
        ");
        $stmtStu->execute([':cid' => $selected_class_id]);
        $preloaded_students = $stmtStu->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sesi keamanan formulir telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
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
        } elseif ($identity_mode === 'picker' && $selected_student_id <= 0) {
            $error = 'Silakan pilih Unit Pendidikan, Kelas, dan Nama Santri terlebih dahulu.';
        } elseif ($identity_mode === 'nisn' && empty($nisn)) {
            $error = 'Nomor Induk / NISN santri wajib diisi.';
        } elseif (empty($tgl_lahir) || empty($parent_name) || empty($clean_phone) || empty($password)) {
            $error = 'Semua formulir wajib diisi dengan benar.';
        } elseif (strlen($clean_phone) < 9) {
            $error = 'Nomor WhatsApp minimal 9 digit angka.';
        } elseif (strlen($password) < 6) {
            $error = 'Kata sandi / PIN minimal 6 karakter.';
        } else {
            if (strpos($clean_phone, '62') === 0) {
                $clean_phone = '0' . substr($clean_phone, 2);
            }

            // 1. Verifikasi Kecocokan Data Santri (Hanya Santri Aktif)
            if ($identity_mode === 'picker') {
                $stmt_student = $conn->prepare("
                    SELECT * FROM students
                    WHERE id = :sid AND tanggal_lahir = :tgl_lahir AND status = 'Aktif'
                    LIMIT 1
                ");
                $stmt_student->execute([
                    ':sid' => $selected_student_id,
                    ':tgl_lahir' => $tgl_lahir
                ]);
            } else {
                $stmt_student = $conn->prepare("
                    SELECT * FROM students
                    WHERE nomor_induk = :nisn AND tanggal_lahir = :tgl_lahir AND status = 'Aktif'
                    LIMIT 1
                ");
                $stmt_student->execute([
                    ':nisn' => $nisn,
                    ':tgl_lahir' => $tgl_lahir
                ]);
            }
            $student = $stmt_student->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $error = $identity_mode === 'picker'
                    ? 'Verifikasi gagal! Tanggal lahir tidak cocok dengan santri yang dipilih atau santri sudah tidak aktif.'
                    : 'Data santri aktif tidak ditemukan! Pastikan NISN dan tanggal lahir santri sesuai serta berstatus aktif.';
            } else {
                // 2. Cek apakah akun wali dengan nomor WA ini sudah pernah dibuat sebelumnya
                $alt_phone = (strpos($clean_phone, '0') === 0) ? '62' . substr($clean_phone, 1) : '0' . substr($clean_phone, 2);
                $stmt_guard = $conn->prepare("SELECT id FROM guardians WHERE (phone_number = :p1 OR phone_number = :p2) LIMIT 1");
                $stmt_guard->execute([':p1' => $clean_phone, ':p2' => $alt_phone]);
                $guardian = $stmt_guard->fetch(PDO::FETCH_ASSOC);

                if ($guardian) {
                    // Cegah penimpaan akun yang sudah ada (cegah account takeover)
                    $error = 'Nomor WhatsApp ini sudah terdaftar sebagai wali. Silakan masuk (login) terlebih dahulu, lalu hubungkan santri melalui menu Tambah Santri.';
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

                    // 4. Regenerasi ID sesi untuk mencegah fixation & Auto Login
                    session_regenerate_id(true);
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
    }
}

$page_title = "Aktivasi Akun Wali";
require_once __DIR__ . '/layouts/header.php';
?>

<style>
    .pselect-wrap {
        position: relative;
        width: 100%;
    }
    .pselect-wrap.is-open {
        z-index: 50;
    }
    .pselect-trigger {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.625rem;
        padding: 0.5625rem 0.75rem;
        border-radius: 0.875rem;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        transition: border-color 0.18s ease, box-shadow 0.18s ease, background-color 0.18s ease;
        text-align: left;
        cursor: pointer;
    }
    .pselect-trigger:hover:not(:disabled) {
        border-color: #94a3b8;
    }
    .pselect-wrap.is-open .pselect-trigger {
        border-color: #448AFF;
        box-shadow: 0 0 0 4px rgba(68, 138, 255, 0.12);
    }
    .pselect-trigger:disabled {
        background-color: #f8fafc;
        border-color: #e2e8f0;
        cursor: not-allowed;
        opacity: 0.78;
    }
    .pselect-dropdown {
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 6px);
        z-index: 60;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 18px 36px -10px rgba(15, 23, 42, 0.18), 0 4px 12px rgba(15, 23, 42, 0.06);
        opacity: 0;
        transform: translateY(-6px) scale(0.98);
        pointer-events: none;
        transition: opacity 0.16s ease, transform 0.16s ease;
        overflow: hidden;
    }
    .pselect-dropdown.dropup {
        top: auto;
        bottom: calc(100% + 6px);
        transform: translateY(6px) scale(0.98);
    }
    .pselect-wrap.is-open .pselect-dropdown {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }
    .pselect-list {
        max-height: 220px;
        overflow-y: auto;
        padding: 0.375rem;
    }
    .pselect-list::-webkit-scrollbar {
        width: 5px;
    }
    .pselect-list::-webkit-scrollbar-track {
        background: transparent;
    }
    .pselect-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 9999px;
    }
    .pselect-option {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.55rem 0.75rem;
        border-radius: 0.625rem;
        font-size: 0.8125rem;
        color: #334155;
        text-align: left;
        transition: background-color 0.12s ease, color 0.12s ease;
        cursor: pointer;
    }
    .pselect-option:hover {
        background-color: #eef5ff;
        color: #1e489e;
    }
    .pselect-option.is-selected {
        background-color: #eef5ff;
        color: #255cc7;
        font-weight: 600;
    }
    .pselect-icon-badge {
        width: 1.75rem;
        height: 1.75rem;
        border-radius: 0.5rem;
        background-color: #eef5ff;
        color: #448AFF;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        flex-shrink: 0;
    }
    .pselect-search-input {
        width: 100%;
        padding: 0.5rem 1.75rem 0.5rem 2rem;
        font-size: 0.75rem;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        color: #1e293b;
        outline: none;
    }
    .pselect-search-input:focus {
        border-color: #448AFF;
        box-shadow: 0 0 0 3px rgba(68, 138, 255, 0.14);
    }
    .pselect-check-badge {
        width: 1.25rem;
        height: 1.25rem;
        border-radius: 9999px;
        background-color: #448AFF;
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.625rem;
        flex-shrink: 0;
    }
</style>

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
            <strong>Verifikasi Keamanan:</strong> Pilih data santri (berdasarkan <strong>Unit &amp; Kelas</strong> atau <strong>NISN</strong>) lalu masukkan <strong>Tanggal Lahir</strong> santri untuk memvalidasi kepemilikan data anak.
        </div>
    </div>

    <!-- Form Aktivasi -->
    <form method="POST" action="aktivasi.php" id="activationForm" class="space-y-4">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="identity_mode" id="identityModeInput" value="<?php echo htmlspecialchars($identity_mode); ?>">

        <!-- Section: Data Santri -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3.5">
            <div class="flex items-center justify-between gap-2 pb-2 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-brand-50 text-[#448AFF] flex items-center justify-center text-xs font-bold">1</span>
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Identitas Santri</h3>
                </div>
                <?php if (!empty($active_year['name'])): ?>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-brand-50 text-[#448AFF] border border-brand-200/80">
                        TA <?php echo htmlspecialchars($active_year['name']); ?>
                    </span>
                <?php else: ?>
                    <span class="text-[10px] font-medium text-slate-400">Wajib diisi</span>
                <?php endif; ?>
            </div>

            <!-- Segmented Switcher: Pilih Unit/Kelas vs Input NISN -->
            <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-slate-100 text-[11px] font-semibold">
                <button type="button" id="modeBtnPicker" onclick="switchIdentityMode('picker')"
                        class="py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 <?php echo $identity_mode === 'picker' ? 'bg-white text-[#448AFF] shadow-xs font-bold' : 'text-slate-500 hover:text-slate-700'; ?>">
                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                    <span>Unit &amp; Kelas</span>
                </button>
                <button type="button" id="modeBtnNisn" onclick="switchIdentityMode('nisn')"
                        class="py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 <?php echo $identity_mode === 'nisn' ? 'bg-white text-[#448AFF] shadow-xs font-bold' : 'text-slate-500 hover:text-slate-700'; ?>">
                    <i class="fa-solid fa-hashtag text-[10px]"></i>
                    <span>Input NISN</span>
                </button>
            </div>

            <!-- Mode 1: Pilih Santri by Unit -> Kelas -> Nama Santri (Premium Searchable Select) -->
            <div id="panelIdentityPicker" class="space-y-3 <?php echo $identity_mode === 'picker' ? '' : 'hidden'; ?>">
                <!-- 1a. Unit Pendidikan -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Unit Pendidikan <span class="text-rose-500">*</span>
                    </label>
                    <select name="unit_id" id="selectUnit" class="premium-select"
                            data-placeholder="Pilih Unit Pendidikan..."
                            data-search-placeholder="Cari unit (SDIT, MTs, MA...)..."
                            data-icon="fa-school">
                        <option value="">Pilih Unit Pendidikan...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo $selected_unit_id === (int)$u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1b. Kelas -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Kelas <span class="text-rose-500">*</span>
                    </label>
                    <select name="class_id" id="selectClass" class="premium-select"
                            data-placeholder="<?php echo $selected_unit_id > 0 ? 'Pilih Kelas...' : 'Pilih unit terlebih dahulu'; ?>"
                            data-search-placeholder="Cari kelas (contoh: 1A, 7B...)..."
                            data-icon="fa-chalkboard-user"
                            <?php echo $selected_unit_id <= 0 ? 'disabled' : ''; ?>>
                        <option value=""><?php echo $selected_unit_id > 0 ? 'Pilih Kelas...' : 'Pilih unit terlebih dahulu'; ?></option>
                        <?php foreach ($preloaded_classes as $cls): ?>
                            <option value="<?php echo (int)$cls['id']; ?>" <?php echo $selected_class_id === (int)$cls['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cls['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 1c. Nama Santri -->
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Nama Santri <span class="text-rose-500">*</span>
                    </label>
                    <select name="student_id" id="selectStudent" class="premium-select"
                            data-placeholder="<?php echo $selected_class_id > 0 ? 'Cari & Pilih Nama Santri...' : 'Pilih kelas terlebih dahulu'; ?>"
                            data-search-placeholder="Ketik nama santri untuk mencari..."
                            data-icon="fa-user-graduate"
                            <?php echo $selected_class_id <= 0 ? 'disabled' : ''; ?>>
                        <option value=""><?php echo $selected_class_id > 0 ? 'Cari & Pilih Nama Santri...' : 'Pilih kelas terlebih dahulu'; ?></option>
                        <?php foreach ($preloaded_students as $stu): ?>
                            <option value="<?php echo (int)$stu['id']; ?>" <?php echo $selected_student_id === (int)$stu['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($stu['nama_siswa']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Mode 2: Input NISN Manual -->
            <div id="panelIdentityNisn" class="<?php echo $identity_mode === 'nisn' ? '' : 'hidden'; ?>">
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Nomor Induk / NISN Santri <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="nisn" id="inputNisn" placeholder="Contoh: 2-2223101"
                       value="<?php echo htmlspecialchars($_POST['nisn'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs">
            </div>

            <!-- Tanggal Lahir Santri (Verifikasi Keamanan Wajib di kedua mode) -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                    Tanggal Lahir Santri <span class="text-rose-500">*</span>
                </label>
                <input type="date" name="tgl_lahir" required
                       value="<?php echo htmlspecialchars($_POST['tgl_lahir'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs">
                <span class="text-[10px] text-slate-400 mt-1 block">Wajib diisi sesuai data pendaftaran di sekolah untuk verifikasi</span>
            </div>
        </div>

        <!-- Section: Data Akun Orang Tua -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs space-y-3.5">
            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                <span class="w-6 h-6 rounded-lg bg-brand-50 text-[#448AFF] flex items-center justify-center text-xs font-bold">2</span>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Data Akun Orang Tua</h3>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Nama Lengkap Orang Tua / Wali <span class="text-rose-500">*</span>
                </label>
                <input type="text" name="parent_name" required placeholder="Contoh: H. Ahmad Subagio"
                       value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs capitalize">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Hubungan <span class="text-rose-500">*</span>
                    </label>
                    <select name="relationship" class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none shadow-xs">
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
                           class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-xs focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none shadow-xs">
                    <span class="text-[10px] text-slate-400 mt-0.5 block">Hanya angka (contoh: 08xxxxxxxxxx).</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">
                    Buat Kata Sandi / PIN Baru <span class="text-rose-500">*</span>
                </label>
                <input type="password" name="password" required minlength="6" placeholder="Minimal 6 karakter"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-800 text-sm focus:border-[#448AFF] focus:ring-2 focus:ring-brand-500/20 focus:outline-none transition-all shadow-xs">
                <span class="text-[10px] text-slate-400 mt-1 block">Kata sandi ini digunakan untuk masuk ke portal berikutnya</span>
            </div>
        </div>

        <button type="submit" 
                class="w-full py-3.5 bg-[#448AFF] hover:bg-brand-600 text-white rounded-2xl font-bold text-sm shadow-md shadow-brand-500/25 transition-all active:scale-[0.98] flex items-center justify-center gap-2 mt-4">
            <i class="fa-solid fa-check"></i>
            <span>Verifikasi &amp; Aktifkan Akun</span>
        </button>
    </form>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
// -----------------------------------------------------------------------------
// Premium Searchable Select Component
// -----------------------------------------------------------------------------
const premiumSelectInstances = new Map();

function initPremiumSelect(selectEl) {
    if (!selectEl || premiumSelectInstances.has(selectEl.id)) return;

    const iconClass = selectEl.dataset.icon || 'fa-list';
    const searchPlaceholder = selectEl.dataset.searchPlaceholder || 'Ketik untuk mencari...';

    const wrap = document.createElement('div');
    wrap.className = 'pselect-wrap';

    // Trigger Button
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'pselect-trigger';
    trigger.disabled = selectEl.disabled;

    const leftBox = document.createElement('div');
    leftBox.className = 'flex items-center gap-2.5 min-w-0 flex-1 pointer-events-none';

    const iconBadge = document.createElement('span');
    iconBadge.className = 'pselect-icon-badge';
    const iconEl = document.createElement('i');
    iconEl.className = `fa-solid ${iconClass}`;
    iconBadge.appendChild(iconEl);

    const labelSpan = document.createElement('span');
    labelSpan.className = 'text-xs sm:text-sm truncate block';

    leftBox.appendChild(iconBadge);
    leftBox.appendChild(labelSpan);

    const chevron = document.createElement('i');
    chevron.className = 'fa-solid fa-chevron-down text-[11px] text-slate-400 transition-transform duration-200 shrink-0 pointer-events-none';

    trigger.appendChild(leftBox);
    trigger.appendChild(chevron);

    // Dropdown Panel
    const dropdown = document.createElement('div');
    dropdown.className = 'pselect-dropdown';

    const searchWrap = document.createElement('div');
    searchWrap.className = 'p-2 border-b border-slate-100 bg-slate-50/70';

    const searchInner = document.createElement('div');
    searchInner.className = 'relative';

    const searchIcon = document.createElement('i');
    searchIcon.className = 'fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[11px] text-slate-400 pointer-events-none';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = searchPlaceholder;
    searchInput.autocomplete = 'off';
    searchInput.className = 'pselect-search-input';

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 hidden items-center justify-center text-[10px]';
    clearBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';

    searchInner.appendChild(searchIcon);
    searchInner.appendChild(searchInput);
    searchInner.appendChild(clearBtn);
    searchWrap.appendChild(searchInner);

    const listEl = document.createElement('div');
    listEl.className = 'pselect-list';

    dropdown.appendChild(searchWrap);
    dropdown.appendChild(listEl);

    selectEl.parentNode.insertBefore(wrap, selectEl);
    wrap.appendChild(trigger);
    wrap.appendChild(dropdown);
    selectEl.style.display = 'none';
    wrap.appendChild(selectEl);

    let isLoading = false;

    function syncLabel() {
        trigger.disabled = selectEl.disabled;
        const selectedOpt = selectEl.options[selectEl.selectedIndex];
        if (isLoading) {
            labelSpan.textContent = 'Memuat data...';
            labelSpan.className = 'text-xs sm:text-sm truncate block text-slate-400 italic';
            iconEl.className = 'fa-solid fa-circle-notch fa-spin';
            return;
        }
        iconEl.className = `fa-solid ${iconClass}`;

        if (selectedOpt && selectedOpt.value !== '') {
            labelSpan.textContent = selectedOpt.textContent.trim();
            labelSpan.className = 'text-xs sm:text-sm truncate block text-slate-800 font-semibold';
        } else {
            const ph = selectEl.dataset.placeholder || (selectedOpt ? selectedOpt.textContent.trim() : 'Pilih...');
            labelSpan.textContent = ph;
            labelSpan.className = 'text-xs sm:text-sm truncate block text-slate-400 font-normal';
        }
    }

    function closeDropdown() {
        wrap.classList.remove('is-open');
        dropdown.classList.remove('dropup');
        chevron.classList.remove('rotate-180');
    }

    function renderOptions(query = '') {
        listEl.innerHTML = '';
        const q = query.toLowerCase().trim();
        let matchCount = 0;

        if (isLoading) {
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'py-5 text-center text-xs text-slate-400 flex items-center justify-center gap-2';
            loadingDiv.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-[#448AFF]"></i><span>Memuat daftar...</span>';
            listEl.appendChild(loadingDiv);
            return;
        }

        Array.from(selectEl.options).forEach((opt) => {
            if (opt.value === '') return; // Skip placeholder option in list
            const label = opt.textContent.trim();
            if (q && !label.toLowerCase().includes(q)) return;

            matchCount++;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pselect-option' + (opt.selected ? ' is-selected' : '');

            const textEl = document.createElement('span');
            textEl.className = 'truncate';
            textEl.textContent = label;
            btn.appendChild(textEl);

            if (opt.selected) {
                const checkBadge = document.createElement('span');
                checkBadge.className = 'pselect-check-badge';
                checkBadge.innerHTML = '<i class="fa-solid fa-check"></i>';
                btn.appendChild(checkBadge);
            }

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                selectEl.value = opt.value;
                selectEl.dispatchEvent(new Event('change', { bubbles: true }));
                syncLabel();
                closeDropdown();
            });

            listEl.appendChild(btn);
        });

        if (matchCount === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'py-5 px-3 text-center text-xs text-slate-400';
            emptyDiv.textContent = q ? `Tidak ditemukan hasil untuk "${query}"` : 'Belum ada data tersedia';
            listEl.appendChild(emptyDiv);
        }
    }

    function openDropdown() {
        if (selectEl.disabled) return;
        const alreadyOpen = wrap.classList.contains('is-open');

        // Close all other open selects
        premiumSelectInstances.forEach((inst) => inst.close());

        if (alreadyOpen) return;

        const rect = wrap.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;
        if (spaceBelow < 260 && spaceAbove > spaceBelow) {
            dropdown.classList.add('dropup');
        } else {
            dropdown.classList.remove('dropup');
        }

        wrap.classList.add('is-open');
        chevron.classList.add('rotate-180');
        searchInput.value = '';
        clearBtn.classList.add('hidden');
        clearBtn.classList.remove('flex');
        renderOptions('');
        setTimeout(() => searchInput.focus(), 40);
    }

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        openDropdown();
    });

    searchInput.addEventListener('click', (e) => e.stopPropagation());
    searchInput.addEventListener('input', (e) => {
        const val = e.target.value;
        if (val.length > 0) {
            clearBtn.classList.remove('hidden');
            clearBtn.classList.add('flex');
        } else {
            clearBtn.classList.add('hidden');
            clearBtn.classList.remove('flex');
        }
        renderOptions(val);
    });

    clearBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        searchInput.value = '';
        clearBtn.classList.add('hidden');
        clearBtn.classList.remove('flex');
        renderOptions('');
        searchInput.focus();
    });

    const instance = {
        close: closeDropdown,
        refresh: () => {
            syncLabel();
            renderOptions(searchInput.value || '');
        },
        setLoading: (state) => {
            isLoading = !!state;
            syncLabel();
            renderOptions('');
        }
    };

    premiumSelectInstances.set(selectEl.id, instance);
    syncLabel();
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.pselect-wrap')) {
        premiumSelectInstances.forEach((inst) => inst.close());
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        premiumSelectInstances.forEach((inst) => inst.close());
    }
});

// -----------------------------------------------------------------------------
// Cascading Logic: Unit -> Kelas -> Santri & Mode Switcher
// -----------------------------------------------------------------------------
function switchIdentityMode(mode) {
    const modeInput = document.getElementById('identityModeInput');
    const panelPicker = document.getElementById('panelIdentityPicker');
    const panelNisn = document.getElementById('panelIdentityNisn');
    const btnPicker = document.getElementById('modeBtnPicker');
    const btnNisn = document.getElementById('modeBtnNisn');

    modeInput.value = mode;
    if (mode === 'nisn') {
        panelPicker.classList.add('hidden');
        panelNisn.classList.remove('hidden');
        btnNisn.className = 'py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 bg-white text-[#448AFF] shadow-xs font-bold';
        btnPicker.className = 'py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 text-slate-500 hover:text-slate-700';
    } else {
        panelNisn.classList.add('hidden');
        panelPicker.classList.remove('hidden');
        btnPicker.className = 'py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 bg-white text-[#448AFF] shadow-xs font-bold';
        btnNisn.className = 'py-2 px-2.5 rounded-lg transition-all flex items-center justify-center gap-1.5 text-slate-500 hover:text-slate-700';
    }
}
window.switchIdentityMode = switchIdentityMode;

document.addEventListener('DOMContentLoaded', () => {
    const unitSelect = document.getElementById('selectUnit');
    const classSelect = document.getElementById('selectClass');
    const studentSelect = document.getElementById('selectStudent');

    document.querySelectorAll('select.premium-select').forEach(initPremiumSelect);

    function resetSelect(el, placeholder, disabled = true) {
        el.innerHTML = '';
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder;
        el.appendChild(opt);
        el.value = '';
        el.dataset.placeholder = placeholder;
        el.disabled = disabled;
        const inst = premiumSelectInstances.get(el.id);
        if (inst) inst.refresh();
    }

    unitSelect.addEventListener('change', async () => {
        const unitId = unitSelect.value;
        resetSelect(studentSelect, 'Pilih kelas terlebih dahulu', true);

        if (!unitId) {
            resetSelect(classSelect, 'Pilih unit terlebih dahulu', true);
            return;
        }

        const classInst = premiumSelectInstances.get('selectClass');
        classSelect.disabled = true;
        if (classInst) classInst.setLoading(true);

        try {
            const res = await fetch(`aktivasi.php?ajax=get_classes&unit_id=${encodeURIComponent(unitId)}`);
            const json = await res.json();
            classSelect.innerHTML = '';

            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = (json.data && json.data.length > 0) ? 'Pilih Kelas...' : 'Tidak ada kelas aktif';
            classSelect.appendChild(defaultOpt);

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                json.data.forEach((cls) => {
                    const opt = document.createElement('option');
                    opt.value = cls.id;
                    opt.textContent = cls.name;
                    classSelect.appendChild(opt);
                });
                classSelect.dataset.placeholder = 'Pilih Kelas...';
                classSelect.disabled = false;
            } else {
                classSelect.dataset.placeholder = 'Tidak ada kelas aktif';
                classSelect.disabled = true;
            }
        } catch (err) {
            resetSelect(classSelect, 'Gagal memuat daftar kelas', true);
        } finally {
            if (classInst) classInst.setLoading(false);
        }
    });

    classSelect.addEventListener('change', async () => {
        const classId = classSelect.value;
        if (!classId) {
            resetSelect(studentSelect, 'Pilih kelas terlebih dahulu', true);
            return;
        }

        const studentInst = premiumSelectInstances.get('selectStudent');
        studentSelect.disabled = true;
        if (studentInst) studentInst.setLoading(true);

        try {
            const res = await fetch(`aktivasi.php?ajax=get_students&class_id=${encodeURIComponent(classId)}`);
            const json = await res.json();
            studentSelect.innerHTML = '';

            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = (json.data && json.data.length > 0) ? 'Cari & Pilih Nama Santri...' : 'Belum ada santri di kelas ini';
            studentSelect.appendChild(defaultOpt);

            if (json.success && Array.isArray(json.data) && json.data.length > 0) {
                json.data.forEach((stu) => {
                    const opt = document.createElement('option');
                    opt.value = stu.id;
                    opt.textContent = stu.nama_siswa;
                    studentSelect.appendChild(opt);
                });
                studentSelect.dataset.placeholder = 'Cari & Pilih Nama Santri...';
                studentSelect.disabled = false;
            } else {
                studentSelect.dataset.placeholder = 'Belum ada santri di kelas ini';
                studentSelect.disabled = true;
            }
        } catch (err) {
            resetSelect(studentSelect, 'Gagal memuat daftar santri', true);
        } finally {
            if (studentInst) studentInst.setLoading(false);
        }
    });

    // Validasi ringan sebelum submit
    document.getElementById('activationForm').addEventListener('submit', (e) => {
        const mode = document.getElementById('identityModeInput').value;
        if (mode === 'picker') {
            if (!studentSelect.value) {
                e.preventDefault();
                alert('Silakan pilih Unit Pendidikan, Kelas, dan Nama Santri terlebih dahulu.');
            }
        } else {
            const nisnVal = document.getElementById('inputNisn').value.trim();
            if (!nisnVal) {
                e.preventDefault();
                alert('Nomor Induk / NISN santri wajib diisi.');
                document.getElementById('inputNisn').focus();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>
