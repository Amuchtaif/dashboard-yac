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
    <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-4 gap-3.5 mb-6">
        <!-- 1. Perangkat Aktif -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-2xs transition-colors flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] pointer-events-none select-none">
                <i class="fa-solid fa-mobile-screen"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 block">Perangkat Aktif</span>
                <div class="text-2xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['active_fcm_users']); ?></div>
                <div class="text-xs text-slate-400 mt-0.5">Token FCM terhubung</div>
            </div>
        </div>

        <!-- 2. Total Broadcast -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-2xs transition-colors flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] pointer-events-none select-none">
                <i class="fa-solid fa-paper-plane"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 block">Total Broadcast</span>
                <div class="text-2xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['total_broadcasts']); ?></div>
                <div class="text-xs text-slate-400 mt-0.5">Riwayat pengiriman</div>
            </div>
        </div>

        <!-- 3. Hari Ini -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-2xs transition-colors flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] pointer-events-none select-none">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 block">Hari Ini</span>
                <div class="text-2xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['today_broadcasts']); ?></div>
                <div class="text-xs text-slate-400 mt-0.5">Broadcast terkirim</div>
            </div>
        </div>

        <!-- 4. Total Pesan Diterima -->
        <div class="group relative overflow-hidden bg-white p-4 rounded-xl border border-slate-200 shadow-2xs transition-colors flex flex-col justify-between">
            <div class="absolute -right-2 -bottom-2 text-6xl text-slate-800/[0.05] pointer-events-none select-none">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="relative z-10">
                <span class="text-xs font-medium text-slate-500 block">Pesan Diterima</span>
                <div class="text-2xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['total_delivered']); ?></div>
                <div class="text-xs text-slate-400 mt-0.5">Total terkirim sukses</div>
            </div>
        </div>
    </div>

    <!-- Main Grid: Form (8 Cols) & Preview (4 Cols) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start mb-8">
        
        <!-- LEFT: Form Pengiriman Broadcast -->
        <div class="lg:col-span-8">
            <form id="fcmBroadcastForm" method="POST" action="" class="bg-white rounded-xl border border-slate-200 shadow-2xs p-5 md:p-6 space-y-6">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="send_broadcast">

                <!-- 1. TARGET PENERIMA -->
                <div class="space-y-3">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Target Penerima
                    </label>

                    <!-- Segmented Radio Pills -->
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <label class="relative flex items-center justify-center p-2.5 text-center rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-xs font-semibold select-none group has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 has-[:checked]:text-teal-800">
                            <input type="radio" name="target_type" value="all" checked class="sr-only target-radio" onchange="toggleTargetPanel('all')">
                            <span>Semua Pegawai</span>
                        </label>

                        <label class="relative flex items-center justify-center p-2.5 text-center rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-xs font-semibold select-none group has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 has-[:checked]:text-teal-800">
                            <input type="radio" name="target_type" value="unit" class="sr-only target-radio" onchange="toggleTargetPanel('unit')">
                            <span>Per Unit</span>
                        </label>

                        <label class="relative flex items-center justify-center p-2.5 text-center rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-xs font-semibold select-none group has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 has-[:checked]:text-teal-800">
                            <input type="radio" name="target_type" value="position" class="sr-only target-radio" onchange="toggleTargetPanel('position')">
                            <span>Per Jabatan</span>
                        </label>

                        <label class="relative flex items-center justify-center p-2.5 text-center rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-xs font-semibold select-none group has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 has-[:checked]:text-teal-800">
                            <input type="radio" name="target_type" value="specific" class="sr-only target-radio" onchange="toggleTargetPanel('specific')">
                            <span>Pilih Karyawan</span>
                        </label>

                        <label class="relative flex items-center justify-center p-2.5 text-center rounded-lg border border-slate-200 cursor-pointer hover:bg-slate-50 transition-colors text-xs font-semibold select-none group has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50/60 has-[:checked]:text-teal-800 col-span-2 sm:col-span-1">
                            <input type="radio" name="target_type" value="topic" class="sr-only target-radio" onchange="toggleTargetPanel('topic')">
                            <span>Topik FCM</span>
                        </label>
                    </div>

                    <!-- SUB-PANEL: SEMUA PEGAWAI -->
                    <div id="panel-all" class="target-subpanel p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600">
                        Pesan akan dikirim ke seluruh <strong><?php echo $stats['active_fcm_users']; ?> pegawai aktif</strong> yang terdaftar di aplikasi mobile.
                    </div>

                    <!-- SUB-PANEL: PER UNIT -->
                    <div id="panel-unit" class="target-subpanel hidden space-y-2 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="flex items-center justify-between pb-1.5 border-b border-slate-200">
                            <span class="text-xs font-semibold text-slate-700">Pilih Unit:</span>
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
                    <div id="panel-position" class="target-subpanel hidden space-y-2 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <div class="flex items-center justify-between pb-1.5 border-b border-slate-200">
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
                    <div id="panel-specific" class="target-subpanel hidden p-3.5 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-700">Pemilihan Karyawan Tertentu:</span>
                            <button type="button" onclick="openEmployeeModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-teal-600 bg-teal-50 hover:bg-teal-100 text-teal-800 text-xs font-semibold transition-colors">
                                <i class="fa-solid fa-users text-teal-700"></i>
                                <span>Buka Pilihan Karyawan</span>
                            </button>
                        </div>
                        <p id="employeeSelectedSummary" class="text-xs text-slate-600">
                            Belum ada karyawan yang dipilih. Klik tombol di atas untuk memilih karyawan dalam modal.
                        </p>
                    </div>

                    <!-- SUB-PANEL: TOPIK FCM -->
                    <div id="panel-topic" class="target-subpanel hidden space-y-2 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                        <span class="text-xs font-semibold text-slate-700 block">Pilih Topik Broadcast:</span>
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

                    <!-- Target Estimate Counter Banner -->
                    <div class="p-2.5 bg-slate-50 rounded-lg border border-slate-200 flex items-center justify-between text-xs">
                        <span class="text-slate-600 font-medium">Estimasi Penerima:</span>
                        <span id="recipientEstimateBadge" class="font-bold text-teal-800 bg-teal-50 border border-teal-200 px-2 py-0.5 rounded">
                            <?php echo $stats['active_fcm_users']; ?> Perangkat Aktif
                        </span>
                    </div>
                </div>

                <!-- 2. KONTEN NOTIFIKASI -->
                <div class="space-y-4 pt-3 border-t border-slate-200">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                        Isi Pesan
                    </label>

                    <!-- Judul Notifikasi -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="notifTitle" class="text-xs font-semibold text-slate-700">
                                Judul Notifikasi <span class="text-rose-500">*</span>
                            </label>
                            <span id="titleCounter" class="text-[11px] text-slate-400">0/100</span>
                        </div>
                        <input type="text" id="notifTitle" name="title" maxlength="100" required placeholder="Contoh: Undangan Rapat Evaluasi Bulanan" oninput="updateLivePreview()" class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 text-slate-800 placeholder-slate-400 bg-white">
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
                        <div>
                            <label for="notifScreen" class="block text-xs font-semibold text-slate-700 mb-1">
                                Layar Tujuan Saat Ditap
                            </label>
                            <select id="notifScreen" name="screen" onchange="updateLivePreview()" class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white text-slate-700 font-medium">
                                <option value="general" selected>Beranda (Umum)</option>
                                <option value="announcement">Tab Pengumuman</option>
                                <option value="attendance">Layar Presensi</option>
                                <option value="news">Tab Berita</option>
                                <option value="teaching_schedule">Jadwal Mengajar</option>
                            </select>
                        </div>

                        <div class="flex items-center pt-2 sm:pt-5">
                            <label class="flex items-start gap-2 cursor-pointer select-none">
                                <input type="checkbox" name="save_to_inbox" value="1" checked class="mt-0.5 rounded text-teal-600 focus:ring-teal-500 h-4 w-4">
                                <span class="text-xs text-slate-700">
                                    <strong class="font-semibold block text-slate-800">Simpan ke Tab Notifikasi Mobile</strong>
                                    Pesan tersimpan di riwayat aplikasi mobile pegawai.
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-3">
                    <button type="button" onclick="resetBroadcastForm()" class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors">
                        Reset Form
                    </button>
                    <button type="button" onclick="openBroadcastModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold shadow-sm transition-colors">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span>Kirim Notifikasi</span>
                    </button>
                </div>

                <!-- MODAL PILIH KARYAWAN (Didalam Form agar input employee_ids[] terkirim otomatis) -->
                <div id="employeeModal" class="fixed inset-0 z-[70] hidden bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5" onclick="if(event.target === this) closeEmployeeModal()">
                    <div class="bg-white rounded-xl max-w-2xl w-full shadow-2xl border border-slate-200 flex flex-col max-h-[90vh] overflow-hidden transform transition-all">
                        <!-- Header Modal -->
                        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Pilih Karyawan Penerima Notifikasi</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Centang karyawan yang akan menerima pesan.</p>
                            </div>
                            <button type="button" onclick="closeEmployeeModal()" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100">
                                <i class="fa-solid fa-xmark text-sm"></i>
                            </button>
                        </div>

                        <!-- Search & Quick Selection Toolbar -->
                        <div class="p-3 border-b border-slate-200 bg-slate-50 space-y-2.5">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                                <input type="text" id="modalEmployeeSearch" placeholder="Cari nama, NIK, unit, atau jabatan..." onkeyup="filterEmployeeModalList()" class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-teal-500 bg-white">
                            </div>

                            <div class="flex items-center justify-between text-xs flex-wrap gap-2">
                                <div class="flex items-center gap-2">
                                    <button type="button" onclick="toggleAllEmployeesInModal(true, false)" class="text-xs text-teal-700 hover:underline font-medium">
                                        Pilih Semua (FCM Aktif)
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button" onclick="toggleAllEmployeesInModal(true, true)" class="text-xs text-slate-600 hover:underline">
                                        Pilih Semua Karyawan
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button" onclick="toggleAllEmployeesInModal(false)" class="text-xs text-slate-500 hover:underline">
                                        Kosongkan
                                    </button>
                                </div>
                                <span id="modalSelectedCounter" class="font-bold text-teal-800 bg-teal-100/70 px-2 py-0.5 rounded text-[11px]">
                                    0 dipilih
                                </span>
                            </div>
                        </div>

                        <!-- Employee List (Scrollable) -->
                        <div id="modalEmployeeListContainer" class="p-3 overflow-y-auto space-y-1.5 max-h-[55vh]">
                            <?php if (empty($employees)): ?>
                                <div class="py-8 text-center text-xs text-slate-400">
                                    Tidak ada data karyawan aktif ditemukan.
                                </div>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <label class="emp-row flex items-center justify-between p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-slate-50/70 cursor-pointer text-xs transition-colors select-none"
                                           data-search="<?php echo strtolower(htmlspecialchars(($emp['full_name'] ?? '') . ' ' . ($emp['nik'] ?? '') . ' ' . ($emp['unit_name'] ?? '') . ' ' . ($emp['position_name'] ?? ''))); ?>"
                                           data-has-fcm="<?php echo $emp['has_fcm'] ? '1' : '0'; ?>">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>"
                                               data-has-fcm="<?php echo $emp['has_fcm'] ? '1' : '0'; ?>"
                                               data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                               onchange="updateModalSelectedCounter()"
                                               class="emp-checkbox rounded text-teal-600 focus:ring-teal-500 h-4 w-4">
                                        <div class="truncate">
                                            <span class="font-semibold text-slate-800 block truncate"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                            <span class="text-[11px] text-slate-400 block truncate">
                                                <?php echo htmlspecialchars($emp['unit_name'] ?? '-'); ?> &bull; <?php echo htmlspecialchars($emp['position_name'] ?? '-'); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="shrink-0 ml-2">
                                        <?php if ($emp['has_fcm']): ?>
                                            <span class="inline-flex items-center text-[10px] font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                                                FCM Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                                                Belum Login
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Footer Modal -->
                        <div class="p-3 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-2">
                            <button type="button" onclick="closeEmployeeModal()" class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 rounded-lg transition-colors">
                                Tutup
                            </button>
                            <button type="button" onclick="applyEmployeeSelection()" class="px-4 py-1.5 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg shadow-sm transition-colors">
                                Terapkan Pilihan
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- RIGHT: Pratinjau & Template Cepat -->
        <div class="lg:col-span-4 space-y-4">
            
            <!-- Notification Preview Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-4 space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 text-xs">
                    <span class="font-semibold text-slate-700">Pratinjau Notifikasi</span>
                    <span class="text-[11px] text-slate-400">Push Banner</span>
                </div>

                <!-- Push Notification Item -->
                <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-1.5">
                            <div class="w-4 h-4 rounded bg-teal-600 text-white flex items-center justify-center text-[9px]">
                                <i class="fa-solid fa-bell"></i>
                            </div>
                            <span class="font-bold text-[11px] text-slate-800">SIMPEG YAC</span>
                            <span class="text-slate-400 text-[10px]">&bull;</span>
                            <span class="text-slate-400 text-[10px]">Baru saja</span>
                        </div>
                        <span id="previewScreenTag" class="text-[9px] font-semibold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">
                            Beranda
                        </span>
                    </div>

                    <div>
                        <h4 id="previewTitle" class="font-semibold text-xs text-slate-900 leading-snug break-words">
                            Judul Notifikasi
                        </h4>
                        <p id="previewBody" class="text-xs text-slate-600 mt-1 leading-relaxed break-words">
                            Isi pesan notifikasi akan tampil di sini saat diketik...
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Templates Card -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-4 space-y-2.5">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 text-xs">
                    <span class="font-semibold text-slate-700">Template Cepat</span>
                    <span class="text-[11px] text-slate-400">Klik untuk isi form</span>
                </div>

                <div class="space-y-1.5">
                    <button type="button" data-template="rapat" onclick="applyTemplate('rapat')" class="template-btn w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-slate-50 transition-colors block text-xs cursor-pointer">
                        <span class="font-semibold text-slate-800 block">Undangan Rapat</span>
                        <span class="text-[11px] text-slate-500 line-clamp-1">Diberitahukan kepada seluruh asatidzah &amp; staf...</span>
                    </button>
                    <button type="button" data-template="presensi" onclick="applyTemplate('presensi')" class="template-btn w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-slate-50 transition-colors block text-xs cursor-pointer">
                        <span class="font-semibold text-slate-800 block">Pengingat Presensi</span>
                        <span class="text-[11px] text-slate-500 line-clamp-1">Jangan lupa melakukan presensi kehadiran hari ini...</span>
                    </button>
                    <button type="button" data-template="pengumuman" onclick="applyTemplate('pengumuman')" class="template-btn w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-slate-50 transition-colors block text-xs cursor-pointer">
                        <span class="font-semibold text-slate-800 block">Pengumuman Yayasan</span>
                        <span class="text-[11px] text-slate-500 line-clamp-1">Terdapat informasi penting terbaru dari pimpinan...</span>
                    </button>
                    <button type="button" data-template="update" onclick="applyTemplate('update')" class="template-btn w-full text-left p-2.5 rounded-lg border border-slate-200 hover:border-teal-400 hover:bg-slate-50 transition-colors block text-xs cursor-pointer">
                        <span class="font-semibold text-slate-800 block">Pembaruan Aplikasi</span>
                        <span class="text-[11px] text-slate-500 line-clamp-1">Tersedia pembaruan versi terbaru SIMPEG Assunnah...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Pengiriman Notifikasi (Broadcast Logs) -->
    <div id="riwayat-broadcast" class="bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="p-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900">
                    Riwayat Pengiriman Notifikasi
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Daftar broadcast notifikasi yang telah dikirim oleh administrator.
                </p>
            </div>
            <span class="text-xs text-slate-400">
                <?php echo count($recentLogs); ?> pengiriman terakhir
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] font-semibold text-slate-600 uppercase tracking-wider border-b border-slate-200">
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
                                    <span class="px-2 py-0.5 rounded text-[11px] font-mono bg-slate-100 text-slate-700">
                                        <?php echo htmlspecialchars($log['screen'] ?? 'general'); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <?php if ($log['failure_count'] == 0 && $log['success_count'] > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <?php echo $log['success_count']; ?> Terkirim
                                        </span>
                                    <?php elseif ($log['success_count'] > 0 && $log['failure_count'] > 0): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                            <?php echo $log['success_count']; ?> / <?php echo $log['total_recipients']; ?> Sukses
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-rose-50 text-rose-700 border border-rose-200">
                                            Gagal
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    <button type="button" 
                                            onclick="reuseTemplate(<?php echo htmlspecialchars(json_encode($log['title'])); ?>, <?php echo htmlspecialchars(json_encode($log['body'])); ?>, '<?php echo htmlspecialchars($log['screen'] ?? 'general'); ?>')"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-teal-700 hover:text-teal-900 bg-teal-50 hover:bg-teal-100 px-2.5 py-1 rounded transition-colors">
                                        <i class="fa-solid fa-copy text-[10px]"></i>
                                        <span>Gunakan Ulang</span>
                                    </button>
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
<div id="broadcastConfirmModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 flex items-center justify-center p-4" onclick="if(event.target === this) closeBroadcastModal()">
    <div class="bg-white rounded-xl max-w-md w-full shadow-lg border border-slate-200 p-5 space-y-4">
        <div>
            <h3 class="text-base font-bold text-slate-900">Konfirmasi Kirim Notifikasi</h3>
            <p class="text-xs text-slate-500 mt-0.5">
                Pastikan target penerima dan isi pesan sudah benar sebelum dikirimkan.
            </p>
        </div>

        <div class="p-3 bg-slate-50 rounded-lg border border-slate-200 text-xs space-y-1.5">
            <div class="flex justify-between">
                <span class="text-slate-500">Target Penerima:</span>
                <span id="modalTargetSummary" class="font-semibold text-slate-800">Semua Pegawai</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Estimasi Perangkat:</span>
                <span id="modalRecipientCount" class="font-bold text-teal-800">268 Perangkat</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-500">Layar Tujuan:</span>
                <span id="modalScreenSummary" class="font-medium text-slate-800">Beranda</span>
            </div>
            <div class="pt-1.5 border-t border-slate-200">
                <span class="text-slate-500 block mb-0.5">Judul:</span>
                <p id="modalTitleSummary" class="font-semibold text-slate-900 break-words">-</p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeBroadcastModal()" class="px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 rounded-lg transition-colors">
                Batal
            </button>
            <button type="button" id="confirmSubmitBtn" onclick="submitBroadcastForm()" class="px-4 py-2 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                <i class="fa-solid fa-paper-plane text-xs"></i>
                <span>Kirim Sekarang</span>
            </button>
        </div>
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
        'announcement': 'Pengumuman',
        'attendance': 'Presensi',
        'news': 'Berita',
        'teaching_schedule': 'Jadwal'
    };
    if (pTag) pTag.textContent = screenLabels[screenVal] || 'Umum';

    const tCounter = document.getElementById('titleCounter');
    const bCounter = document.getElementById('bodyCounter');
    if (tCounter && titleInput) tCounter.textContent = `${titleInput.value.length}/100`;
    if (bCounter && bodyInput) bCounter.textContent = `${bodyInput.value.length}/500`;
}

