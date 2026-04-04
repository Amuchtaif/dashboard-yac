<?php
// logic/students/update_status.php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_academic');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $status = $_POST['status'] ?? null;

    $allowed_statuses = ['Aktif', 'Non_aktif', 'Lulus', 'Pindah', 'Dikeluarkan'];

    if ($student_id && in_array($status, $allowed_statuses)) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("UPDATE students SET status = :status WHERE id = :id");
            $result = $stmt->execute([
                ':status' => $status,
                ':id' => $student_id
            ]);

            if ($result) {
                $status_msg = str_replace('_', ' ', $status);
                $_SESSION['success_msg'] = "Status siswa berhasil diubah menjadi: " . ucwords($status_msg);
            } else {
                $_SESSION['error_msg'] = "Gagal memperbarui status siswa.";
            }

        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Database error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_msg'] = "Data tidak valid atau status tidak dikenali.";
    }
}

// Redirect back to referring page or index
$referer = $_SERVER['HTTP_REFERER'] ?? '../../views/students/index.php';
header("Location: " . $referer);
exit();
