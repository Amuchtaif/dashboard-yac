<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $employee_id = $_POST['employee_id'] ?? null;
    $unit_id = $_POST['unit_id'] ?? null;
    $position_id = 12; // Koordinator Tahfidz ID

    if (!$employee_id) {
        header("Location: ../../views/assignments/index.php?error=" . urlencode("Pilih pegawai terlebih dahulu."));
        exit;
    }

    try {
        // Validation: Check if this assignment already exists
        $stmt = $conn->prepare("SELECT id FROM employee_assignments WHERE employee_id = ? AND position_id = ? AND unit_id = ? AND is_active = 1");
        $stmt->execute([$employee_id, $position_id, $unit_id]);
        
        if ($stmt->fetch()) {
            header("Location: ../../views/assignments/index.php?error=" . urlencode("Pegawai ini sudah menjabat sebagai Koordinator Tahfidz di unit tersebut."));
            exit;
        }

        // Insert new assignment
        $stmt = $conn->prepare("INSERT INTO employee_assignments (employee_id, position_id, unit_id) VALUES (?, ?, ?)");
        $result = $stmt->execute([$employee_id, $position_id, $unit_id]);

        if ($result) {
            header("Location: ../../views/assignments/index.php?success=" . urlencode("Koordinator Tahfidz berhasil ditambahkan."));
        } else {
            header("Location: ../../views/assignments/index.php?error=" . urlencode("Gagal menambahkan jabatan tambahan."));
        }
    } catch (PDOException $e) {
        header("Location: ../../views/assignments/index.php?error=" . urlencode("Error: " . $e->getMessage()));
    }
} else {
        header("Location: ../../views/assignments/index.php?error=Operasi+gagal");
}
