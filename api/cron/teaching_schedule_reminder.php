<?php
// api/cron/teaching_schedule_reminder.php
// Script ini dijalankan secara berkala (misal: tiap 1-5 menit) via Cron Job / Windows Task Scheduler.
// Berfungsi untuk mengirimkan FCM Push Notification ke guru 10 menit sebelum jam pelajaran dimulai.

header("Content-Type: application/json; charset=UTF-8");
date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/fcm_helper.php';

try {
    /** @var \Database $db */
    $database = new Database();
    $conn = $database->getConnection();

    // 1. Pastikan tabel log pengingat ada agar tidak mengirim duplikat pada jadwal yang sama di hari yang sama
    $conn->exec("
        CREATE TABLE IF NOT EXISTS `schedule_reminder_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `class_schedule_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            `schedule_date` DATE NOT NULL,
            `schedule_time` TIME NOT NULL,
            `sent_at` DATETIME NOT NULL,
            UNIQUE KEY `uk_schedule_date` (`class_schedule_id`, `schedule_date`),
            KEY `idx_employee_date` (`employee_id`, `schedule_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $todayDay = date('l'); // 'Monday', 'Tuesday', ...
    $todayDate = date('Y-m-d');
    $currentTime = date('H:i:s');

    // 2. Ambil tahun ajaran aktif
    $activeYearId = 1;
    try {
        $stmtYear = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
        $val = $stmtYear->fetchColumn();
        if ($val) $activeYearId = (int)$val;
    } catch (Exception $e) {}

    // 3. Cari jadwal mengajar hari ini yang dimulai dalam rentang 1 s.d. 10 menit ke depan
    //    dan BELUM pernah dikirimkan notifikasi pengingat hari ini.
    $sql = "
        SELECT 
            cs.id as schedule_id,
            cs.employee_id,
            e.full_name as teacher_name,
            e.fcm_token,
            s.name as subject_name,
            gl.name as class_name,
            lp.start_time,
            COALESCE(lp_end.end_time, lp.end_time) as end_time
        FROM class_schedules cs
        JOIN employees e ON cs.employee_id = e.id
        JOIN subjects s ON cs.subject_id = s.id
        JOIN grade_levels gl ON cs.grade_level_id = gl.id
        JOIN lesson_periods lp ON cs.lesson_period_id = lp.id
        LEFT JOIN lesson_periods lp_end ON cs.end_lesson_period_id = lp_end.id
        LEFT JOIN schedule_reminder_logs srl 
            ON srl.class_schedule_id = cs.id AND srl.schedule_date = :today_date
        WHERE cs.day = :today_day
          AND cs.is_active = 1
          AND (:today_date BETWEEN cs.valid_from AND COALESCE(cs.valid_until, '9999-12-31'))
          AND cs.academic_year_id = :active_year_id
          AND e.status = 'active'
          AND srl.id IS NULL
          AND TIMEDIFF(lp.start_time, :current_time) BETWEEN '00:00:00' AND '00:10:59'
        ORDER BY lp.start_time ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':today_day' => $todayDay,
        ':today_date' => $todayDate,
        ':active_year_id' => $activeYearId,
        ':current_time' => $currentTime
    ]);
    $upcomingSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fcm = new FcmHelper();
    $sentCount = 0;
    $results = [];

    $stmtInsertLog = $conn->prepare("
        INSERT IGNORE INTO schedule_reminder_logs 
        (class_schedule_id, employee_id, schedule_date, schedule_time, sent_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    foreach ($upcomingSchedules as $sched) {
        $startTimeFormatted = date('H:i', strtotime($sched['start_time']));
        $title = "🔔 Pengingat Mengajar: " . $sched['subject_name'];
        $body = "10 menit lagi: Pelajaran {$sched['subject_name']} di kelas {$sched['class_name']} ({$startTimeFormatted} WIB). Silakan bersiap menuju kelas.";

        $notifData = [
            'screen' => 'teaching_schedule',
            'type' => 'teaching_reminder',
            'schedule_id' => (string)$sched['schedule_id'],
            'subject' => $sched['subject_name'],
            'class' => $sched['class_name'],
            'start_time' => $startTimeFormatted,
            'title' => $title,
            'body' => $body
        ];

        $token = $sched['fcm_token'];
        $fcmResult = null;

        // Catat log agar tidak terulang
        $stmtInsertLog->execute([
            $sched['schedule_id'],
            $sched['employee_id'],
            $todayDate,
            $sched['start_time']
        ]);

        if (!empty($token)) {
            $fcmResult = $fcm->sendNotification($token, $title, $body, $notifData);
            $sentCount++;

            // Debug log ke fcm_debug.log
            $logLine = "[" . date('Y-m-d H:i:s') . "] TEACHER REMINDER: Sent to {$sched['teacher_name']} (ID: {$sched['employee_id']}) for {$sched['subject_name']} at {$sched['class_name']} ({$startTimeFormatted})\n";
            file_put_contents(dirname(__DIR__) . '/fcm_debug.log', $logLine, FILE_APPEND);
        }

        $results[] = [
            'schedule_id' => $sched['schedule_id'],
            'teacher' => $sched['teacher_name'],
            'subject' => $sched['subject_name'],
            'class' => $sched['class_name'],
            'time' => $sched['start_time'],
            'has_token' => !empty($token),
            'fcm_result' => $fcmResult
        ];
    }

    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'day' => $todayDay,
        'matched_count' => count($upcomingSchedules),
        'sent_count' => $sentCount,
        'details' => $results
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
