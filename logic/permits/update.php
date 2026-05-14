<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $id = $_POST['id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $permit_type = $_POST['permit_type'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $status = $_POST['status'] ?? 'Pending';

    if (empty($id) || empty($employee_id) || empty($permit_type) || empty($start_date) || empty($end_date)) {
        header("Location: ../../views/permits/edit.php?id=$id&error=Please fill in all required fields");
        exit;
    }

    // Handle File Upload for Update
    $attachment = null;
    $updateAttachmentSql = "";
    if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] !== '') {
        if ($_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['attachment']['tmp_name'];
            $fileName = $_FILES['attachment']['name'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));
            $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

            if (in_array($fileExtension, $allowedfileExtensions)) {
                $uploadFileDir = '../../uploads/permits/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $attachment = $newFileName;
                    $updateAttachmentSql = ", attachment = :attachment";
                } else {
                    header("Location: ../../views/permits/edit.php?id=$id&error=Gagal mengupload file baru");
                    exit;
                }
            } else {
                header("Location: ../../views/permits/edit.php?id=$id&error=Format file tidak valid");
                exit;
            }
        } else {
             header("Location: ../../views/permits/edit.php?id=$id&error=Gagal upload file (Error: ".$_FILES['attachment']['error'].")");
             exit;
        }
    }

    $sql = "UPDATE permits SET 
            employee_id = :employee_id, 
            permit_type = :permit_type, 
            start_date = :start_date, 
            end_date = :end_date, 
            reason = :reason, 
            status = :status 
            $updateAttachmentSql 
            WHERE id = :id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':employee_id', $employee_id);
    $stmt->bindParam(':permit_type', $permit_type);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':reason', $reason);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $id);
    
    if ($attachment) {
        $stmt->bindParam(':attachment', $attachment);
    }

    if ($stmt->execute()) {
        header("Location: ../../views/permits/index.php?success=Izin berhasil diperbarui");
    } else {
        header("Location: ../../views/permits/edit.php?id=$id&error=Gagal memperbarui database");
    }
} else {
        header("Location: ../../views/permits/index.php?error=Operasi+gagal");
}
exit;