// Template Cepat
const broadcastTemplates = {
    'rapat': {
        title: 'Undangan Rapat Evaluasi Pegawai',
        body: 'Diberitahukan kepada seluruh asatidzah & staf agar dapat menghadiri rapat evaluasi berkala yang diselenggarakan di ruang pertemuan utama.',
        screen: 'general'
    },
    'presensi': {
        title: 'Pengingat Presensi Pegawai',
        body: 'Jangan lupa untuk melakukan presensi kehadiran hari ini melalui aplikasi mobile SIMPEG Assunnah.',
        screen: 'attendance'
    },
    'pengumuman': {
        title: 'Pengumuman Resmi Yayasan Assunnah',
        body: 'Terdapat informasi penting terbaru dari pimpinan yayasan. Silakan periksa menu pengumuman pada aplikasi Anda.',
        screen: 'announcement'
    },
    'update': {
        title: 'Pembaruan Aplikasi Tersedia',
        body: 'Tersedia pembaruan versi terbaru SIMPEG Assunnah. Silakan perbarui aplikasi Anda untuk mendapatkan performa dan fitur terbaru.',
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
    if (screenInput) screenInput.value = t.screen;

    updateLivePreview();
}

function reuseTemplate(title, body, screen) {
    const titleInput = document.getElementById('notifTitle');
    const bodyInput = document.getElementById('notifBody');
    const screenInput = document.getElementById('notifScreen');

    if (titleInput) titleInput.value = title || '';
    if (bodyInput) bodyInput.value = body || '';
    if (screenInput) screenInput.value = screen || 'general';

    updateLivePreview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetBroadcastForm() {
    const form = document.getElementById('fcmBroadcastForm');
    if (form) form.reset();
    toggleTargetPanel('all');
    calculateRecipientEstimate();
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

function submitBroadcastForm() {
    const btn = document.getElementById('confirmSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin text-xs"></i> <span>Mengirim...</span>`;
    }
    document.getElementById('fcmBroadcastForm').submit();
}

// Event Listeners for Reliable Template Binding & Initial State
document.addEventListener('DOMContentLoaded', function() {
    // Bind template buttons click
    document.querySelectorAll('.template-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tmpl = this.getAttribute('data-template');
            applyTemplate(tmpl);
        });
    });

    // Close modal on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEmployeeModal();
            closeBroadcastModal();
        }
    });

    // Initial setup
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
window.applyTemplate = applyTemplate;
window.reuseTemplate = reuseTemplate;
window.resetBroadcastForm = resetBroadcastForm;
window.openBroadcastModal = openBroadcastModal;
window.closeBroadcastModal = closeBroadcastModal;
window.submitBroadcastForm = submitBroadcastForm;
</script>

<?php include '../layouts/footer.php'; ?>
