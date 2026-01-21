<?php
require_once __DIR__ . '/../../config/database.php';

/**
 * Assigns a list of students to a class for a specific academic year.
 * Handles capacity checks and updates existing assignments if necessary.
 * 
 * @param int $class_id
 * @param int $academic_year_id
 * @param array $student_ids
 * @return array ['success' => bool, 'message' => string]
 */
function assignStudentsToClass($class_id, $academic_year_id, $student_ids)
{
    global $conn;
    if (!$conn) {
        $db = new Database();
        $conn = $db->getConnection();
    }

    try {
        $conn->beginTransaction();

        // 1. Check Capacity
        // Get Class Capacity
        $stmtCap = $conn->prepare("SELECT capacity FROM grade_levels WHERE id = ?");
        $stmtCap->execute([$class_id]);
        $classCapacity = $stmtCap->fetchColumn();
        if (!$classCapacity)
            $classCapacity = 36; // Fallback default

        // Get Current Enrollment Count for this class & year
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM student_class_history WHERE class_id = ? AND academic_year_id = ?");
        $stmtCount->execute([$class_id, $academic_year_id]);
        $currentCount = $stmtCount->fetchColumn();

        $neededDetails = count($student_ids);

        // Note: Logic depends on if we are moving students *out* of this class (no change in net count for them) 
        // or *into* it. For simplicity, assume new additions.
        if (($currentCount + $neededDetails) > $classCapacity) {
            $conn->rollBack();
            return ['success' => false, 'message' => "Class capacity exceeded. Capacity: $classCapacity, Current: $currentCount, Adding: $neededDetails"];
        }

        // 2. Assign Students (Upsert Logic)
        // Table has UNIQUE KEY (student_id, academic_year_id)
        $sql = "INSERT INTO student_class_history (student_id, class_id, academic_year_id, status, joined_at) 
                VALUES (:student_id, :class_id, :year_id, 'ACTIVE', CURDATE())
                ON DUPLICATE KEY UPDATE 
                class_id = VALUES(class_id),
                status = 'ACTIVE', -- Reactivate if they were inactive
                joined_at = CURDATE() -- Update join date if moving";

        $stmt = $conn->prepare($sql);

        foreach ($student_ids as $student_id) {
            $stmt->execute([
                ':student_id' => $student_id,
                ':class_id' => $class_id,
                ':year_id' => $academic_year_id
            ]);
        }

        $conn->commit();
        return ['success' => true, 'message' => "Successfully assigned " . count($student_ids) . " students."];

    } catch (PDOException $e) {
        $conn->rollBack();
        return ['success' => false, 'message' => "Database Error: " . $e->getMessage()];
    }
}
?>