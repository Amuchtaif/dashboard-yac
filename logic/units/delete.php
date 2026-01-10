<?php
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("DELETE FROM units WHERE id = :id");
    $stmt->bindParam(':id', $_GET['id']);

    if ($stmt->execute()) {
        header("Location: ../../views/units/index.php?success=Unit deleted successfully");
    } else {
        header("Location: ../../views/units/index.php?error=Failed to delete unit");
    }
} else {
    header("Location: ../../views/units/index.php");
}
exit;
