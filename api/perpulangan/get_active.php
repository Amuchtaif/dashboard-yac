<?php
// api/perpulangan/get_active.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $now = date('Y-m-d H:i:s');
    $search = isset($_GET['search']) ? $_GET['search'] : '';

    // Fetch active permits (students currently at home)
    $query = "
        SELECT bp.id, bp.student_id, s.nama_siswa, s.kelas, s.foto,
               bp.category, bp.reason, bp.start_date, bp.end_date, bp.status
        FROM boarding_permits bp
        JOIN students s ON bp.student_id = s.id
        WHERE bp.status = 'Disetujui'
        AND :now BETWEEN bp.start_date AND bp.end_date
        " . ($search ? "AND s.nama_siswa LIKE :search" : "") . "
        ORDER BY bp.start_date DESC
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':now', $now);
    if ($search) {
        $searchParam = "%$search%";
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->execute();
    $active_permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($active_permits),
        "data" => $active_permits
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database Error: " . $e->getMessage()
    ]);
}
?>
