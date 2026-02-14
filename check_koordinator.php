<?php
require_once 'config/db_mysqli.php';

// 1. Find Ahmad Ghozali
echo "--- Finding 'Ghozali' ---\n";
$res = $mysqli->query("SELECT id, full_name, position_id FROM employees WHERE full_name LIKE '%Ghozali%'");
if ($res) {
    if ($res->num_rows == 0) echo "Not found.\n";
    while ($row = $res->fetch_assoc()) {
        echo "ID: " . $row['id'] . ", Name: " . $row['full_name'] . ", Pos: " . $row['position_id'] . "\n";
    }
}

// 2. Check Approved rows with NULL
echo "\n--- Null Approval ---\n";
$res2 = $mysqli->query("SELECT id, status_approval, approved_by FROM tahfidz_teacher_attendance WHERE status_approval = 'approved' AND approved_by IS NULL");
if ($res2 && $res2->num_rows > 0) {
    echo "Found " . $res2->num_rows . " rows approved but with NULL approved_by.\n";
    while ($row = $res2->fetch_assoc()) echo $row['id'] . " ";
    echo "\n";
} else {
    echo "No NULL anomalies found in approved status.\n";
}

// 3. Check All Approved
echo "\n--- All Approved Rows ---\n";
$res3 = $mysqli->query("SELECT id, approved_by FROM tahfidz_teacher_attendance WHERE status_approval = 'approved'");
while ($r = $res3->fetch_assoc()) {
    echo "ID: " . $r['id'] . " ApprovedBy: " . var_export($r['approved_by'], true) . "\n";
}
?>
