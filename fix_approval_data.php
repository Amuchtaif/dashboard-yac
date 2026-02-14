<?php
require_once 'config/db_mysqli.php';

// Fix NULL approved_by for approved rows, assuming Ahmad Ghozali (ID 25)
$sql = "UPDATE tahfidz_teacher_attendance SET approved_by = 25 WHERE status_approval = 'approved' AND approved_by IS NULL";
if ($mysqli->query($sql)) {
    echo "Updated " . $mysqli->affected_rows . " rows to be approved by Ahmad Ghozali (ID 25).\n";
} else {
    echo "Error: " . $mysqli->error . "\n";
}

// Verification
$res = $mysqli->query("SELECT ta.id, ta.approved_by, e.full_name FROM tahfidz_teacher_attendance ta LEFT JOIN employees e ON ta.approved_by = e.id WHERE ta.status_approval = 'approved'");
while ($r = $res->fetch_assoc()) {
    echo "ID: " . $r['id'] . " Name: " . $r['full_name'] . "\n";
}
?>
