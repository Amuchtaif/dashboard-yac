<?php
require_once '../../config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "Date: " . date('Y-m-d') . "\n";
$emp_count = $conn->query("SELECT COUNT(*) FROM employees")->fetchColumn();
echo "Total Employees: $emp_count\n";

echo "--- Chart Data ---\n";
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));

    // Debug the raw query for one of the days
    if ($i == 0) {
        echo "Querying for $d ...\n";
    }

    $sql = "SELECT COUNT(DISTINCT user_id) FROM attendance WHERE date = '$d' AND status IN ('Present', 'Late', 'Hadir', 'hadir', 'Telat')";
    $daily_count = $conn->query($sql)->fetchColumn();

    echo "$d: $daily_count\n";
}
?>