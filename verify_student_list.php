<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "--- Verifying Student List Logic ---\n";

// Filters (none)
$where_clauses = ["1=1"];
$where_sql = implode(" AND ", $where_clauses);
$params = [];

// Query Logic from index.php
$query = "
    SELECT 
        s.id, 
        s.nama_siswa, 
        s.nomor_induk, 
        gl.name AS class_name,
        eu.name AS unit_name
    FROM students s
    LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = 1
    LEFT JOIN grade_levels gl ON sch.class_id = gl.id
    LEFT JOIN education_units eu ON gl.education_unit_id = eu.id
    WHERE s.status = 'Aktiv' AND ($where_sql)
    ORDER BY eu.name ASC, s.nama_siswa ASC
    LIMIT 20
";

$stmt = $conn->prepare($query);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($students) > 0) {
    echo "Found " . count($students) . " students.\n";
    foreach ($students as $s) {
        $unit = $s['unit_name'] ?? 'NULL';
        $class = $s['class_name'] ?? 'NULL';
        echo sprintf("[%s] %s (Class: %s, Unit: %s)\n", $s['id'], $s['nama_siswa'], $class, $unit);
    }
} else {
    echo "No students found.\n";
}
?>