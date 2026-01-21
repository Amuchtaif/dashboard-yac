<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM divisions WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: ../../views/departments/index.php?success=Department Deleted");
    } catch (PDOException $e) {
        header("Location: ../../views/departments/index.php?error=Error Deleting Department");
    }
}
