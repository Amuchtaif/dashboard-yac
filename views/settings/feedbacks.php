<?php
// views/settings/feedbacks.php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/csrf.php';
require_once '../../config/fcm_helper.php';

check_login();
check_permission('Administrator');

$page_title = "Usulan & Kendala Pegawai";

$db = new Database();
$conn = $db->getConnection();

// -----------------------------------------------------------------------------
// 1. Self-healing DB check for user_feedbacks
// -----------------------------------------------------------------------------
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `user_feedbacks` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` INT UNSIGNED NOT NULL,
          `type` ENUM('feature', 'improvement', 'bug', 'general') NOT NULL DEFAULT 'feature',
          `module` VARCHAR(100) NOT NULL DEFAULT 'Umum',
          `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
          `title` VARCHAR(255) NOT NULL,
          `description` TEXT NOT NULL,
          `attachment_path` VARCHAR(255) NULL DEFAULT NULL,
          `app_version` VARCHAR(50) NULL DEFAULT NULL,
          `device_info` VARCHAR(100) NULL DEFAULT NULL,
          `status` ENUM('pending', 'reviewed', 'in_progress', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
          `admin_notes` TEXT NULL DEFAULT NULL,
          `admin_id` INT UNSIGNED NULL DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_user_feedbacks_user` (`user_id`),
          INDEX `idx_user_feedbacks_status` (`status`),
          INDEX `idx_user_feedbacks_module` (`module`),
          INDEX `idx_user_feedbacks_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
} catch (\Throwable $migErr) {
    error_log("user_feedbacks table check error: " . $migErr->getMessage());
}

// Helper untuk mempertahankan parameter filter saat redirect
function build_feedback_return_url($source) {
    $returnQuery = [];
    foreach (['status', 'type', 'module', 'search', 'page'] as $key) {
        $paramKey = 'return_' . $key;
        if (!empty($source[$paramKey])) {
            $returnQuery[$key] = $source[$paramKey];
        }
    }
    return 'feedbacks.php' . (!empty($returnQuery) ? '?' . http_build_query($returnQuery) : '');
}

