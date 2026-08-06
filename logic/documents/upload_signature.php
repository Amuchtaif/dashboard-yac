<?php
// logic/documents/upload_signature.php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('document.sign');

$db = new Database();
$conn = $db->getConnection();

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['signature']['tmp_name'];
        $file_name = $_FILES['signature']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'png') {
            redirect("views/documents/signature.php?error=Format+file+harus+PNG");
        }

        // Create uploads/signatures directory if not exists
        $upload_dir = BASE_PATH . '/uploads/signatures';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $new_filename = 'sig_' . $user_id . '_' . time() . '.png';
        $dest_path = $upload_dir . '/' . $new_filename;

        // Fetch old signature to delete it and keep filesystem clean
        $stmtOld = $conn->prepare("SELECT signature_path FROM employees WHERE id = ?");
        $stmtOld->execute([$user_id]);
        $old_path = $stmtOld->fetchColumn();

        if (move_uploaded_file($file_tmp, $dest_path)) {
            $signature_path = 'uploads/signatures/' . $new_filename;

            // Update database
            $stmtUpdate = $conn->prepare("UPDATE employees SET signature_path = ? WHERE id = ?");
            $stmtUpdate->execute([$signature_path, $user_id]);

            // Delete old file if exists
            if ($old_path && file_exists(BASE_PATH . '/' . $old_path)) {
                @unlink(BASE_PATH . '/' . $old_path);
            }

            Logger::activity('Pegawai', 'UPLOAD_SIGNATURE', 'Mengunggah file tanda tangan PNG baru', ['id' => $user_id]);
            redirect("views/documents/signature.php?success=Tanda+tangan+berhasil+diunggah");
        } else {
            redirect("views/documents/signature.php?error=Gagal+menyimpan+file+tanda+tangan");
        }
    } else {
        redirect("views/documents/signature.php?error=Tidak+ada+file+tanda+tangan+yang+diunggah");
    }
}
redirect("views/documents/signature.php");
