<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "--- Permits for Students in Umar Room (ID 2) ---\n";
$stmt = $conn->query("
    SELECT bp.id, s.nama_siswa, bp.status
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    JOIN boarding_room_members brm ON s.id = brm.student_id
    WHERE brm.room_id = 2
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " Student: " . $row['nama_siswa'] . " Status: " . $row['status'] . "\n";
}
?>
