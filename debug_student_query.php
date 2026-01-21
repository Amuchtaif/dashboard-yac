<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $active_year_id = 1; // Assumption from requirements

    // New Query
    $query = "
        SELECT 
            s.id, 
            s.nama_siswa, 
            s.nomor_induk, 
            s.foto, 
            s.status,
            gl.name AS class_name,
            gl.id AS class_id
        FROM students s
        LEFT JOIN student_class_history sch ON s.id = sch.student_id AND sch.academic_year_id = :year_id
        LEFT JOIN grade_levels gl ON sch.class_id = gl.id
        WHERE s.status = 'Aktiv'
        ORDER BY s.id DESC
        LIMIT 5
    ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':year_id', $active_year_id);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($students) . " active students.\n";
    foreach ($students as $student) {
        echo "ID: {$student['id']}, Name: {$student['nama_siswa']}, Status: {$student['status']}, Class: " . ($student['class_name'] ?? 'Non-Kelas') . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
