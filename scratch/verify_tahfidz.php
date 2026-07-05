<?php
require_once __DIR__ . '/../app/Services/Tahfidz/BaselineService.php';
require_once __DIR__ . '/../app/Services/Tahfidz/MemorizationService.php';
require_once __DIR__ . '/../app/Services/Tahfidz/ProgressService.php';
require_once __DIR__ . '/../app/Services/Tahfidz/SemesterReportService.php';
require_once __DIR__ . '/../app/Services/Tahfidz/SemesterClosingService.php';
require_once __DIR__ . '/../app/Services/Tahfidz/SnapshotService.php';

// Fetch a student and active academic year
require_once __DIR__ . '/../config/db_mysqli.php';

echo "=== STARTING VERIFICATION ===\n";

$ay_res = $mysqli->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
$ay = $ay_res->fetch_assoc();
$academic_year_id = (int)$ay['id'];
echo "Active Academic Year ID: $academic_year_id\n";

$student_res = $mysqli->query("SELECT id FROM students WHERE status = 'Aktif' LIMIT 1");
$student = $student_res->fetch_assoc();
$student_id = (int)$student['id'];
echo "Active Student ID: $student_id\n";

$baselineService = new BaselineService();
$memoService = new MemorizationService();
$progressService = new ProgressService();
$reportService = new SemesterReportService();
$closingService = new SemesterClosingService();
$snapshotService = new SnapshotService();

// 1. Create a baseline
try {
    echo "Creating baseline...\n";
    $b_id = $baselineService->createBaseline([
        'academic_year_id' => $academic_year_id,
        'student_id' => $student_id,
        'baseline_juz' => 5.5,
        'notes' => 'Baseline test'
    ]);
    echo "Baseline created with ID: $b_id\n";
} catch (Exception $e) {
    echo "Baseline create error (expected if duplicate): " . $e->getMessage() . "\n";
}

// 2. Create a new memorization entry
try {
    echo "Creating entry...\n";
    $e_id = $memoService->createEntry([
        'student_id' => $student_id,
        'date' => date('Y-m-d'),
        'entry_type' => 'HAFALAN_BARU',
        'start_surah_id' => 1,
        'start_ayah' => 1,
        'end_surah_id' => 1,
        'end_ayah' => 7,
        'line_count' => 15,
        'notes' => 'Entry test'
    ]);
    echo "Entry created with ID: $e_id\n";
} catch (Exception $e) {
    echo "Entry create error: " . $e->getMessage() . "\n";
}

// 3. Check progress
try {
    echo "Checking progress...\n";
    $prog = $progressService->getStudentProgress($student_id, $academic_year_id);
    print_r($prog);
} catch (Exception $e) {
    echo "Progress check error: " . $e->getMessage() . "\n";
}

// 4. Get Report
try {
    echo "Checking report...\n";
    $rep = $reportService->getSemesterReport($student_id, $academic_year_id);
    print_r($rep);
} catch (Exception $e) {
    echo "Report error: " . $e->getMessage() . "\n";
}

// 5. Try closing semester
try {
    echo "Closing semester...\n";
    // Using a try-catch since it might already be closed in DB
    $snap_count = $closingService->closeSemester($academic_year_id, 'Ganjil');
    echo "Closed semester. Created $snap_count snapshots.\n";
} catch (Exception $e) {
    echo "Closing semester status: " . $e->getMessage() . "\n";
}

// 6. View snapshot
try {
    echo "Checking snapshot...\n";
    $snap = $snapshotService->getStudentSnapshot($student_id, $academic_year_id, 'Ganjil');
    print_r($snap);
} catch (Exception $e) {
    echo "Snapshot error: " . $e->getMessage() . "\n";
}

echo "=== VERIFICATION COMPLETED ===\n";
