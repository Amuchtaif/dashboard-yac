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

        // Logic Approval Bertingkat:
        // Level 4/5 (Staf/Guru) -> Cari Level 3 (Ka. Unit) di unit yang sama
        // Level 3 (Ka. Unit)    -> Cari Level 2 (Ka. Bidang) di divisi yang sama
        // Level 2 (Ka. Bidang)  -> Cari Level 1 (Mudir)

        $approverQuery = "";
        $approverParams = [];

        if ($level >= 4) {
            // KASUS 1: STAF -> Cari Kepala Unit
            // Cek apakah staf punya unit?
            if (!empty($unit_id)) {
                $approverQuery = "SELECT e.id FROM employees e 
                                  JOIN positions p ON e.position_id = p.id 
                                  WHERE e.unit_id = :val_id AND p.level = 3 LIMIT 1";
                $approverParams = [':val_id' => $unit_id];
            } else {
                // Jika staf tidak punya unit (staf divisi), langsung ke Kabid
                $approverQuery = "SELECT e.id FROM employees e 
                                  JOIN positions p ON e.position_id = p.id 
                                  WHERE e.division_id = :val_id AND p.level = 2 LIMIT 1";
                $approverParams = [':val_id' => $division_id];
            }

        } elseif ($level == 3) {
            // KASUS 2: KEPALA UNIT -> Cari Kepala Bidang
            $approverQuery = "SELECT e.id FROM employees e 
                              JOIN positions p ON e.position_id = p.id 
                              WHERE e.division_id = :val_id AND p.level = 2 LIMIT 1";
            $approverParams = [':val_id' => $division_id];

        } elseif ($level == 2) {
            // KASUS 3: KEPALA BIDANG -> Cari Mudir (Level 1)
            $approverQuery = "SELECT e.id FROM employees e 
                              JOIN positions p ON e.position_id = p.id 
                              WHERE p.level = 1 LIMIT 1";
            $approverParams = [];
        }

        // Eksekusi pencarian Approver
        if (!empty($approverQuery)) {
            $stmtApp = $conn->prepare($approverQuery);
            $stmtApp->execute($approverParams);
            $approverData = $stmtApp->fetch(PDO::FETCH_ASSOC);

            if ($approverData) {
                $approver_id = $approverData['id'];
            }
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