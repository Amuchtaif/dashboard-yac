<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

try {
    // Drop old foreign key if exists
    try {
        $conn->exec("ALTER TABLE halaqah_members DROP FOREIGN KEY fk_halaqah_student");
    } catch (Exception $e) {}

    // Change student_id reference to students table
    $conn->exec("ALTER TABLE halaqah_members 
                 ADD CONSTRAINT fk_halaqah_student 
                 FOREIGN KEY (student_id) REFERENCES students(id) 
                 ON DELETE CASCADE");

    echo "Relationship fixed: halaqah_members(student_id) now points to students(id).\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
