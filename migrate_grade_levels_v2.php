<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Add new columns to grade_levels table
    $sql = "ALTER TABLE grade_levels
            ADD COLUMN education_unit_id INT NULL AFTER name,
            ADD COLUMN teacher_id INT NULL AFTER education_unit_id,
            ADD COLUMN level VARCHAR(50) NULL AFTER teacher_id";

    $conn->exec($sql);
    echo "Columns added successfully to grade_levels table.\n";

    // Add foreign key constraint for education_unit_id
    // Assuming education_units table uses 'id' as primary key
    // Checking if education_units table exists to refer
    $sql_fk_unit = "ALTER TABLE grade_levels
                    ADD CONSTRAINT fk_gl_unit
                    FOREIGN KEY (education_unit_id) REFERENCES education_units(id)
                    ON DELETE SET NULL";

    $conn->exec($sql_fk_unit);
    echo "Foreign key fk_gl_unit added successfully.\n";

    // Add foreign key constraint for teacher_id (employees)
    $sql_fk_teacher = "ALTER TABLE grade_levels
                       ADD CONSTRAINT fk_gl_teacher
                       FOREIGN KEY (teacher_id) REFERENCES employees(id)
                       ON DELETE SET NULL";

    $conn->exec($sql_fk_teacher);
    echo "Foreign key fk_gl_teacher added successfully.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>