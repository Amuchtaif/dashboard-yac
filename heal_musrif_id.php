<?php
require_once 'd:/xampp/htdocs/dashboard-yac/config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Heal musrif_id by looking up student's supervisor
$query = "
    UPDATE boarding_permits bp
    JOIN students s ON bp.student_id = s.id
    JOIN boarding_room_members brm ON s.id = brm.student_id
    JOIN boarding_rooms br ON brm.room_id = br.id
    SET bp.musrif_id = br.supervisor_id
    WHERE bp.musrif_id IS NULL
";

try {
    $count = $conn->exec($query);
    echo "Healed $count permit records with missing musrif_id.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
