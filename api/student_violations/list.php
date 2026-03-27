<?php
require_once '../../config/database.php';
require_once '../../config/app.php';
require_once '../../config/permission.php';

header('Content-Type: application/json');

// Auth check: support both session and request parameter (for Flutter app)
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!hasPermission($user_id, 'can_access_kesantrian')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// 2. Check if user is a designated officer or admin
$stmtOfficer = $conn->prepare("SELECT COUNT(*) FROM petugas_pelanggaran WHERE employee_id = ?");
$stmtOfficer->execute([$user_id]);
$is_officer = $stmtOfficer->fetchColumn() > 0;

if (!$is_officer) {
    $stmtAdmin = $conn->prepare("SELECT p.name FROM employees e JOIN positions p ON e.position_id = p.id WHERE e.id = ?");
    $stmtAdmin->execute([$user_id]);
    if ($stmtAdmin->fetchColumn() === 'Administrator') $is_officer = true;
}

$status = $_GET['status'] ?? '';
$kategori_id = $_GET['kategori_id'] ?? '';
$santri_id = $_GET['santri_id'] ?? '';
$search = $_GET['search'] ?? '';

$query = "SELECT p.*, s.nama_siswa, k.nama_kategori, k.poin, e.full_name as pelapor_name
          FROM pelanggaran p
          JOIN students s ON p.santri_id = s.id
          JOIN kategori_pelanggaran k ON p.kategori_id = k.id
          JOIN employees e ON p.pelapor = e.id
          WHERE 1=1";

$params = [];

// Jika request DARI mobile app (user_id explicitly passed) dan BUKAN petugas/admin, filter milik sendiri
if (!$is_officer && isset($_GET['user_id'])) {
    $query .= " AND p.pelapor = ?";
    $params[] = $user_id;
}

if ($status) {
    $query .= " AND p.status = ?";
    $params[] = $status;
}

if ($kategori_id) {
    $query .= " AND p.kategori_id = ?";
    $params[] = $kategori_id;
}

if ($santri_id) {
    $query .= " AND p.santri_id = ?";
    $params[] = $santri_id;
}

if ($search) {
    $query .= " AND (s.nama_siswa LIKE ? OR p.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY p.tanggal_pelanggaran DESC, p.created_at DESC";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'Data pelanggaran berhasil dimuat',
        'data' => $violations
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
