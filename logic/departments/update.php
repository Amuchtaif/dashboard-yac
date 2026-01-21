<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'];
    $name = $_POST['name'];
    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

    if (!empty($name) && !empty($id)) {
        try {
            $stmt = $conn->prepare("UPDATE divisions SET name = :name, schedule_id = :schedule_id, manager_id = :manager_id WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':schedule_id' => $schedule_id,
                ':manager_id' => $manager_id,
                ':id' => $id
            ]);

            header("Location: ../../views/departments/index.php?success=Department updated successfully");
            exit;
        } catch (PDOException $e) {
            header("Location: ../../views/departments/form.php?id=$id&error=Database Error: " . $e->getMessage());
            exit;
        }
    } else {
        header("Location: ../../views/departments/form.php?id=$id&error=Name is required");
        exit;
    }
} else {
    header("Location: ../../views/departments/index.php");
    exit;
}
?>