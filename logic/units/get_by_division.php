<?php
require_once '../../config/database.php';

if (isset($_GET['division_id'])) {
    $division_id = $_GET['division_id'];

    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT id, name FROM units WHERE division_id = :division_id ORDER BY name ASC");
    $stmt->execute([':division_id' => $division_id]);
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($units);
}
?>