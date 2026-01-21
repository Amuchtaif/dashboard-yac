<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = trim($_POST['name']);
    $kode = trim($_POST['code']);
    $deskripsi = trim($_POST['description']);
    $kategori = $_POST['category'] ?? 'Umum';

    if (!empty($nama) && !empty($kode)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Cek duplikasi kode
        $check = $conn->prepare("SELECT id FROM subjects WHERE code = ?");
        $check->execute([$kode]);
        if ($check->rowCount() > 0) {
            header("Location: ../../views/subjects/form.php?error=Kode mata pelajaran sudah ada");
            exit;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO subjects (name, code, description, category) VALUES (:nama, :kode, :deskripsi, :kategori)");
            $stmt->bindParam(':nama', $nama);
            $stmt->bindParam(':kode', $kode);
            $stmt->bindParam(':deskripsi', $deskripsi);
            $stmt->bindParam(':kategori', $kategori);
            $stmt->execute();

            header("Location: ../../views/subjects/index.php?success=Mata pelajaran berhasil ditambahkan");
        } catch (PDOException $e) {
            header("Location: ../../views/subjects/form.php?error=Kesalahan Database: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/subjects/form.php?error=Nama dan Kode wajib diisi");
    }
}
?>