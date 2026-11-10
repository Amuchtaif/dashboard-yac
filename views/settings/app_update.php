<?php
// views/settings/app_update.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('Administrator');

$page_title = "Update Control & Versi Aplikasi";

$db = new Database();
$conn = $db->getConnection();

// Auto-migrate: Pastikan kolom versi di tabel employees sudah ada (self-healing untuk production)
$hasVersionCols = false;
try {
    $checkCols = $conn->query("SHOW COLUMNS FROM employees LIKE 'app_version'")->fetchAll();
    if (empty($checkCols)) {
        $conn->exec("
            ALTER TABLE employees 
            ADD COLUMN app_version VARCHAR(50) NULL DEFAULT NULL,
            ADD COLUMN app_build_number INT NULL DEFAULT NULL,
            ADD COLUMN last_mobile_activity DATETIME NULL DEFAULT NULL
        ");
        $hasVersionCols = true;
    } else {
        $hasVersionCols = true;
    }
} catch (\Throwable $migErr) {
    try {
        $checkAgain = $conn->query("SHOW COLUMNS FROM employees LIKE 'app_version'")->fetchAll();
        $hasVersionCols = !empty($checkAgain);
    } catch (\Throwable $e) {
        $hasVersionCols = false;
    }
}

// 1. Handle Form Update Control
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_version_control') {
    $latest_version = trim($_POST['app_latest_version'] ?? '1.0.0');
    $latest_build = (int)($_POST['app_latest_build'] ?? 1);
    $min_build = (int)($_POST['app_min_build_required'] ?? 1);
    $apk_url = trim($_POST['app_apk_url'] ?? '');
    $changelog = trim($_POST['app_changelog'] ?? '');

    // Handle Upload File APK jika diunggah langsung
    if (!empty($_FILES['apk_file']['name']) && $_FILES['apk_file']['error'] === UPLOAD_ERR_OK) {
        $fileInfo = pathinfo($_FILES['apk_file']['name']);
        $ext = strtolower($fileInfo['extension'] ?? '');
        if ($ext === 'apk') {
            $targetDir = '../../uploads/app/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $fileName = 'app-release-b' . $latest_build . '-' . time() . '.apk';
            $targetPath = $targetDir . $fileName;

            if (move_uploaded_file($_FILES['apk_file']['tmp_name'], $targetPath)) {
                $apk_url = 'uploads/app/' . $fileName;
            } else {
                $_SESSION['error'] = "Gagal mengunggah file APK ke server.";
            }
        } else {
            $_SESSION['error'] = "Format file tidak didukung. Harap unggah file berekstensi .apk.";
        }
    }

    try {
        $settingsToSave = [
            'app_latest_version' => $latest_version,
            'app_latest_build' => (string)$latest_build,
            'app_min_build_required' => (string)$min_build,
            'app_apk_url' => $apk_url,
            'app_changelog' => $changelog
        ];

        foreach ($settingsToSave as $k => $v) {
            $stmt = $conn->prepare("REPLACE INTO app_settings (setting_key, setting_value) VALUES (:k, :v)");
            $stmt->execute([':k' => $k, ':v' => $v]);
        }

        // Kirim notifikasi FCM jika dicentang
        if (isset($_POST['send_fcm_notification']) && $_POST['send_fcm_notification'] == '1') {
            try {
                if (file_exists('../../config/fcm_helper.php')) {
                    require_once '../../config/fcm_helper.php';
                    $fcm = new FcmHelper();
                    $fcm->sendTopicData('app_updates', [
                        'type' => 'app_update',
                        'version' => $latest_version,
                        'build' => $latest_build,
                        'message' => 'Tersedia pembaruan aplikasi versi ' . $latest_version
                    ]);
                }
            } catch (\Throwable $fcmErr) {
                error_log("FCM Update Notification Error: " . $fcmErr->getMessage());
            }
        }

        $_SESSION['success'] = "Pengaturan versi dan unduhan aplikasi berhasil disimpan.";
        header("Location: app_update.php");
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Kesalahan Database: " . $e->getMessage();
    }
}

