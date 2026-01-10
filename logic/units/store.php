<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $name = $_POST['name'] ?? '';
    $department_id = $_POST['department_id'] ?? '';

    if (empty($name) || empty($department_id)) {
        header("Location: ../../views/units/index.php?error=Please fill in all fields");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO units (name, department_id) VALUES (:name, :department_id)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':department_id', $department_id);

    if ($stmt->execute()) {
        header("Location: ../../views/units/index.php?success=Unit created successfully");
    } else {
        header("Location: ../../views/units/index.php?error=Failed to create unit");
    }
} else {
    header("Location: ../../views/units/index.php");
}
exit;
