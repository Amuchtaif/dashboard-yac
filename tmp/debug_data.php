<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "--- Counts ---\n";
echo "boarding_rooms: " . $db->query("SELECT COUNT(*) FROM boarding_rooms")->fetchColumn() . "\n";
echo "boarding_room_members: " . $db->query("SELECT COUNT(*) FROM boarding_room_members")->fetchColumn() . "\n";
echo "students: " . $db->query("SELECT COUNT(*) FROM students")->fetchColumn() . "\n";
echo "boarding_room_supervisors: " . $db->query("SELECT COUNT(*) FROM boarding_room_supervisors")->fetchColumn() . "\n";

echo "\n--- Last 5 Students ---\n";
print_r($db->query("SELECT id, nama_siswa, status FROM students LIMIT 5")->fetchAll());

echo "\n--- Last 5 Room Members ---\n";
print_r($db->query("SELECT * FROM boarding_room_members LIMIT 5")->fetchAll());
?>
