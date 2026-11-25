<?php
// views/settings/app_update.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('Administrator');

// Matikan batas waktu eksekusi untuk transfer file besar
@set_time_limit(300);
@ini_set('memory_limit', '512M');

// Deteksi jika request POST kosong karena payload melebihi post_max_size hosting
$isAjaxRequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_POST['action']) && in_array($_POST['action'], ['chunk_upload_apk', 'cancel_upload_apk']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    if ($isAjaxRequest) {
        http_response_code(413);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'error' => 'Ukuran file melebihi batas post_max_size server hosting (' . ini_get('post_max_size') . ').'
        ]);
        exit;
    }
    $_SESSION['error'] = 'Ukuran upload melebihi batas post_max_size server hosting (' . ini_get('post_max_size') . '). Harap gunakan uploader bertahap atau tautan APK eksternal.';
    header("Location: app_update.php");
    exit;
}

// -----------------------------------------------------------------------------
// AJAX: Chunked Resilient APK Upload Handler
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chunk_upload_apk') {
    header('Content-Type: application/json; charset=UTF-8');

    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
    $chunkIndex = (int)($_POST['chunk_index'] ?? 0);
    $totalChunks = (int)($_POST['total_chunks'] ?? 1);
    $fileName = preg_replace('/[^a-zA-Z0-9_.-]/', '', basename($_POST['file_name'] ?? 'app.apk'));
    $fileSize = (int)($_POST['file_size'] ?? 0);
    $buildNumber = (int)($_POST['build_number'] ?? 1);

    if (empty($uploadId)) {
        echo json_encode(['success' => false, 'error' => 'Upload ID tidak valid.']);
        exit;
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($ext !== 'apk') {
        echo json_encode(['success' => false, 'error' => 'Format file tidak didukung. Harap unggah file berekstensi .apk']);
        exit;
    }

    if (!isset($_FILES['file_chunk']) || $_FILES['file_chunk']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrCode = $_FILES['file_chunk']['error'] ?? -1;
        $errMsg = 'Gagal menerima potongan file (Error Code: ' . $uploadErrCode . ').';
        if ($uploadErrCode === UPLOAD_ERR_INI_SIZE || $uploadErrCode === UPLOAD_ERR_FORM_SIZE) {
            $errMsg = 'Ukuran chunk melebihi batas upload_max_filesize server hosting (' . ini_get('upload_max_filesize') . ').';
        }
        echo json_encode(['success' => false, 'error' => $errMsg]);
        exit;
    }

    $targetDir = '../../uploads/app/';
    $tempDir = $targetDir . 'temp/';

    if (!is_dir($targetDir)) {
        if (!@mkdir($targetDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Folder uploads/app/ gagal dibuat di server. Periksa hak akses direktori hosting.']);
            exit;
        }
    }
    if (!is_dir($tempDir)) {
        if (!@mkdir($tempDir, 0755, true)) {
            echo json_encode(['success' => false, 'error' => 'Folder uploads/app/temp/ gagal dibuat di server. Periksa hak akses direktori hosting.']);
            exit;
        }
    }

    $tempFilePath = $tempDir . 'upload_' . $uploadId . '.part';

    // Tulis/sambungkan potongan file (append mode)
    $in = @fopen($_FILES['file_chunk']['tmp_name'], 'rb');
    $out = @fopen($tempFilePath, ($chunkIndex === 0) ? 'wb' : 'ab');

    if (!$in || !$out) {
        if ($in) fclose($in);
        if ($out) fclose($out);
        echo json_encode(['success' => false, 'error' => 'Gagal menulis potongan file ke server. Periksa permission folder uploads/app/temp.']);
        exit;
    }

    while ($buff = fread($in, 8192)) {
        fwrite($out, $buff);
    }
    fclose($in);
    fclose($out);

    // Cek apakah seluruh potongan sudah selesai diunggah
    if (($chunkIndex + 1) >= $totalChunks) {
        $finalSize = file_exists($tempFilePath) ? filesize($tempFilePath) : 0;
        
        $finalFileName = 'app-release-b' . $buildNumber . '-' . time() . '.apk';
        $finalTargetPath = $targetDir . $finalFileName;

        if (!@rename($tempFilePath, $finalTargetPath)) {
            if (!@copy($tempFilePath, $finalTargetPath)) {
                echo json_encode(['success' => false, 'error' => 'Gagal memindahkan file APK selesai ke folder uploads/app/.']);
                exit;
            }
            @unlink($tempFilePath);
        }

        $relUrl = 'uploads/app/' . $finalFileName;

        // Resolve absolute URL
        $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);
        $proto = $isHttps ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'dashboard.assunnahcirebon.com';
        $fullUrl = rtrim($proto . "://" . $host, '/') . '/' . ltrim($relUrl, '/');

        // Format ukuran file
        $sizeMb = round($finalSize / (1024 * 1024), 2);
        $sizeFormatted = $sizeMb > 0 ? ($sizeMb . ' MB') : (round($finalSize / 1024, 1) . ' KB');

        // Bersihkan file part usang yang lebih lama dari 12 jam
        try {
            $oldParts = glob($tempDir . '*.part');
            $now = time();
            if ($oldParts) {
                foreach ($oldParts as $part) {
                    if (is_file($part) && ($now - filemtime($part) > 43200)) {
                        @unlink($part);
                    }
                }
            }
        } catch (\Throwable $t) {}

        echo json_encode([
            'success' => true,
            'completed' => true,
            'file_url' => $relUrl,
            'full_url' => $fullUrl,
            'file_name' => $finalFileName,
            'original_name' => $fileName,
            'file_size' => $finalSize,
            'file_size_formatted' => $sizeFormatted,
            'message' => 'File APK berhasil diunggah dan siap dirilis.'
        ]);
        exit;
    } else {
        echo json_encode([
            'success' => true,
            'completed' => false,
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'percent' => round((($chunkIndex + 1) / $totalChunks) * 100)
        ]);
        exit;
    }
}

