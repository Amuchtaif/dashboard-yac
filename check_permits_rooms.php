<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("
    SELECT bp.id, s.nama_siswa, br.room_name, br.supervisor_id
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
    LEFT JOIN boarding_rooms br ON brm.room_id = br.id
    ORDER BY bp.created_at DESC
    LIMIT 10
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Permit ID: " . $row['id'] . " Student: " . $row['nama_siswa'] . " Room: " . ($row['room_name'] ?? 'N/A') . " Supervisor ID: " . ($row['supervisor_id'] ?? 'N/A') . "\n";
}
?>
