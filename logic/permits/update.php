<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $permit_type = $_POST['permit_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $status = $_POST['status'] ?? 'Pending';

    if (empty($id) || empty($employee_id) || empty($permit_type) || empty($start_date) || empty($end_date)) {
        header("Location: ../../views/permits/edit.php?id=$id&error=Please fill in all required fields");
        exit;
    }

    $stmt = $conn->prepare("UPDATE permits SET employee_id = :employee_id, permit_type = :permit_type, start_date = :start_date, end_date = :end_date, reason = :reason, status = :status WHERE id = :id");
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':permit_type', $permit_type);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        header("Location: ../../views/permits/index.php?success=Permit+updated+successfully");
    } else {
        header("Location: ../../views/permits/edit.php?id=$id&error=Failed to update permit");
    }
} else {
        header("Location: ../../views/permits/index.php?error=Operasi+gagal");
}
exit;
