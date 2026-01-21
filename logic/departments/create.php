<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);

    if (!empty($name)) {
        $db = new Database();
        $conn = $db->getConnection();
        $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

        try {
            $stmt = $conn->prepare("INSERT INTO divisions (name, schedule_id, manager_id) VALUES (:name, :schedule_id, :manager_id)");
            $stmt->execute([
                ':name' => $name,
                ':schedule_id' => $schedule_id,
                ':manager_id' => $manager_id
            ]);
            header("Location: ../../views/departments/index.php?success=Department Created");
        } catch (PDOException $e) {
            header("Location: ../../views/departments/index.php?error=Error Creating Department: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/departments/index.php?error=Name Required");
    }
}
