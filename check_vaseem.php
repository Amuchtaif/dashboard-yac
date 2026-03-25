<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT s.nama_siswa, brm.room_id, br.room_name, br.supervisor_id
    FROM boarding_room_members brm
    JOIN students s ON brm.student_id = s.id
    JOIN boarding_rooms br ON brm.room_id = br.id
    WHERE s.nama_siswa LIKE '%VASEEM%'
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Student: " . $row['nama_siswa'] . " Room: " . $row['room_name'] . " Supervisor: " . $row['supervisor_id'] . "\n";
}
?>
