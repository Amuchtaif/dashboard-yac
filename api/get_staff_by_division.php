<?php
// api/get_staff_by_division.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

// 1. Ambil Parameter Pengirim Request (Kabid)
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
    exit();
}

try {
    // 2. Cari Division ID dari Pengirim Request
    $queryUser = "SELECT division_id FROM employees WHERE id = ?";
    $stmtUser = $mysqli->prepare($queryUser);
    $stmtUser->bind_param("i", $user_id);
    $stmtUser->execute();
    $resultUser = $stmtUser->get_result();

    if ($resultUser->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "User tidak ditemukan."]);
        exit();
    }

    $userData = $resultUser->fetch_assoc();
    $division_id = $userData['division_id'];

    if (empty($division_id)) {
        echo json_encode(["success" => false, "message" => "User tidak memiliki divisi (division_id null)."]);
        exit();
    }

    // 3. Ambil Karyawan Lain dalam Divisi yang Sama
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    $sql = "SELECT id, full_name, position_id FROM employees WHERE division_id = ? AND status = 'active'";
    $params = [$division_id];
    $types = "i";

    if (!empty($search)) {
        $sql .= " AND full_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }

    $sql .= " ORDER BY full_name ASC";

    $stmtStaff = $mysqli->prepare($sql);
    $stmtStaff->bind_param($types, ...$params);
    $stmtStaff->execute();
    $resultStaff = $stmtStaff->get_result();

    $staffList = [];
    while ($row = $resultStaff->fetch_assoc()) {
        $staffList[] = $row;
    }

    echo json_encode([
        "success" => true,
        "division_id" => $division_id,
        "data" => $staffList
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

$mysqli->close();
?>