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
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: ../../views/employees/index.php?success=Pegawai+berhasil+dihapus" . $redirect_qs);
    } catch (PDOException $e) {
        header("Location: ../../views/employees/index.php?error=Gagal+menghapus+pegawai" . $redirect_qs);
    }
}
