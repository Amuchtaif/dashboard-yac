<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Create academic_years table
    $sqlYears = "CREATE TABLE IF NOT EXISTS academic_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL, -- e.g., '2025/2026'
        is_active BOOLEAN DEFAULT 0,
        start_date DATE NULL,
        end_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sqlYears);
    echo "Table 'academic_years' created/checked.<br>";

    // Seed a default academic year if empty
    $checkYear = $conn->query("SELECT COUNT(*) FROM academic_years")->fetchColumn();
    if ($checkYear == 0) {
        $conn->exec("INSERT INTO academic_years (name, is_active, start_date, end_date) VALUES ('2025/2026', 1, '2025-07-01', '2026-06-30')");
        echo "Seeded default Academic Year '2025/2026'.<br>";
    }

    // 2. Create student_class_history (junction table)
    // Note: 'classes' are stored in 'grade_levels' table based on previous refactor
    $sqlHistory = "CREATE TABLE IF NOT EXISTS student_class_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL, -- References grade_levels(id)
        academic_year_id INT NOT NULL,
        status ENUM('ACTIVE', 'TRANSFERRED', 'DROPPED', 'PROMOTED') DEFAULT 'ACTIVE',
        joined_at DATE NOT NULL,
        left_at DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES grade_levels(id) ON DELETE RESTRICT,
        FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,

        -- Constraint: Student can only have ONE 'ACTIVE' class per Academic Year
        -- Actually, usually unique per year is enough if we want strictly one class. 
        -- But 'ACTIVE' allows history if they moved classes mid-year.
        -- Let's stick to the user's request: 'Student cannot be assigned to two different classes in the same academic year'
        -- This implies a stricter Unique Key on (student_id, academic_year_id) if strictly one per year.
        -- HOWEVER, if they move, they might have 2 records (one inactive, one active).
        -- Strategy: Unique Key on (student_id, academic_year_id, status) might be tricky if status is mutable.
        -- User Constraint: 'Ensure a student cannot be assigned to two different classes in the same academic year'
        -- Interpretation: One ACTIVE assignment at a time.
        -- Simpler interpretation for now (and often better for placement tables): One record per year.
        -- If they move, UPDATE the record OR allow multiple but enforce logic. 
        -- User asked for 'Left Join... if no record... return NULL'.
        -- Let's use a Unique Index on (student_id, academic_year_id) to enforce 1-to-1 mapping per year for simplicity as requested.
        UNIQUE KEY unique_placement (student_id, academic_year_id)
    )";
    $conn->exec($sqlHistory);
    echo "Table 'student_class_history' created/checked.<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>