// AJAX: Batalkan Upload & Bersihkan File Temp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_upload_apk') {
    header('Content-Type: application/json; charset=UTF-8');
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['upload_id'] ?? '');
    if (!empty($uploadId)) {
        $tempPart = '../../uploads/app/temp/upload_' . $uploadId . '.part';
        if (file_exists($tempPart)) {
            @unlink($tempPart);
        }
    }
    echo json_encode(['success' => true]);
    exit;
}

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

    // Ambil URL lama sebagai fallback jika input kosong dan tidak ada upload file baru
    $stmtOld = $conn->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_apk_url'");
    $stmtOld->execute();
    $oldApkUrl = $stmtOld->fetchColumn() ?: '';

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
    } elseif (empty($apk_url) && !empty($oldApkUrl)) {
        // Pertahankan URL APK yang sudah tersimpan sebelumnya
        $apk_url = $oldApkUrl;
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

                    // 1. Kirim data update ke topic app_updates
                    $fcm->sendTopicData('app_updates', [
                        'type' => 'app_update',
                        'version' => $latest_version,
                        'build' => (string)$latest_build,
                        'apk_url' => $apk_url,
                        'message' => 'Tersedia pembaruan aplikasi versi ' . $latest_version
                    ]);

                    // 2. Kirim push notification visual ke pengguna
                    $notifTitle = "Pembaruan Aplikasi Tersedia (v{$latest_version})";
                    $notifBody = !empty($changelog) ? $changelog : "Tersedia pembaruan versi {$latest_version}. Buka aplikasi untuk memperbarui.";
                    $extraData = [
                        'type' => 'app_update',
                        'screen' => 'app_update',
                        'version' => (string)$latest_version,
                        'build' => (string)$latest_build,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ];

                    $fcm->sendTopicNotification('app_updates', $notifTitle, $notifBody, $extraData);
                    $fcm->sendTopicNotification('all_users', $notifTitle, $notifBody, $extraData);
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
        e.fcm_token,
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
        $sql_users .= " AND ((e.app_build_number IS NOT NULL AND e.app_build_number < $cur_build) OR (e.app_build_number IS NULL AND e.fcm_token IS NOT NULL))";
    } elseif ($filter === 'force_required') {
        $sql_users .= " AND ((e.app_build_number IS NOT NULL AND e.app_build_number < $cur_min_build) OR (e.app_build_number IS NULL AND e.fcm_token IS NOT NULL))";
    } elseif ($filter === 'active_mobile') {
        $sql_users .= " AND (e.app_build_number IS NOT NULL OR e.fcm_token IS NOT NULL)";
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
                COUNT(CASE WHEN app_build_number IS NOT NULL OR fcm_token IS NOT NULL THEN 1 END) as total_mobile_users,
                COUNT(CASE WHEN app_build_number >= $cur_build THEN 1 END) as up_to_date_users,
                COUNT(CASE WHEN (app_build_number IS NOT NULL AND app_build_number < $cur_build) OR (app_build_number IS NULL AND fcm_token IS NOT NULL) THEN 1 END) as outdated_users,
                COUNT(CASE WHEN (app_build_number IS NOT NULL AND app_build_number < $cur_min_build) OR (app_build_number IS NULL AND fcm_token IS NOT NULL) THEN 1 END) as forced_users
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

            <!-- APK Link & Interactive Chunked Upload -->
            <div class="pt-4 border-t border-slate-100 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <span>File Paket Aplikasi (APK)</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i class="fa-solid fa-circle-check text-emerald-500"></i> Chunked Uploader Aktif
                            </span>
                        </h4>
                        <p class="text-[11px] text-slate-400">Unggah file APK langsung ke server (proses bertahap anti-timeout) atau gunakan tautan URL eksternal.</p>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono">
                        Max: 250 MB • Slices: 1 MB
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Left: Interactive Upload Box (7 cols) -->
                    <div class="lg:col-span-7 space-y-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Unggah File APK Baru
                        </label>

                        <!-- Drag and Drop Zone -->
                        <div id="apkDropZone" 
                             class="group relative border-2 border-dashed border-slate-300 hover:border-teal-500 rounded-2xl p-6 text-center bg-slate-50/50 hover:bg-teal-50/30 transition-all cursor-pointer">
                            <input type="file" id="apkFileInput" accept=".apk" class="hidden">
                            
                            <div id="dropZonePrompt" class="space-y-3">
                                <div class="w-14 h-14 mx-auto rounded-2xl bg-teal-100/70 text-teal-700 flex items-center justify-center text-2xl group-hover:scale-110 group-hover:bg-teal-600 group-hover:text-white transition-all shadow-xs">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-700 group-hover:text-teal-700">
                                        Klik untuk memilih file APK <span class="text-slate-400 font-normal">atau seret file ke sini</span>
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-1">
                                        Hanya menerima format <span class="font-semibold text-slate-600">.apk</span> • Otomatis diunggah seketika
                                    </p>
                                </div>
                                <div class="flex items-center justify-center gap-2 pt-1 text-[11px] text-slate-500">
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600 font-mono text-[10px]">
                                        <i class="fa-solid fa-layer-group text-teal-500 mr-1"></i>1MB Per Slice
                                    </span>
                                    <span class="text-slate-300">•</span>
                                    <span>Upload selesai dulu sebelum disimpan</span>
                                </div>
                            </div>
                        </div>

                        <!-- Upload Progress & Status Card (Hidden by default) -->
                        <div id="uploadProgressCard" class="hidden p-4 bg-white border border-slate-200 rounded-2xl shadow-xs space-y-3">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div id="uploadIconContainer" class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl shrink-0">
                                        <i class="fa-brands fa-android"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p id="uploadFileName" class="text-xs font-bold text-slate-800 truncate">app-release.apk</p>
                                        <p id="uploadFileSize" class="text-[11px] text-slate-400">0 MB / 0 MB</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span id="uploadStatusBadge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1">
                                        <i class="fa-solid fa-spinner fa-spin text-amber-500"></i> Mengunggah (0%)
                                    </span>
                                    <button type="button" id="btnCancelUpload" class="text-slate-400 hover:text-rose-600 p-1.5 text-xs transition-colors" title="Batalkan Upload">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Animated Progress Bar with Shimmer Effect -->
                            <div class="relative w-full bg-slate-100 rounded-full h-3.5 overflow-hidden shadow-inner">
                                <div id="uploadProgressBar" 
                                     class="h-full bg-gradient-to-r from-teal-500 via-emerald-500 to-teal-600 rounded-full transition-all duration-200 relative overflow-hidden" 
                                     style="width: 0%;">
                                    <div class="absolute inset-0 bg-white/25 animate-pulse"></div>
                                </div>
                            </div>

                            <!-- Metrics & Info -->
                            <div class="flex items-center justify-between text-[11px] text-slate-500 font-mono">
                                <span id="uploadProgressDetail">Menyiapkan koneksi...</span>
                                <span id="uploadProgressSpeed" class="font-semibold text-slate-700">0 KB/s</span>
                            </div>

                            <!-- Upload Complete Info Box -->
                            <div id="uploadSuccessBox" class="hidden p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-start gap-2.5">
                                <i class="fa-solid fa-circle-check text-emerald-600 text-sm mt-0.5 shrink-0"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold">File APK Berhasil Diunggah ke Server!</p>
                                    <p class="text-[11px] text-emerald-700 mt-0.5">
                                        File telah tersimpan di server. Klik tombol <span class="font-bold">"Simpan & Rilis Update"</span> untuk mengaktifkan pembaruan bagi seluruh pengguna.
                                    </p>
                                </div>
                                <button type="button" id="btnResetUpload" class="text-xs text-emerald-700 hover:text-emerald-900 underline font-semibold shrink-0">
                                    Ganti File
                                </button>
                            </div>

                            <!-- Upload Error Alert Box -->
                            <div id="uploadErrorBox" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-800 flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-rose-600 text-sm mt-0.5 shrink-0"></i>
                                    <div>
                                        <p class="font-bold">Upload Terkendala</p>
                                        <p id="uploadErrorMessage" class="text-[11px] text-rose-600 mt-0.5">Terjadi kesalahan saat mengunggah file.</p>
                                    </div>
                                </div>
                                <button type="button" id="btnRetryUpload" class="px-2.5 py-1 bg-white border border-rose-300 rounded-lg text-rose-700 hover:bg-rose-100 font-bold text-[10px] shrink-0">
                                    <i class="fa-solid fa-rotate-right mr-1"></i>Coba Lagi
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right: URL & Active APK Status Card (5 cols) -->
                    <div class="lg:col-span-5 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">
                                URL Unduh File APK
                            </label>
                            <input type="text" name="app_apk_url" id="appApkUrlInput" placeholder="uploads/app/... atau https://..."
                                   value="<?php echo htmlspecialchars($cur_apk_url); ?>"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 text-slate-800 text-sm font-mono focus:border-teal-600 focus:ring-2 focus:ring-teal-500/20 focus:outline-none">
                            <p class="text-[11px] text-slate-400 mt-1">Otomatis terisi saat upload selesai, atau tempel link manual (Drive/CDN).</p>
                        </div>

                        <!-- Active APK Status Card -->
                        <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Status APK Aktif</span>
                                <span id="apkLocationBadge" class="text-[10px] font-semibold px-2 py-0.5 rounded-full border <?php echo !empty($cur_apk_url) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'; ?>">
                                    <?php echo !empty($cur_apk_url) ? (!preg_match('/^https?:\/\//i', $cur_apk_url) ? 'Server Lokal' : 'Tautan Eksternal') : 'Belum Ada'; ?>
                                </span>
                            </div>

                            <?php 
                            $resolvedApkUrl = $cur_apk_url;
                            if (!empty($resolvedApkUrl) && !preg_match('/^https?:\/\//i', $resolvedApkUrl)) {
                                $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
                                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                                    || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);
                                $proto = $isHttps ? "https" : "http";
                                $host = $_SERVER['HTTP_HOST'] ?? 'dashboard.assunnahcirebon.com';
                                $resolvedApkUrl = rtrim($proto . "://" . $host, '/') . '/' . ltrim($resolvedApkUrl, '/');
                            }
                            ?>

                            <div id="activeApkInfoContainer">
                                <?php if (!empty($cur_apk_url)): ?>
                                    <div class="p-2.5 bg-white border border-slate-200 rounded-xl space-y-2 shadow-xs">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="truncate text-slate-600 font-mono text-[11px]" title="<?php echo htmlspecialchars($resolvedApkUrl); ?>">
                                                <i class="fa-solid fa-circle-check text-emerald-500 mr-1.5"></i><?php echo htmlspecialchars($resolvedApkUrl); ?>
                                            </span>
                                            <a href="<?php echo htmlspecialchars($resolvedApkUrl); ?>" target="_blank" id="btnTestDownload" class="px-2.5 py-1 bg-teal-50 border border-teal-200 rounded-lg text-teal-700 hover:bg-teal-100 font-bold text-[10px] shrink-0 inline-flex items-center gap-1 transition-colors">
                                                <i class="fa-solid fa-download"></i> Tes Unduh
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
                                        <span>Belum ada file APK aktif. Unggah file .apk di sebelah kiri untuk merilis pembaruan.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
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
                <button type="submit" id="btnSubmitUpdate"
                        class="px-6 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white text-xs font-bold shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span id="btnSubmitText">Simpan & Rilis Update</span>
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
                            $hasVersionRecorded = ($bNum > 0 || !empty($u['app_version']));
                            $hasActiveFcm = !empty($u['fcm_token']);

                            if (!$hasVersionRecorded) {
                                if ($hasActiveFcm) {
                                    $statusBadge = ['Versi Lawas (< v' . $cur_version . ')', 'bg-indigo-50 text-indigo-700 border-indigo-200 font-semibold'];
                                } else {
                                    $statusBadge = ['Belum Terpasang', 'bg-slate-100 text-slate-500 border-slate-200'];
                                }
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
                                    <?php 
                                    if (!empty($u['app_version'])) {
                                        echo htmlspecialchars($u['app_version']);
                                    } elseif ($hasActiveFcm) {
                                        echo '<span class="text-indigo-600 text-[11px] font-sans">&lt; ' . htmlspecialchars($cur_version) . '</span>';
                                    } else {
                                        echo '<span class="text-slate-300">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-700">
                                    <?php echo $bNum > 0 ? '#' . $bNum : ($hasActiveFcm ? '<span class="text-slate-400 font-normal text-[11px]">Legacy</span>' : '<span class="text-slate-300">-</span>'); ?>
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

<script nonce="<?php echo csp_nonce(); ?>">
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('apkDropZone');
    const fileInput = document.getElementById('apkFileInput');
    const progressCard = document.getElementById('uploadProgressCard');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressDetail = document.getElementById('uploadProgressDetail');
    const progressSpeed = document.getElementById('uploadProgressSpeed');
    const statusBadge = document.getElementById('uploadStatusBadge');
    const fileNameEl = document.getElementById('uploadFileName');
    const fileSizeEl = document.getElementById('uploadFileSize');
    const successBox = document.getElementById('uploadSuccessBox');
    const errorBox = document.getElementById('uploadErrorBox');
    const errorMessage = document.getElementById('uploadErrorMessage');
    const btnCancel = document.getElementById('btnCancelUpload');
    const btnReset = document.getElementById('btnResetUpload');
    const btnRetry = document.getElementById('btnRetryUpload');
    const apkUrlInput = document.getElementById('appApkUrlInput');
    const btnSubmit = document.getElementById('btnSubmitUpdate');
    const btnSubmitText = document.getElementById('btnSubmitText');
    const activeApkContainer = document.getElementById('activeApkInfoContainer');
    const apkLocationBadge = document.getElementById('apkLocationBadge');
    const buildInput = document.querySelector('input[name="app_latest_build"]');

    let isUploading = false;
    let abortController = null;
    let selectedFile = null;
    let currentUploadId = null;
    const CHUNK_SIZE = 1024 * 1024; // 1 MB per slice (sangat aman untuk hosting dengan batas 2M)
    const uploadUrl = '../../logic/settings/upload_apk.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Klik drop zone untuk membuka dialog pilih file
    if (dropZone) {
        dropZone.addEventListener('click', () => {
            if (!isUploading && fileInput) fileInput.click();
        });

        // Drag and Drop events
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('border-teal-500', 'bg-teal-50/50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('border-teal-500', 'bg-teal-50/50');
            }, false);
        });

        dropZone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                handleFileSelection(files[0]);
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files.length > 0) {
                handleFileSelection(fileInput.files[0]);
            }
        });
    }

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function handleFileSelection(file) {
        if (!file.name.toLowerCase().endsWith('.apk')) {
            alert('Format file tidak didukung! Harap pilih file berekstensi .apk');
            return;
        }

        selectedFile = file;
        if (fileNameEl) fileNameEl.textContent = file.name;
        if (fileSizeEl) fileSizeEl.textContent = 'Ukuran: ' + formatBytes(file.size);

        startUpload(file);
    }

    async function startUpload(file) {
        isUploading = true;
        abortController = new AbortController();

        // Tampilkan progress card dan sembunyikan drop zone
        if (dropZone) dropZone.classList.add('hidden');
        if (progressCard) progressCard.classList.remove('hidden');
        if (successBox) successBox.classList.add('hidden');
        if (errorBox) errorBox.classList.add('hidden');
        if (btnCancel) btnCancel.classList.remove('hidden');

        if (progressBar) {
            progressBar.style.width = '0%';
            progressBar.className = 'h-full bg-gradient-to-r from-teal-500 via-emerald-500 to-teal-600 rounded-full transition-all duration-200 relative overflow-hidden';
        }
        if (statusBadge) {
            statusBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 flex items-center gap-1';
            statusBadge.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-amber-500"></i> Mengunggah (0%)';
        }
        if (progressDetail) progressDetail.textContent = 'Menyiapkan potongan file...';
        if (progressSpeed) progressSpeed.textContent = '0 KB/s';

        // Kunci tombol simpan form selama proses upload berjalan
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
        }
        if (btnSubmitText) {
            btnSubmitText.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Menunggu Upload Selesai...';
        }

        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        currentUploadId = 'apk_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
        const buildNum = buildInput ? (parseInt(buildInput.value, 10) || 1) : 1;
        const startTime = Date.now();
        let uploadedBytes = 0;

        try {
            for (let i = 0; i < totalChunks; i++) {
                if (abortController.signal.aborted) {
                    throw new Error('Upload dibatalkan oleh pengguna.');
                }

                const start = i * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('action', 'chunk_upload_apk');
                formData.append('upload_id', currentUploadId);
                formData.append('chunk_index', i);
                formData.append('total_chunks', totalChunks);
                formData.append('file_name', file.name);
                formData.append('file_size', file.size);
                formData.append('build_number', buildNum);
                formData.append('csrf_token', csrfToken);
                formData.append('file_chunk', chunk, file.name);

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: formData,
                    signal: abortController.signal
                });

                const rawText = await response.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (e) {
                    let preview = rawText.replace(/<[^>]*>?/gm, ' ').replace(/\s+/g, ' ').trim();
                    if (preview.length > 180) preview = preview.substring(0, 180) + '...';
                    throw new Error('Server mengembalikan respons non-JSON (HTTP ' + response.status + '): ' + (preview || 'Bukan format JSON. Sesi mungkin kedaluwarsa.'));
                }

                if (!response.ok || !data.success) {
                    throw new Error(data.error || ('Gagal memproses potongan file (HTTP ' + response.status + ').'));
                }

                uploadedBytes += (end - start);
                const percent = Math.min(100, Math.round((uploadedBytes / file.size) * 100));
                if (progressBar) progressBar.style.width = percent + '%';
                if (statusBadge) {
                    statusBadge.innerHTML = `<i class="fa-solid fa-spinner fa-spin text-amber-500"></i> Mengunggah (${percent}%)`;
                }

                // Kalkulasi kecepatan upload real-time
                const elapsedSec = (Date.now() - startTime) / 1000;
                const speedBytesPerSec = elapsedSec > 0 ? (uploadedBytes / elapsedSec) : 0;
                if (progressSpeed) progressSpeed.textContent = formatBytes(speedBytesPerSec) + '/s';
                if (progressDetail) {
                    progressDetail.textContent = `Bagian ${i + 1} dari ${totalChunks} (${formatBytes(uploadedBytes)} / ${formatBytes(file.size)})`;
                }

                // Chunk terakhir selesai
                if (data.completed) {
                    onUploadSuccess(data);
                    return;
                }
            }
        } catch (err) {
            if (abortController.signal.aborted) {
                onUploadCancelled();
            } else {
                onUploadError(err.message || 'Terjadi gangguan jaringan saat mengunggah file.');
            }
        }
    }

    function onUploadSuccess(data) {
        isUploading = false;
        if (progressBar) {
            progressBar.style.width = '100%';
            progressBar.className = 'h-full bg-emerald-500 rounded-full transition-all duration-300';
        }
        if (statusBadge) {
            statusBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center gap-1';
            statusBadge.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-600"></i> Upload Selesai & Terverifikasi';
        }
        if (progressDetail) {
            progressDetail.textContent = 'File tersimpan: ' + (data.file_size_formatted || '');
        }
        if (progressSpeed) progressSpeed.textContent = '100% Selesai';
        if (btnCancel) btnCancel.classList.add('hidden');
        if (successBox) successBox.classList.remove('hidden');

        // Isi otomatis input form app_apk_url
        if (apkUrlInput) {
            apkUrlInput.value = data.file_url;
            apkUrlInput.classList.add('ring-2', 'ring-emerald-500', 'border-emerald-500');
            setTimeout(() => {
                apkUrlInput.classList.remove('ring-2', 'ring-emerald-500', 'border-emerald-500');
            }, 3000);
        }

        // Perbarui kartu preview APK aktif
        if (activeApkContainer) {
            activeApkContainer.innerHTML = `
                <div class="p-2.5 bg-white border border-emerald-200 rounded-xl space-y-2 shadow-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-slate-700 font-mono text-[11px]" title="${data.full_url}">
                            <i class="fa-solid fa-circle-check text-emerald-500 mr-1.5"></i>${data.full_url}
                        </span>
                        <a href="${data.full_url}" target="_blank" class="px-2.5 py-1 bg-teal-50 border border-teal-200 rounded-lg text-teal-700 hover:bg-teal-100 font-bold text-[10px] shrink-0 inline-flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-download"></i> Tes Unduh
                        </a>
                    </div>
                    <div class="flex items-center gap-3 text-[10px] text-slate-400 border-t border-slate-100 pt-1.5">
                        <span><i class="fa-solid fa-file-arrow-down mr-1 text-teal-600"></i>${data.file_name}</span>
                        <span><i class="fa-solid fa-hard-drive mr-1 text-slate-500"></i>${data.file_size_formatted || ''}</span>
                        <span class="text-emerald-600 font-semibold ml-auto"><i class="fa-solid fa-bolt mr-1"></i>Siap Dirilis</span>
                    </div>
                </div>
            `;
        }
        if (apkLocationBadge) {
            apkLocationBadge.className = 'text-[10px] font-semibold px-2 py-0.5 rounded-full border bg-emerald-50 text-emerald-700 border-emerald-200';
            apkLocationBadge.textContent = 'Server Lokal (Baru Diunggah)';
        }

        // Buka kunci tombol simpan form & berikan visual cue
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
            btnSubmit.classList.add('ring-4', 'ring-emerald-400/30');
            setTimeout(() => btnSubmit.classList.remove('ring-4', 'ring-emerald-400/30'), 3000);
        }
        if (btnSubmitText) {
            btnSubmitText.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i>Simpan & Rilis Update';
        }
    }

    function onUploadError(msg) {
        isUploading = false;
        if (statusBadge) {
            statusBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 flex items-center gap-1';
            statusBadge.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Upload Terkendala';
        }
        if (errorMessage) errorMessage.textContent = msg;
        if (errorBox) errorBox.classList.remove('hidden');

        // Buka kembali tombol simpan jika admin ingin menyimpan konfigurasi teks saja
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (btnSubmitText) {
            btnSubmitText.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i>Simpan & Rilis Update';
        }
    }

    function onUploadCancelled() {
        isUploading = false;
        if (currentUploadId) {
            const cancelData = new FormData();
            cancelData.append('action', 'cancel_upload_apk');
            cancelData.append('upload_id', currentUploadId);
            cancelData.append('csrf_token', csrfToken);
            navigator.sendBeacon ? navigator.sendBeacon(uploadUrl, cancelData) : fetch(uploadUrl, { method: 'POST', body: cancelData });
        }
        resetToDropZone();
    }

    function resetToDropZone() {
        if (abortController) {
            abortController.abort();
        }
        isUploading = false;
        selectedFile = null;
        if (fileInput) fileInput.value = '';
        if (dropZone) dropZone.classList.remove('hidden');
        if (progressCard) progressCard.classList.add('hidden');
        if (successBox) successBox.classList.add('hidden');
        if (errorBox) errorBox.classList.add('hidden');

        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
        }
        if (btnSubmitText) {
            btnSubmitText.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1"></i>Simpan & Rilis Update';
        }
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            if (confirm('Yakin ingin membatalkan unggahan file APK ini?')) {
                if (abortController) abortController.abort();
                onUploadCancelled();
            }
        });
    }

    if (btnReset) {
        btnReset.addEventListener('click', () => {
            resetToDropZone();
        });
    }

    if (btnRetry) {
        btnRetry.addEventListener('click', () => {
            if (selectedFile) {
                startUpload(selectedFile);
            } else {
                resetToDropZone();
            }
        });
    }

    // Mencegah submit form jika proses upload sedang berjalan di tengah jalan
    if (btnSubmit) {
        const form = btnSubmit.closest('form');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (isUploading) {
                    e.preventDefault();
                    alert('Harap tunggu hingga proses upload file APK selesai sebelum menyimpan pengaturan.');
                }
            });
        }
    }
});
</script>

<?php include '../layouts/footer.php'; ?>

