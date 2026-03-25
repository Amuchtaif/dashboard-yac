<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/settings/shifts/index.php?success=Operasi+berhasil');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_exchange') {
        $requester_id = $_POST['requester_id'] ?? '';
        $substitute_id = $_POST['substitute_id'] ?? '';
        $exchange_date = $_POST['exchange_date'] ?? '';
        $reason = $_POST['reason'] ?? '';

        if (empty($requester_id) || empty($substitute_id) || empty($exchange_date)) {
            throw new Exception("Data tidak lengkap.");
        }

        if ($requester_id == $substitute_id) {
            throw new Exception("Pegawai pengganti tidak boleh sama dengan pemohon.");
        }

        $stmt = $conn->prepare("INSERT INTO shift_exchanges (requester_id, substitute_id, exchange_date, reason) VALUES (?, ?, ?, ?)");
        $stmt->execute([$requester_id, $substitute_id, $exchange_date, $reason]);

        $_SESSION['success'] = "Permohonan tukar shift berhasil dikirim.";
    }
    elseif ($action === 'process_exchange') {
        $id = $_POST['id'] ?? '';
        $status = $_POST['status'] ?? 'Menunggu';
        $approved_by = $_SESSION['user_id'];

        $stmt = $conn->prepare("UPDATE shift_exchanges SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$status, $approved_by, $id]);

        $_SESSION['success'] = "Pertukaran shift telah " . ($status == 'Disetujui' ? 'disetujui' : 'ditolak') . ".";
    }
    elseif ($action === 'delete_exchange') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM shift_exchanges WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Data pertukaran berhasil dihapus.";
    }

        header('Location: ../../views/settings/shifts/index.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
