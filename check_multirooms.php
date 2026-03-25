<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT s.nama_siswa, COUNT(*) as room_count
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    GROUP BY brm.student_id
    HAVING room_count > 1
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['nama_siswa'] . " - rooms: " . $row['room_count'] . "\n";
}
?>
