<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$params = $_GET;
unset($params['id']);
$qs = http_build_query($params);
$redirect_qs = $qs ? "&" . $qs : "";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $db = new Database();
    $conn = $db->getConnection();

    // Get current status
    $stmt = $conn->prepare("SELECT full_name, status FROM employees WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        $emp_name = $employee['full_name'];
        $old_status = $employee['status'];
        $new_status = ($old_status === 'active') ? 'inactive' : 'active';

        $updateStmt = $conn->prepare("UPDATE employees SET status = :status WHERE id = :id");
        $updateStmt->execute([
            ':status' => $new_status,
            ':id' => $id
        ]);

        $status_label = ($new_status === 'active') ? 'Aktif' : 'Non Aktif';

        Logger::activity(
            'Pegawai',
            'UPDATE_STATUS',
            "Mengubah status pegawai '$emp_name' menjadi '$status_label'",
            [
                'table' => 'employees',
                'record_id' => $id,
                'old_data' => ['status' => $old_status],
                'new_data' => ['status' => $new_status]
            ]
        );
    }
}

// Redirect back to employees list
header("Location: ../../views/employees/index.php?success=Status+berhasil+diubah" . $redirect_qs);
exit();
?>
