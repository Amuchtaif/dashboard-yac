<?php
// List Users correct column
$host = "127.0.0.1";
$db_name = "attendance_db";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);

    // Find 'Idris' or 'Ma'
    $stmt = $conn->query("SELECT id, full_name, unit_id, division_id, position_id FROM employees WHERE full_name LIKE '%Idris%' OR full_name LIKE '%Ma%' LIMIT 10");
    if ($stmt) {
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>