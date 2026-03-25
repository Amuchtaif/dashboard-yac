<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: ../../views/boarding/violations/index.php?success=Operasi+berhasil');
    exit;
}

$db = new Database();
$conn = $db->getConnection();
$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_type') {
        $name = $_POST['type_name'] ?? '';
        $points = $_POST['points'] ?? 0;
        $category = $_POST['category'] ?? 'Ringan';

        if (empty($name)) throw new Exception("Nama jenis pelanggaran harus diisi.");

        $stmt = $conn->prepare("INSERT INTO boarding_violation_types (type_name, points, category) VALUES (?, ?, ?)");
        $stmt->execute([$name, $points, $category]);

        $_SESSION['success'] = "Jenis pelanggaran berhasil ditambahkan.";
        header('Location: ../../views/boarding/violation_types/index.php?success=Operasi+berhasil');
        exit;
    }
    elseif ($action === 'delete_type') {
        $id = $_POST['id'] ?? '';
        $stmt = $conn->prepare("DELETE FROM boarding_violation_types WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success'] = "Jenis pelanggaran berhasil dihapus.";
        header('Location: ../../views/boarding/violation_types/index.php?success=Operasi+berhasil');
        exit;
    }
    elseif ($action === 'create_violation') {
        $student_id = $_POST['student_id'] ?? '';
        $type_id = $_POST['type_id'] ?? '';
        $date = $_POST['date'] ?? date('Y-m-d');
        $description = $_POST['description'] ?? '';
        $reporter_id = $_POST['reporter_id'] ?? '';

        if (empty($student_id) || empty($type_id)) throw new Exception("Santri dan jenis pelanggaran harus dipilih.");

        $stmt = $conn->prepare("INSERT INTO boarding_violations (student_id, type_id, date, description, reporter_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $type_id, $date, $description, $reporter_id]);

        $_SESSION['success'] = "Pelanggaran santri berhasil dicatat.";
        header('Location: ../../views/boarding/violations/index.php?success=Operasi+berhasil');
        exit;
    }

        header('Location: ../../views/boarding/violations/index.php?success=Operasi+berhasil');

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ' . $_SERVER['HTTP_REFERER']);
}
exit;
