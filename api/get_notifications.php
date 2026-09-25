<?php
// api/get_notifications.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "User ID required"]);
    exit();
}

try {
    $notifications = [];

    // --- Dismissed checks ---

    // Load dismissed notification keys for this user
    $stmtDismissed = $conn->prepare("SELECT notification_key FROM dismissed_notifications WHERE user_id = :uid");
    $stmtDismissed->execute([':uid' => $user_id]);
    $dismissedKeys = $stmtDismissed->fetchAll(PDO::FETCH_COLUMN, 0);
    $dismissedSet = array_flip($dismissedKeys);

    // 1. CARI TUGAS APPROVAL (Sebagai Atasan)
    $sqlApproval = "SELECT p.id, p.created_at, p.permit_type, e.full_name as sender_name, 'incoming' as type, p.status
                    FROM permits p
                    JOIN employees e ON p.employee_id = e.id
                    WHERE p.approver_id = :uid AND p.status = 'Pending'
                    ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($sqlApproval);
    $stmt->execute([':uid' => $user_id]);
    $incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incoming as $row) {
        $key = "inc_" . $row['id'];
        if (isset($dismissedSet[$key]))
            continue; // Skip dismissed

        $notifications[] = [
            'id' => $key,
            'title' => "Izin Masuk",
            'body' => "{$row['sender_name']} mengajukan izin {$row['permit_type']}.",
            'type' => 'incoming',
            'status' => 'Pending',
            'screen' => 'approval',
            'created_at' => $row['created_at']
        ];
    }

    // 2. CARI UPDATE STATUS (Sebagai Pemohon)
    $sqlUpdate = "SELECT p.id, p.approved_at, p.created_at, p.permit_type, p.status, p.rejection_note
                  FROM permits p
                  WHERE p.employee_id = :uid 
                  AND p.status IN ('Approved', 'Rejected')
                  AND (p.approved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) OR p.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY))
                  ORDER BY p.approved_at DESC";

    $stmt2 = $conn->prepare($sqlUpdate);
    $stmt2->execute([':uid' => $user_id]);
    $updates = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updates as $row) {
        $key = "upd_" . $row['id'];
        if (isset($dismissedSet[$key]))
            continue; // Skip dismissed

        $msg = ($row['status'] == 'Approved')
            ? "Izin {$row['permit_type']} Anda telah disetujui."
            : "Izin {$row['permit_type']} ditolak. Alasan: {$row['rejection_note']}";

        $time = !empty($row['approved_at']) ? $row['approved_at'] : $row['created_at'];

        $notifications[] = [
            'id' => $key,
            'title' => "Status Izin",
            'body' => $msg,
            'type' => 'update',
            'status' => $row['status'],
            'created_at' => $time
        ];
    }

    // 3. CARI NOTIFIKASI PENUGASAN (dari tabel notifications)
    try {
        $checkTable = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($checkTable->rowCount() > 0) {
            $sqlAssignment = "SELECT id, title, body, type, reference_id, is_read, created_at
                              FROM notifications
                              WHERE user_id = :uid AND type = 'assignment'
                              AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                              ORDER BY created_at DESC";
            $stmt3 = $conn->prepare($sqlAssignment);
            $stmt3->execute([':uid' => $user_id]);
            $assignments = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            foreach ($assignments as $row) {
                $key = "asn_" . $row['id'];
                if (isset($dismissedSet[$key]))
                    continue; // Skip dismissed

                $notifications[] = [
                    'id' => $key,
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'type' => 'assignment',
                    'status' => $row['is_read'] ? 'Read' : 'Unread',
                    'task_id' => (int)$row['reference_id'],
                    'reference_id' => (int)$row['reference_id'],
                    'created_at' => $row['created_at']
                ];
            }
        }
    }
    catch (Exception $e) {
    // Notifications table might not exist yet, skip silently
    }

        // 4. CARI PENGINGAT JADWAL MENGAJAR (dari schedule_reminder_logs)
    try {
        $sqlRem = "
            SELECT srl.id, srl.schedule_date, srl.schedule_time, srl.sent_at,
                   s.name as subject_name, gl.name as class_name
            FROM schedule_reminder_logs srl
            JOIN class_schedules cs ON srl.class_schedule_id = cs.id
            JOIN subjects s ON cs.subject_id = s.id
            JOIN grade_levels gl ON cs.grade_level_id = gl.id
            WHERE srl.employee_id = :uid 
              AND srl.schedule_date >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
            ORDER BY srl.sent_at DESC
        ";
        $stmtRem = $conn->prepare($sqlRem);
        $stmtRem->execute([':uid' => $user_id]);
        $reminders = $stmtRem->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reminders as $row) {
            $key = "sched_rem_" . $row['id'];
            if (isset($dismissedSet[$key])) continue;

            $startTimeFormatted = substr($row['schedule_time'], 0, 5);
            $notifications[] = [
                'id' => $key,
                'title' => "Pengingat Mengajar",
                'body' => "Pelajaran " . $row['subject_name'] . " di kelas " . $row['class_name'] . " (" . $startTimeFormatted . " WIB).",
                'type' => 'teaching_reminder',
                'screen' => 'teaching_schedule',
                'status' => 'Reminder',
                'created_at' => $row['sent_at']
            ];
        }
    } catch (Exception $e) {
        // Skip if table not ready
    }

    // 5. CARI UNDANGAN RAPAT (dari meeting_participants & meetings)
    try {
        $checkMeetingTable = $conn->query("SHOW TABLES LIKE 'meetings'");
        if ($checkMeetingTable->rowCount() > 0) {
            $sqlMeeting = "SELECT mp.id as participant_id, mp.meeting_id, mp.status as participant_status,
                                  m.title, m.description, m.meeting_date, m.start_time, m.end_time,
                                  m.type as meeting_type, m.location, m.created_at,
                                  e.full_name as creator_name
                           FROM meeting_participants mp
                           JOIN meetings m ON mp.meeting_id = m.id
                           LEFT JOIN employees e ON m.created_by = e.id
                           WHERE mp.employee_id = :uid
                             AND mp.status = 'invited'
                             AND m.meeting_date >= CURDATE()
                             AND (m.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) OR m.meeting_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY))
                           ORDER BY m.meeting_date ASC, m.start_time ASC";

            $stmtMeeting = $conn->prepare($sqlMeeting);
            $stmtMeeting->execute([':uid' => $user_id]);
            $meetings = $stmtMeeting->fetchAll(PDO::FETCH_ASSOC);

            foreach ($meetings as $row) {
                $key = "meet_" . $row['meeting_id'];
                if (isset($dismissedSet[$key]))
                    continue;

                $meetingDateFormatted = date('d M Y', strtotime($row['meeting_date']));
                $startTimeFormatted = substr($row['start_time'], 0, 5);
                $creator = !empty($row['creator_name']) ? $row['creator_name'] : 'Penyelenggara';
                $loc = ($row['meeting_type'] === 'online') ? 'Online' : (!empty($row['location']) ? $row['location'] : 'Offline');

                $notifications[] = [
                    'id' => $key,
                    'title' => "Undangan Rapat",
                    'body' => "{$creator} mengundang Anda ke rapat \"{$row['title']}\" pada {$meetingDateFormatted} pukul {$startTimeFormatted} WIB ({$loc}).",
                    'type' => 'meeting',
                    'status' => 'Undangan',
                    'screen' => 'meeting',
                    'meeting_id' => (int)$row['meeting_id'],
                    'created_at' => $row['created_at']
                ];
            }
        }
    } catch (Exception $e) {
        // Skip if meeting tables not ready
    }

    // Sort by created_at descending
    usort($notifications, function ($a, $b) {
        return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
    });

    // --- ETag / Cache Control Optimization ---
    $output = json_encode(["success" => true, "data" => $notifications]);
    $etag = md5($output);

    header("ETag: \"$etag\"");
    // header("Cache-Control: public, max-age=30"); // Client boleh tidak memanggil server selama 30 detik

    $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') : false;
    if ($ifNoneMatch === $etag) {
        header("HTTP/1.1 304 Not Modified");
        exit();
    }

    echo $output;

}
catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>