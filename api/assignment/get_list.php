<?php
// api/assignment/get_list.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $user_id = $_GET['user_id'] ?? null;
    $created_by = $_GET['created_by'] ?? null;
    $status = $_GET['status'] ?? null;

    // Join both creator (author) and assignee info
    $query = "SELECT a.*, 
              e_creator.full_name as author_name, 
              p_creator.name as author_position, 
              e_creator.profile_photo as author_photo,
              e_assignee.full_name as assignee_name,
              p_assignee.name as assignee_position,
              e_assignee.profile_photo as assignee_photo
              FROM assignments a
              LEFT JOIN employees e_creator ON a.created_by = e_creator.id
              LEFT JOIN positions p_creator ON e_creator.position_id = p_creator.id
              LEFT JOIN employees e_assignee ON a.assigned_to = e_assignee.id
              LEFT JOIN positions p_assignee ON e_assignee.position_id = p_assignee.id";
    
    $conditions = [];
    $params = [];

    if ($user_id) {
        $conditions[] = "a.assigned_to = :user_id";
        $params[':user_id'] = $user_id;
    }

    if ($created_by) {
        // Ambil info supervisor (Level, Divisi, & Unit)
        $stmtUser = $db->prepare("
            SELECT e.division_id, e.unit_id, p.level 
            FROM employees e 
            INNER JOIN positions p ON e.position_id = p.id 
            WHERE e.id = ?
        ");
        $stmtUser->execute([$created_by]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $userLevel = (int)$user['level'];
            $division_id = $user['division_id'];
            $unit_id = $user['unit_id'];

            if ($userLevel === 1) {
                // Mudir: Lihat urusan sendiri + urusan by Kabids (Lvl 2)
                $conditions[] = "(a.created_by = :created_by OR p_creator.level = 2)";
                $params[':created_by'] = $created_by;
            } else if ($userLevel === 2) {
                // Kabid: Lihat urusan sendiri + urusan by subunit/staff di divisinya
                // Filter pencipta tugas (creator) yang merupakan bawahan Kabid
                $conditions[] = "(a.created_by = :created_by OR (
                    e_creator.division_id = :division_id 
                    AND e_creator.id != :created_by
                    AND (
                        p_creator.level = 3 
                        OR (p_creator.name = 'Staf' AND (e_creator.unit_id IS NULL OR e_creator.unit_id = 0))
                    )
                ))";
                $params[':created_by'] = $created_by;
                $params[':division_id'] = $division_id;
            } else if ($userLevel === 3) {
                // Kepala Unit/Sub: Lihat urusan sendiri + urusan by siapapun di unitnya
                $conditions[] = "(a.created_by = :created_by OR (
                    e_creator.unit_id = :unit_id AND e_creator.id != :created_by
                ))";
                $params[':created_by'] = $created_by;
                $params[':unit_id'] = $unit_id;
            } else {
                // Posisi lain: Hanya lihat urusan sendiri
                $conditions[] = "a.created_by = :created_by";
                $params[':created_by'] = $created_by;
            }
        } else {
            $conditions[] = "a.created_by = :created_by";
            $params[':created_by'] = $created_by;
        }
    }

    if ($status) {
        $conditions[] = "a.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY a.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_replace('/api/assignment/get_list.php', '', $scriptName);
    $baseUrl = $protocol . "://" . $host . $basePath . "/";

    // Format for easier consumption
    foreach ($tasks as &$task) {
        
        // Author avatar
        if (!empty($task['author_photo'])) {
            $task['author_avatar'] = $baseUrl . "uploads/profile_photos/" . $task['author_photo'];
        } else {
            $task['author_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($task['author_name'] ?? 'U') . "&background=random";
        }

        // Assignee avatar
        if (!empty($task['assignee_photo'])) {
            $task['assignee_avatar'] = $baseUrl . "uploads/profile_photos/" . $task['assignee_photo'];
        } else {
            $task['assignee_avatar'] = "https://ui-avatars.com/api/?name=" . urlencode($task['assignee_name'] ?? 'U') . "&background=random";
        }
        // Task Attachment URL
        if (!empty($task['attachment_path'])) {
            $task['attachment_path'] = $baseUrl . "uploads/assignments/" . $task['attachment_path'];
            $task['attachment'] = $task['attachment_path'];
        } elseif (!empty($task['attachment'])) {
            $task['attachment'] = $baseUrl . "uploads/assignments/" . $task['attachment'];
        }

        // Report Attachment URL
        if (!empty($task['report_attachment'])) {
            $task['report_attachment_url'] = $baseUrl . "uploads/assignments/" . $task['report_attachment'];
        }
    }
    unset($task); // CRITICAL: Break the reference to the last element

    // Header count based on Belum Dimulai
    $waiting_count = 0;
    foreach ($tasks as $task) {
        if ($task['status'] == 'Belum Dimulai') $waiting_count++;
    }

    echo json_encode([
        "status" => "success",
        "waiting_count" => $waiting_count,
        "count" => count($tasks),
        "data" => $tasks
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Internal Server Error: " . $e->getMessage()]);
}
