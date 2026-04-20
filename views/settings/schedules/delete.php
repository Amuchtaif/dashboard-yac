<?php
require_once '../../../config/app.php';
require_once '../../../config/database.php';

check_login();
check_permission('manage_employees');

if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        // Optional Safety: Check if used
        // Check employees table if they use this schedule_id (assuming the column exists or will exist)
        // For now, we will just delete as per request, but let's check if 'schedule_id' column exists in employees first or just try-catch constraint violation.
        // Assuming strict foreign keys might not be there yet for this new table.

        $sql = "DELETE FROM work_schedules WHERE id = :id";
        $stmt = $conn->prepare($sql);

        if ($stmt->execute([':id' => $id])) {
            header("Location: " . BASE_URL . "views/settings/schedules/index.php?success=" . urlencode("Jadwal berhasil dihapus"));
        } else {
            header("Location: " . BASE_URL . "views/settings/schedules/index.php?error=" . urlencode("Gagal menghapus jadwal"));
        }
    } catch (PDOException $e) {
        $msg = "Gagal menghapus jadwal. Mungkin masih digunakan.";
        if (strpos($e->getMessage(), 'Integrity constraint violation') !== false) {
            $msg = "Tidak dapat menghapus: Jadwal ini masih digunakan oleh pegawai atau divisi.";
        }
        header("Location: " . BASE_URL . "views/settings/schedules/index.php?error=" . urlencode($msg));
    }
} else {
    header("Location: " . BASE_URL . "views/settings/schedules/index.php");
}
exit;
?>