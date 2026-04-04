<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

$result = [];
$result['counts'] = [
    'boarding_rooms' => $db->query("SELECT COUNT(*) FROM boarding_rooms")->fetchColumn(),
    'boarding_room_members' => $db->query("SELECT COUNT(*) FROM boarding_room_members")->fetchColumn(),
    'students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
    'boarding_room_supervisors' => $db->query("SELECT COUNT(*) FROM boarding_room_supervisors")->fetchColumn(),
];

$result['sample_students'] = $db->query("SELECT id, nama_siswa, status FROM students LIMIT 5")->fetchAll();
$result['sample_room_members'] = $db->query("SELECT * FROM boarding_room_members LIMIT 5")->fetchAll();
$result['sample_supervisors'] = $db->query("SELECT * FROM boarding_room_supervisors LIMIT 5")->fetchAll();

file_put_contents('tmp/debug_data_dump.json', json_encode($result, JSON_PRETTY_PRINT));
?>
