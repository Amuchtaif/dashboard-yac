<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

try {
    // Get all active students without unit restrictions
    $stmt = $conn->query("SELECT id, nama_siswa, kelas FROM students 
                          WHERE status = 'Aktif'
                          ORDER BY nama_siswa ASC");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Data santri berhasil dimuat',
        'data' => $students
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
