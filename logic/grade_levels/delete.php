<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $id = $_GET['id'];

    // Construct redirect query string from current GET params
    $params = $_GET;
    unset($params['id']);
    $qs = http_build_query($params);
    $redirect_qs = $qs ? "&" . $qs : "";

    try {
        $query = "DELETE FROM grade_levels WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $id);

        if ($stmt->execute()) {
            redirect('views/grade_levels/index.php?success=' . urlencode('Kelas berhasil dihapus.') . $redirect_qs);
        } else {
            redirect('views/grade_levels/index.php?error=' . urlencode('Gagal menghapus kelas.') . $redirect_qs);
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            redirect('views/grade_levels/index.php?error=Tidak+dapat+menghapus+kelas+karena+masih+ada+riwayat+siswa+di+dalamnya.+Silakan+hapus+data+siswa+terkait+terlebih+dahulu.' . $redirect_qs);
        } else {
            redirect('views/grade_levels/index.php?error=' . urlencode('Database error: ' . $e->getMessage()) . $redirect_qs);
        }
    }
}
?>