// 2. Ambil Konfigurasi Versi
$keys = ['app_latest_version', 'app_latest_build', 'app_min_build_required', 'app_apk_url', 'app_changelog'];
$stmt = $conn->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ('" . implode("','", $keys) . "')");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$cur_version = $settings['app_latest_version'] ?? '1.0.0';
$cur_build = (int)($settings['app_latest_build'] ?? 1);
$cur_min_build = (int)($settings['app_min_build_required'] ?? 1);
$cur_apk_url = $settings['app_apk_url'] ?? '';
$cur_changelog = $settings['app_changelog'] ?? 'Pembaruan aplikasi berkala dan stabilitas sistem.';

// 3. Query Daftar Pengguna & Versi Aplikasi yang Digunakan
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$verColsSql = $hasVersionCols 
    ? "e.app_version, e.app_build_number, e.last_mobile_activity" 
    : "NULL as app_version, NULL as app_build_number, NULL as last_mobile_activity";

$sql_users = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        e.phone_number,
        $verColsSql,
        u.name as unit_name,
        p.name as position_name
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.status = 'active'
";

if ($hasVersionCols) {
    if ($filter === 'latest') {
        $sql_users .= " AND e.app_build_number >= $cur_build";
    } elseif ($filter === 'outdated') {
        $sql_users .= " AND (e.app_build_number IS NOT NULL AND e.app_build_number < $cur_build)";
    } elseif ($filter === 'force_required') {
        $sql_users .= " AND (e.app_build_number IS NOT NULL AND e.app_build_number < $cur_min_build)";
    } elseif ($filter === 'active_mobile') {
        $sql_users .= " AND e.app_build_number IS NOT NULL";
    }
}

if (!empty($search)) {
    $sql_users .= " AND (e.full_name LIKE :search OR e.email LIKE :search OR u.name LIKE :search)";
}

$orderBy = $hasVersionCols ? "e.last_mobile_activity DESC, e.full_name ASC" : "e.full_name ASC";
$sql_users .= " ORDER BY $orderBy";

$stmtUsers = $conn->prepare($sql_users);
if (!empty($search)) {
    $stmtUsers->execute([':search' => "%$search%"]);
} else {
    $stmtUsers->execute();
}
$usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// 4. Statistik KPI Versi
$kpi = [
    'total_mobile_users' => 0,
    'up_to_date_users' => 0,
    'outdated_users' => 0,
    'forced_users' => 0
];

