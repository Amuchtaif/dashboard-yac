<?php
// views/settings/fcm_broadcast.php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/csrf.php';
require_once '../../config/fcm_helper.php';

check_login();
check_permission('Administrator');

$page_title = "Kirim Notifikasi FCM";

$db = new Database();
$conn = $db->getConnection();

// 1. Self-healing DB Table: fcm_broadcast_logs
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS fcm_broadcast_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NULL,
            sender_name VARCHAR(150) NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            target_summary TEXT NULL,
            screen VARCHAR(50) DEFAULT 'general',
            saved_to_inbox TINYINT(1) DEFAULT 0,
            total_recipients INT DEFAULT 0,
            success_count INT DEFAULT 0,
            failure_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created_at (created_at),
            INDEX idx_target_type (target_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (\Throwable $migErr) {
    error_log("FCM Broadcast Log Table Creation: " . $migErr->getMessage());
}

// 2. Handle POST Request (Broadcast Submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_broadcast') {
    if (!verify_csrf()) {
        $_SESSION['error'] = "Sesi keamanan telah berakhir. Silakan muat ulang halaman.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $target_type = trim($_POST['target_type'] ?? 'all');
    $screen = trim($_POST['screen'] ?? 'general');
    $save_to_inbox = (isset($_POST['save_to_inbox']) && $_POST['save_to_inbox'] == '1') ? 1 : 0;

    if (empty($title) || empty($body)) {
        $_SESSION['error'] = "Judul dan isi pesan notifikasi wajib diisi.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    $recipients = [];
    $target_summary = "";

    try {
        if ($target_type === 'all') {
            $stmt = $conn->query("
                SELECT id, full_name, fcm_token 
                FROM employees 
                WHERE status = 'active' AND fcm_token IS NOT NULL AND fcm_token != ''
            ");
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $target_summary = "Semua Pegawai (" . count($recipients) . " perangkat)";
        } elseif ($target_type === 'unit') {
            $unit_ids = $_POST['unit_ids'] ?? [];
            if (!is_array($unit_ids) || empty($unit_ids)) {
                $_SESSION['error'] = "Pilih minimal satu unit lembaga penerima.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            $clean_units = array_map('intval', $unit_ids);
            $placeholders = implode(',', array_fill(0, count($clean_units), '?'));

            $stmtU = $conn->prepare("SELECT name FROM units WHERE id IN ($placeholders) ORDER BY name");
            $stmtU->execute($clean_units);
            $unitNames = $stmtU->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $conn->prepare("
                SELECT id, full_name, fcm_token 
                FROM employees 
                WHERE status = 'active' AND unit_id IN ($placeholders) AND fcm_token IS NOT NULL AND fcm_token != ''
            ");
            $stmt->execute($clean_units);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $target_summary = "Unit: " . implode(', ', $unitNames) . " (" . count($recipients) . " perangkat)";
        } elseif ($target_type === 'position') {
            $position_ids = $_POST['position_ids'] ?? [];
            if (!is_array($position_ids) || empty($position_ids)) {
                $_SESSION['error'] = "Pilih minimal satu jabatan penerima.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            $clean_pos = array_map('intval', $position_ids);
            $placeholders = implode(',', array_fill(0, count($clean_pos), '?'));

            $stmtP = $conn->prepare("SELECT name FROM positions WHERE id IN ($placeholders) ORDER BY name");
            $stmtP->execute($clean_pos);
            $posNames = $stmtP->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $conn->prepare("
                SELECT id, full_name, fcm_token 
                FROM employees 
                WHERE status = 'active' AND position_id IN ($placeholders) AND fcm_token IS NOT NULL AND fcm_token != ''
            ");
            $stmt->execute($clean_pos);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $target_summary = "Jabatan: " . implode(', ', $posNames) . " (" . count($recipients) . " perangkat)";
        } elseif ($target_type === 'specific') {
            $employee_ids = $_POST['employee_ids'] ?? [];
            if (!is_array($employee_ids) || empty($employee_ids)) {
                $_SESSION['error'] = "Pilih minimal satu karyawan penerima.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            $clean_emp = array_map('intval', $employee_ids);
            $placeholders = implode(',', array_fill(0, count($clean_emp), '?'));

            $stmt = $conn->prepare("
                SELECT id, full_name, fcm_token 
                FROM employees 
                WHERE status = 'active' AND id IN ($placeholders) AND fcm_token IS NOT NULL AND fcm_token != ''
            ");
            $stmt->execute($clean_emp);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $namesPreview = array_slice(array_column($recipients, 'full_name'), 0, 3);
            $extra = count($recipients) > 3 ? " +" . (count($recipients) - 3) . " lainnya" : "";
            $target_summary = "Karyawan Tertentu (" . count($recipients) . " perangkat: " . implode(', ', $namesPreview) . $extra . ")";
        } elseif ($target_type === 'topic') {
            $topic = trim($_POST['topic_name'] ?? 'all_users');
            if (empty($topic)) {
                $topic = 'all_users';
            }
            $target_summary = "Topik FCM: " . $topic;
        }

        $fcm = new FcmHelper();
        $totalRecipients = 0;
        $successCount = 0;
        $failureCount = 0;

        $extraData = [
            'screen' => (string)$screen,
            'type' => 'broadcast',
            'title' => (string)$title,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ];

        if ($target_type === 'topic') {
            $topic = trim($_POST['topic_name'] ?? 'all_users');
            $res = $fcm->sendTopicNotification($topic, $title, $body, $extraData);
            $totalRecipients = 1;
            if ($res !== false && !isset($res['error'])) {
                $successCount = 1;
            } else {
                $failureCount = 1;
            }
        } else {
            if (empty($recipients)) {
                $_SESSION['error'] = "Tidak ditemukan pegawai dengan token FCM aktif pada target yang dipilih.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }

            $tokens = array_column($recipients, 'fcm_token');
            $result = $fcm->sendBulkNotification($tokens, $title, $body, $extraData, 25);
            $totalRecipients = $result['total'];
            $successCount = $result['success'];
            $failureCount = $result['failure'];

            // Simpan ke inbox tabel notifications jika dipilih
            if ($save_to_inbox === 1 && !empty($recipients)) {
                try {
                    $insertChunks = array_chunk($recipients, 50);
                    foreach ($insertChunks as $chunk) {
                        $values = [];
                        $params = [];
                        foreach ($chunk as $idx => $r) {
                            $values[] = "(:uid_{$idx}, :title_{$idx}, :body_{$idx}, 'broadcast', 0, NOW())";
                            $params[":uid_{$idx}"] = $r['id'];
                            $params[":title_{$idx}"] = $title;
                            $params[":body_{$idx}"] = $body;
                        }
                        $sqlNotif = "INSERT INTO notifications (user_id, title, body, type, is_read, created_at) VALUES " . implode(', ', $values);
                        $stmtNotif = $conn->prepare($sqlNotif);
                        $stmtNotif->execute($params);
                    }
                } catch (\Throwable $inboxErr) {
                    error_log("Error saving broadcast to inbox: " . $inboxErr->getMessage());
                }
            }
        }

        // Simpan log pengiriman
        $senderId = $_SESSION['user_id'] ?? null;
        $senderName = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Administrator';

        $stmtLog = $conn->prepare("
            INSERT INTO fcm_broadcast_logs 
            (sender_id, sender_name, title, body, target_type, target_summary, screen, saved_to_inbox, total_recipients, success_count, failure_count, created_at)
            VALUES (:sid, :sname, :title, :body, :ttype, :tsummary, :screen, :inbox, :total, :succ, :fail, NOW())
        ");
        $stmtLog->execute([
            ':sid' => $senderId,
            ':sname' => $senderName,
            ':title' => $title,
            ':body' => $body,
            ':ttype' => $target_type,
            ':tsummary' => $target_summary,
            ':screen' => $screen,
            ':inbox' => $save_to_inbox,
            ':total' => $totalRecipients,
            ':succ' => $successCount,
            ':fail' => $failureCount
        ]);

        if ($successCount > 0) {
            $_SESSION['success'] = "Notifikasi berhasil dikirim ke {$successCount} perangkat" . ($failureCount > 0 ? " ({$failureCount} gagal)" : "") . ".";
        } else {
            $_SESSION['error'] = "Gagal mengirim notifikasi FCM. Periksa konfigurasi service-account.json.";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (\Throwable $e) {
        $_SESSION['error'] = "Gagal memproses pengiriman: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 2b. Handle POST Request (Delete Broadcast Log)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_log') {
    if (!verify_csrf()) {
        $_SESSION['error'] = "Sesi keamanan telah berakhir. Silakan muat ulang halaman.";
        header("Location: " . $_SERVER['PHP_SELF'] . "#riwayat-broadcast");
        exit;
    }

    $log_id = (int)($_POST['log_id'] ?? 0);
    if ($log_id > 0) {
        try {
            $stmtDel = $conn->prepare("DELETE FROM fcm_broadcast_logs WHERE id = :id");
            $stmtDel->execute([':id' => $log_id]);
            $_SESSION['success'] = "Riwayat broadcast berhasil dihapus.";
        } catch (\Throwable $e) {
            $_SESSION['error'] = "Gagal menghapus riwayat broadcast: " . $e->getMessage();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "#riwayat-broadcast");
    exit;
}

// 3. Fetch Data for Form & Stats
$stats = [
    'active_fcm_users' => 0,
    'total_employees' => 0,
    'total_broadcasts' => 0,
    'today_broadcasts' => 0,
    'total_delivered' => 0
];

try {
    $empStats = $conn->query("
        SELECT 
            COUNT(*) as total_employees,
            COUNT(CASE WHEN status = 'active' AND fcm_token IS NOT NULL AND fcm_token != '' THEN 1 END) as active_fcm_users
        FROM employees
    ")->fetch(PDO::FETCH_ASSOC);

    if ($empStats) {
        $stats['total_employees'] = (int)$empStats['total_employees'];
        $stats['active_fcm_users'] = (int)$empStats['active_fcm_users'];
    }

    $logStats = $conn->query("
        SELECT 
            COUNT(*) as total_broadcasts,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_broadcasts,
            COALESCE(SUM(success_count), 0) as total_delivered
        FROM fcm_broadcast_logs
    ")->fetch(PDO::FETCH_ASSOC);

    if ($logStats) {
        $stats['total_broadcasts'] = (int)$logStats['total_broadcasts'];
        $stats['today_broadcasts'] = (int)$logStats['today_broadcasts'];
        $stats['total_delivered'] = (int)$logStats['total_delivered'];
    }
} catch (\Throwable $e) {}

// Units with active FCM count
$units = [];
try {
    $units = $conn->query("
        SELECT u.id, u.name,
               COUNT(CASE WHEN e.status = 'active' AND e.fcm_token IS NOT NULL AND e.fcm_token != '' THEN 1 END) as fcm_count,
               COUNT(CASE WHEN e.status = 'active' THEN 1 END) as total_emp
        FROM units u
        LEFT JOIN employees e ON e.unit_id = u.id
        GROUP BY u.id, u.name
        ORDER BY u.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// Positions with active FCM count
$positions = [];
try {
    $positions = $conn->query("
        SELECT p.id, p.name,
               COUNT(CASE WHEN e.status = 'active' AND e.fcm_token IS NOT NULL AND e.fcm_token != '' THEN 1 END) as fcm_count,
               COUNT(CASE WHEN e.status = 'active' THEN 1 END) as total_emp
        FROM positions p
        LEFT JOIN employees e ON e.position_id = p.id
        GROUP BY p.id, p.name
        ORDER BY p.name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// All Active Employees for Modal Picker
$employees = [];
try {
    $employees = $conn->query("
        SELECT e.id, e.full_name, e.nik, u.name as unit_name, p.name as position_name,
               (e.fcm_token IS NOT NULL AND e.fcm_token != '') as has_fcm
        FROM employees e
        LEFT JOIN units u ON e.unit_id = u.id
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.status = 'active'
        ORDER BY has_fcm DESC, e.full_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    error_log("FCM Employees List Query Error: " . $e->getMessage());
}

// Recent Broadcast Logs
$recentLogs = [];
try {
    $recentLogs = $conn->query("
        SELECT * FROM fcm_broadcast_logs 
        ORDER BY created_at DESC 
        LIMIT 25
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

include '../layouts/header.php';
?>

<style nonce="<?php echo csp_nonce(); ?>">
#customScreenSelectMenu::-webkit-scrollbar,
#modalEmployeeListContainer::-webkit-scrollbar {
    width: 6px;
}
#customScreenSelectMenu::-webkit-scrollbar-track,
#modalEmployeeListContainer::-webkit-scrollbar-track {
    background: transparent;
}
#customScreenSelectMenu::-webkit-scrollbar-thumb,
#modalEmployeeListContainer::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 9999px;
}
#customScreenSelectMenu::-webkit-scrollbar-thumb:hover,
#modalEmployeeListContainer::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
</style>

<div class="w-full pb-12">

    <!-- Header Block -->
    <div class="mb-6">
        <!-- Breadcrumb -->
        <nav class="flex mb-3" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-2 text-xs text-slate-500">
                <li>
                    <a href="<?php url('views/dashboard/index.php'); ?>" class="hover:text-slate-800 transition-colors">Beranda</a>
                </li>
                <li>
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-500">Manajemen Aplikasi</span>
                </li>
                <li aria-current="page">
                    <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 mx-1"></i>
                    <span class="text-slate-800 font-medium">Broadcast Notifikasi FCM</span>
                </li>
            </ol>
        </nav>

        <!-- Title & Action Buttons -->
        <div class="sm:flex sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Kirim Notifikasi FCM</h1>
                <p class="text-slate-500 text-sm mt-1">
                    Kirim pesan push notification massal ke aplikasi mobile pegawai.
                </p>
            </div>

            <div class="mt-4 sm:mt-0 flex items-center gap-2.5 flex-wrap">
                <a href="<?php url('views/settings/app_update.php'); ?>"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-mobile-screen-button mr-2 text-slate-500"></i>
                    Versi Aplikasi
                </a>
                <a href="#riwayat-broadcast"
                   class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-slate-500"></i>
                    Riwayat Pengiriman
                </a>
            </div>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <!-- 1. Perangkat Aktif -->
        <div class="group relative overflow-hidden bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-sm transition-all duration-200 flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] group-hover:text-teal-600/[0.08] transition-colors pointer-events-none select-none">
                <i class="fa-solid fa-mobile-screen"></i>
            </div>
            <div class="relative z-10">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Perangkat Aktif</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?php echo number_format($stats['active_fcm_users']); ?></div>
                <div class="text-xs text-teal-700 font-medium mt-1">Token FCM terhubung</div>
            </div>
        </div>

        <!-- 2. Total Broadcast -->
        <div class="group relative overflow-hidden bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-sm transition-all duration-200 flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] group-hover:text-indigo-600/[0.08] transition-colors pointer-events-none select-none">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div class="relative z-10">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Broadcast</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?php echo number_format($stats['total_broadcasts']); ?></div>
                <div class="text-xs text-slate-500 font-medium mt-1">Riwayat pengiriman</div>
            </div>
        </div>

        <!-- 3. Hari Ini -->
        <div class="group relative overflow-hidden bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-sm transition-all duration-200 flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] group-hover:text-amber-600/[0.08] transition-colors pointer-events-none select-none">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div class="relative z-10">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Hari Ini</span>
                <div class="text-2xl sm:text-3xl font-black text-slate-900 mt-2"><?php echo number_format($stats['today_broadcasts']); ?></div>
                <div class="text-xs text-amber-700 font-medium mt-1">Broadcast terkirim</div>
            </div>
        </div>

        <!-- 4. Total Pesan Diterima -->
        <div class="group relative overflow-hidden bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-sm transition-all duration-200 flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] group-hover:text-emerald-600/[0.08] transition-colors pointer-events-none select-none">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="relative z-10">
                <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Pesan Diterima</span>
                <div class="text-2xl sm:text-3xl font-black text-emerald-600 mt-2"><?php echo number_format($stats['total_delivered']); ?></div>
                <div class="text-xs text-emerald-700 font-medium mt-1">Total terkirim sukses</div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Form (8 Cols) & Preview (4 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start mb-8">
        
        <!-- LEFT: Form Pengiriman Broadcast -->
        <div class="lg:col-span-8">
            <form id="fcmBroadcastForm" method="POST" action="" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="send_broadcast">

                <!-- Form Header -->
                <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-white rounded-t-2xl">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Buat Notifikasi Broadcast</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Tentukan sasaran penerima dan formulasi pesan push notification.</p>
                    </div>
                    <button type="button" onclick="resetBroadcastForm()" class="text-xs text-slate-400 hover:text-slate-600 inline-flex items-center gap-1 font-medium transition-colors cursor-pointer" title="Kosongkan formulir">
                        <i class="fa-solid fa-arrow-rotate-left text-[11px]"></i>
                        <span>Reset</span>
                    </button>
                </div>

                <div class="p-5 md:p-6 space-y-6">
                <!-- 1. TARGET PENERIMA -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Target Penerima
                        </label>
                        <span id="recipientEstimateBadge" class="text-xs font-semibold text-teal-800 bg-teal-50 border border-teal-200/80 px-2.5 py-0.5 rounded-full transition-all">
                            <?php echo $stats['active_fcm_users']; ?> Perangkat Aktif
                        </span>
                    </div>

                    <!-- Segmented Switch Tab -->
                    <div class="p-1 bg-slate-100 rounded-xl grid grid-cols-2 sm:grid-cols-5 gap-1 text-xs">
                        <label class="flex items-center justify-center py-2 px-2 rounded-lg cursor-pointer transition text-center select-none text-slate-600 hover:text-slate-900 font-medium has-[:checked]:bg-white has-[:checked]:text-teal-900 has-[:checked]:font-semibold has-[:checked]:shadow-2xs">
                            <input type="radio" name="target_type" value="all" checked class="sr-only target-radio" onchange="toggleTargetPanel('all')">
                            <span>Semua Pegawai</span>
                        </label>

                        <label class="flex items-center justify-center py-2 px-2 rounded-lg cursor-pointer transition text-center select-none text-slate-600 hover:text-slate-900 font-medium has-[:checked]:bg-white has-[:checked]:text-teal-900 has-[:checked]:font-semibold has-[:checked]:shadow-2xs">
                            <input type="radio" name="target_type" value="unit" class="sr-only target-radio" onchange="toggleTargetPanel('unit')">
                            <span>Per Unit</span>
                        </label>

                        <label class="flex items-center justify-center py-2 px-2 rounded-lg cursor-pointer transition text-center select-none text-slate-600 hover:text-slate-900 font-medium has-[:checked]:bg-white has-[:checked]:text-teal-900 has-[:checked]:font-semibold has-[:checked]:shadow-2xs">
                            <input type="radio" name="target_type" value="position" class="sr-only target-radio" onchange="toggleTargetPanel('position')">
                            <span>Per Jabatan</span>
                        </label>

                        <label class="flex items-center justify-center py-2 px-2 rounded-lg cursor-pointer transition text-center select-none text-slate-600 hover:text-slate-900 font-medium has-[:checked]:bg-white has-[:checked]:text-teal-900 has-[:checked]:font-semibold has-[:checked]:shadow-2xs">
                            <input type="radio" name="target_type" value="specific" class="sr-only target-radio" onchange="toggleTargetPanel('specific')">
                            <span>Pilih Karyawan</span>
                        </label>

                        <label class="flex items-center justify-center py-2 px-2 rounded-lg cursor-pointer transition text-center select-none text-slate-600 hover:text-slate-900 font-medium has-[:checked]:bg-white has-[:checked]:text-teal-900 has-[:checked]:font-semibold has-[:checked]:shadow-2xs col-span-2 sm:col-span-1">
                            <input type="radio" name="target_type" value="topic" class="sr-only target-radio" onchange="toggleTargetPanel('topic')">
                            <span>Topik Global</span>
                        </label>
                    </div>

                    <!-- SUB-PANEL: SEMUA PEGAWAI -->
                    <div id="panel-all" class="target-subpanel p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg text-xs text-slate-600 flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center shrink-0 border border-teal-200/60">
                            <i class="fa-solid fa-users text-xs"></i>
                        </div>
                        <div>
                            Notifikasi akan dikirim ke seluruh <strong><?php echo $stats['active_fcm_users']; ?> pegawai aktif</strong> yang terdaftar di Aplikasi YAC.
                        </div>
                    </div>

                    <!-- SUB-PANEL: PER UNIT -->
                    <div id="panel-unit" class="target-subpanel hidden space-y-2 p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="text-xs font-semibold text-slate-700">Pilih Unit Lembaga:</span>
                            <div class="flex gap-2">
                                <button type="button" onclick="checkAllInGroup('unit_ids[]', true)" class="text-xs text-teal-700 hover:underline font-medium">Pilih Semua</button>
                                <span class="text-slate-300">|</span>
                                <button type="button" onclick="checkAllInGroup('unit_ids[]', false)" class="text-xs text-slate-500 hover:underline">Reset</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1 max-h-48 overflow-y-auto pr-1">
                            <?php foreach ($units as $u): ?>
                                <label class="flex items-center gap-2 p-2 bg-white rounded-lg border border-slate-200 hover:border-slate-300 cursor-pointer text-xs transition-colors">
                                    <input type="checkbox" name="unit_ids[]" value="<?php echo $u['id']; ?>" data-fcm="<?php echo $u['fcm_count']; ?>" onchange="calculateRecipientEstimate()" class="rounded text-teal-600 focus:ring-teal-500">
                                    <span class="truncate text-slate-700 font-medium"><?php echo htmlspecialchars($u['name']); ?></span>
                                    <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                        <?php echo $u['fcm_count']; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SUB-PANEL: PER JABATAN -->
                    <div id="panel-position" class="target-subpanel hidden space-y-2 p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                            <span class="text-xs font-semibold text-slate-700">Pilih Jabatan:</span>
                            <div class="flex gap-2">
                                <button type="button" onclick="checkAllInGroup('position_ids[]', true)" class="text-xs text-teal-700 hover:underline font-medium">Pilih Semua</button>
                                <span class="text-slate-300">|</span>
                                <button type="button" onclick="checkAllInGroup('position_ids[]', false)" class="text-xs text-slate-500 hover:underline">Reset</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 pt-1 max-h-48 overflow-y-auto pr-1">
                            <?php foreach ($positions as $p): ?>
                                <label class="flex items-center gap-2 p-2 bg-white rounded-lg border border-slate-200 hover:border-slate-300 cursor-pointer text-xs transition-colors">
                                    <input type="checkbox" name="position_ids[]" value="<?php echo $p['id']; ?>" data-fcm="<?php echo $p['fcm_count']; ?>" onchange="calculateRecipientEstimate()" class="rounded text-teal-600 focus:ring-teal-500">
                                    <span class="truncate text-slate-700 font-medium"><?php echo htmlspecialchars($p['name']); ?></span>
                                    <span class="ml-auto text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                        <?php echo $p['fcm_count']; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- SUB-PANEL: PILIH KARYAWAN (TRIGGER MODAL) -->
                    <div id="panel-specific" class="target-subpanel hidden p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg space-y-2">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-semibold text-slate-800 block">Karyawan Spesifik</span>
                                <p id="employeeSelectedSummary" class="text-xs text-slate-500 mt-0.5">
                                    Belum ada karyawan dipilih. Klik tombol di samping untuk memilih.
                                </p>
                            </div>
                            <button type="button" onclick="openEmployeeModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-teal-600 bg-white hover:bg-teal-50 text-teal-800 text-xs font-semibold transition-colors shadow-2xs cursor-pointer">
                                <i class="fa-solid fa-users text-teal-600"></i>
                                <span>Pilih Karyawan</span>
                            </button>
                        </div>
                    </div>

                    <!-- SUB-PANEL: TOPIK FCM -->
                    <div id="panel-topic" class="target-subpanel hidden space-y-2 p-3.5 bg-slate-50 border border-slate-200/80 rounded-lg">
                        <span class="text-xs font-semibold text-slate-700 block">Pilih Topik FCM:</span>
                        <div class="space-y-1.5">
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                <input type="radio" name="topic_name" value="all_users" checked onchange="calculateRecipientEstimate()" class="text-teal-600 focus:ring-teal-500">
                                <span><code class="font-bold text-slate-800">all_users</code> - Seluruh pengguna aplikasi</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                <input type="radio" name="topic_name" value="app_updates" onchange="calculateRecipientEstimate()" class="text-teal-600 focus:ring-teal-500">
                                <span><code class="font-bold text-slate-800">app_updates</code> - Pembaruan aplikasi APK</span>
                            </label>
                            <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                <input type="radio" name="topic_name" value="maintenance" onchange="calculateRecipientEstimate()" class="text-teal-600 focus:ring-teal-500">
                                <span><code class="font-bold text-slate-800">maintenance</code> - Peringatan maintenance server</span>
                            </label>
                        </div>
                    </div>
                </div>

                    <!-- 2. KONTEN NOTIFIKASI -->
                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Isi Pesan Notifikasi
                        </label>

                        <!-- Judul Notifikasi -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="notifTitle" class="text-xs font-semibold text-slate-700">
                                    Judul Notifikasi <span class="text-rose-500">*</span>
                                </label>
                                <span id="titleCounter" class="text-[11px] text-slate-400">0/100</span>
                            </div>
                            <input type="text" id="notifTitle" name="title" maxlength="100" required placeholder="Contoh: Pengumuman Kegiatan Yayasan" oninput="updateLivePreview()" class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 text-slate-800 placeholder-slate-400 bg-white">
                        </div>

                        <!-- Isi Pesan -->
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="notifBody" class="text-xs font-semibold text-slate-700">
                                    Pesan Notifikasi <span class="text-rose-500">*</span>
                                </label>
                                <span id="bodyCounter" class="text-[11px] text-slate-400">0/500</span>
                            </div>
                            <textarea id="notifBody" name="body" rows="4" maxlength="500" required placeholder="Tuliskan isi pesan notifikasi..." oninput="updateLivePreview()" class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 text-slate-800 placeholder-slate-400 bg-white leading-relaxed"></textarea>
                        </div>

                        <!-- Navigasi Layar & Checkbox Inbox -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                            <!-- Premium Custom Select: Layar Tujuan -->
                            <div class="relative z-30">
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Layar Tujuan Saat Ditap</span>
                                    <span class="text-[10px] text-teal-700 font-medium bg-teal-50 px-2 py-0.5 rounded-full border border-teal-100">Deep-link</span>
                                </label>

                                <!-- Hidden native select for form serialization & fallback -->
                                <select id="notifScreen" name="screen" onchange="updateLivePreview()" class="sr-only">
                                    <option value="general" selected>Beranda (Umum)</option>
                                    <option value="attendance">Layar Presensi Pegawai</option>
                                    <option value="tahfidz">Presensi &amp; Setoran Tahfidz</option>
                                    <option value="teaching_schedule">Jadwal Mengajar &amp; Jurnal</option>
                                    <option value="announcement">Tab Pengumuman</option>
                                    <option value="news">Tab Berita</option>
                                </select>

                                <!-- Premium Trigger Button -->
                                <button type="button" 
                                        id="customScreenSelectTrigger" 
                                        onclick="toggleCustomScreenSelect()" 
                                        class="w-full text-left p-2.5 bg-white rounded-xl border border-slate-200/90 hover:border-teal-400 focus:outline-none focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 shadow-2xs transition-all flex items-center justify-between cursor-pointer group">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div id="triggerScreenIconBox" class="w-7 h-7 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center text-xs shrink-0 border border-teal-100 group-hover:bg-teal-600 group-hover:text-white transition-colors">
                                            <i id="triggerScreenIcon" class="fa-solid fa-house"></i>
                                        </div>
                                        <div class="truncate">
                                            <span id="triggerScreenTitle" class="text-xs font-semibold text-slate-800 block truncate group-hover:text-teal-900 transition-colors">Beranda (Umum)</span>
                                            <span id="triggerScreenSubtitle" class="text-[10px] text-slate-400 block truncate">Layar utama aplikasi mobile</span>
                                        </div>
                                    </div>
                                    <i id="triggerScreenChevron" class="fa-solid fa-chevron-down text-slate-400 text-[11px] ml-2 shrink-0 transition-transform duration-200 group-hover:text-slate-600"></i>
                                </button>

                                <!-- Premium Dropdown Options Menu Overlay -->
                                <div id="customScreenSelectMenu" 
                                     class="hidden absolute left-0 right-0 top-full mt-1.5 bg-white rounded-2xl border border-slate-200 shadow-2xl z-50 p-1.5 space-y-1 overflow-y-auto transition-all"
                                     style="max-height: 280px; overflow-y: auto; -webkit-overflow-scrolling: touch;">
                                    
                                    <!-- Option: general -->
                                    <div onclick="selectScreenOption('general')" data-screen="general" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer select-none group/item bg-teal-50/60 border border-teal-200/60 transition-colors">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-house"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Beranda (Umum)</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Halaman beranda aplikasi mobile pegawai</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2"></i>
                                    </div>

                                    <!-- Option: attendance -->
                                    <div onclick="selectScreenOption('attendance')" data-screen="attendance" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-slate-50 border border-transparent transition-colors select-none group/item">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-clipboard-user"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Layar Presensi Pegawai</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Absensi harian datang &amp; pulang</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2 hidden"></i>
                                    </div>

                                    <!-- Option: tahfidz -->
                                    <div onclick="selectScreenOption('tahfidz')" data-screen="tahfidz" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-slate-50 border border-transparent transition-colors select-none group/item">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-book-quran"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Presensi &amp; Setoran Tahfidz</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Halaqah &amp; mutaba'ah hafalan santri</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2 hidden"></i>
                                    </div>

                                    <!-- Option: teaching_schedule -->
                                    <div onclick="selectScreenOption('teaching_schedule')" data-screen="teaching_schedule" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-slate-50 border border-transparent transition-colors select-none group/item">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-chalkboard-user"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Jadwal Mengajar &amp; Jurnal</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Absensi kehadiran siswa di kelas &amp; jurnal KBM</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2 hidden"></i>
                                    </div>

                                    <!-- Option: announcement -->
                                    <div onclick="selectScreenOption('announcement')" data-screen="announcement" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-slate-50 border border-transparent transition-colors select-none group/item">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-bullhorn"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Tab Pengumuman</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Pemberitahuan resmi Yayasan Assunnah</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2 hidden"></i>
                                    </div>

                                    <!-- Option: news -->
                                    <div onclick="selectScreenOption('news')" data-screen="news" class="screen-option-item flex items-center justify-between p-2 rounded-xl cursor-pointer hover:bg-slate-50 border border-transparent transition-colors select-none group/item">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 group-hover/item:bg-teal-100 group-hover/item:text-teal-800 flex items-center justify-center text-xs shrink-0 transition-colors">
                                                <i class="fa-solid fa-newspaper"></i>
                                            </div>
                                            <div class="truncate">
                                                <span class="text-xs font-semibold text-slate-800 block truncate">Tab Berita</span>
                                                <span class="text-[10px] text-slate-400 block truncate">Informasi artikel &amp; liputan kegiatan</span>
                                            </div>
                                        </div>
                                        <i class="option-check fa-solid fa-check text-teal-600 text-xs shrink-0 ml-2 hidden"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 mb-1.5 flex items-center justify-between">
                                    <span>Penyimpanan Pesan</span>
                                    <span class="text-[10px] text-slate-400 font-medium">Inbox Riwayat</span>
                                </label>
                                <label class="flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200/90 bg-slate-50/70 hover:bg-slate-100/70 hover:border-slate-300 cursor-pointer select-none transition-all shadow-2xs">
                                    <input type="checkbox" name="save_to_inbox" value="1" checked class="mt-0.5 rounded text-teal-600 focus:ring-teal-500 h-4 w-4">
                                    <span class="text-xs text-slate-700">
                                        <strong class="font-semibold block text-slate-800">Simpan ke Tab Notifikasi</strong>
                                        <span class="text-[10px] text-slate-500 leading-tight block mt-0.5">Tersimpan di riwayat aplikasi mobile pegawai.</span>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Footer & Action Buttons -->
                <div class="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between rounded-b-2xl">
                    <button type="button" onclick="resetBroadcastForm()" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-200/60 rounded-lg transition-colors cursor-pointer">
                        Reset Form
                    </button>
                    <button type="button" onclick="openBroadcastModal()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold shadow-xs transition-colors cursor-pointer">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Kirim Notifikasi</span>
                    </button>
                </div>

                <!-- MODAL PILIH KARYAWAN (Didalam Form agar input employee_ids[] terkirim otomatis) -->
                <div id="employeeModal" class="fixed inset-0 z-[90] hidden bg-slate-900/40 backdrop-blur-md flex items-center justify-center p-3 sm:p-5 transition-all duration-200" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);" onclick="if(event.target === this) closeEmployeeModal()">
                    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-hidden transform transition-all">
                        <!-- Header Modal -->
                        <div class="p-4 sm:p-5 border-b border-slate-100 flex items-center justify-between bg-white">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center text-sm border border-teal-100">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Pilih Karyawan Penerima Notifikasi</h3>
                                    <p class="text-xs text-slate-500">Centang karyawan target penerima push notification.</p>
                                </div>
                            </div>
                            <button type="button" onclick="closeEmployeeModal()" class="text-slate-400 hover:text-slate-600 p-2 rounded-xl hover:bg-slate-100 transition-colors cursor-pointer">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <!-- Search & Quick Selection Toolbar -->
                        <div class="p-3.5 border-b border-slate-100 bg-slate-50/70 space-y-2.5">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                                <input type="text" id="modalEmployeeSearch" placeholder="Cari nama, NIK, unit, atau jabatan..." onkeyup="filterEmployeeModalList()" class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 bg-white placeholder-slate-400">
                            </div>

                            <div class="flex items-center justify-between text-xs flex-wrap gap-2">
                                <div class="flex items-center gap-1.5">
                                    <button type="button" onclick="toggleAllEmployeesInModal(true, false)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200/80 transition-colors cursor-pointer">
                                        <i class="fa-solid fa-check-double text-[10px] mr-1"></i>Pilih Semua (FCM Aktif)
                                    </button>
                                    <button type="button" onclick="toggleAllEmployeesInModal(true, true)" class="px-2.5 py-1 rounded-lg text-xs font-semibold text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 transition-colors cursor-pointer">
                                        Semua Karyawan
                                    </button>
                                    <button type="button" onclick="toggleAllEmployeesInModal(false)" class="px-2 py-1 text-xs font-medium text-slate-500 hover:text-slate-700 transition-colors cursor-pointer">
                                        Reset
                                    </button>
                                </div>
                                <span id="modalSelectedCounter" class="font-bold text-teal-800 bg-teal-100/70 px-2.5 py-1 rounded-full text-[11px]">
                                    0 dipilih
                                </span>
                            </div>
                        </div>

                        <!-- Employee List (Scrollable) -->
                        <div id="modalEmployeeListContainer" class="p-3 sm:p-4 overflow-y-auto space-y-2 max-h-[55vh] flex-1 min-h-0" style="-webkit-overflow-scrolling: touch;">
                            <?php if (empty($employees)): ?>
                                <div class="py-8 text-center text-xs text-slate-400">
                                    Tidak ada data karyawan aktif ditemukan.
                                </div>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <label class="emp-row flex items-center justify-between p-2.5 sm:p-3 rounded-xl border border-slate-200/80 hover:border-teal-400 hover:bg-teal-50/20 cursor-pointer text-xs transition-all select-none"
                                           data-search="<?php echo strtolower(htmlspecialchars(($emp['full_name'] ?? '') . ' ' . ($emp['nik'] ?? '') . ' ' . ($emp['unit_name'] ?? '') . ' ' . ($emp['position_name'] ?? ''))); ?>"
                                           data-has-fcm="<?php echo $emp['has_fcm'] ? '1' : '0'; ?>">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>"
                                                   data-has-fcm="<?php echo $emp['has_fcm'] ? '1' : '0'; ?>"
                                                   data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                   onchange="updateModalSelectedCounter()"
                                                   class="emp-checkbox rounded-md text-teal-600 focus:ring-teal-500 h-4 w-4">
                                            <div class="truncate">
                                                <span class="font-semibold text-slate-800 block truncate"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                                <span class="text-[11px] text-slate-400 block truncate mt-0.5">
                                                    <?php if (!empty($emp['nik'])): ?>
                                                        <span class="font-mono text-slate-500"><?php echo htmlspecialchars($emp['nik']); ?></span> &bull; 
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?> &bull; <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="shrink-0 ml-2">
                                            <?php if ($emp['has_fcm']): ?>
                                                <span class="inline-flex items-center text-[10px] font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/60">
                                                    FCM Aktif
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center text-[10px] text-slate-400 bg-slate-100 px-2.5 py-0.5 rounded-full">
                                                    Belum Login
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Footer Modal -->
                        <div class="p-3.5 border-t border-slate-100 bg-slate-50/80 flex items-center justify-end gap-2">
                            <button type="button" onclick="closeEmployeeModal()" class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-200/60 rounded-xl transition-colors cursor-pointer">
                                Tutup
                            </button>
                            <button type="button" onclick="applyEmployeeSelection()" class="px-4 py-2 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-xl shadow-xs transition-colors cursor-pointer">
                                Terapkan Pilihan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- RIGHT: Pratinjau & Template Cepat (Sticky Sidebar) -->
        <div class="lg:col-span-4 space-y-4 lg:sticky lg:top-4">
            
            <!-- Notification Preview Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-4">
                <div class="flex items-center justify-between pb-2.5 mb-3 border-b border-slate-100 text-xs">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-mobile-screen text-teal-600"></i> Pratinjau Push Notif
                    </span>
                    <span class="text-[10px] uppercase font-semibold text-slate-400">Lockscreen</span>
                </div>

                <!-- Push Notification Item -->
                <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <div class="w-4 h-4 rounded bg-teal-600 text-white flex items-center justify-center text-[9px] shadow-2xs">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <span class="font-bold text-[11px] text-slate-800">Aplikasi YAC</span>
                            <span class="text-slate-300 text-[10px]">&bull;</span>
                            <span class="text-slate-400 text-[10px]">Baru saja</span>
                        </div>
                        <span id="previewScreenTag" class="text-[9px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">
                            Beranda
                        </span>
                    </div>

                    <div>
                        <h4 id="previewTitle" class="font-bold text-xs text-slate-900 leading-snug break-words">
                            Judul Notifikasi
                        </h4>
                        <p id="previewBody" class="text-xs text-slate-600 mt-1 leading-relaxed break-words line-clamp-3">
                            Isi pesan notifikasi akan tampil di sini saat diketik...
                        </p>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-2.5 text-center">
                    Simulasi tampilan banner notifikasi di HP pegawai.
                </p>
            </div>

            <!-- Quick Templates Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-4 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 text-xs">
                    <span class="font-bold text-slate-800 flex items-center gap-1.5">
                        <i class="fa-solid fa-bolt text-amber-500"></i> Template Cepat
                    </span>
                    <span class="text-[11px] text-slate-400">1-Klik Isi Form</span>
                </div>

                <div class="space-y-1.5">
                    <!-- Guru Tahfidz -->
                    <button type="button" data-template="tahfidz" onclick="applyTemplate('tahfidz')" class="template-btn group w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/20 transition-all block text-xs cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 group-hover:text-teal-900">Presensi &amp; Setoran Tahfidz</span>
                            <span class="text-[10px] font-medium text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Tahfidz</span>
                        </div>
                        <span class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">Presensi halaqah dan input mutaba'ah setoran santri...</span>
                    </button>

                    <!-- Guru Kelas & Jurnal -->
                    <button type="button" data-template="jurnal_guru" onclick="applyTemplate('jurnal_guru')" class="template-btn group w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/20 transition-all block text-xs cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 group-hover:text-teal-900">Absensi Kelas &amp; Jurnal Guru</span>
                            <span class="text-[10px] font-medium text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">KBM</span>
                        </div>
                        <span class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">Absensi kehadiran siswa di kelas dan input jurnal KBM...</span>
                    </button>

                    <!-- Presensi Pegawai Umum -->
                    <button type="button" data-template="presensi" onclick="applyTemplate('presensi')" class="template-btn group w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/20 transition-all block text-xs cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 group-hover:text-teal-900">Pengingat Presensi Pegawai</span>
                            <span class="text-[10px] font-medium text-teal-700 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-200">Presensi</span>
                        </div>
                        <span class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">Jangan lupa melakukan presensi kehadiran hari ini...</span>
                    </button>

                    <!-- Pengumuman Yayasan -->
                    <button type="button" data-template="pengumuman" onclick="applyTemplate('pengumuman')" class="template-btn group w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/20 transition-all block text-xs cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 group-hover:text-teal-900">Pengumuman Yayasan</span>
                            <span class="text-[10px] font-medium text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-200">Info</span>
                        </div>
                        <span class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">Terdapat informasi penting terbaru dari pimpinan...</span>
                    </button>

                    <!-- Pembaruan Aplikasi -->
                    <button type="button" data-template="update" onclick="applyTemplate('update')" class="template-btn group w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-teal-50/20 transition-all block text-xs cursor-pointer">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold text-slate-800 group-hover:text-teal-900">Pembaruan Aplikasi</span>
                            <span class="text-[10px] font-medium text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">Update</span>
                        </div>
                        <span class="text-[11px] text-slate-500 line-clamp-1 mt-0.5">Tersedia pembaruan versi terbaru Aplikasi YAC...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Pengiriman Notifikasi (Broadcast Logs) -->
    <div id="riwayat-broadcast" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-white">
            <div>
                <h3 class="text-sm font-bold text-slate-900">
                    Riwayat Pengiriman Notifikasi
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Daftar broadcast notifikasi yang telah dikirim oleh administrator.
                </p>
            </div>
            <span class="text-xs font-semibold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-full w-fit">
                <?php echo count($recentLogs); ?> pengiriman terakhir
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100">
                    <tr>
                        <th class="px-4 py-3">Waktu Kirim</th>
                        <th class="px-4 py-3">Pengirim</th>
                        <th class="px-4 py-3">Target Penerima</th>
                        <th class="px-4 py-3">Judul &amp; Pesan</th>
                        <th class="px-4 py-3">Layar</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">
                                Belum ada riwayat pengiriman notifikasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                    <span class="font-medium text-slate-800 block">
                                        <?php echo date('d M Y', strtotime($log['created_at'])); ?>
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        <?php echo date('H:i', strtotime($log['created_at'])); ?> WIB
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="font-medium text-slate-800">
                                        <?php echo htmlspecialchars($log['sender_name'] ?? 'Admin'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 max-w-xs">
                                    <span class="inline-block text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 mb-0.5">
                                        <?php echo htmlspecialchars($log['target_type']); ?>
                                    </span>
                                    <p class="text-xs text-slate-700 line-clamp-1 font-medium" title="<?php echo htmlspecialchars($log['target_summary'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($log['target_summary'] ?? '-'); ?>
                                    </p>
                                </td>
                                <td class="px-4 py-3 max-w-sm">
                                    <p class="font-semibold text-slate-900 truncate">
                                        <?php echo htmlspecialchars($log['title']); ?>
                                    </p>
                                    <p class="text-slate-500 truncate text-[11px] mt-0.5">
                                        <?php echo htmlspecialchars($log['body']); ?>
                                    </p>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md text-[11px] font-mono bg-slate-100 text-slate-700">
                                        <?php echo htmlspecialchars($log['screen'] ?? 'general'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php if ($log['failure_count'] == 0 && $log['success_count'] > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i> <?php echo $log['success_count']; ?> Terkirim
                                        </span>
                                    <?php elseif ($log['success_count'] > 0 && $log['failure_count'] > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            <?php echo $log['success_count']; ?> / <?php echo $log['total_recipients']; ?> Sukses
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            <i class="fa-solid fa-circle-xmark text-[10px]"></i> Gagal
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button" 
                                                onclick="reuseTemplate(<?php echo htmlspecialchars(json_encode($log['title'])); ?>, <?php echo htmlspecialchars(json_encode($log['body'])); ?>, '<?php echo htmlspecialchars($log['screen'] ?? 'general'); ?>')"
                                                class="inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:text-teal-900 bg-teal-50 hover:bg-teal-100/80 px-2.5 py-1.5 rounded-lg border border-teal-200/60 transition-colors cursor-pointer"
                                                title="Gunakan ulang template ini">
                                            <i class="fa-solid fa-copy text-[11px]"></i>
                                            <span class="hidden sm:inline">Pakai</span>
                                        </button>
                                        <button type="button" 
                                                onclick="openDeleteLogModal(<?php echo $log['id']; ?>, <?php echo htmlspecialchars(json_encode($log['title'])); ?>)"
                                                class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-50 hover:bg-rose-100/80 px-2.5 py-1.5 rounded-lg border border-rose-200/60 transition-colors cursor-pointer"
                                                title="Hapus riwayat broadcast ini">
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                            <span class="hidden sm:inline">Hapus</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Kirim Notifikasi (Unik, tidak tabrakan dengan modal global footer) -->
<div id="broadcastConfirmModal" class="fixed inset-0 z-[90] hidden bg-slate-900/40 backdrop-blur-md flex items-center justify-center p-4 transition-all duration-200" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);" onclick="if(event.target === this) closeBroadcastModal()">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-100 p-5 space-y-4">
        <div>
            <h3 class="text-base font-bold text-slate-900">Konfirmasi Kirim Notifikasi</h3>
            <p class="text-xs text-slate-500 mt-0.5">
                Pastikan target penerima dan isi pesan sudah benar sebelum dikirimkan.
            </p>
        </div>

        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 text-xs space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Target Penerima:</span>
                <span id="modalTargetSummary" class="font-semibold text-slate-800">Semua Pegawai</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Estimasi Perangkat:</span>
                <span id="modalRecipientCount" class="font-bold text-teal-800">268 Perangkat</span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Layar Tujuan:</span>
                <span id="modalScreenSummary" class="font-medium text-slate-800">Beranda</span>
            </div>
            <div class="pt-2 border-t border-slate-200/80">
                <span class="text-slate-500 block mb-0.5">Judul Pesan:</span>
                <p id="modalTitleSummary" class="font-semibold text-slate-900 break-words">-</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeBroadcastModal()" class="px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
                Batal
            </button>
            <button type="button" id="confirmSubmitBtn" onclick="submitBroadcastForm()" class="px-4 py-2 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-xl shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span>Kirim Sekarang</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus Riwayat Broadcast -->
<div id="deleteLogModal" class="fixed inset-0 z-[90] hidden bg-slate-900/40 backdrop-blur-md flex items-center justify-center p-4 transition-all duration-200" style="backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);" onclick="if(event.target === this) closeDeleteLogModal()">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl border border-slate-100 p-5 space-y-4">
        <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg mx-auto border border-rose-100">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <div class="text-center">
            <h3 class="text-sm font-bold text-slate-900">Hapus Riwayat Broadcast?</h3>
            <p id="deleteLogTitleText" class="text-xs text-slate-500 mt-1 line-clamp-2">Data riwayat pengiriman notifikasi ini akan dihapus permanen.</p>
        </div>
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="delete_log">
            <input type="hidden" id="deleteLogIdInput" name="log_id" value="">
            <div class="grid grid-cols-2 gap-2 pt-2">
                <button type="button" onclick="closeDeleteLogModal()" class="w-full py-2 px-3 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" class="w-full py-2 px-3 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs transition-colors cursor-pointer">
                    Ya, Hapus
                </button>
            </div>
        </form>
    </div>
</div>

<script nonce="<?php echo csp_nonce(); ?>">
const currentActiveFCM = <?php echo (int)$stats['active_fcm_users']; ?>;

function toggleTargetPanel(type) {
    document.querySelectorAll('.target-subpanel').forEach(el => el.classList.add('hidden'));
    const targetPanel = document.getElementById('panel-' + type);
    if (targetPanel) {
        targetPanel.classList.remove('hidden');
    }
    calculateRecipientEstimate();
    if (type === 'specific') {
        const checkedCount = document.querySelectorAll('input[name="employee_ids[]"]:checked').length;
        if (checkedCount === 0) {
            openEmployeeModal();
        }
    }
}

function checkAllInGroup(name, state) {
    const checkboxes = document.querySelectorAll(`input[name="${name}"]`);
    checkboxes.forEach(cb => cb.checked = state);
    calculateRecipientEstimate();
}

function calculateRecipientEstimate() {
    const targetTypeRadio = document.querySelector('input[name="target_type"]:checked');
    const targetType = targetTypeRadio ? targetTypeRadio.value : 'all';
    const badge = document.getElementById('recipientEstimateBadge');
    if (!badge) return;

    if (targetType === 'all') {
        badge.textContent = `${currentActiveFCM} Perangkat Aktif`;
    } else if (targetType === 'unit') {
        const checked = document.querySelectorAll('input[name="unit_ids[]"]:checked');
        let total = 0;
        checked.forEach(cb => {
            total += parseInt(cb.getAttribute('data-fcm') || 0);
        });
        badge.textContent = `${total} Perangkat (${checked.length} Unit)`;
    } else if (targetType === 'position') {
        const checked = document.querySelectorAll('input[name="position_ids[]"]:checked');
        let total = 0;
        checked.forEach(cb => {
            total += parseInt(cb.getAttribute('data-fcm') || 0);
        });
        badge.textContent = `${total} Perangkat (${checked.length} Jabatan)`;
    } else if (targetType === 'specific') {
        const checked = document.querySelectorAll('input[name="employee_ids[]"]:checked');
        let fcmCount = 0;
        checked.forEach(cb => {
            if (cb.getAttribute('data-has-fcm') === '1') fcmCount++;
        });
        badge.textContent = `${fcmCount} Perangkat (${checked.length} Karyawan)`;
        
        const summaryEl = document.getElementById('employeeSelectedSummary');
        if (summaryEl) {
            if (checked.length === 0) {
                summaryEl.innerHTML = `<span class="text-slate-500">Belum ada karyawan yang dipilih. Klik tombol di atas untuk memilih karyawan.</span>`;
            } else {
                summaryEl.innerHTML = `<span class="font-semibold text-teal-800">${checked.length} karyawan dipilih</span> <span class="text-slate-500">(${fcmCount} memiliki token FCM aktif)</span>`;
            }
        }
    } else if (targetType === 'topic') {
        const topicRadio = document.querySelector('input[name="topic_name"]:checked');
        const topic = topicRadio ? topicRadio.value : 'all_users';
        badge.textContent = `Topik: ${topic}`;
    }
}

// Modal Karyawan Functions
function openEmployeeModal() {
    const modal = document.getElementById('employeeModal');
    if (modal) {
        modal.classList.remove('hidden');
        updateModalSelectedCounter();
        const searchInput = document.getElementById('modalEmployeeSearch');
        if (searchInput) {
            searchInput.value = '';
            filterEmployeeModalList();
            setTimeout(() => searchInput.focus(), 50);
        }
    }
}

function closeEmployeeModal() {
    const modal = document.getElementById('employeeModal');
    if (modal) {
        modal.classList.add('hidden');
    }
    calculateRecipientEstimate();
}

function filterEmployeeModalList() {
    const searchInput = document.getElementById('modalEmployeeSearch');
    const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
    const rows = document.querySelectorAll('.emp-row');
    rows.forEach(row => {
        const text = row.getAttribute('data-search') || '';
        if (!q || text.includes(q)) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

function toggleAllEmployeesInModal(state, includeNoFcm = true) {
    const checkboxes = document.querySelectorAll('.emp-checkbox');
    checkboxes.forEach(cb => {
        const parentRow = cb.closest('.emp-row');
        // Only modify if row is currently visible
        if (parentRow && parentRow.style.display !== 'none') {
            if (!state) {
                cb.checked = false;
            } else {
                const hasFcm = cb.getAttribute('data-has-fcm') === '1';
                if (includeNoFcm || hasFcm) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            }
        }
    });
    updateModalSelectedCounter();
}

function updateModalSelectedCounter() {
    const checked = document.querySelectorAll('input[name="employee_ids[]"]:checked');
    let fcmCount = 0;
    checked.forEach(cb => {
        if (cb.getAttribute('data-has-fcm') === '1') fcmCount++;
    });
    const counterEl = document.getElementById('modalSelectedCounter');
    if (counterEl) {
        counterEl.textContent = `${checked.length} dipilih (${fcmCount} FCM aktif)`;
    }
}

function applyEmployeeSelection() {
    closeEmployeeModal();
    calculateRecipientEstimate();
    if (typeof queueSaveDraft === 'function') queueSaveDraft();
}

// Live Preview Updates
function updateLivePreview() {
    const titleInput = document.getElementById('notifTitle');
    const bodyInput = document.getElementById('notifBody');
    const screenInput = document.getElementById('notifScreen');

    const titleVal = titleInput ? titleInput.value.trim() : '';
    const bodyVal = bodyInput ? bodyInput.value.trim() : '';
    const screenVal = screenInput ? screenInput.value : 'general';

    const pTitle = document.getElementById('previewTitle');
    const pBody = document.getElementById('previewBody');
    const pTag = document.getElementById('previewScreenTag');

    if (pTitle) pTitle.textContent = titleVal || "Judul Notifikasi";
    if (pBody) pBody.textContent = bodyVal || "Isi pesan notifikasi akan tampil di sini saat diketik...";

    const screenLabels = {
        'general': 'Beranda',
        'attendance': 'Presensi',
        'tahfidz': 'Tahfidz',
        'teaching_schedule': 'Jadwal & Jurnal',
        'announcement': 'Pengumuman',
        'news': 'Berita'
    };
    if (pTag) pTag.textContent = screenLabels[screenVal] || 'Umum';

    const tCounter = document.getElementById('titleCounter');
    const bCounter = document.getElementById('bodyCounter');
    if (tCounter && titleInput) tCounter.textContent = `${titleInput.value.length}/100`;
    if (bCounter && bodyInput) bCounter.textContent = `${bodyInput.value.length}/500`;
    if (typeof queueSaveDraft === 'function') queueSaveDraft();
}

// Screen Metadata for Premium Select
const screenData = {
    'general': {
        title: 'Beranda (Umum)',
        subtitle: 'Halaman beranda aplikasi mobile pegawai',
        icon: 'fa-solid fa-house'
    },
    'attendance': {
        title: 'Layar Presensi Pegawai',
        subtitle: 'Absensi harian datang & pulang',
        icon: 'fa-solid fa-clipboard-user'
    },
    'tahfidz': {
        title: 'Presensi & Setoran Tahfidz',
        subtitle: 'Halaqah & mutaba\'ah hafalan santri',
        icon: 'fa-solid fa-book-quran'
    },
    'teaching_schedule': {
        title: 'Jadwal Mengajar & Jurnal',
        subtitle: 'Absensi kehadiran siswa di kelas & jurnal KBM',
        icon: 'fa-solid fa-chalkboard-user'
    },
    'announcement': {
        title: 'Tab Pengumuman',
        subtitle: 'Pemberitahuan resmi Yayasan Assunnah',
        icon: 'fa-solid fa-bullhorn'
    },
    'news': {
        title: 'Tab Berita',
        subtitle: 'Informasi artikel & liputan kegiatan',
        icon: 'fa-solid fa-newspaper'
    }
};

function toggleCustomScreenSelect(forceState) {
    const menu = document.getElementById('customScreenSelectMenu');
    const chevron = document.getElementById('triggerScreenChevron');
    const trigger = document.getElementById('customScreenSelectTrigger');
    if (!menu) return;

    const isHidden = menu.classList.contains('hidden');
    const shouldOpen = (forceState !== undefined) ? forceState : isHidden;

    if (shouldOpen) {
        menu.classList.remove('hidden');
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        if (trigger) {
            trigger.classList.add('border-teal-500', 'ring-2', 'ring-teal-500/20');
        }
    } else {
        menu.classList.add('hidden');
        if (chevron) chevron.style.transform = 'rotate(0deg)';
        if (trigger) {
            trigger.classList.remove('border-teal-500', 'ring-2', 'ring-teal-500/20');
        }
    }
}

function selectScreenOption(val) {
    const screenInput = document.getElementById('notifScreen');
    if (screenInput) {
        screenInput.value = val;
    }
    updatePremiumSelectUI(val);
    updateLivePreview();
    toggleCustomScreenSelect(false);
}

function updatePremiumSelectUI(val) {
    const d = screenData[val] || screenData['general'];
    const titleEl = document.getElementById('triggerScreenTitle');
    const subEl = document.getElementById('triggerScreenSubtitle');
    const iconEl = document.getElementById('triggerScreenIcon');

    if (titleEl) titleEl.textContent = d.title;
    if (subEl) subEl.textContent = d.subtitle;
    if (iconEl) iconEl.className = d.icon;

    document.querySelectorAll('.screen-option-item').forEach(item => {
        const itemVal = item.getAttribute('data-screen');
        const check = item.querySelector('.option-check');
        if (itemVal === val) {
            item.classList.add('bg-teal-50/60', 'border-teal-200/60');
            item.classList.remove('hover:bg-slate-50', 'border-transparent');
            if (check) check.classList.remove('hidden');
        } else {
            item.classList.remove('bg-teal-50/60', 'border-teal-200/60');
            item.classList.add('hover:bg-slate-50', 'border-transparent');
            if (check) check.classList.add('hidden');
        }
    });
}

// Template Cepat
const broadcastTemplates = {
    'tahfidz': {
        title: 'Pengingat Presensi & Setoran Tahfidz',
        body: 'Diberitahukan kepada Asatidzah pengampu tahfidz agar melakukan presensi halaqah dan menginput mutaba\'ah setoran hafalan santri/siswa hari ini.',
        screen: 'tahfidz'
    },
    'jurnal_guru': {
        title: 'Pengingat Absensi Kelas & Jurnal Mengajar',
        body: 'Diberitahukan kepada Bapak/Ibu Guru agar melakukan absensi kehadiran siswa di kelas dan mengisi jurnal kegiatan pembelajaran (KBM) hari ini.',
        screen: 'teaching_schedule'
    },
    'presensi': {
        title: 'Pengingat Presensi Pegawai',
        body: 'Jangan lupa untuk melakukan presensi kehadiran hari ini melalui Aplikasi YAC.',
        screen: 'attendance'
    },
    'pengumuman': {
        title: 'Pengumuman Resmi Yayasan Assunnah',
        body: 'Terdapat informasi penting terbaru dari pimpinan yayasan. Silakan periksa menu pengumuman pada aplikasi Anda.',
        screen: 'announcement'
    },
    'update': {
        title: 'Pembaruan Aplikasi Tersedia',
        body: 'Tersedia pembaruan versi terbaru Aplikasi YAC. Silakan perbarui aplikasi Anda untuk mendapatkan performa dan fitur terbaru.',
        screen: 'general'
    }
};

function applyTemplate(type) {
    const t = broadcastTemplates[type];
    if (!t) return;

    const titleInput = document.getElementById('notifTitle');
    const bodyInput = document.getElementById('notifBody');
    const screenInput = document.getElementById('notifScreen');

    if (titleInput) titleInput.value = t.title;
    if (bodyInput) bodyInput.value = t.body;
    if (screenInput) {
        screenInput.value = t.screen;
        updatePremiumSelectUI(t.screen);
    }

    updateLivePreview();
}

function reuseTemplate(title, body, screen) {
    const titleInput = document.getElementById('notifTitle');
    const bodyInput = document.getElementById('notifBody');
    const screenInput = document.getElementById('notifScreen');

    const sVal = screen || 'general';
    if (titleInput) titleInput.value = title || '';
    if (bodyInput) bodyInput.value = body || '';
    if (screenInput) {
        screenInput.value = sVal;
        updatePremiumSelectUI(sVal);
    }

    updateLivePreview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetBroadcastForm() {
    clearFormDraft();
    const form = document.getElementById('fcmBroadcastForm');
    if (form) form.reset();
    toggleTargetPanel('all');
    calculateRecipientEstimate();
    updatePremiumSelectUI('general');
    updateLivePreview();
}

// Modal Konfirmasi Pengiriman (Unik)
function openBroadcastModal() {
    const titleInput = document.getElementById('notifTitle');
    const bodyInput = document.getElementById('notifBody');
    const title = titleInput ? titleInput.value.trim() : '';
    const body = bodyInput ? bodyInput.value.trim() : '';

    const targetTypeRadio = document.querySelector('input[name="target_type"]:checked');
    const targetType = targetTypeRadio ? targetTypeRadio.value : 'all';

    if (!title || !body) {
        alert('Mohon isi judul dan pesan notifikasi terlebih dahulu.');
        return;
    }

    if (targetType === 'unit' && document.querySelectorAll('input[name="unit_ids[]"]:checked').length === 0) {
        alert('Pilih minimal satu unit lembaga penerima.');
        return;
    }
    if (targetType === 'position' && document.querySelectorAll('input[name="position_ids[]"]:checked').length === 0) {
        alert('Pilih minimal satu jabatan penerima.');
        return;
    }
    if (targetType === 'specific' && document.querySelectorAll('input[name="employee_ids[]"]:checked').length === 0) {
        alert('Pilih minimal satu karyawan penerima.');
        openEmployeeModal();
        return;
    }

    const badge = document.getElementById('recipientEstimateBadge');
    const tag = document.getElementById('previewScreenTag');
    const estimateText = badge ? badge.textContent : '-';
    const screenText = tag ? tag.textContent : 'Umum';

    const targetTypeNames = {
        'all': 'Semua Pegawai Mobile',
        'unit': 'Per Unit Lembaga Terpilih',
        'position': 'Per Jabatan Terpilih',
        'specific': 'Karyawan Tertentu Terpilih',
        'topic': 'Topik FCM Global'
    };

    document.getElementById('modalTargetSummary').textContent = targetTypeNames[targetType] || targetType;
    document.getElementById('modalRecipientCount').textContent = estimateText;
    document.getElementById('modalScreenSummary').textContent = screenText;
    document.getElementById('modalTitleSummary').textContent = title;

    const modal = document.getElementById('broadcastConfirmModal');
    if (modal) modal.classList.remove('hidden');
}

function closeBroadcastModal() {
    const modal = document.getElementById('broadcastConfirmModal');
    if (modal) modal.classList.add('hidden');
}

function openDeleteLogModal(id, title) {
    const input = document.getElementById('deleteLogIdInput');
    const titleEl = document.getElementById('deleteLogTitleText');
    if (input) input.value = id;
    if (titleEl) titleEl.textContent = 'Menghapus riwayat: "' + (title || 'Broadcast') + '"';
    const modal = document.getElementById('deleteLogModal');
    if (modal) modal.classList.remove('hidden');
}

function closeDeleteLogModal() {
    const modal = document.getElementById('deleteLogModal');
    if (modal) modal.classList.add('hidden');
}

function submitBroadcastForm() {
    clearFormDraft();
    const btn = document.getElementById('confirmSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Mengirim...</span>`;
    }
    document.getElementById('fcmBroadcastForm').submit();
}

// Client-Side Auto-Save Draft (Mencegah kehilangan isian saat sesi habis/terputus)
const DRAFT_KEY = 'fcm_broadcast_draft';
let draftSaveTimer = null;

function queueSaveDraft() {
    clearTimeout(draftSaveTimer);
    draftSaveTimer = setTimeout(saveFormDraft, 250);
}

function saveFormDraft() {
    try {
        const targetTypeRadio = document.querySelector('input[name="target_type"]:checked');
        const targetType = targetTypeRadio ? targetTypeRadio.value : 'all';
        const topicRadio = document.querySelector('input[name="topic_name"]:checked');

        const unitIds = Array.from(document.querySelectorAll('input[name="unit_ids[]"]:checked')).map(cb => cb.value);
        const positionIds = Array.from(document.querySelectorAll('input[name="position_ids[]"]:checked')).map(cb => cb.value);
        const employeeIds = Array.from(document.querySelectorAll('input[name="employee_ids[]"]:checked')).map(cb => cb.value);

        const title = document.getElementById('notifTitle')?.value || '';
        const body = document.getElementById('notifBody')?.value || '';
        const screen = document.getElementById('notifScreen')?.value || 'general';
        const saveInbox = document.querySelector('input[name="save_to_inbox"]')?.checked ?? true;

        // Jangan simpan jika form masih kosong
        if (!title && !body && unitIds.length === 0 && positionIds.length === 0 && employeeIds.length === 0 && targetType === 'all') {
            localStorage.removeItem(DRAFT_KEY);
            return;
        }

        const draft = {
            targetType,
            topic: topicRadio ? topicRadio.value : 'all_users',
            unitIds,
            positionIds,
            employeeIds,
            title,
            body,
            screen,
            saveInbox,
            savedAt: Date.now()
        };
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    } catch (e) {}
}

function restoreFormDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (!raw) return;
        const d = JSON.parse(raw);
        if (!d) return;

        // Pulihkan target penerima
        if (d.targetType) {
            const radio = document.querySelector(`input[name="target_type"][value="${d.targetType}"]`);
            if (radio) {
                radio.checked = true;
                toggleTargetPanel(d.targetType);
            }
        }

        // Pulihkan topik jika dipilih
        if (d.topic) {
            const tRadio = document.querySelector(`input[name="topic_name"][value="${d.topic}"]`);
            if (tRadio) tRadio.checked = true;
        }

        // Pulihkan centang unit
        if (Array.isArray(d.unitIds)) {
            document.querySelectorAll('input[name="unit_ids[]"]').forEach(cb => {
                cb.checked = d.unitIds.includes(cb.value);
            });
        }

        // Pulihkan centang jabatan
        if (Array.isArray(d.positionIds)) {
            document.querySelectorAll('input[name="position_ids[]"]').forEach(cb => {
                cb.checked = d.positionIds.includes(cb.value);
            });
        }

        // Pulihkan centang karyawan terpilih
        if (Array.isArray(d.employeeIds) && d.employeeIds.length > 0) {
            document.querySelectorAll('input[name="employee_ids[]"]').forEach(cb => {
                cb.checked = d.employeeIds.includes(cb.value);
            });
            updateModalSelectedCounter();
        }

        // Pulihkan konten pesan
        const titleInput = document.getElementById('notifTitle');
        const bodyInput = document.getElementById('notifBody');
        const screenInput = document.getElementById('notifScreen');
        const inboxCheck = document.querySelector('input[name="save_to_inbox"]');

        if (titleInput && d.title !== undefined) titleInput.value = d.title;
        if (bodyInput && d.body !== undefined) bodyInput.value = d.body;
        if (inboxCheck && d.saveInbox !== undefined) inboxCheck.checked = d.saveInbox;
        if (screenInput && d.screen) {
            screenInput.value = d.screen;
            updatePremiumSelectUI(d.screen);
        }
    } catch (e) {}
}

function clearFormDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY);
    } catch (e) {}
}

// Event Listeners for Reliable Template Binding & Initial State
document.addEventListener('DOMContentLoaded', function() {
    // Bind template buttons click
    document.querySelectorAll('.template-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tmpl = this.getAttribute('data-template');
            applyTemplate(tmpl);
            queueSaveDraft();
        });
    });

    // Auto-save draf saat elemen form mengalami perubahan
    const broadcastForm = document.getElementById('fcmBroadcastForm');
    if (broadcastForm) {
        broadcastForm.addEventListener('change', queueSaveDraft);
    }

    // Close custom select dropdown on click outside
    document.addEventListener('click', function(e) {
        const trigger = document.getElementById('customScreenSelectTrigger');
        const menu = document.getElementById('customScreenSelectMenu');
        if (menu && trigger && !trigger.contains(e.target) && !menu.contains(e.target)) {
            toggleCustomScreenSelect(false);
        }
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEmployeeModal();
            closeBroadcastModal();
            closeDeleteLogModal();
            toggleCustomScreenSelect(false);
        }
    });

    // Initial setup & Restore draf jika ada
    const curScreen = document.getElementById('notifScreen') ? document.getElementById('notifScreen').value : 'general';
    updatePremiumSelectUI(curScreen);
    restoreFormDraft();
    updateLivePreview();
    calculateRecipientEstimate();
    updateModalSelectedCounter();
});

// Expose functions globally to window
window.toggleTargetPanel = toggleTargetPanel;
window.checkAllInGroup = checkAllInGroup;
window.calculateRecipientEstimate = calculateRecipientEstimate;
window.openEmployeeModal = openEmployeeModal;
window.closeEmployeeModal = closeEmployeeModal;
window.filterEmployeeModalList = filterEmployeeModalList;
window.toggleAllEmployeesInModal = toggleAllEmployeesInModal;
window.updateModalSelectedCounter = updateModalSelectedCounter;
window.applyEmployeeSelection = applyEmployeeSelection;
window.updateLivePreview = updateLivePreview;
window.toggleCustomScreenSelect = toggleCustomScreenSelect;
window.selectScreenOption = selectScreenOption;
window.updatePremiumSelectUI = updatePremiumSelectUI;
window.applyTemplate = applyTemplate;
window.reuseTemplate = reuseTemplate;
window.resetBroadcastForm = resetBroadcastForm;
window.openBroadcastModal = openBroadcastModal;
window.closeBroadcastModal = closeBroadcastModal;
window.openDeleteLogModal = openDeleteLogModal;
window.closeDeleteLogModal = closeDeleteLogModal;
window.submitBroadcastForm = submitBroadcastForm;
window.saveFormDraft = saveFormDraft;
window.restoreFormDraft = restoreFormDraft;
window.clearFormDraft = clearFormDraft;
</script>

<?php include '../layouts/footer.php'; ?>
