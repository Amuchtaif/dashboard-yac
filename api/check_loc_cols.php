<?php
include_once '../config/database.php';
$database = new Database();
$db = $database->getConnection();
try {
    $stmt = $db->query("SELECT * FROM locations LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(array_keys($row));
} catch (PDOException $e) {
    echo $e->getMessage();
}
?>
