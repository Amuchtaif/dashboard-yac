<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $db = new Database();
    $conn = $db->getConnection();

    // Get current status
    $stmt = $conn->prepare("SELECT status FROM employees WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $new_status = ($employee['status'] === 'active') ? 'inactive' : 'active';

        $updateStmt = $conn->prepare("UPDATE employees SET status = :status WHERE id = :id");
        $updateStmt->execute([
            ':status' => $new_status,
            ':id' => $id
        ]);
    }
}

// Redirect back to employees list
header("Location: ../../views/employees/index.php");
exit();
?>