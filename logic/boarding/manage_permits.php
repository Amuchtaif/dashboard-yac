<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/boarding/permits/index.php?success=Operasi+berhasil');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_permit') {
        $student_id = $_POST['student_id'] ?? '';
        $musrif_id = $_SESSION['user_id'];
        $category = $_POST['category'] ?? 'Izin';
        $reason = $_POST['reason'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';

        if (empty($student_id) || empty($reason) || empty($start_date) || empty($end_date)) throw new Exception("Semua data harus diisi.");

        $stmt = $conn->prepare("INSERT INTO boarding_permits (student_id, musrif_id, category, reason, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$student_id, $musrif_id, $category, $reason, $start_date, $end_date]);
        $newPermitId = $conn->lastInsertId();

        // Send FCM Notification to Mudir Kepengasuhan
        try {
            require_once __DIR__ . '/../../config/fcm_helper.php';
            $fcm = new FcmHelper();

            // Find Mudir Kepengasuhan (Level 1, Level 2-3 in Ma'had Unit 16)
            $stmtMudir = $conn->prepare("
                SELECT e.fcm_token 
                FROM employees e
                JOIN positions p ON e.position_id = p.id
                WHERE ( (e.position_id IN (1, 2, 3) AND e.unit_id = 16)
                   OR (e.position_id = 1)
                   OR p.name LIKE '%Mudir%'
                   OR p.name LIKE '%Kepengasuhan%' )
                AND e.fcm_token IS NOT NULL AND e.fcm_token != ''
            ");
            $stmtMudir->execute();
            $tokens = $stmtMudir->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($tokens)) {
                // Get student name
                $stmtStudent = $conn->prepare("SELECT nama_siswa FROM students WHERE id = ?");
                $stmtStudent->execute([$student_id]);
                $studentName = $stmtStudent->fetchColumn();

                // Get Musrif name
                $stmtCreator = $conn->prepare("SELECT full_name FROM employees WHERE id = ?");
                $stmtCreator->execute([$musrif_id]);
                $musrifName = $stmtCreator->fetchColumn() ?: "Musrif";

                $title = "Izin Santri Baru";
                $body = "{$musrifName} mengajukan izin untuk {$studentName}. Silakan tinjau dan berikan persetujuan.";
                $notifData = [
                    "screen" => "izin_santri",
                    "id" => (string)$newPermitId,
                    "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                ];

                foreach ($tokens as $token) {
                    $fcm->sendNotification($token, $title, $body, $notifData);
                }
            }
        } catch (Exception $e) {
            error_log("FCM Notification Error: " . $e->getMessage());
        }

        $_SESSION['success'] = "Permohonan izin berhasil diajukan dan menunggu persetujuan Mudir.";
    }
    elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? '';
        $approved_by = $_SESSION['user_id'];

        if (empty($id) || empty($status)) throw new Exception("Data tidak lengkap.");

        $stmt = $conn->prepare("UPDATE boarding_permits SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$status, $approved_by, $id]);

        // Send FCM Notification to Musrif
        try {
            // Fetch permit details
            $stmtDetails = $conn->prepare("
                SELECT bp.musrif_id, s.nama_siswa 
                FROM boarding_permits bp
                JOIN students s ON bp.student_id = s.id
                WHERE bp.id = ? LIMIT 1
            ");
            $stmtDetails->execute([$id]);
            $pData = $stmtDetails->fetch(PDO::FETCH_ASSOC);

            if ($pData && $pData['musrif_id'] && in_array($status, ['Disetujui', 'Ditolak'])) {
                require_once __DIR__ . '/../../config/fcm_helper.php';
                $fcm = new FcmHelper();

                $stmtMusrif = $conn->prepare("SELECT fcm_token FROM employees WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
                $stmtMusrif->execute([$pData['musrif_id']]);
                $musrifToken = $stmtMusrif->fetchColumn();

                if ($musrifToken) {
                    $status_upper = strtoupper($status);
                    $title = "Izin Santri " . $status;
                    $body = "Permohonan izin untuk " . $pData['nama_siswa'] . " telah " . $status_upper . " oleh Mudir.";
                    $notifData = [
                        "screen" => "izin_santri",
                        "id" => (string)$id,
                        "click_action" => "FLUTTER_NOTIFICATION_CLICK"
                    ];
                    $fcm->sendNotification($musrifToken, $title, $body, $notifData);
                }
            }
        } catch (Exception $e) {
            error_log("FCM Error in web update_status: " . $e->getMessage());
        }

        $_SESSION['success'] = "Status izin berhasil diperbarui.";
    }
    elseif ($action === 'delete_permit') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM boarding_permits WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Data izin berhasil dihapus.";
    }

        header('Location: ../../views/boarding/permits/index.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
