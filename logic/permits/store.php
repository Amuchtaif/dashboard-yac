<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Inputs
    $employee_id = $_POST['employee_id'] ?? '';
    $permit_type = $_POST['permit_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Status default is Pending
    $status = 'Pending';

    // Validation
    if (empty($employee_id) || empty($permit_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        header("Location: ../../views/permits/create.php?error=Semua kolom wajib diisi");
        exit;
    }

    // 2. File Upload Handling
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['attachment']['tmp_name'];
        $fileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];
        $fileType = $_FILES['attachment']['type'];

        // Ambil ekstensi file
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory
            $uploadFileDir = '../../uploads/permits/';
            // Buat folder jika belum ada
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            // Unique Name: timestamp_random.ext
            $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $attachment = $newFileName;
            } else {
                header("Location: ../../views/permits/create.php?error=Gagal mengupload file");
                exit;
            }
        } else {
            header("Location: ../../views/permits/create.php?error=Format file tidak valid. Gunakan: jpg, png, pdf");
            exit;
        }
    }

    // 3. Hierarchical Approver Logic (PERBAIKAN UTAMA DISINI)
    $approver_id = null;

    // Ambil Data Karyawan (Level, Unit, dan Division)
    // Perbaikan: Menggunakan e.division_id (sesuai schema), bukan department_id
    $stmt = $conn->prepare("
        SELECT e.unit_id, e.division_id, p.level 
        FROM employees e 
        LEFT JOIN positions p ON e.position_id = p.id 
        WHERE e.id = :id
    ");
    $stmt->execute([':id' => $employee_id]);
    $empData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empData) {
        $level = $empData['level'];
        $unit_id = $empData['unit_id'];
        $division_id = $empData['division_id']; // Menggunakan division_id

        // LOGIKA APPROVAL BERTINGKAT (WATERFALL) - SYNCED WITH API
        // 1. STAFF / GURU (Level 4 atau 5)
        if ($level >= 4) {
            // A. Cari Kepala Unit (Level 3) di Unit yang sama
            if (!empty($unit_id)) {
                $stmtBoss = $conn->prepare("SELECT e.id FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.unit_id = :val AND p.level = 3 AND e.status = 'active' LIMIT 1");
                $stmtBoss->execute([':val' => $unit_id]);
                $approver_id = $stmtBoss->fetchColumn();
            }
            
            // B. Jika tidak ada Ka Unit, cari Kabid (Level 2) di Divisi yang sama
            if (!$approver_id && !empty($division_id)) {
                $stmtBoss = $conn->prepare("SELECT e.id FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.division_id = :val AND p.level = 2 AND e.status = 'active' LIMIT 1");
                $stmtBoss->execute([':val' => $division_id]);
                $approver_id = $stmtBoss->fetchColumn();
            }
        } 
        // 2. KEPALA UNIT (Level 3)
        elseif ($level == 3) {
            // Cari Kabid (Level 2) di Divisi yang sama
            if (!empty($division_id)) {
                $stmtBoss = $conn->prepare("SELECT e.id FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.division_id = :val AND p.level = 2 AND e.status = 'active' LIMIT 1");
                $stmtBoss->execute([':val' => $division_id]);
                $approver_id = $stmtBoss->fetchColumn();
            }
        }
        // 3. KEPALA BIDANG (Level 2)
        elseif ($level == 2) {
            // Mudir (Level 1) HANYA menerima dari Level 2 (Kabid)
            $stmtMudir = $conn->prepare("SELECT e.id FROM employees e JOIN positions p ON e.position_id = p.id WHERE p.level = 1 AND e.status = 'active' LIMIT 1");
            $stmtMudir->execute();
            $approver_id = $stmtMudir->fetchColumn();
        }

        // --- PREVENT SELF-APPROVAL ---
        if ($approver_id == $employee_id) {
            $approver_id = null;
        }
    }

    // 4. Save to Database
    $sql = "INSERT INTO permits (employee_id, permit_type, start_date, end_date, reason, attachment, status, approver_id) 
            VALUES (:employee_id, :permit_type, :start_date, :end_date, :reason, :attachment, :status, :approver_id)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':permit_type', $permit_type);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':attachment', $attachment);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':approver_id', $approver_id);

    if ($stmt->execute()) {
        header("Location: ../../views/permits/index.php?success=Pengajuan izin berhasil dikirim");
    } else {
        header("Location: ../../views/permits/create.php?error=Gagal membuat izin");
    }

} else {
    header("Location: ../../views/permits/index.php");
}
exit;
?>