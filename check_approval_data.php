<?php
require_once 'config/db_mysqli.php';

echo "--- Tahfidz Attendance Debug ---\n";
// Fetch last 5 attendance
$res = $mysqli->query("SELECT id, status_approval, approved_by FROM tahfidz_teacher_attendance ORDER BY id DESC LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Status: " . $row['status_approval'] . ", ApprovedBy: " . var_export($row['approved_by'], true) . "\n";
        
        if ($row['approved_by']) {
            $emp = $mysqli->query("SELECT id, full_name FROM employees WHERE id = " . $row['approved_by']);
            if ($emp && $r = $emp->fetch_assoc()) {
                echo "  -> Found Employee: " . $r['full_name'] . "\n";
            } else {
                echo "  -> Employee NOT FOUND for ID " . $row['approved_by'] . "\n";
            }
        }
    }
} else {
    echo "Query failed.\n";
}

echo "\n--- Employees Check ---\n";
$res = $mysqli->query("SELECT id, full_name FROM employees LIMIT 5");
while($row = $res->fetch_assoc()) {
    echo $row['id'] . ": " . $row['full_name'] . "\n";
}
?>
