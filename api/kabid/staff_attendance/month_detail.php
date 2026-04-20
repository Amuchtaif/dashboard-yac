<?php
// api/kabid/staff_attendance/month_detail.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
date_default_timezone_set('Asia/Jakarta');

require_once dirname(__DIR__, 3) . '/config/database.php';

try {
    /** @var \Database $db */
    $db = new Database();
    $conn = $db->getConnection();

    $user_id = $_GET['user_id'] ?? null;
    $month_label = $_GET['month'] ?? null; // e.g. "Februari 2026"

    if (!$user_id || !$month_label) {
        echo json_encode(["success" => false, "message" => "Parameter user_id dan month wajib diisi."]);
        exit;
    }

    // Map Indo to Eng for parsing
    $indoToEng = [
        'Januari' => 'January',
        'Februari' => 'February',
        'Maret' => 'March',
        'April' => 'April',
        'Mei' => 'May',
        'Juni' => 'June',
        'Juli' => 'July',
        'Agustus' => 'August',
        'September' => 'September',
        'Oktober' => 'October',
        'November' => 'November',
        'Desember' => 'December'
    ];
    $engLabel = $month_label;
    foreach ($indoToEng as $id => $en) {
        if (strpos($month_label, $id) !== false) {
            $engLabel = str_replace($id, $en, $month_label);
            break;
        }
    }

    // Parse English label
    $dateObj = DateTime::createFromFormat('F Y', $engLabel);
    if (!$dateObj) {
        echo json_encode(["success" => false, "message" => "Format bulan tidak valid. Gunakan 'Month Year' (e.g. February 2026)."]);
        exit;
    }
    $month = $dateObj->format('m');
    $year = $dateObj->format('Y');

    // 1. Ambil info user (Level, Divisi, & Unit)
    $stmtUser = $conn->prepare("
        SELECT e.division_id, e.unit_id, p.level 
        FROM employees e 
        INNER JOIN positions p ON e.position_id = p.id 
        WHERE e.id = ?
    ");
    $stmtUser->execute([$user_id]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["success" => false, "message" => "User tidak ditemukan."]);
        exit;
    }

    $userLevel = (int) $user['level'];
    $division_id = $user['division_id'];
    $unit_id = $user['unit_id'];

    // 2. Tentukan Filter Subordinat berdasarkan Level
    $subordinateFilter = "";
    $params = [];
    if ($userLevel === 1) {
        // Mudir: Tampilkan semua Kepala Bidang (Level 2)
        $subordinateFilter = "p.level = 2";
    } else if ($userLevel === 2) {
        // Kepala Bidang (Kabid): Tampilkan Kepala Unit/Sub (Level 3) 
        // dan Staff Langsung di bawah divisi (Posisi 'Staf' dengan unit_id kosong)
        $subordinateFilter = "e.division_id = :div_id 
                             AND e.id != :user_id
                             AND (
                                 p.level = 3 
                                 OR (p.name = 'Staf' AND (e.unit_id IS NULL OR e.unit_id = 0))
                             )";
        $params['div_id'] = $division_id;
        $params['user_id'] = $user_id;
    } else if ($userLevel === 3) {
        // Kepala Unit/Sub: Tampilkan semua pegawai dalam satu unit
        $subordinateFilter = "e.unit_id = :unit_id AND e.id != :user_id";
        $params['unit_id'] = $unit_id;
        $params['user_id'] = $user_id;
    } else {
        $subordinateFilter = "1=0";
    }

    // 3. Ambil List Staff Sesuai Filter
    $queryStaff = "
        SELECT e.id, e.full_name, e.profile_photo 
        FROM employees e
        INNER JOIN positions p ON e.position_id = p.id
        WHERE e.status = 'active' AND $subordinateFilter
        ORDER BY e.full_name ASC
    ";
    $stmtStaff = $conn->prepare($queryStaff);
    $stmtStaff->execute($params);
    $staffList = $stmtStaff->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($staffList as $staff) {
        // Hitung Hadir & Telat
        $stmtA = $conn->prepare("
            SELECT 
                SUM(CASE WHEN status = 'Hadir' OR status = 'Tepat Waktu' THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status = 'Telat' THEN 1 ELSE 0 END) as telat
            FROM attendances 
            WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?
        ");
        $stmtA->execute([$staff['id'], $month, $year]);
        $stats = $stmtA->fetch(PDO::FETCH_ASSOC);

        // Hitung Izin/Sakit
        $stmtP = $conn->prepare("
            SELECT COUNT(*) FROM permits 
            WHERE employee_id = ? AND status = 'approved' 
            AND (MONTH(start_date) = ? AND YEAR(start_date) = ?)
        ");
        $stmtP->execute([$staff['id'], $month, $year]);
        $permits = (int) $stmtP->fetchColumn();

        $results[] = [
            "id" => $staff['id'],
            "name" => $staff['full_name'],
            "hadir" => (int) ($stats['hadir'] ?? 0),
            "telat" => (int) ($stats['telat'] ?? 0),
            "absent" => $permits, // Per UI naming convention
            "photo" => $staff['profile_photo']
        ];
    }

    echo json_encode([
        "success" => true,
        "month" => $month_label,
        "data" => $results
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>