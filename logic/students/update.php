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

    $is_admin = (isset($_SESSION['position_name']) && $_SESSION['position_name'] === 'Administrator');
    $user_stmt = $conn->prepare("
        SELECT e.unit_id, p.level, u.name as unit_name
        FROM employees e 
        LEFT JOIN positions p ON e.position_id = p.id 
        LEFT JOIN units u ON e.unit_id = u.id
        WHERE e.id = :user_id LIMIT 1
    ");
    $user_stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    $user_level = $user_data ? (int)$user_data['level'] : 5;
    $user_unit_name = $user_data ? $user_data['unit_name'] : '';

    $mapped_education_unit_ids = [];
    if (!empty($user_unit_name)) {
        $clean_unit_name = str_replace(["'", " "], ["", ""], strtolower($user_unit_name));
        $edu_stmt = $conn->query("SELECT id, name FROM education_units");
        while ($edu_row = $edu_stmt->fetch(PDO::FETCH_ASSOC)) {
            $clean_edu_name = str_replace(["'", " "], ["", ""], strtolower($edu_row['name']));
            if (strpos($clean_unit_name, $clean_edu_name) !== false || strpos($clean_edu_name, $clean_unit_name) !== false) {
                $mapped_education_unit_ids[] = (int)$edu_row['id'];
            }
        }
    }

    if (!$is_admin && $user_level > 2 && !empty($mapped_education_unit_ids)) {
        // Verify target student belongs to user unit
        $student_unit_check = $conn->prepare("
            SELECT gl.education_unit_id, s.tingkat 
            FROM students s
            LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id AND sch.status = 'ACTIVE'
            LEFT JOIN grade_levels gl ON sch.class_id = gl.id
            WHERE s.id = :id LIMIT 1
        ");
        $student_unit_check->execute([':id' => $id, ':active_year_id' => $active_year_id]);
        $student_unit_data = $student_unit_check->fetch(PDO::FETCH_ASSOC);
        
        $student_edu_unit_id = $student_unit_data ? $student_unit_data['education_unit_id'] : null;
        $student_tingkat = $student_unit_data ? $student_unit_data['tingkat'] : '';
        
        $edu_names_stmt = $conn->prepare("SELECT name FROM education_units WHERE id IN (" . implode(',', $mapped_education_unit_ids) . ")");
        $edu_names_stmt->execute();
        $edu_names = $edu_names_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $allowed = false;
        if ($student_edu_unit_id && in_array((int)$student_edu_unit_id, $mapped_education_unit_ids)) {
            $allowed = true;
        } else if ($student_tingkat && in_array($student_tingkat, $edu_names)) {
            $allowed = true;
        }
        
        if (!$allowed) {
            header("Location: ../../views/students/edit.php?id=$id&error=Akses+ditolak+Siswa+di+luar+unit+kerja+Anda");
            exit();
        }

        // Verify new class_id is in user unit
        if (!empty($class_id)) {
            $class_check = $conn->prepare("SELECT education_unit_id FROM grade_levels WHERE id = :cid");
            $class_check->execute([':cid' => $class_id]);
            $edu_unit_id = $class_check->fetchColumn();
            if (!$edu_unit_id || !in_array((int)$edu_unit_id, $mapped_education_unit_ids)) {
                header("Location: ../../views/students/edit.php?id=$id&error=Akses+ditolak+Kelas+di+luar+unit+kerja+Anda");
                exit();
            }
        }
    }

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
        $old_stmt = $conn->prepare("SELECT * FROM students WHERE id = :id LIMIT 1");
        $old_stmt->execute([':id' => $id]);
        $old_student_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

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

        Logger::activity(
            'Siswa',
            'UPDATE',
            "Mengubah data siswa '$nama_siswa'",
            [
                'table' => 'students',
                'record_id' => $id,
                'old_data' => $old_student_data ?: null,
                'new_data' => [
                    'nama_siswa' => $nama_siswa,
                    'nomor_induk' => $nomor_induk,
                    'status' => $status,
                    'class_id' => $class_id
                ]
            ]
        );

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
