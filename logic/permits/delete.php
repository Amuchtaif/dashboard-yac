<?php
require_once '../../config/database.php';

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM permits WHERE id = :id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: ../../views/permits/index.php?success=Permit+deleted+successfully");
    } else {
        header("Location: ../../views/permits/index.php?error=Failed+to+delete+permit");
    }
} else {
        header("Location: ../../views/permits/index.php?error=Operasi+gagal");
}
exit;
