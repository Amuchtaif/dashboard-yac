<?php
// scripts/fix_student_names.php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->query("SELECT id, nama_siswa FROM students");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conn->beginTransaction();
    $update_stmt = $conn->prepare("UPDATE students SET nama_siswa = :nama WHERE id = :id");

    $count = 0;
    foreach ($students as $s) {
        $old_name = $s['nama_siswa'];
        $new_name = ucwords(strtolower($old_name));
        
        if ($old_name !== $new_name) {
            $update_stmt->execute([':nama' => $new_name, ':id' => $s['id']]);
            $count++;
        }
    }

    $conn->commit();
    echo "Successfully updated $count student names to Capitalized Case.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
