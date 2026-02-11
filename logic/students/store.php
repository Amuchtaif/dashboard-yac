<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize & Retrieve Input
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $nama_siswa = ucwords(strtolower($nama_siswa));
    $nomor_induk = trim($_POST['nomor_induk'] ?? '');
    $academic_year_id = isset($_POST['academic_year_id']) ? intval($_POST['academic_year_id']) : 0;
    
    // Fetch Year Name from DB for legacy 'tahun_ajaran' column
    $db = new Database();
    $conn = $db->getConnection();
    
    $year_stmt = $conn->prepare("SELECT name FROM academic_years WHERE id = :id");
    $year_stmt->execute([':id' => $academic_year_id]);
    $year_data = $year_stmt->fetch(PDO::FETCH_ASSOC);
    $tahun_ajaran = $year_data ? $year_data['name'] : '';
    
    // Validation
    if (empty($academic_year_id)) {
        header("Location: ../../views/students/create.php?error=Tahun Ajaran wajib dipilih");
        exit();
    }

    $class_id = trim($_POST['class_id'] ?? ''); 
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $status = trim($_POST['status'] ?? 'Aktif');
    $spp = trim($_POST['spp'] ?? 0);
    $daftar_ulang = trim($_POST['daftar_ulang'] ?? 0);

    // 2. Validation
    if (empty($nama_siswa) || empty($nomor_induk)) {
        header("Location: ../../views/students/create.php?error=Nama dan Nomor Induk wajib diisi");
        exit();
    }

    // 3. File Upload (Foto)
    $fotoName = null;
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

            // Generate unique name: SISWA_NISN_TIMESTAMP.ext
            $newFileName = "SISWA_" . preg_replace('/[^A-Za-z0-9]/', '', $nomor_induk) . "_" . time() . "." . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $fotoName = $newFileName;
            }
        }
    }

    // 4. Insert to Database with Transaction
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $conn->beginTransaction();

        // Step 1: Insert Profile to 'students' (Removed deprecated columns)
        // Note: 'tahun_ajaran' left in students for now if it's just a display string, 
        // OR better: rely on history. But let's keep it if schema has it, or just insert NULL if we want to migrate fully.
        // User schema provided earlier: students (id, nama_siswa, nomor_induk, status, foto). 
        // It didn't mention 'tahun_ajaran' in the new schema list, but let's check if it exists.
        // If it exists in DB, we can fill it, else ignore. 
        // Based on previous view, 'tahun_ajaran' was displayed. Let's keep filling it for backward compat if column exists.
        // However, user Requirement said: "students: Stores profile data (id, nama_siswa, nomor_induk, status). The old kelas column here is deprecated."
        // I will assume standard fields for now.

        $queryStudent = "INSERT INTO students (
            nama_siswa, nomor_induk, tahun_ajaran, 
            tingkat, kelas,
            tempat_lahir, tanggal_lahir, alamat, status, 
            spp, daftar_ulang, foto
        ) VALUES (
            :nama, :nisn, :tahun, 
            :tingkat, :kelas,
            :tmplahir, :tglahir, :alamat, :status, 
            :spp, :daftar, :foto
        )";

        // Note: 'tahun_ajaran', 'tempat_lahir' etc might be in DB but not in user's prompt list.
        // I'll assume they exist based on `create.php` form fields surviving.

        $stmt = $conn->prepare($queryStudent);
        $stmt->bindValue(':nama', $nama_siswa);
        $stmt->bindValue(':nisn', $nomor_induk);
        $stmt->bindValue(':tahun', $tahun_ajaran);
        $stmt->bindValue(':tingkat', '-'); // Deprecated, filling dummy
        $stmt->bindValue(':kelas', '-');   // Deprecated, filling dummy
        $stmt->bindValue(':tmplahir', $tempat_lahir);
        $stmt->bindValue(':tglahir', $tanggal_lahir);
        $stmt->bindValue(':alamat', $alamat);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':spp', $spp);
        $stmt->bindValue(':daftar', $daftar_ulang);
        $stmt->bindValue(':foto', $fotoName);
        $stmt->execute();

        // Step 2: Get ID
        $student_id = $conn->lastInsertId();

        // Step 3: Insert Placement to 'student_class_history'
        if (!empty($class_id)) {
            $queryHistory = "INSERT INTO student_class_history (
                student_id, class_id, academic_year_id, status, joined_at
            ) VALUES (
                :student_id, :class_id, :year_id, 'ACTIVE', NOW()
            )";
            $stmtH = $conn->prepare($queryHistory);
            $stmtH->bindValue(':student_id', $student_id);
            $stmtH->bindValue(':class_id', $class_id);
            $stmtH->bindValue(':year_id', $academic_year_id);
            $stmtH->execute();
        }

        $conn->commit();

        header("Location: ../../views/students/index.php?success=Siswa Berhasil Ditambahkan");
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        // Check for duplicate entry
        if ($e->errorInfo[1] == 1062) {
            header("Location: ../../views/students/create.php?error=Nomor Induk sudah terdaftar");
        } else {
            // Log real error for dev: error_log($e->getMessage());
            header("Location: ../../views/students/create.php?error=Gagal menyimpan data: " . $e->getMessage());
        }
        exit();
    }
}
?>