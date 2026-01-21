<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $name = trim($_POST['name']);
    $level = (int) $_POST['level'];

    if (empty($id) || empty($name) || empty($level)) {
        header("Location: " . BASE_URL . "/views/positions/form.php?id=$id&error=" . urlencode("All fields are required"));
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("UPDATE positions SET name = :name, level = :level WHERE id = :id");
        $stmt->execute([':name' => $name, ':level' => $level, ':id' => $id]);

        header("Location: " . BASE_URL . "/views/positions/index.php?success=" . urlencode("Position updated successfully"));
        exit;
    } catch (PDOException $e) {
        header("Location: " . BASE_URL . "/views/positions/form.php?id=$id&error=" . urlencode("Database Error: " . $e->getMessage()));
        exit;
    }
}
?>