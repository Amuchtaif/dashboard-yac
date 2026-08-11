<?php
// logic/calendar/save_event.php
require_once '../../config/app.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

check_login();

$user_id = $_SESSION['user_id'] ?? null;
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['title']) || empty($data['start_date'])) {
    echo json_encode(['success' => false, 'message' => 'Judul dan Tanggal Mulai wajib diisi']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch active academic year if not provided
$ay_id = !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
if (!$ay_id) {
    $ayStmt = $conn->query("SELECT id FROM academic_years WHERE is_active = 1 LIMIT 1");
    if ($ayStmt) {
        $ay_id = $ayStmt->fetchColumn() ?: null;
    }
}

$title = trim($data['title']);
$description = isset($data['description']) ? trim($data['description']) : '';
$start_date = trim($data['start_date']);
$end_date = !empty($data['end_date']) ? trim($data['end_date']) : $start_date;
$start_time = !empty($data['start_time']) ? trim($data['start_time']) : null;
$end_time = !empty($data['end_time']) ? trim($data['end_time']) : null;
$location = isset($data['location']) ? trim($data['location']) : '';
$category = !empty($data['category']) ? trim($data['category']) : 'Kegiatan';
$unit_id = !empty($data['unit_id']) ? (int)$data['unit_id'] : null;
$source_type = !empty($data['source_type']) ? trim($data['source_type']) : 'bidang_pendidikan';
if ($unit_id) {
    $source_type = 'unit';
}
$is_holiday = isset($data['is_holiday']) ? (int)$data['is_holiday'] : (in_array($category, ['Libur Nasional', 'Libur Sekolah', 'Cuti Bersama']) ? 1 : 0);

try {
    if (!empty($data['id'])) {
        // Update
        $sql = "UPDATE academic_calendar SET 
                title = :title, 
                description = :description, 
                start_date = :start_date, 
                end_date = :end_date, 
                start_time = :start_time,
                end_time = :end_time,
                location = :location,
                category = :category, 
                source_type = :source_type,
                unit_id = :unit_id,
                is_holiday = :is_holiday,
                updated_by = :updated_by
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $data['id']);
        $stmt->bindParam(':updated_by', $user_id);
    } else {
        // Insert
        $sql = "INSERT INTO academic_calendar 
                (title, description, start_date, end_date, start_time, end_time, location, category, source_type, unit_id, academic_year_id, visibility, status, is_holiday, created_by, updated_by) 
                VALUES 
                (:title, :description, :start_date, :end_date, :start_time, :end_time, :location, :category, :source_type, :unit_id, :academic_year_id, 'public', 'scheduled', :is_holiday, :created_by, :updated_by)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':academic_year_id', $ay_id);
        $stmt->bindParam(':created_by', $user_id);
        $stmt->bindParam(':updated_by', $user_id);
    }

    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':start_time', $start_time);
    $stmt->bindParam(':end_time', $end_time);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':source_type', $source_type);
    $stmt->bindParam(':unit_id', $unit_id);
    $stmt->bindParam(':is_holiday', $is_holiday);

    if ($stmt->execute()) {
        $msg = !empty($data['id']) ? 'Kegiatan berhasil diperbarui' : 'Kegiatan berhasil ditambahkan';
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

