<?php
require_once 'config/db_mysqli.php';
$query = "SELECT ta.id, ta.date, ta.check_in_time, ta.status_approval, ta.approved_by, e.full_name as coordinator_name 
          FROM tahfidz_teacher_attendance ta 
          LEFT JOIN employees e ON ta.approved_by = e.id 
          ORDER BY ta.id DESC LIMIT 10";
$res = $mysqli->query($query);
$output = "";
while ($row = $res->fetch_assoc()) {
    $output .= "ID: {$row['id']} | Date: {$row['date']} | Time: {$row['check_in_time']} | Status: {$row['status_approval']} | ApprovedBy ID: " . ($row['approved_by'] ?? 'NULL') . " | Name: " . ($row['coordinator_name'] ?? 'NULL') . "\n";
}
file_put_contents('debug_log.txt', $output);
echo "Output written to debug_log.txt";
?>
