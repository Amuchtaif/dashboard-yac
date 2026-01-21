<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $division_id = $_POST['division_id'] ?? '';
    $schedule_id = $_POST['schedule_id'] ?? '';

    if (empty($id) || empty($name) || empty($division_id)) {
        header("Location: ../../views/units/edit.php?id=$id&error=Please fill in all fields");
        exit;
    }

    $stmt = $conn->prepare("UPDATE units SET name = :name, division_id = :division_id, schedule_id = :schedule_id WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':division_id', $division_id);
    $stmt->bindValue(':schedule_id', !empty($schedule_id) ? $schedule_id : null, PDO::PARAM_INT);
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
