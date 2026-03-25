<?php
require_once '../../config/database.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_GET['id'];
    $action = $_GET['action'];

    // Determine status
    $status = ($action === 'approve') ? 'Approved' : (($action === 'reject') ? 'Rejected' : '');

    if ($status) {
        $stmt = $conn->prepare("UPDATE permits SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            header("Location: ../../views/permits/index.php?success=Permit " . strtolower($status) . " successfully");
        } else {
        header("Location: ../../views/permits/index.php?error=Failed+to+update+status");
        }
    } else {
        header("Location: ../../views/permits/index.php?error=Invalid+action");
    }
} else {
        header("Location: ../../views/permits/index.php?error=Operasi+gagal");
}
exit;
