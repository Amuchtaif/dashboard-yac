<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

header('Content-Type: application/json');

$db = new Database();
$conn = $db->getConnection();

try {
    // Get all students except TKIT, SDIT, and Playgroup
    $exclude = ["'TKIT'", "'SDIT'", "'PLAY GROUP'"];
    $exclude_str = implode(',', $exclude);
    $stmt = $conn->query("SELECT id, nama_siswa, kelas FROM students 
                          WHERE status = 'Aktif' AND (tingkat NOT IN ($exclude_str) 
                          OR tingkat IS NULL)
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
