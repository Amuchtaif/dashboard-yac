<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

echo "Checking Academic Years:\n";
$years = $conn->query("SELECT * FROM academic_years")->fetchAll(PDO::FETCH_ASSOC);
print_r($years);

// Get Active Year ID
$activeYearId = 0;
foreach ($years as $y) {
    if ($y['is_active'])
        $activeYearId = $y['id'];
}
echo "Active Year ID: " . $activeYearId . "\n\n";

if (!$activeYearId) {
    echo "No active academic year found! Using default 0.\n";
}

echo "Running Student Query with year_id = $activeYearId ...\n";

$sql = "SELECT 
            s.id,
            s.nama_siswa,
            s.status
        FROM students s
        LEFT JOIN student_class_history h ON s.id = h.student_id AND h.academic_year_id = :year_id AND h.status = 'ACTIVE'
        LEFT JOIN grade_levels gl ON h.class_id = gl.id
        WHERE s.status = 'ACTIVE'
        ORDER BY s.nama_siswa ASC";

$stmt = $conn->prepare($sql);
$stmt->execute([':year_id' => $activeYearId]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($results) . " students.\n";
if (count($results) > 0) {
    echo "First 3 students:\n";
    print_r(array_slice($results, 0, 3));
} else {
    echo "Query returned NO results.\n";

    // Check if there are ANY students
    $count = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
    echo "Total rows in 'students' table: $count\n";

    if ($count > 0) {
        $sample = $conn->query("SELECT status FROM students LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        echo "Sample statuses from 'students':\n";
        print_r($sample);
    }
}
?>