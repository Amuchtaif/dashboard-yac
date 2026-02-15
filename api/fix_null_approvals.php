<?php
require_once 'config/db_mysqli.php';
$mysqli->query("UPDATE tahfidz_teacher_attendance SET approved_by = 25, approval_time = NOW() WHERE approved_by IS NULL AND status_approval IN ('approved', 'rejected')");
echo "Fixed data.\n";
?>
