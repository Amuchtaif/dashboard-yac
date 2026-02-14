<?php
// test_approve.php
session_start();
$_SESSION['user_id'] = 1; // Simulate Admin login

require_once 'config/db_mysqli.php';

// Create a dummy pending attendance
$mysqli->query("INSERT INTO tahfidz_teacher_attendance (teacher_id, date, status, status_approval) VALUES (1, CURDATE(), 'Hadir', 'pending')");
$id = $mysqli->insert_id;

echo "Created Dummy Attendance ID: $id\n";

// Call API Logic manually (simulation)
$attendance_id = $id;
$user_id = 1;

$query = "UPDATE tahfidz_teacher_attendance 
            SET status_approval = 'approved', 
                approved_by = ?, 
                approval_time = NOW() 
            WHERE id = ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $user_id, $attendance_id);
$stmt->execute();

echo "Executed Update for ID: $attendance_id by User: $user_id\n";

// Check Result
$res = $mysqli->query("SELECT status_approval, approved_by FROM tahfidz_teacher_attendance WHERE id = $attendance_id");
$row = $res->fetch_assoc();
echo "Result -> Status: " . $row['status_approval'] . ", ApprovedBy: " . $row['approved_by'] . "\n";

// Check if Employee 1 exists
$emp = $mysqli->query("SELECT full_name FROM employees WHERE id = " . $row['approved_by']);
if ($emp && $r = $emp->fetch_assoc()) echo "Employee Name: " . $r['full_name'] . "\n";
else echo "Employee Not Found.\n";

// Clean up
$mysqli->query("DELETE FROM tahfidz_teacher_attendance WHERE id = $id");
?>
