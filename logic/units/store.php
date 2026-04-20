<?php
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $name = $_POST['name'] ?? '';
    $division_id = $_POST['division_id'] ?? '';
    $schedule_id = $_POST['schedule_id'] ?? '';

    if (empty($name) || empty($division_id)) {
        header("Location: ../../views/units/index.php?error=Mohon+lengkapi+semua+bidang");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO units (name, division_id, schedule_id) VALUES (:name, :division_id, :schedule_id)");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':division_id', $division_id);
    $stmt->bindValue(':schedule_id', !empty($schedule_id) ? $schedule_id : null, PDO::PARAM_INT);

    if ($stmt->execute()) {
        header("Location: ../../views/units/index.php?success=Unit+berhasil+dibuat");
    } else {
        header("Location: ../../views/units/index.php?error=Gagal+membuat+unit");
    }
} else {
        header("Location: ../../views/units/index.php?error=Operasi+gagal");
}
exit;
