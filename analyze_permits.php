<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "--- ALL PERMITS (Recent FIRST) ---\n";
$stmt = $conn->query("
    SELECT bp.id, s.nama_siswa, br.room_name, br.supervisor_id, bp.created_at
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
    LEFT JOIN boarding_rooms br ON brm.room_id = br.id
    ORDER BY bp.created_at DESC
    LIMIT 5
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " Student: " . $row['nama_siswa'] . " Room: " . ($row['room_name'] ?? 'N/A') . " Supervisor: " . ($row['supervisor_id'] ?? 'N/A') . " Time: " . $row['created_at'] . "\n";
}
?>
