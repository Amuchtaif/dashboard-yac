<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();
echo "--- Luthfi (ID 158) ---\n";
$rooms = $conn->query("SELECT id, room_name FROM boarding_rooms WHERE supervisor_id = 158");
while($r = $rooms->fetch(PDO::FETCH_ASSOC)) {
    echo "  Room: " . $r['room_name'] . " (ID: " . $r['id'] . ")\n";
    $students_res = $conn->query("SELECT s.id, s.nama_siswa FROM boarding_room_members brm JOIN students s ON brm.student_id = s.id WHERE brm.room_id = " . $r['id']);
    while($s = $students_res->fetch(PDO::FETCH_ASSOC)) {
        echo "    - " . $s['nama_siswa'] . " (ID: " . $s['id'] . ")\n";
    }
}
?>
