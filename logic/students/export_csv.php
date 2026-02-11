<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

// --- Filter Logic (Same as index.php) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

$db = new Database();
$conn = $db->getConnection();

// Build Where Clause
$where_clauses = ["1=1"];
$params = [];

if ($search) {
    $where_clauses[] = "(nama_siswa LIKE :search OR nomor_induk LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($unit_id) {
    $where_clauses[] = "eu.id = :unit_id";
    $params[':unit_id'] = $unit_id;
}

if ($class_id) {
    $where_clauses[] = "gl.id = :class_id";
    $params[':class_id'] = $class_id;
}

if ($status) {
    $where_clauses[] = "s.status = :status";
    $params[':status'] = $status;
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Active Academic Year
$active_year_query = "SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1";
$active_year_stmt = $conn->query($active_year_query);
$active_year = $active_year_stmt->fetch(PDO::FETCH_ASSOC);
$active_year_id = $active_year ? $active_year['id'] : 1; // Default to 1 if none found

// Fetch All Matching Students (No Pagination)
$query = "
    SELECT 
        s.nama_siswa, 
        s.nomor_induk, 
        s.status, 
        s.tahun_ajaran,
        eu.name AS unit_name,
        gl.name AS class_name
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :active_year_id
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE ($where_sql)
    ORDER BY 
        CASE 
            WHEN eu.name LIKE '%PG%' THEN 1
            WHEN eu.name LIKE '%TK%' THEN 2
            WHEN eu.name LIKE '%SD%' THEN 3
            WHEN eu.name LIKE '%MTs%' THEN 4
            WHEN eu.name LIKE '%MA%' THEN 5
            WHEN eu.name LIKE '%Mahad%' THEN 6
            ELSE 7 
        END ASC, 
        gl.name ASC, 
        s.nama_siswa ASC
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':active_year_id', $active_year_id, PDO::PARAM_INT); // Bind Active Year
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Export to CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data_siswa_' . date('Y-m-d_H-i') . '.csv');

$output = fopen('php://output', 'w');

// Headers
fputcsv($output, ['No', 'Nama Siswa', 'NISN', 'Unit', 'Kelas', 'Tahun Ajaran', 'Status']);

$no = 1;
foreach ($students as $row) {
    fputcsv($output, [
        $no++,
        ucwords(strtolower($row['nama_siswa'])),
        "'" . $row['nomor_induk'], // Prevent Excel auto-format
        $row['unit_name'] ?? '-',
        $row['class_name'] ?? '-',
        $row['tahun_ajaran'],
        $row['status']
    ]);
}

fclose($output);
exit;
