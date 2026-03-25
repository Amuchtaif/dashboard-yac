<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $nama = trim($_POST['name']);
    $kode = trim($_POST['code']);
    $deskripsi = trim($_POST['description']);
    $kategori = $_POST['category'] ?? 'Umum';

    if (!empty($id) && !empty($nama) && !empty($kode)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Cek duplikasi kode kecuali ID ini
        $check = $conn->prepare("SELECT id FROM subjects WHERE code = ? AND id != ?");
        $check->execute([$kode, $id]);
        if ($check->rowCount() > 0) {
            header("Location: ../../views/subjects/form.php?id=$id&error=Kode mata pelajaran sudah digunakan");
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE subjects SET name = :nama, code = :kode, description = :deskripsi, category = :kategori WHERE id = :id");
            $stmt->bindParam(':nama', $nama);
            $stmt->bindParam(':kode', $kode);
            $stmt->bindParam(':deskripsi', $deskripsi);
            $stmt->bindParam(':kategori', $kategori);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

        header("Location: ../../views/subjects/index.php?success=Mata+pelajaran+berhasil+diperbarui");
        } catch (PDOException $e) {
            header("Location: ../../views/subjects/form.php?id=$id&error=Kesalahan Database: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/subjects/form.php?id=$id&error=Data tidak lengkap");
    }
}
?>
