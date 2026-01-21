<?php
// api/get_notifications.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

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

    // 1. CARI TUGAS APPROVAL (Sebagai Atasan)
    // Mencari izin yang harus diapprove oleh user ini (Status Pending)
    $sqlApproval = "SELECT p.id, p.created_at, p.permit_type, e.full_name as sender_name, 'incoming' as type, p.status
                    FROM permits p
                    JOIN employees e ON p.employee_id = e.id
                    WHERE p.approver_id = :uid AND p.status = 'Pending'
                    ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($sqlApproval);
    $stmt->execute([':uid' => $user_id]);
    $incoming = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($incoming as $row) {
        $notifications[] = [
            'id' => "inc_" . $row['id'], // Prefix ID biar unik
            'title' => "Izin Masuk",
            'body' => "{$row['sender_name']} mengajukan izin {$row['permit_type']}.",
            'type' => 'incoming', // Tipe untuk UI (warna/icon)
            'status' => 'Pending',
            'timestamp' => $row['created_at']
        ];
    }

    // 2. CARI UPDATE STATUS (Sebagai Pemohon)
    // Mencari izin milik user ini yang sudah direspon (Approved/Rejected) dalam 3 hari terakhir
    // Fix: Use approved_at instead of updated_at. If approved_at is NULL (shouldnt be for App/Rej), use created_at.
    $sqlUpdate = "SELECT p.id, p.approved_at, p.created_at, p.permit_type, p.status, p.rejection_note
                  FROM permits p
                  WHERE p.employee_id = :uid 
                  AND p.status IN ('Approved', 'Rejected')
                  AND (p.approved_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) OR p.created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY))
                  ORDER BY p.approved_at DESC";

    $stmt2 = $conn->prepare($sqlUpdate);
    $stmt2->execute([':uid' => $user_id]);
    $updates = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    foreach ($updates as $row) {
        $msg = ($row['status'] == 'Approved')
            ? "Izin {$row['permit_type']} Anda telah disetujui."
            : "Izin {$row['permit_type']} ditolak. Alasan: {$row['rejection_note']}";

        // Use approved_at if available, otherwise created_at
        $time = !empty($row['approved_at']) ? $row['approved_at'] : $row['created_at'];

        $notifications[] = [
            'id' => "upd_" . $row['id'],
            'title' => "Status Izin",
            'body' => $msg,
            'type' => 'update',
            'status' => $row['status'],
            'timestamp' => $time
        ];
    }

    // Sort by timestamp descending (Terbaru diatas)
    usort($notifications, function ($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    echo json_encode(["success" => true, "data" => $notifications]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>