<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // 1. Sanitize & Retrieve Input
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $nama_siswa = ucwords(strtolower($nama_siswa));
    $nomor_induk = trim($_POST['nomor_induk'] ?? '');
    $class_id = trim($_POST['class_id'] ?? ''); 
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $status = trim($_POST['status'] ?? 'Aktif');
    $spp = trim($_POST['spp'] ?? 0);
    $daftar_ulang = trim($_POST['daftar_ulang'] ?? 0);
    $active_year_id = isset($_POST['active_year_id']) ? intval($_POST['active_year_id']) : 0;

    // 2. Validation
    if (empty($id) || empty($nama_siswa) || empty($nomor_induk)) {
        header("Location: ../../views/students/edit.php?id=$id&error=Nama dan Nomor Induk wajib diisi");
        exit();
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $conn->beginTransaction();

        // 3. File Upload (Foto)
        $fotoName = null;
        $fotoSql = ""; 
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['foto']['tmp_name'];
            $fileName = $_FILES['foto']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = '../../uploads/students/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = "SISWA_" . preg_replace('/[^A-Za-z0-9]/', '', $nomor_induk) . "_" . time() . "." . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $fotoName = $newFileName;
                    $fotoSql = ", foto = :foto";
                    
                    // Optional: Delete old photo?
                    // We can fetch old photo name and unlink it here if needed.
                }
            }
        }

        // 4. Update Student Profile
        $queryStudent = "UPDATE students SET 
            nama_siswa = :nama, 
            nomor_induk = :nisn, 
            tempat_lahir = :tmplahir, 
            tanggal_lahir = :tglahir, 
            alamat = :alamat, 
            status = :status, 
            spp = :spp, 
            daftar_ulang = :daftar
            $fotoSql
            WHERE id = :id";

        $stmt = $conn->prepare($queryStudent);
        $stmt->bindValue(':nama', $nama_siswa);
        $stmt->bindValue(':nisn', $nomor_induk);
        $stmt->bindValue(':tmplahir', $tempat_lahir);
        $stmt->bindValue(':tglahir', $tanggal_lahir);
        $stmt->bindValue(':alamat', $alamat);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':spp', $spp);
        $stmt->bindValue(':daftar', $daftar_ulang);
        $stmt->bindValue(':id', $id);
        
        if ($fotoName) {
            $stmt->bindValue(':foto', $fotoName);
        }
        
        $stmt->execute();

        // 5. Update Class Placement for Active Year
        if ($active_year_id && $class_id !== '') {
            // Check if exists
            $checkStmt = $conn->prepare("SELECT id FROM student_class_history WHERE student_id = :sid AND academic_year_id = :ayid");
            $checkStmt->execute([':sid' => $id, ':ayid' => $active_year_id]);
            $exists = $checkStmt->fetchColumn();

            if ($exists) {
                // Update
                $updateHist = $conn->prepare("UPDATE student_class_history SET class_id = :cid WHERE id = :hist_id");
                $updateHist->execute([':cid' => $class_id, ':hist_id' => $exists]);
            } else {
                // Insert
                $insertHist = $conn->prepare("INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, joined_at) VALUES (:sid, :cid, :ayid, 'ACTIVE', NOW())");
                $insertHist->execute([':sid' => $id, ':cid' => $class_id, ':ayid' => $active_year_id]);
            }
        }

        $conn->commit();

        header("Location: ../../views/students/index.php?success=Data+Siswa+Berhasil+Diperbarui");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        header("Location: ../../views/students/edit.php?id=$id&error=Gagal menyimpan data: " . $e->getMessage());
        exit();
    }
} else {
        header("Location: ../../views/students/index.php?error=Operasi+gagal");
    exit();
}
?>
