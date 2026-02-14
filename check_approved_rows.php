<?php
require_once 'config/db_mysqli.php';

echo "--- Debug Approved Rows ---\n";
$res = $mysqli->query("SELECT id, status_approval, approved_by FROM tahfidz_teacher_attendance WHERE status_approval = 'approved'");
if ($res) {
    if ($res->num_rows == 0) echo "No approved rows found.\n";
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", ApprovedBy: " . var_export($row['approved_by'], true) . "\n";
    }
} else {
    echo "Query Error: " . $mysqli->error . "\n";
}
?>
