<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Construct redirect query string from current GET params
    $params = $_GET;
    unset($params['id']);
    $qs = http_build_query($params);
    $redirect_qs = $qs ? "&" . $qs : "";

    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        header("Location: ../../views/employees/index.php?error=Anda+tidak+dapat+menghapus+akun+Anda+sendiri" . $redirect_qs);
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $old_stmt = $conn->prepare("SELECT full_name, nik FROM employees WHERE id = :id LIMIT 1");
        $old_stmt->execute([':id' => $id]);
        $old_emp = $old_stmt->fetch(PDO::FETCH_ASSOC);
        $emp_name = $old_emp ? $old_emp['full_name'] : "ID $id";

        $stmt = $conn->prepare("DELETE FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        Logger::activity(
            'Pegawai',
            'DELETE',
            "Menghapus pegawai '$emp_name'",
            [
                'table' => 'employees',
                'record_id' => $id,
                'old_data' => $old_emp ?: null
            ]
        );

        header("Location: ../../views/employees/index.php?success=Pegawai+berhasil+dihapus" . $redirect_qs);
    } catch (PDOException $e) {
        header("Location: ../../views/employees/index.php?error=Gagal+menghapus+pegawai" . $redirect_qs);
    }
}
