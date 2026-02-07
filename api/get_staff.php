<?php
// api/get_staff.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

// 1. Validasi Parameter User ID (Pembuat Rapat)
$user_id = isset($_GET['user_id']) ? $_GET['user_id'] : '';

if (empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Parameter user_id wajib diisi."]);
    exit();
}

try {
    // 2. Ambil Divisi Pengguna
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
        // Jika user tidak punya divisi (misal admin level atas atau belum diset)
        // Opsional: return semua atau kosong?
        // Sesuai requirement "agar privasi terjaga", lebih aman return kosong atau error jika tidak punya divisi.
        echo json_encode(["success" => false, "message" => "User tidak terdaftar dalam divisi manapun."]);
        exit();
    }

    // 3. Ambil Staff dalam Divisi yang Sama
    // Bisa tambah filter search jika diperlukan
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    $sqlContent = "SELECT id, full_name, position_id, employee_id_number, photo 
                   FROM employees 
                   WHERE division_id = ? AND status = 'active'";
    
    // Jika user juga ingin dikecualikan dari list? Biasanya create meeting juga bisa invite diri sendiri atau tidak. 
    // Kita biarkan ada di list.

    $params = [$division_id];
    $types = "i";

    if (!empty($search)) {
        $sqlContent .= " AND full_name LIKE ?";
        $params[] = "%$search%";
        $types .= "s";
    }

    // Order by name
    $sqlContent .= " ORDER BY full_name ASC";

    $stmtStaff = $mysqli->prepare($sqlContent);
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
    echo json_encode(["success" => false, "message" => "Terjadi kesalahan: " . $e->getMessage()]);
}

$mysqli->close();
?>
