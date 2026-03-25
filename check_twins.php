<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("
    SELECT s.nama_siswa, br.room_name, br.supervisor_id
    FROM students s
    LEFT JOIN boarding_room_members brm ON s.id = brm.student_id
    LEFT JOIN boarding_rooms br ON brm.room_id = br.id
    WHERE s.nama_siswa LIKE '%AZEEM%' OR s.nama_siswa LIKE '%VASEEM%'
");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Name: " . $row['nama_siswa'] . " Room: " . ($row['room_name'] ?? 'N/A') . " Supervisor: " . ($row['supervisor_id'] ?? 'N/A') . "\n";
}
?>
