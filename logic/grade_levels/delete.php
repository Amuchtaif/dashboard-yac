<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $id = $_GET['id'];

    try {
        $query = "DELETE FROM grade_levels WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            redirect('views/grade_levels/index.php?success=' . urlencode('Kelas berhasil dihapus.'));
        } else {
            redirect('views/grade_levels/index.php?error=' . urlencode('Gagal menghapus kelas.'));
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
        redirect('views/grade_levels/index.php?error=Tidak+dapat+menghapus+kelas+karena+masih+ada+riwayat+siswa+di+dalamnya.+Silakan+hapus+data+siswa+terkait+terlebih+dahulu.');
        } else {
            redirect('views/grade_levels/index.php?error=' . urlencode('Database error: ' . $e->getMessage()));
        }
    }
}
?>
