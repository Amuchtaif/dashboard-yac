<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $id = $_GET['id'];

    $query = "DELETE FROM grade_levels WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        redirect('views/grade_levels/index.php?success=Grade Level deleted successfully');
    } else {
        redirect('views/grade_levels/index.php?error=Failed to delete Grade Level');
    }
}
?>