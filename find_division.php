<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, name FROM divisions WHERE name LIKE '%Education%' OR name LIKE '%Pendidikan%'");
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($result) {
    foreach ($result as $row) {
        echo "ID: " . $row['id'] . " - Name: " . $row['name'] . "\n";
    }
} else {
    echo "No division found matching Education/Pendidikan.\n";
    // List all to be sure
    $all = $conn->query("SELECT id, name FROM divisions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($all as $row)
        echo "ALL: " . $row['id'] . "-" . $row['name'] . "\n";
}
?>