// -----------------------------------------------------------------------------
// 2. Handle POST Request: Update Status & Reply / Delete Feedback
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_feedback') {
    if (!verify_csrf()) {
        $_SESSION['error'] = "Sesi token keamanan tidak valid. Silakan coba lagi.";
        header("Location: " . build_feedback_return_url($_POST));
        exit;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    if ($feedback_id <= 0) {
        $_SESSION['error'] = "ID usulan tidak valid.";
        header("Location: " . build_feedback_return_url($_POST));
        exit;
    }

    try {
        $stmtFind = $conn->prepare("SELECT id, title, attachment_path FROM user_feedbacks WHERE id = :id LIMIT 1");
        $stmtFind->execute([':id' => $feedback_id]);
        $row = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $_SESSION['error'] = "Data usulan tidak ditemukan.";
            header("Location: " . build_feedback_return_url($_POST));
            exit;
        }

        $stmtDel = $conn->prepare("DELETE FROM user_feedbacks WHERE id = :id");
        $stmtDel->execute([':id' => $feedback_id]);

        // Hapus file lampiran jika ada dan berada di direktori uploads/feedbacks
        if (!empty($row['attachment_path'])) {
            $baseUploadDir = realpath(__DIR__ . '/../../uploads/feedbacks');
            $targetFile = realpath(__DIR__ . '/../../' . ltrim($row['attachment_path'], '/'));
            if ($baseUploadDir && $targetFile && strpos($targetFile, $baseUploadDir . DIRECTORY_SEPARATOR) === 0 && is_file($targetFile)) {
                @unlink($targetFile);
            }
        }

        $_SESSION['success'] = "Data usulan #" . $feedback_id . " (\"" . $row['title'] . "\") berhasil dihapus.";
        header("Location: " . build_feedback_return_url($_POST));
        exit;
    } catch (\Throwable $e) {
        $_SESSION['error'] = "Gagal menghapus data: " . $e->getMessage();
        header("Location: " . build_feedback_return_url($_POST));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_feedback') {
    if (!verify_csrf()) {
        $_SESSION['error'] = "Sesi token keamanan tidak valid. Silakan coba lagi.";
        header("Location: feedbacks.php");
        exit;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $admin_notes = trim($_POST['admin_notes'] ?? '');
    $send_fcm = isset($_POST['send_fcm']) && $_POST['send_fcm'] == '1';
    $admin_id = (int)($_SESSION['user_id'] ?? 0);

    $valid_statuses = ['pending', 'reviewed', 'in_progress', 'completed', 'rejected'];
    if ($feedback_id <= 0 || !in_array($status, $valid_statuses, true)) {
        $_SESSION['error'] = "Data atau status tidak valid.";
        header("Location: feedbacks.php");
        exit;
    }

    try {
        // Fetch current ticket info
        $stmtCurr = $conn->prepare("
            SELECT uf.id, uf.user_id, uf.title, uf.status, e.fcm_token, e.full_name 
            FROM user_feedbacks uf
            LEFT JOIN employees e ON uf.user_id = e.id
            WHERE uf.id = :id
        ");
        $stmtCurr->execute([':id' => $feedback_id]);
        $curr = $stmtCurr->fetch(PDO::FETCH_ASSOC);

        if (!$curr) {
            $_SESSION['error'] = "Data usulan tidak ditemukan.";
            header("Location: feedbacks.php");
            exit;
        }

        // Update database
        $stmtUpdate = $conn->prepare("
            UPDATE user_feedbacks 
            SET status = :status, admin_notes = :notes, admin_id = :admin_id, updated_at = NOW()
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':status' => $status,
            ':notes' => $admin_notes ?: null,
            ':admin_id' => $admin_id ?: null,
            ':id' => $feedback_id
        ]);

        // Send FCM Notification if requested and token is present
        $fcmSent = false;
        if ($send_fcm && !empty($curr['fcm_token'])) {
            $statusLabels = [
                'pending' => 'Menunggu',
                'reviewed' => 'Ditinjau',
                'in_progress' => 'Dalam Antrean Rilis',
                'completed' => 'Selesai',
                'rejected' => 'Ditunda'
            ];
            $statusLabel = $statusLabels[$status] ?? $status;

            try {
                $fcm = new FcmHelper();
                $notifTitle = "Tanggapan Usulan Fitur";
                
                if (!empty($admin_notes)) {
                    $notifBody = "Tim IT telah menanggapi usulan Anda: \"" . $curr['title'] . "\" (Status: " . $statusLabel . ")";
                } else {
                    $notifBody = "Status usulan Anda: \"" . $curr['title'] . "\" diperbarui menjadi " . $statusLabel . ".";
                }

                $notifData = [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'type' => 'feedback',
                    'feedback_id' => (string)$feedback_id,
                    'status' => (string)$status,
                    'screen' => 'feedback_detail',
                    'title' => $notifTitle,
                    'body' => $notifBody
                ];

                $res = $fcm->sendNotification($curr['fcm_token'], $notifTitle, $notifBody, $notifData);
                if ($res !== false) {
                    $fcmSent = true;
                }

                // Insert into notifications inbox table
                try {
                    $stmtInbox = $conn->prepare("
                        INSERT INTO notifications (user_id, title, body, type, is_read, created_at)
                        VALUES (:user_id, :title, :body, 'feedback', 0, NOW())
                    ");
                    $stmtInbox->execute([
                        ':user_id' => (int)$curr['user_id'],
                        ':title' => $notifTitle,
                        ':body' => $notifBody
                    ]);
                } catch (\Throwable $inboxErr) {
                    // Ignore inbox table errors
                }

            } catch (\Throwable $fcmErr) {
                error_log("FCM Feedback Error: " . $fcmErr->getMessage());
            }
        }

        $msg = "Status usulan #" . $feedback_id . " berhasil diperbarui.";
        if ($send_fcm && !empty($curr['fcm_token'])) {
            $msg .= $fcmSent ? " Notifikasi FCM berhasil dikirim ke pengusul." : " (Notifikasi FCM gagal terkirim).";
        } elseif ($send_fcm && empty($curr['fcm_token'])) {
            $msg .= " (Pengusul belum memiliki token FCM aktif).";
        }

        $_SESSION['success'] = $msg;

        header("Location: " . build_feedback_return_url($_POST));
        exit;

    } catch (\Throwable $e) {
        $_SESSION['error'] = "Terjadi kesalahan: " . $e->getMessage();
        header("Location: feedbacks.php");
        exit;
    }
}

// -----------------------------------------------------------------------------
// 3. Status Summary KPI Counts
// -----------------------------------------------------------------------------
$summary = [
    'total' => 0,
    'pending' => 0,
    'reviewed' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0
];
try {
    $sumStmt = $conn->query("
        SELECT 
            COUNT(*) AS total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
            COUNT(CASE WHEN status = 'reviewed' THEN 1 END) AS reviewed,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) AS in_progress,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected
        FROM user_feedbacks
    ");
    $rawSummary = $sumStmt->fetch(PDO::FETCH_ASSOC);
    if ($rawSummary) {
        $summary = array_map('intval', $rawSummary);
    }
} catch (\Throwable $e) {}

// -----------------------------------------------------------------------------
// 4. Filtering & Pagination
// -----------------------------------------------------------------------------
$filter_status = trim($_GET['status'] ?? '');
$filter_type = trim($_GET['type'] ?? '');
$filter_module = trim($_GET['module'] ?? '');
$filter_search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where = [];
$params = [];

if (!empty($filter_status) && in_array($filter_status, ['pending', 'reviewed', 'in_progress', 'completed', 'rejected'], true)) {
    $where[] = "uf.status = :status";
    $params[':status'] = $filter_status;
}

if (!empty($filter_type) && in_array($filter_type, ['feature', 'improvement', 'bug', 'general'], true)) {
    $where[] = "uf.type = :type";
    $params[':type'] = $filter_type;
}

if (!empty($filter_module) && $filter_module !== 'all') {
    $where[] = "uf.module = :module";
    $params[':module'] = $filter_module;
}

if (!empty($filter_search)) {
    $where[] = "(uf.title LIKE :search OR uf.description LIKE :search OR e.full_name LIKE :search OR e.nik LIKE :search)";
    $params[':search'] = '%' . $filter_search . '%';
}

$whereSql = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

// Count total records
$totalRecords = 0;
try {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        {$whereSql}
    ");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
} catch (\Throwable $e) {}

$totalPages = (int)ceil($totalRecords / $limit) ?: 1;

// Fetch feedbacks
$feedbacks = [];
try {
    $query = "
        SELECT 
            uf.id,
            uf.user_id,
            COALESCE(e.full_name, 'Pengguna') AS user_name,
            COALESCE(u.name, d.name, 'Umum') AS user_division,
            COALESCE(e.phone_number, '') AS user_phone,
            e.fcm_token,
            uf.type,
            uf.module,
            uf.priority,
            uf.title,
            uf.description,
            uf.attachment_path,
            uf.app_version,
            uf.device_info,
            uf.status,
            uf.admin_notes,
            uf.admin_id,
            COALESCE(adm.full_name, 'Tim IT') AS admin_name,
            uf.created_at,
            uf.updated_at
        FROM user_feedbacks uf
        LEFT JOIN employees e ON uf.user_id = e.id
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN employees adm ON uf.admin_id = adm.id
        {$whereSql}
        ORDER BY uf.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($query);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("Fetch feedbacks list error: " . $e->getMessage());
}

// Fetch list of available modules for filter
$availableModules = [
    'Keasramaan', 'Tahfidz', 'Presensi Makan', 'Presensi Pegawai', 
    'Payroll/Gaji', 'Homeroom/Wali Kelas', 'Akademik', 'Umum'
];
try {
    $modStmt = $conn->query("SELECT DISTINCT module FROM user_feedbacks WHERE module IS NOT NULL AND module != '' ORDER BY module ASC");
    $dbModules = $modStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($dbModules)) {
        $availableModules = array_values(array_unique(array_merge($availableModules, $dbModules)));
        sort($availableModules);
    }
} catch (\Throwable $e) {}

$baseUrl = rtrim(BASE_URL, '/');

include '../layouts/header.php';
?>

<div class="w-full pb-16">
    <!-- Breadcrumb -->
    <nav class="flex mb-3" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
            <li>
                <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
            </li>
            <li>
                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                <span class="text-slate-500">Pengaturan</span>
            </li>
            <li aria-current="page">
                <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                <span class="text-slate-800 font-semibold">Usulan & Kendala Pegawai</span>
            </li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="sm:flex sm:items-center sm:justify-between gap-4 mb-6">
        <div class="min-w-0 flex-1">
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-lightbulb text-amber-500"></i>
                <span>Usulan Fitur & Lapor Kendala</span>
            </h1>
            <p class="text-slate-500 text-sm mt-1">
                Kelola masukan, ide fitur, serta laporan kendala teknis dari pegawai pengguna aplikasi mobile YAC.
            </p>
        </div>

        <div class="mt-4 sm:mt-0 flex items-center gap-2 flex-wrap">
            <a href="feedbacks.php" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-arrows-rotate mr-1.5 text-slate-500"></i>
                Refresh
            </a>
            <a href="<?php url('views/settings/fcm_broadcast.php'); ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                <i class="fa-solid fa-bullhorn mr-1.5 text-indigo-500"></i>
                Broadcast Notifikasi
            </a>
        </div>
    </div>

    <!-- Alert Flash Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-6 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800 flex items-start justify-between shadow-sm">
            <div class="flex items-start gap-2.5">
                <i class="fa-solid fa-circle-check text-emerald-600 mt-0.5 text-base"></i>
                <span><?php echo htmlspecialchars($_SESSION['success']); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-6 rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800 flex items-start justify-between shadow-sm">
            <div class="flex items-start gap-2.5">
                <i class="fa-solid fa-circle-exclamation text-rose-600 mt-0.5 text-base"></i>
                <span><?php echo htmlspecialchars($_SESSION['error']); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mb-6">
        <!-- Total -->
        <a href="feedbacks.php" class="block p-4 rounded-xl border transition-all duration-200 bg-white hover:bg-slate-50 text-slate-800 shadow-sm <?php echo empty($filter_status) ? 'border-slate-300 ring-2 ring-blue-500/20' : 'border-slate-200'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo empty($filter_status) ? 'text-slate-700' : 'text-slate-500'; ?>">
                <span>Total Tiket</span>
                <i class="fa-solid fa-inbox <?php echo empty($filter_status) ? 'text-blue-500' : 'text-slate-400'; ?>"></i>
            </div>
            <div class="text-2xl font-bold mt-2 text-slate-800">
                <?php echo number_format($summary['total']); ?>
            </div>
            <div class="text-[11px] mt-1 text-slate-400">
                Semua usulan
            </div>
        </a>

        <!-- Pending -->
        <a href="feedbacks.php?status=pending<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?>" 
           class="block p-4 rounded-xl border transition-all duration-200 <?php echo $filter_status === 'pending' ? 'bg-amber-500 text-white border-amber-500 shadow-md ring-2 ring-amber-500/20' : 'bg-white hover:bg-amber-50/50 border-amber-200/80 text-slate-800 shadow-sm'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo $filter_status === 'pending' ? 'text-amber-100' : 'text-amber-700'; ?>">
                <span>Menunggu</span>
                <span class="flex h-2 w-2 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
            </div>
            <div class="text-2xl font-bold mt-2 <?php echo $filter_status === 'pending' ? 'text-white' : 'text-amber-600'; ?>">
                <?php echo number_format($summary['pending']); ?>
            </div>
            <div class="text-[11px] mt-1 <?php echo $filter_status === 'pending' ? 'text-amber-100' : 'text-slate-400'; ?>">
                Perlu ditinjau
            </div>
        </a>

        <!-- Reviewed -->
        <a href="feedbacks.php?status=reviewed<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?>" 
           class="block p-4 rounded-xl border transition-all duration-200 <?php echo $filter_status === 'reviewed' ? 'bg-blue-600 text-white border-blue-600 shadow-md ring-2 ring-blue-600/20' : 'bg-white hover:bg-blue-50/50 border-blue-200/80 text-slate-800 shadow-sm'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo $filter_status === 'reviewed' ? 'text-blue-100' : 'text-blue-700'; ?>">
                <span>Ditinjau</span>
                <i class="fa-solid fa-eye"></i>
            </div>
            <div class="text-2xl font-bold mt-2 <?php echo $filter_status === 'reviewed' ? 'text-white' : 'text-blue-600'; ?>">
                <?php echo number_format($summary['reviewed']); ?>
            </div>
            <div class="text-[11px] mt-1 <?php echo $filter_status === 'reviewed' ? 'text-blue-100' : 'text-slate-400'; ?>">
                Sudah dibaca
            </div>
        </a>

        <!-- In Progress -->
        <a href="feedbacks.php?status=in_progress<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?>" 
           class="block p-4 rounded-xl border transition-all duration-200 <?php echo $filter_status === 'in_progress' ? 'bg-indigo-600 text-white border-indigo-600 shadow-md ring-2 ring-indigo-600/20' : 'bg-white hover:bg-indigo-50/50 border-indigo-200/80 text-slate-800 shadow-sm'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo $filter_status === 'in_progress' ? 'text-indigo-100' : 'text-indigo-700'; ?>">
                <span>Antrean Rilis</span>
                <i class="fa-solid fa-code-fork"></i>
            </div>
            <div class="text-2xl font-bold mt-2 <?php echo $filter_status === 'in_progress' ? 'text-white' : 'text-indigo-600'; ?>">
                <?php echo number_format($summary['in_progress']); ?>
            </div>
            <div class="text-[11px] mt-1 <?php echo $filter_status === 'in_progress' ? 'text-indigo-100' : 'text-slate-400'; ?>">
                Masuk backlog/dev
            </div>
        </a>

        <!-- Completed -->
        <a href="feedbacks.php?status=completed<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?>" 
           class="block p-4 rounded-xl border transition-all duration-200 <?php echo $filter_status === 'completed' ? 'bg-emerald-600 text-white border-emerald-600 shadow-md ring-2 ring-emerald-600/20' : 'bg-white hover:bg-emerald-50/50 border-emerald-200/80 text-slate-800 shadow-sm'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo $filter_status === 'completed' ? 'text-emerald-100' : 'text-emerald-700'; ?>">
                <span>Selesai</span>
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="text-2xl font-bold mt-2 <?php echo $filter_status === 'completed' ? 'text-white' : 'text-emerald-600'; ?>">
                <?php echo number_format($summary['completed']); ?>
            </div>
            <div class="text-[11px] mt-1 <?php echo $filter_status === 'completed' ? 'text-emerald-100' : 'text-slate-400'; ?>">
                Dirilis / Selesai
            </div>
        </a>

        <!-- Rejected -->
        <a href="feedbacks.php?status=rejected<?php echo !empty($filter_type) ? '&type=' . urlencode($filter_type) : ''; ?>" 
           class="block p-4 rounded-xl border transition-all duration-200 <?php echo $filter_status === 'rejected' ? 'bg-rose-600 text-white border-rose-600 shadow-md ring-2 ring-rose-600/20' : 'bg-white hover:bg-rose-50/50 border-rose-200/80 text-slate-800 shadow-sm'; ?>">
            <div class="flex items-center justify-between text-xs font-medium <?php echo $filter_status === 'rejected' ? 'text-rose-100' : 'text-rose-700'; ?>">
                <span>Ditunda</span>
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <div class="text-2xl font-bold mt-2 <?php echo $filter_status === 'rejected' ? 'text-white' : 'text-rose-600'; ?>">
                <?php echo number_format($summary['rejected']); ?>
            </div>
            <div class="text-[11px] mt-1 <?php echo $filter_status === 'rejected' ? 'text-rose-100' : 'text-slate-400'; ?>">
                Ditunda / Belum relevan
            </div>
        </a>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm mb-6">
        <form method="GET" action="feedbacks.php" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
            <!-- Search -->
            <div class="md:col-span-4 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" 
                       placeholder="Cari judul, isi usulan, nama pengusul..." 
                       class="w-full pl-9 pr-3.5 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-800">
            </div>

            <!-- Status Dropdown -->
            <div class="md:col-span-2">
                <select name="status" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-700 bg-white">
                    <option value="">Semua Status</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                    <option value="reviewed" <?php echo $filter_status === 'reviewed' ? 'selected' : ''; ?>>Ditinjau</option>
                    <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>Antrean Rilis</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>Ditunda</option>
                </select>
            </div>

            <!-- Type Dropdown -->
            <div class="md:col-span-2">
                <select name="type" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-700 bg-white">
                    <option value="">Semua Tipe</option>
                    <option value="feature" <?php echo $filter_type === 'feature' ? 'selected' : ''; ?>>✨ Fitur Baru</option>
                    <option value="improvement" <?php echo $filter_type === 'improvement' ? 'selected' : ''; ?>>📈 Peningkatan</option>
                    <option value="bug" <?php echo $filter_type === 'bug' ? 'selected' : ''; ?>>🐛 Lapor Kendala</option>
                    <option value="general" <?php echo $filter_type === 'general' ? 'selected' : ''; ?>>💬 Saran Umum</option>
                </select>
            </div>

            <!-- Module Dropdown -->
            <div class="md:col-span-2">
                <select name="module" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-700 bg-white">
                    <option value="">Semua Modul</option>
                    <?php foreach ($availableModules as $mod): ?>
                        <option value="<?php echo htmlspecialchars($mod); ?>" <?php echo $filter_module === $mod ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mod); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-2 flex items-center gap-2">
                <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">
                    <i class="fa-solid fa-filter mr-1.5"></i>
                    Terapkan
                </button>
                <?php if (!empty($filter_status) || !empty($filter_type) || !empty($filter_module) || !empty($filter_search)): ?>
                    <a href="feedbacks.php" title="Reset Filter" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-2.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-2">
            <div class="text-sm font-semibold text-slate-800">
                Daftar Usulan & Laporan (<?php echo number_format($totalRecords); ?>)
            </div>
            <div class="text-xs text-slate-500">
                Menampilkan halaman <?php echo $page; ?> dari <?php echo $totalPages; ?>
            </div>
        </div>

        <?php if (empty($feedbacks)): ?>
            <div class="py-16 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 text-slate-400 mb-4">
                    <i class="fa-regular fa-folder-open text-2xl"></i>
                </div>
                <h3 class="text-base font-semibold text-slate-800">Belum ada usulan yang cocok</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    <?php if (!empty($filter_status) || !empty($filter_type) || !empty($filter_module) || !empty($filter_search)): ?>
                        Tidak ada tiket yang sesuai dengan filter yang dipilih. Coba reset filter.
                    <?php else: ?>
                        Belum ada usulan fitur atau laporan kendala yang dikirimkan oleh pengguna aplikasi mobile.
                    <?php endif; ?>
                </p>
                <?php if (!empty($filter_status) || !empty($filter_type) || !empty($filter_module) || !empty($filter_search)): ?>
                    <div class="mt-4">
                        <a href="feedbacks.php" class="inline-flex items-center rounded-lg bg-blue-50 border border-blue-200 px-3.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                            Reset Filter
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 text-slate-600 border-b border-slate-200 font-semibold uppercase tracking-wider text-[11px]">
                            <th class="py-3 px-4 w-12 text-center">#</th>
                            <th class="py-3 px-4 min-w-[200px]">Pengusul</th>
                            <th class="py-3 px-4 min-w-[130px]">Kategori & Modul</th>
                            <th class="py-3 px-4 min-w-[260px]">Judul & Uraian</th>
                            <th class="py-3 px-4 text-center w-20">Lampiran</th>
                            <th class="py-3 px-4 min-w-[110px]">Status</th>
                            <th class="py-3 px-4 min-w-[170px]">Tanggapan Admin</th>
                            <th class="py-3 px-4 text-right w-36">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach ($feedbacks as $index => $fb): 
                            $rowNum = $offset + $index + 1;
                            $attachUrl = !empty($fb['attachment_path']) ? $baseUrl . '/' . ltrim($fb['attachment_path'], '/') : null;

                            // Type config
                            $typeBadges = [
                                'feature' => ['label' => 'Fitur Baru', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'icon' => 'fa-wand-magic-sparkles'],
                                'improvement' => ['label' => 'Peningkatan', 'class' => 'bg-blue-50 text-blue-700 border-blue-200', 'icon' => 'fa-arrow-trend-up'],
                                'bug' => ['label' => 'Lapor Kendala', 'class' => 'bg-rose-50 text-rose-700 border-rose-200', 'icon' => 'fa-bug'],
                                'general' => ['label' => 'Saran Umum', 'class' => 'bg-violet-50 text-violet-700 border-violet-200', 'icon' => 'fa-comment-dots'],
                            ];
                            $typeCfg = $typeBadges[$fb['type']] ?? ['label' => $fb['type'], 'class' => 'bg-slate-100 text-slate-700 border-slate-200', 'icon' => 'fa-circle-info'];

                            // Status config
                            $statusBadges = [
                                'pending' => ['label' => 'Menunggu', 'class' => 'bg-amber-50 text-amber-800 border-amber-200', 'dot' => 'bg-amber-500'],
                                'reviewed' => ['label' => 'Ditinjau', 'class' => 'bg-blue-50 text-blue-800 border-blue-200', 'dot' => 'bg-blue-500'],
                                'in_progress' => ['label' => 'Antrean Rilis', 'class' => 'bg-indigo-50 text-indigo-800 border-indigo-200', 'dot' => 'bg-indigo-500'],
                                'completed' => ['label' => 'Selesai', 'class' => 'bg-emerald-50 text-emerald-800 border-emerald-200', 'dot' => 'bg-emerald-500'],
                                'rejected' => ['label' => 'Ditunda', 'class' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'],
                            ];
                            $statCfg = $statusBadges[$fb['status']] ?? ['label' => $fb['status'], 'class' => 'bg-slate-100 text-slate-700 border-slate-200', 'dot' => 'bg-slate-400'];

                            // Priority config
                            $priorityBadges = [
                                'high' => ['label' => 'Mendesak', 'class' => 'text-rose-600 bg-rose-50 border-rose-200'],
                                'medium' => ['label' => 'Sedang', 'class' => 'text-amber-700 bg-amber-50 border-amber-200'],
                                'low' => ['label' => 'Rendah', 'class' => 'text-slate-600 bg-slate-50 border-slate-200'],
                            ];
                            $prioCfg = $priorityBadges[$fb['priority']] ?? $priorityBadges['medium'];

                            // JSON encoded data for modal
                            $modalData = json_encode([
                                'id' => (int)$fb['id'],
                                'user_id' => (int)$fb['user_id'],
                                'user_name' => $fb['user_name'],
                                'user_division' => $fb['user_division'],
                                'user_phone' => $fb['user_phone'],
                                'type' => $fb['type'],
                                'type_label' => $typeCfg['label'],
                                'module' => $fb['module'],
                                'priority' => $fb['priority'],
                                'priority_label' => $prioCfg['label'],
                                'title' => $fb['title'],
                                'description' => $fb['description'],
                                'attachment_url' => $attachUrl,
                                'app_version' => $fb['app_version'] ?: '-',
                                'device_info' => $fb['device_info'] ?: '-',
                                'status' => $fb['status'],
                                'admin_notes' => $fb['admin_notes'] ?: '',
                                'admin_name' => $fb['admin_name'],
                                'has_fcm' => !empty($fb['fcm_token']),
                                'created_at' => date('d M Y H:i', strtotime($fb['created_at'])),
                                'updated_at' => !empty($fb['updated_at']) ? date('d M Y H:i', strtotime($fb['updated_at'])) : null,
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <!-- # -->
                                <td class="py-3 px-4 text-center font-mono text-slate-400 text-[11px]">
                                    <?php echo $rowNum; ?>
                                </td>

                                <!-- Pengusul -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-800 text-xs">
                                        <?php echo htmlspecialchars($fb['user_name']); ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                        <i class="fa-regular fa-building text-[10px] mr-1 text-slate-400"></i>
                                        <?php echo htmlspecialchars($fb['user_division']); ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-2">
                                        <?php if (!empty($fb['user_phone'])): ?>
                                            <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $fb['user_phone'])); ?>" target="_blank" class="text-emerald-600 hover:text-emerald-700 flex items-center gap-1 font-medium">
                                                <i class="fa-brands fa-whatsapp text-xs"></i>
                                                <span><?php echo htmlspecialchars($fb['user_phone']); ?></span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($fb['app_version'])): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.2 rounded bg-slate-100 text-[10px] text-slate-600">
                                                v<?php echo htmlspecialchars($fb['app_version']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Kategori & Modul -->
                                <td class="py-3 px-4">
                                    <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium border <?php echo $typeCfg['class']; ?> mb-1">
                                        <i class="fa-solid <?php echo $typeCfg['icon']; ?> text-[9px]"></i>
                                        <span><?php echo $typeCfg['label']; ?></span>
                                    </div>
                                    <div class="text-xs text-slate-700 font-medium">
                                        <?php echo htmlspecialchars($fb['module']); ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="inline-block px-1.5 py-0.2 text-[10px] rounded font-medium border <?php echo $prioCfg['class']; ?>">
                                            <?php echo $prioCfg['label']; ?>
                                        </span>
                                    </div>
                                </td>

                                <!-- Judul & Uraian -->
                                <td class="py-3 px-4">
                                    <button type="button" onclick="openFeedbackModal(<?php echo htmlspecialchars($modalData); ?>)" class="text-left font-semibold text-slate-900 hover:text-blue-600 line-clamp-1 transition-colors">
                                        <?php echo htmlspecialchars($fb['title']); ?>
                                    </button>
                                    <p class="text-slate-500 text-[11px] line-clamp-2 mt-0.5 leading-relaxed">
                                        <?php echo htmlspecialchars($fb['description']); ?>
                                    </p>
                                    <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-2">
                                        <span><i class="fa-regular fa-clock mr-1"></i><?php echo date('d M Y, H:i', strtotime($fb['created_at'])); ?></span>
                                        <?php if (!empty($fb['device_info'])): ?>
                                            <span>• <?php echo htmlspecialchars($fb['device_info']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <!-- Lampiran -->
                                <td class="py-3 px-4 text-center">
                                    <?php if ($attachUrl): ?>
                                        <button type="button" onclick="openImageLightbox('<?php echo htmlspecialchars($attachUrl); ?>', '<?php echo htmlspecialchars(addslashes($fb['title'])); ?>')" class="relative inline-block group" title="Klik untuk memperbesar gambar">
                                            <img src="<?php echo htmlspecialchars($attachUrl); ?>" alt="Attachment" class="w-10 h-10 object-cover rounded-lg border border-slate-200 group-hover:ring-2 group-hover:ring-blue-500 transition-all shadow-xs">
                                            <span class="absolute inset-0 bg-slate-900/30 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                <i class="fa-solid fa-magnifying-glass-plus text-white text-xs"></i>
                                            </span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-slate-300 font-mono text-[11px]">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border <?php echo $statCfg['class']; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $statCfg['dot']; ?>"></span>
                                        <span><?php echo $statCfg['label']; ?></span>
                                    </span>
                                </td>

                                <!-- Tanggapan Admin -->
                                <td class="py-3 px-4">
                                    <?php if (!empty($fb['admin_notes'])): ?>
                                        <p class="text-slate-700 text-[11px] line-clamp-2 italic bg-slate-50 border-l-2 border-blue-400 pl-2 py-0.5 rounded-r">
                                            "<?php echo htmlspecialchars($fb['admin_notes']); ?>"
                                        </p>
                                        <div class="text-[10px] text-slate-400 mt-1 flex items-center gap-1">
                                            <i class="fa-solid fa-user-shield text-[9px]"></i>
                                            <span><?php echo htmlspecialchars($fb['admin_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px]">Belum ditanggapi</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="openFeedbackModal(<?php echo htmlspecialchars($modalData); ?>)" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 hover:border-slate-400 transition-colors" title="Tinjau & Beri Tanggapan">
                                            <i class="fa-solid fa-reply text-blue-600 text-[11px]"></i>
                                            <span>Tanggapi</span>
                                        </button>
                                        <button type="button" onclick="openDeleteFeedbackModal(<?php echo (int)$fb['id']; ?>, <?php echo htmlspecialchars(json_encode($fb['title'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '""', ENT_QUOTES, 'UTF-8'); ?>)" class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50/70 px-2.5 py-1.5 text-xs font-semibold text-rose-600 shadow-2xs hover:bg-rose-100 hover:border-rose-300 transition-colors" title="Hapus Usulan">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
                <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-between flex-wrap gap-2 text-xs">
                    <div class="text-slate-500">
                        Total <strong><?php echo number_format($totalRecords); ?></strong> data usulan
                    </div>

                    <div class="inline-flex items-center gap-1">
                        <?php
                        $buildQuery = function($p) use ($filter_status, $filter_type, $filter_module, $filter_search) {
                            $q = ['page' => $p];
                            if (!empty($filter_status)) $q['status'] = $filter_status;
                            if (!empty($filter_type)) $q['type'] = $filter_type;
                            if (!empty($filter_module)) $q['module'] = $filter_module;
                            if (!empty($filter_search)) $q['search'] = $filter_search;
                            return 'feedbacks.php?' . http_build_query($q);
                        };
                        ?>

                        <!-- Prev -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo $buildQuery($page - 1); ?>" class="px-2.5 py-1.5 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $startP = max(1, $page - 2);
                        $endP = min($totalPages, $page + 2);
                        for ($p = $startP; $p <= $endP; $p++):
                        ?>
                            <a href="<?php echo $buildQuery($p); ?>" class="px-3 py-1.5 rounded-md font-medium <?php echo $p === $page ? 'bg-blue-600 text-white' : 'border border-slate-300 bg-white hover:bg-slate-50 text-slate-700'; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo $buildQuery($page + 1); ?>" class="px-2.5 py-1.5 rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700">
                                <i class="fa-solid fa-chevron-right text-[10px]"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Modal: Detail & Form Tanggapan Admin -->
<!-- ======================================================================= -->
<div id="feedbackDetailModal" class="fixed inset-0 z-[70] hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" onclick="if(event.target === this) closeFeedbackModal()">
    <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden transform transition-all my-8 animate-fadeIn" onclick="event.stopPropagation()">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900" id="modalTicketTitle">Detail Usulan Pegawai</h3>
                    <p class="text-[11px] text-slate-500" id="modalTicketSub">Tiket #<span id="modalTicketId"></span></p>
                </div>
            </div>
            <button type="button" onclick="closeFeedbackModal()" class="text-slate-400 hover:text-slate-600 rounded-lg p-1.5 hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
            <!-- Meta Bar -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase font-medium">Pengusul</span>
                    <strong class="text-slate-800" id="modalUserName">-</strong>
                    <span class="text-[11px] text-slate-500 block truncate" id="modalUserDivision">-</span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase font-medium">Kategori & Modul</span>
                    <span id="modalCategoryBadge" class="inline-block mt-0.5 text-[11px] font-semibold text-slate-700">-</span>
                    <span class="text-[11px] text-slate-500 block" id="modalModule">-</span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase font-medium">Prioritas</span>
                    <span id="modalPriorityBadge" class="inline-block mt-0.5 text-[11px] font-semibold">-</span>
                </div>
                <div>
                    <span class="text-slate-400 text-[10px] block uppercase font-medium">Waktu Pengiriman</span>
                    <span class="text-slate-800 text-[11px] font-medium block mt-0.5" id="modalCreatedAt">-</span>
                </div>
            </div>

            <!-- Title & Description -->
            <div>
                <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Judul Usulan / Kendala</label>
                <div class="text-sm font-semibold text-slate-900 p-3 bg-white border border-slate-200 rounded-lg" id="modalFullTitle">
                    -
                </div>
            </div>

            <div>
                <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Uraian / Penjelasan Lengkap</label>
                <div class="text-xs text-slate-700 p-3.5 bg-slate-50/70 border border-slate-200 rounded-lg whitespace-pre-line leading-relaxed" id="modalFullDescription">
                    -
                </div>
            </div>

            <!-- Attachment Section (Conditional) -->
            <div id="modalAttachmentSection" class="hidden">
                <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Lampiran Foto / Screenshot</label>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
                    <img id="modalAttachmentImg" src="" alt="Attachment Preview" class="max-h-60 rounded-lg border border-slate-200 mx-auto cursor-pointer object-contain hover:opacity-95 transition-opacity" onclick="openLightboxFromModal()">
                    <div class="text-center mt-2">
                        <a id="modalAttachmentLink" href="#" target="_blank" class="inline-flex items-center text-xs text-blue-600 hover:text-blue-800 font-medium">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka gambar ukuran penuh
                        </a>
                    </div>
                </div>
            </div>

            <!-- Device & App Version Meta -->
            <div class="flex items-center justify-between text-[11px] text-slate-400 px-1 border-t border-slate-100 pt-3">
                <div>
                    <span>Versi App: </span><strong class="text-slate-600" id="modalAppVersion">-</strong>
                    <span class="mx-2">•</span>
                    <span>Perangkat: </span><strong class="text-slate-600" id="modalDeviceInfo">-</strong>
                </div>
                <div id="modalFcmIndicator">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Form Tanggapan Admin -->
            <form method="POST" action="feedbacks.php" id="formUpdateFeedback" class="border-t border-slate-200 pt-4 space-y-4">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_feedback">
                <input type="hidden" name="feedback_id" id="formFeedbackId" value="">
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                <input type="hidden" name="return_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <input type="hidden" name="return_module" value="<?php echo htmlspecialchars($filter_module); ?>">
                <input type="hidden" name="return_search" value="<?php echo htmlspecialchars($filter_search); ?>">
                <input type="hidden" name="return_page" value="<?php echo htmlspecialchars($page); ?>">

                <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-200/80 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-pen-to-square text-blue-600"></i>
                            Tanggapan & Perubahan Status
                        </h4>
                        <span class="text-[10px] text-slate-500">Pemberitahuan resmi ke pengusul</span>
                    </div>

                    <!-- Status Selection -->
                    <div>
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Status Usulan <span class="text-rose-500">*</span></label>
                        <select name="status" id="formStatus" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-800 bg-white font-medium">
                            <option value="pending">⏳ Menunggu (Belum ditindaklanjuti)</option>
                            <option value="reviewed">👀 Ditinjau (Sudah dibaca oleh Tim IT)</option>
                            <option value="in_progress">🚀 Dalam Antrean Rilis (Sedang dikerjakan/disetujui)</option>
                            <option value="completed">✅ Selesai (Fitur/perbaikan telah dirilis)</option>
                            <option value="rejected">🛑 Ditunda (Belum dapat diakomodasi/ditolak)</option>
                        </select>
                    </div>

                    <!-- Admin Notes -->
                    <div>
                        <label class="text-xs font-semibold text-slate-700 block mb-1">
                            Catatan Balasan untuk Pegawai (Admin Notes)
                        </label>
                        <textarea name="admin_notes" id="formAdminNotes" rows="3" 
                                  placeholder="Contoh: Jazakallahu khair atas usulannya. Fitur ini telah disetujui dan dijadwalkan pada rilis versi 2.4." 
                                  class="w-full p-2.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-slate-800 leading-relaxed"></textarea>
                    </div>

                    <!-- Push FCM Option -->
                    <div class="flex items-center gap-2 pt-1">
                        <input type="checkbox" name="send_fcm" id="formSendFcm" value="1" checked class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
                        <label for="formSendFcm" class="text-xs text-slate-700 cursor-pointer font-medium">
                            Kirim Push Notifikasi ke ponsel pengusul via FCM
                        </label>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex items-center justify-between gap-2.5 pt-2">
                    <button type="button" onclick="openDeleteFromDetailModal()" class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-100 hover:border-rose-300 transition-colors">
                        <i class="fa-solid fa-trash-can mr-1.5"></i>
                        Hapus
                    </button>
                    <div class="flex items-center gap-2.5">
                        <button type="button" onclick="closeFeedbackModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                            Batal
                        </button>
                        <button type="submit" id="btnSubmitFeedback" class="inline-flex items-center rounded-lg bg-blue-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors">
                            <i class="fa-solid fa-paper-plane mr-1.5"></i>
                            Simpan & Beri Tanggapan
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Modal: Konfirmasi Hapus Usulan -->
<!-- ======================================================================= -->
<div id="deleteFeedbackModal" class="fixed inset-0 z-[90] hidden overflow-y-auto bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4" style="backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);" onclick="if(event.target === this) closeDeleteFeedbackModal()">
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden transform transition-all animate-fadeIn" onclick="event.stopPropagation()">
        <div class="p-6 text-center">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-rose-100 text-rose-600 border border-rose-200">
                <i class="fa-solid fa-triangle-exclamation text-xl"></i>
            </div>
            <h3 class="text-base font-bold text-slate-900">Hapus Data Usulan?</h3>
            <p class="mt-2 text-xs text-slate-500 leading-relaxed">
                Apakah Anda yakin ingin menghapus tiket <strong class="text-slate-700">#<span id="deleteModalTicketId"></span></strong>:
                <span id="deleteModalTicketTitle" class="block mt-1 font-semibold text-slate-800 break-words"></span>
                <span class="block mt-2 text-rose-600">Tindakan ini permanen dan juga akan menghapus lampiran gambar (jika ada).</span>
            </p>

            <form method="POST" action="feedbacks.php" id="formDeleteFeedback" class="mt-6 flex items-center justify-center gap-2.5">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_feedback">
                <input type="hidden" name="feedback_id" id="deleteFormFeedbackId" value="">
                <input type="hidden" name="return_status" value="<?php echo htmlspecialchars($filter_status); ?>">
                <input type="hidden" name="return_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                <input type="hidden" name="return_module" value="<?php echo htmlspecialchars($filter_module); ?>">
                <input type="hidden" name="return_search" value="<?php echo htmlspecialchars($filter_search); ?>">
                <input type="hidden" name="return_page" value="<?php echo htmlspecialchars($page); ?>">

                <button type="button" onclick="closeDeleteFeedbackModal()" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" id="btnConfirmDeleteFeedback" class="inline-flex items-center rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-rose-700 transition-colors cursor-pointer">
                    <i class="fa-solid fa-trash-can mr-1.5"></i>
                    <span>Ya, Hapus Data</span>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- Modal: Lightbox Image Viewer -->
<!-- ======================================================================= -->
<div id="imageLightboxModal" class="fixed inset-0 z-60 hidden bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4" onclick="closeImageLightbox()">
    <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" onclick="closeImageLightbox()" class="absolute -top-10 right-0 text-white/80 hover:text-white text-xl">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightboxImg" src="" alt="Full preview" class="max-w-full max-h-[82vh] rounded-lg shadow-2xl border border-white/10 object-contain">
        <div id="lightboxCaption" class="text-white text-xs text-center mt-3 font-medium bg-slate-900/70 px-4 py-1.5 rounded-full"></div>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
let currentModalAttachment = null;
let currentModalTitle = '';
let currentModalFeedbackId = null;

function openFeedbackModal(data) {
    currentModalFeedbackId = data.id;
    document.getElementById('modalTicketId').textContent = data.id;
    document.getElementById('formFeedbackId').value = data.id;
    document.getElementById('modalUserName').textContent = data.user_name;
    document.getElementById('modalUserDivision').textContent = data.user_division;
    document.getElementById('modalCategoryBadge').textContent = data.type_label;
    document.getElementById('modalModule').textContent = data.module;
    document.getElementById('modalPriorityBadge').textContent = data.priority_label;
    document.getElementById('modalCreatedAt').textContent = data.created_at;
    document.getElementById('modalFullTitle').textContent = data.title;
    document.getElementById('modalFullDescription').textContent = data.description;
    document.getElementById('modalAppVersion').textContent = data.app_version;
    document.getElementById('modalDeviceInfo').textContent = data.device_info;

    // Status & Notes form
    document.getElementById('formStatus').value = data.status;
    document.getElementById('formAdminNotes').value = data.admin_notes || '';

    // FCM indicator
    const fcmIndicator = document.getElementById('modalFcmIndicator');
    if (data.has_fcm) {
        fcmIndicator.innerHTML = '<span class="inline-flex items-center gap-1 text-emerald-600 font-medium"><i class="fa-solid fa-bell text-[10px]"></i> FCM Aktif</span>';
        document.getElementById('formSendFcm').disabled = false;
        document.getElementById('formSendFcm').checked = true;
    } else {
        fcmIndicator.innerHTML = '<span class="inline-flex items-center gap-1 text-slate-400"><i class="fa-solid fa-bell-slash text-[10px]"></i> Tanpa FCM</span>';
        document.getElementById('formSendFcm').disabled = true;
        document.getElementById('formSendFcm').checked = false;
    }

    // Attachment
    const attachSection = document.getElementById('modalAttachmentSection');
    const attachImg = document.getElementById('modalAttachmentImg');
    const attachLink = document.getElementById('modalAttachmentLink');

    currentModalAttachment = data.attachment_url;
    currentModalTitle = data.title;

    if (data.attachment_url) {
        attachImg.src = data.attachment_url;
        attachLink.href = data.attachment_url;
        attachSection.classList.remove('hidden');
    } else {
        attachSection.classList.add('hidden');
    }

    document.getElementById('feedbackDetailModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeFeedbackModal() {
    document.getElementById('feedbackDetailModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

function openDeleteFeedbackModal(id, title) {
    document.getElementById('deleteModalTicketId').textContent = id;
    document.getElementById('deleteFormFeedbackId').value = id;
    document.getElementById('deleteModalTicketTitle').textContent = '"' + (title || '') + '"';
    const btn = document.getElementById('btnConfirmDeleteFeedback');
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-trash-can mr-1.5"></i><span>Ya, Hapus Data</span>';
    }
    document.getElementById('deleteFeedbackModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function openDeleteFromDetailModal() {
    if (currentModalFeedbackId) {
        openDeleteFeedbackModal(currentModalFeedbackId, currentModalTitle);
    }
}

function closeDeleteFeedbackModal() {
    document.getElementById('deleteFeedbackModal').classList.add('hidden');
    if (document.getElementById('feedbackDetailModal').classList.contains('hidden')) {
        document.body.classList.remove('overflow-hidden');
    }
}

const formDeleteEl = document.getElementById('formDeleteFeedback');
if (formDeleteEl) {
    formDeleteEl.addEventListener('submit', function() {
        const btn = document.getElementById('btnConfirmDeleteFeedback');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1.5"></i><span>Menghapus...</span>';
        }
    });
}

function openImageLightbox(url, caption) {
    document.getElementById('lightboxImg').src = url;
    document.getElementById('lightboxCaption').textContent = caption || 'Lampiran Usulan';
    document.getElementById('imageLightboxModal').classList.remove('hidden');
}

function closeImageLightbox() {
    document.getElementById('imageLightboxModal').classList.add('hidden');
}

function openLightboxFromModal() {
    if (currentModalAttachment) {
        openImageLightbox(currentModalAttachment, currentModalTitle);
    }
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFeedbackModal();
        closeDeleteFeedbackModal();
        closeImageLightbox();
    }
});

// Expose functions globally to window
window.openFeedbackModal = openFeedbackModal;
window.closeFeedbackModal = closeFeedbackModal;
window.openDeleteFeedbackModal = openDeleteFeedbackModal;
window.openDeleteFromDetailModal = openDeleteFromDetailModal;
window.closeDeleteFeedbackModal = closeDeleteFeedbackModal;
window.openImageLightbox = openImageLightbox;
window.closeImageLightbox = closeImageLightbox;
window.openLightboxFromModal = openLightboxFromModal;
</script>

<?php include '../layouts/footer.php'; ?>
