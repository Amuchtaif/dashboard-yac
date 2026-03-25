<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $department_id = $_POST['department_id'];

    if (!empty($name) && !empty($department_id)) {
        $db = new Database();
        $conn = $db->getConnection();

        try {
            $stmt = $conn->prepare("INSERT INTO units (name, department_id) VALUES (:name, :department_id)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':department_id', $department_id);
            $stmt->execute();
        header("Location: ../../views/departments/index.php?success=Unit+Created");
        } catch (PDOException $e) {
        header("Location: ../../views/departments/index.php?error=Error+Creating+Unit");
        }
    } else {
        header("Location: ../../views/departments/index.php?error=Fields+Required");
    }
}
