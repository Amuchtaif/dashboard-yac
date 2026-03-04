<?php
// api/get_staff_by_department.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/db_mysqli.php';

$department_id = isset($_GET['department_id']) ? $_GET['department_id'] : '';

if (empty($department_id)) {
    echo json_encode(["success" => false, "message" => "Parameter department_id wajib diisi."]);
    exit();
}

try {
    // Ambil Karyawan dalam Departemen yang Dipilih
    $sql = "SELECT id, full_name, position_id FROM employees WHERE department_id = ? AND status = 'active' ORDER BY full_name ASC";
    
    $stmtStaff = $mysqli->prepare($sql);
    $stmtStaff->bind_param("i", $department_id);
    $stmtStaff->execute();
    $resultStaff = $stmtStaff->get_result();

    $staffList = [];
    while ($row = $resultStaff->fetch_assoc()) {
        $staffList[] = $row;
    }

    echo json_encode([
        "success" => true,
        "department_id" => $department_id,
        "data" => $staffList
    ]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}

$mysqli->close();
?>