if ($hasVersionCols) {
    try {
        $stmtKpi = $conn->query("
            SELECT 
                COUNT(CASE WHEN app_build_number IS NOT NULL THEN 1 END) as total_mobile_users,
                COUNT(CASE WHEN app_build_number >= $cur_build THEN 1 END) as up_to_date_users,
                COUNT(CASE WHEN app_build_number IS NOT NULL AND app_build_number < $cur_build THEN 1 END) as outdated_users,
                COUNT(CASE WHEN app_build_number IS NOT NULL AND app_build_number < $cur_min_build THEN 1 END) as forced_users
            FROM employees
            WHERE status = 'active'
        ");
        $resKpi = $stmtKpi ? $stmtKpi->fetch(PDO::FETCH_ASSOC) : null;
        if ($resKpi) {
            $kpi = $resKpi;
        }
    } catch (\Throwable $e) {}
}

include '../layouts/header.php';
?>

<div class="w-full pb-12 space-y-8">
    <!-- Breadcrumbs -->
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3 text-xs text-slate-500">
            <li class="inline-flex items-center">
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 flex items-center gap-1">
                    <i class="fa-solid fa-house w-3 h-3"></i> Beranda
                </a>
            </li>
            <li>
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 text-slate-500">Pengaturan</span>
                </div>
            </li>
            <li aria-current="page">
                <div class="flex items-center">
                    <i class="fa-solid fa-chevron-right w-3 h-3 text-gray-400 mx-1"></i>
                    <span class="ml-1 font-medium text-slate-800">Update Control & Versi Aplikasi</span>
                </div>
            </li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-bold leading-7 text-slate-900 sm:truncate sm:text-3xl sm:tracking-tight">
                Versi Aplikasi Mobile
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Rilis pembaruan APK dan pantau versi aplikasi yang terpasang di perangkat pegawai.
            </p>
        </div>
        <div class="mt-4 md:mt-0 flex items-center gap-2">
            <a href="../../api/app_version.php" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold shadow-xs transition-colors">
                <i class="fa-solid fa-code text-teal-600"></i>
                <span>Test Endpoint API</span>
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Target Rilis -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Versi Rilis</span>
                <span class="text-xl font-black text-slate-900 block mt-0.5">v<?php echo htmlspecialchars($cur_version); ?></span>
                <span class="text-[11px] text-teal-700 font-medium">Build #<?php echo $cur_build; ?></span>
            </div>
        </div>

        <!-- Total Terdeteksi Mobile -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-mobile-screen"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Pengguna Terpasang</span>
                <span class="text-xl font-black text-slate-900 block mt-0.5"><?php echo (int)$kpi['total_mobile_users']; ?></span>
                <span class="text-[11px] text-slate-500 font-medium">Pernah login dari HP</span>
            </div>
        </div>

        <!-- Up-to-date -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Sudah Update</span>
                <span class="text-xl font-black text-emerald-600 block mt-0.5"><?php echo (int)$kpi['up_to_date_users']; ?></span>
                <span class="text-[11px] text-emerald-700 font-medium">Menggunakan build terbaru</span>
            </div>
        </div>

        <!-- Outdated -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-xl font-bold shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider block">Belum Update</span>
                <span class="text-xl font-black text-amber-600 block mt-0.5"><?php echo (int)$kpi['outdated_users']; ?></span>
                <span class="text-[11px] text-rose-600 font-medium"><?php echo (int)$kpi['forced_users']; ?> wajib update</span>
            </div>
        </div>
    </div>

    <!-- Form Update Control -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-2xl bg-teal-600 text-white flex items-center justify-center text-lg shadow-xs">
                    <i class="fa-solid fa-sliders"></i>
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-800">Rilis Pembaruan APK</h3>
                    <p class="text-xs text-slate-500">Tentukan versi baru dan unggah file APK untuk perangkat pengguna.</p>
                </div>
            </div>
        </div>

        <form action="app_update.php" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
            <input type="hidden" name="action" value="save_version_control">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Latest Version -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Nomor Versi <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="app_latest_version" required placeholder="Contoh: 1.2.0"
                           value="<?php echo htmlspecialchars($cur_version); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Muncul di teks info update pada HP.</p>
                </div>

                <!-- Latest Build Number -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Build Number <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" name="app_latest_build" min="1" required placeholder="Contoh: 15"
                           value="<?php echo $cur_build; ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Harus lebih tinggi dari build sebelumnya.</p>
                </div>

                <!-- Min Required Build -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Batas Minimal Wajib Update <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" name="app_min_build_required" min="1" required placeholder="Contoh: 12"
                           value="<?php echo $cur_min_build; ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Di bawah build ini, dialog update mengunci dan tidak bisa ditutup.</p>
                </div>
            </div>

            <!-- APK Link & Upload -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-slate-100">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        URL Unduh File APK
                    </label>
                    <input type="text" name="app_apk_url" placeholder="https://domain.com/downloads/app.apk"
                           value="<?php echo htmlspecialchars($cur_apk_url); ?>"
                           class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Gunakan tautan langsung jika file di-host di luar server (Drive/CDN).</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                        Atau Unggah File APK Langsung
                    </label>
                    <input type="file" name="apk_file" accept=".apk"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100 cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-1">File akan disimpan di folder uploads/app/.</p>
                </div>
            </div>

            <!-- Changelog -->
            <div class="pt-2 border-t border-slate-100">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                    Catatan Rilis (Changelog)
                </label>
                <textarea name="app_changelog" rows="3"
                          placeholder="Tuliskan perbaikan bug atau fitur baru yang akan dibaca pengguna..."
                          class="w-full px-4 py-3 rounded-xl border border-slate-300 text-slate-800 text-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none"><?php echo htmlspecialchars($cur_changelog); ?></textarea>
            </div>

            <!-- Checkbox Push Notification -->
            <div class="flex items-center gap-2 pt-2">
                <input type="checkbox" name="send_fcm_notification" value="1" id="fcmCheck"
                       class="w-4 h-4 text-teal-600 border-slate-300 rounded focus:ring-teal-500">
                <label for="fcmCheck" class="text-xs text-slate-700 font-medium cursor-pointer">
                    Kirim notifikasi update langsung ke HP pegawai (FCM)
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="submit" 
                        class="px-6 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Simpan & Rilis Update</span>
                </button>
            </div>
        </form>
    </div>

    <!-- User Version Monitoring Table -->
    <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-6 bg-slate-50/70 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-800">Daftar Pengguna</h3>
                <p class="text-xs text-slate-500">Versi aplikasi yang terakhir kali terdeteksi di setiap akun pegawai.</p>
            </div>

            <!-- Filters & Search -->
            <form action="app_update.php" method="GET" class="flex flex-wrap items-center gap-2">
                <select name="filter" onchange="this.form.submit()"
                        class="px-3 py-2 rounded-xl border border-slate-300 bg-white text-xs text-slate-700 focus:outline-none focus:border-teal-600">
                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>Semua Pegawai</option>
                    <option value="active_mobile" <?php echo $filter === 'active_mobile' ? 'selected' : ''; ?>>Sudah Pasang Aplikasi</option>
                    <option value="latest" <?php echo $filter === 'latest' ? 'selected' : ''; ?>>Versi Terbaru (Build #<?php echo $cur_build; ?>)</option>
                    <option value="outdated" <?php echo $filter === 'outdated' ? 'selected' : ''; ?>>Belum Update (&lt; #<?php echo $cur_build; ?>)</option>
                    <option value="force_required" <?php echo $filter === 'force_required' ? 'selected' : ''; ?>>Wajib Update (&lt; #<?php echo $cur_min_build; ?>)</option>
                </select>

                <div class="relative">
                    <input type="text" name="search" placeholder="Cari nama / unit..." value="<?php echo htmlspecialchars($search); ?>"
                           class="w-48 pl-8 pr-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:border-teal-600">
                    <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
                </div>

                <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-900 transition-colors">
                    Filter
                </button>
                <?php if ($filter !== 'all' || !empty($search)): ?>
                    <a href="app_update.php" class="p-2 text-slate-400 hover:text-slate-600 text-xs" title="Reset filter">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50/50 text-[10px] uppercase font-bold text-slate-400 border-b border-slate-100 tracking-wider">
                    <tr>
                        <th class="py-3.5 px-6">Nama Pegawai</th>
                        <th class="py-3.5 px-4">Unit & Jabatan</th>
                        <th class="py-3.5 px-4 text-center">Versi Aplikasi</th>
                        <th class="py-3.5 px-4 text-center">Build Number</th>
                        <th class="py-3.5 px-4 text-center">Status Update</th>
                        <th class="py-3.5 px-6 text-right">Terakhir Aktif</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">
                                Tidak ada data pegawai yang sesuai.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): 
                            $bNum = (int)($u['app_build_number'] ?? 0);
                            $hasOpenedMobile = ($bNum > 0 || !empty($u['app_version']));

                            if (!$hasOpenedMobile) {
                                $statusBadge = ['Belum Terpasang', 'bg-slate-100 text-slate-500 border-slate-200'];
                            } elseif ($bNum >= $cur_build) {
                                $statusBadge = ['Versi Terbaru', 'bg-emerald-50 text-emerald-700 border-emerald-200'];
                            } elseif ($bNum < $cur_min_build) {
                                $statusBadge = ['Wajib Update', 'bg-rose-50 text-rose-700 border-rose-200 font-bold'];
                            } else {
                                $statusBadge = ['Perlu Update', 'bg-amber-50 text-amber-700 border-amber-200'];
                            }
                        ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3.5 px-6 font-semibold text-slate-800">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full bg-teal-50 text-teal-800 flex items-center justify-center font-bold text-xs shrink-0">
                                            <?php echo strtoupper(substr($u['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <span class="block"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                            <span class="text-[10px] text-slate-400 font-normal"><?php echo htmlspecialchars($u['email']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-medium text-slate-700 block"><?php echo htmlspecialchars($u['unit_name'] ?? '-'); ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo htmlspecialchars($u['position_name'] ?? '-'); ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-medium">
                                    <?php echo !empty($u['app_version']) ? htmlspecialchars($u['app_version']) : '<span class="text-slate-300">-</span>'; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-700">
                                    <?php echo $bNum > 0 ? '#' . $bNum : '<span class="text-slate-300">-</span>'; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] border <?php echo $statusBadge[1]; ?>">
                                        <?php echo $statusBadge[0]; ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-6 text-right text-[11px] text-slate-500 font-mono">
                                    <?php 
                                        if (!empty($u['last_mobile_activity'])) {
                                            echo date('d M Y H:i', strtotime($u['last_mobile_activity']));
                                        } else {
                                            echo '<span class="text-slate-300">-</span>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>

