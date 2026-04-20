<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: ../../views/students/index.php?success=Siswa+berhasil+dihapus");
    } catch (PDOException $e) {
        header("Location: ../../views/students/index.php?error=Gagal+menghapus+siswa");
    }
}
