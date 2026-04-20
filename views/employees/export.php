<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();
check_permission('manage_employees');

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic (Mirrors Index) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$where_clauses = ["e.id != 1"];
$params = [];

if ($search) {
    $where_clauses[] = "(e.full_name LIKE :search OR e.email LIKE :search OR e.phone_number LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where_clauses[] = "e.division_id = :division_id";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where_clauses[] = "e.unit_id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

// Always filter for active employees as requested
$where_clauses[] = "(e.status = 'active' OR e.status IS NULL)";

$where_sql = implode(" AND ", $where_clauses);

// Fetch All Matching Data (No Limit)
$query = "
    SELECT 
        e.id, 
        e.full_name, 
        e.email, 
        e.phone_number, 
        e.address, 
        d.name as division_name, 
        u.name as unit_name,
        p.name as position_name,
        e.status
    FROM employees e 
    LEFT JOIN divisions d ON e.division_id = d.id 
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE $where_sql
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers for CSV Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d') . '.csv"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV Header Row
fputcsv($output, ['No', 'Nama Lengkap', 'Email', 'Telepon', 'Alamat', 'Bidang', 'Unit', 'Jabatan', 'Status']);

// Data Rows
$no = 1;
foreach ($employees as $row) {
    fputcsv($output, [
        $no++,
        $row['full_name'],
        $row['email'],
        $row['phone_number'],
        $row['address'],
        $row['division_name'],
        $row['unit_name'],
        $row['position_name'],
        $row['status'] ?: 'Active'
    ]);
}

fclose($output);
exit;
?>