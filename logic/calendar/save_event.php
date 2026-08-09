<?php
// logic/calendar/save_event.php
require_once '../../config/database.php';

header('Content-Type: application/json');

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data['title']) || empty($data['start_date'])) {
    echo json_encode(['success' => false, 'message' => 'Judul dan Tanggal Mulai wajib diisi']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

try {
    if (!empty($data['id'])) {
        // Update
        $sql = "UPDATE academic_calendar SET 
                title = :title, 
                description = :description, 
                start_date = :start_date, 
                end_date = :end_date, 
                category = :category, 
                is_holiday = :is_holiday 
                WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $data['id']);
    } else {
        // Insert
        $sql = "INSERT INTO academic_calendar (title, description, start_date, end_date, category, is_holiday) 
                VALUES (:title, :description, :start_date, :end_date, :category, :is_holiday)";
        $stmt = $conn->prepare($sql);
    }

    $stmt->bindParam(':title', $data['title']);
    $stmt->bindParam(':description', $data['description']);
    $stmt->bindParam(':start_date', $data['start_date']);
    $end_date = !empty($data['end_date']) ? $data['end_date'] : $data['start_date'];
    $stmt->bindParam(':end_date', $end_date);
    $stmt->bindParam(':category', $data['category']);
    $is_holiday = (int)$data['is_holiday'];
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
