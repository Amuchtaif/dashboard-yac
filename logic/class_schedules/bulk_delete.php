<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

$query_parts = [];
foreach ($_GET as $key => $value) {
    if ($key !== 'success' && $key !== 'error') {
        $query_parts[$key] = $value;
    }
}
$query_string = http_build_query($query_parts);
$redirect_url = "../../views/class_schedules/index.php" . ($query_string ? '?' . $query_string . '&' : '?');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ids']) && is_array($_POST['ids'])) {
    $ids = $_POST['ids'];
    $db = new Database();
    $conn = $db->getConnection();

    try {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id IN ($inQuery)");
        $stmt->execute($ids);

        header("Location: " . $redirect_url . "success=" . urlencode("Berhasil menghapus " . count($ids) . " jadwal"));
    } catch (PDOException $e) {
        header("Location: " . $redirect_url . "error=" . urlencode("Kesalahan Database: " . $e->getMessage()));
    }
} else {
    header("Location: " . $redirect_url . "error=" . urlencode("Tidak ada jadwal yang dipilih"));
}
?>
