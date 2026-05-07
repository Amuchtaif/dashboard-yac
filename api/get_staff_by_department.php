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
    if ($department_id == 1) {
        // Khusus untuk Bidang Pengurus: Tampilkan Mudir (Level 1) dan seluruh Kabid (Level 2)
        // Sesuai struktur organisasi (Muksin + Muadin, Ginanjar, Dedi, Aziz, Ahmad Sowi, Fikrul)
        $sql = "SELECT e.id, e.full_name, e.position_id 
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                WHERE p.level IN (1, 2) 
                AND e.full_name NOT LIKE '%Administrator%'
                AND e.status = 'active' 
                ORDER BY e.full_name ASC";
        $stmtStaff = $mysqli->prepare($sql);
    } else if ($department_id == 2) {
        // Sesuai permintaan: Samakan dengan menu Data Presensi di Kepala Bidang
        // Tampilkan: Level 3 (Kepala Unit/Sub) dan Staf Langsung (unit_id kosong/0)
        $sql = "SELECT e.id, e.full_name, e.position_id 
                FROM employees e
                INNER JOIN positions p ON e.position_id = p.id
                WHERE (e.department_id = 2 OR e.division_id = 2)
                AND (
                    p.level = 3 
                    OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
                    OR p.level = 2 -- Kabid Pendidikan
                    OR p.name LIKE '%Pengawas%'
                )
                AND e.status = 'active' 
                ORDER BY e.full_name ASC";
        $stmtStaff = $mysqli->prepare($sql);
    } else {
        // Ambil Karyawan dalam Departemen yang Dipilih (termasuk yang di divisinya)
        $sql = "SELECT id, full_name, position_id FROM employees 
                WHERE (department_id = ? OR division_id = ?) 
                AND status = 'active' 
                ORDER BY full_name ASC";
        $stmtStaff = $mysqli->prepare($sql);
        $stmtStaff->bind_param("ii", $department_id, $department_id);
    }
    
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
