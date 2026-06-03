<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- Filter Logic ---
$today_day = (int)date('d');
if ($today_day >= 26) {
    $default_start = date('Y-m-26');
    $default_end = date('Y-m-25', strtotime('+1 month'));
} else {
    $default_start = date('Y-m-26', strtotime('-1 month'));
    $default_end = date('Y-m-25');
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $default_end;
$division_id = isset($_GET['division_id']) ? $_GET['division_id'] : '';
$unit_id = isset($_GET['unit_id']) ? $_GET['unit_id'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE Clause
$where = " WHERE e.id != 1 AND (e.status = 'active' OR e.status IS NULL) ";
$params = [':start_date' => $start_date, ':end_date' => $end_date];

if ($search) {
    $where .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($unit_id) {
    $where .= " AND e.unit_id = :unit_id ";
    $params[':unit_id'] = $unit_id;
}

// Query Rekapitulasi (No Pagination for Export)
$query = "
    SELECT 
        e.id, 
        e.nik,
        e.full_name, 
        e.email,
        u.name as unit_name, 
        d.name as division_name, 
        (SELECT COUNT(id) FROM attendances WHERE user_id = e.id AND date BETWEEN :start_date AND :end_date) as total_attendance
    FROM employees e
    LEFT JOIN units u ON e.unit_id = u.id
    LEFT JOIN divisions d ON e.division_id = d.id
    $where
    ORDER BY e.full_name ASC
";

$stmt = $conn->prepare($query);
$stmt->bindValue(':start_date', $start_date);
$stmt->bindValue(':end_date', $end_date);
foreach ($params as $key => $val) {
    if ($key !== ':start_date' && $key !== ':end_date') {
        $stmt->bindValue($key, $val);
    }
}
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers for CSV Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="rekap_absensi_' . $start_date . '_to_' . $end_date . '.csv"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fwrite($output, "\xEF\xBB\xBF");

// CSV Header Row
fputcsv($output, ['No', 'NIK', 'Nama Pegawai', 'Email', 'Unit Kerja', 'Bidang', 'Total Kehadiran']);

// Data Rows
foreach ($summary as $index => $row) {
    fputcsv($output, [
        $index + 1,
        $row['nik'] ?: '-',
        $row['full_name'],
        $row['email'],
        $row['unit_name'] ?: '-',
        $row['division_name'] ?: '-',
        $row['total_attendance'] . ' Hari'
    ]);
}

fclose($output);
exit;
?>
