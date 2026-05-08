<?php
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$day_map = [
    'Monday' => 1,
    'Tuesday' => 2,
    'Wednesday' => 3,
    'Thursday' => 4,
    'Friday' => 5,
    'Saturday' => 6,
    'Sunday' => 7
];

$updated = 0;
foreach ($day_map as $day => $dow) {
    $stmt = $conn->prepare("UPDATE class_schedules SET day_of_week = ? WHERE day = ? AND day_of_week = 0");
    $stmt->execute([$dow, $day]);
    $updated += $stmt->rowCount();
}

echo "Updated $updated records with correct day_of_week.";
?>
