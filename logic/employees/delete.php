<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        header("Location: ../../views/employees/index.php?error=Cannot+delete+your+own+account");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: ../../views/employees/index.php?success=Employee+Deleted");
    } catch (PDOException $e) {
        header("Location: ../../views/employees/index.php?error=Error+Deleting+Employee");
    }
}
