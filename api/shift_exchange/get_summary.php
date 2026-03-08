<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "User ID is required"]);
    exit;
}

try {
    // Count pending incoming requests
    $query = "SELECT COUNT(*) as pending_count FROM shift_exchanges WHERE substitute_id = ? AND status = 'Menunggu'";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "pending_count" => (int)$row['pending_count'],
        "data" => [
            "summary_text" => $row['pending_count'] . " Permintaan Baru",
            "summary_subtext" => "Rekan kerja menunggu persetujuan Anda"
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
