<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $department_id = $_POST['department_id'] ?? '';

    if (empty($id) || empty($name) || empty($department_id)) {
        header("Location: ../../views/units/edit.php?id=$id&error=Please fill in all fields");
        exit;
    }

    $stmt = $conn->prepare("UPDATE units SET name = :name, department_id = :department_id WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':department_id', $department_id);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: ../../views/units/index.php?success=Unit updated successfully");
    } else {
        header("Location: ../../views/units/edit.php?id=$id&error=Failed to update unit");
    }
} else {
    header("Location: ../../views/units/index.php");
}
exit;
