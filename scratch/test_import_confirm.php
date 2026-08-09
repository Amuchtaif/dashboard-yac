<?php
// Simulate POST request to confirm import
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/Logger.php';

$db = new Database();
$conn = $db->getConnection();
$user_id = 1;

$testRows = [
    [
        'status' => 'valid',
        'title' => 'Agenda Uji Coba Import Backend ' . rand(100, 999),
        'description' => 'Uji coba import tanpa error',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d'),
        'start_time' => '08:00:00',
        'end_time' => '10:00:00',
        'location' => 'Ruang Rapat',
        'category' => 'Kegiatan',
        'source_type' => 'bidang_pendidikan',
        'unit_id' => null,
        'academic_year_id' => 1
    ]
];

// Execute logic directly
try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("INSERT INTO academic_calendar 
        (title, description, start_date, end_date, start_time, end_time, location, category, source_type, unit_id, academic_year_id, semester, visibility, status, color, is_holiday, created_by, updated_by) 
        VALUES 
        (:title, :description, :start_date, :end_date, :start_time, :end_time, :location, :category, :source_type, :unit_id, :academic_year_id, :semester, :visibility, :status, :color, :is_holiday, :created_by, :updated_by)");

    $insertedCount = 0;
    foreach ($testRows as $r) {
        if ($r['status'] !== 'valid') continue;

        $is_holiday = in_array($r['category'], ['Libur Nasional', 'Libur Sekolah', 'Cuti Bersama']) ? 1 : 0;
        $color = '#3b82f6';

        $stmt->execute([
            ':title' => $r['title'],
            ':description' => $r['description'] ?? '',
            ':start_date' => $r['start_date'],
            ':end_date' => !empty($r['end_date']) ? $r['end_date'] : $r['start_date'],
            ':start_time' => !empty($r['start_time']) ? $r['start_time'] : null,
            ':end_time' => !empty($r['end_time']) ? $r['end_time'] : null,
            ':location' => $r['location'] ?? '',
            ':category' => $r['category'] ?? 'Kegiatan',
            ':source_type' => $r['source_type'] ?? 'bidang_pendidikan',
            ':unit_id' => !empty($r['unit_id']) ? $r['unit_id'] : null,
            ':academic_year_id' => !empty($r['academic_year_id']) ? $r['academic_year_id'] : null,
            ':semester' => 'Ganjil',
            ':visibility' => 'public',
            ':status' => 'scheduled',
            ':color' => $color,
            ':is_holiday' => $is_holiday,
            ':created_by' => $user_id,
            ':updated_by' => $user_id
        ]);
        $insertedCount++;
    }

    $conn->commit();

    if (class_exists('Logger')) {
        Logger::log('info', 'activity', 'Kalender Akademik', 'Import Agenda', "Mengimport {$insertedCount} agenda ke Kalender Akademik", [
            'user_id' => $user_id,
            'count' => $insertedCount
        ]);
    }

    echo json_encode(['success' => true, 'inserted' => $insertedCount]);

} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
