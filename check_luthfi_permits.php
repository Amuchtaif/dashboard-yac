<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "--- Permits for Luthfi (ID 158) ---\n";
$stmt = $conn->query("
    SELECT bp.*, s.nama_siswa 
    FROM boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    JOIN boarding_room_members brm ON s.id = brm.student_id
    JOIN boarding_rooms br ON brm.room_id = br.id
    WHERE br.supervisor_id = 158
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . " Student: " . $row['nama_siswa'] . "\n";
}
?>
