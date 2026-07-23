<?php
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once BASE_PATH . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

check_login();

$db = new Database();
$conn = $db->getConnection();

// --- Helper Function Hitung Jarak (Haversine) ---
if (!function_exists('calcDistanceMeters')) {
    function calcDistanceMeters($lat1, $lon1, $lat2, $lon2) {
        if (empty($lat1) || empty($lon1) || empty($lat2) || empty($lon2)) return null;
        $earthRadius = 6371000;
        $dLat = deg2rad((float)$lat2 - (float)$lat1);
        $dLon = deg2rad((float)$lon2 - (float)$lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + 
             cos(deg2rad((float)$lat1)) * cos(deg2rad((float)$lat2)) * 
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c);
    }
}

// --- Filter & Search ---
$default_start = date('Y-m-d', strtotime('-1 month'));
$default_end = date('Y-m-d');

$search = isset($_GET['search']) && $_GET['search'] !== '' ? trim($_GET['search']) : null;
$division_id = isset($_GET['division_id']) && $_GET['division_id'] !== '' ? (int)$_GET['division_id'] : null;
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : $default_start;
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : $default_end;

// Build WHERE Clause
$where = " WHERE (e.status = 'active' OR e.status IS NULL) ";
$params = [];

if ($search) {
    $where .= " AND e.full_name LIKE :search ";
    $params[':search'] = "%$search%";
}
if ($division_id) {
    $where .= " AND e.division_id = :division_id ";
    $params[':division_id'] = $division_id;
}
if ($start_date) {
    $where .= " AND a.date >= :start_date ";
    $params[':start_date'] = $start_date;
}
if ($end_date) {
    $where .= " AND a.date <= :end_date ";
    $params[':end_date'] = $end_date;
}

// Check if location_id_out column exists in attendances table, auto-add if missing
$has_loc_out = false;
try {
    $col_check = $conn->query("SHOW COLUMNS FROM attendances LIKE 'location_id_out'")->fetchAll();
    if (empty($col_check)) {
        $conn->exec("ALTER TABLE attendances ADD COLUMN location_id_out INT(11) NULL AFTER location_id");
        $has_loc_out = true;
    } else {
        $has_loc_out = true;
    }
} catch (Exception $e) {
    $has_loc_out = false;
}

// Fetch all filtered records (no limit for export)
if ($has_loc_out) {
    $query = "
        SELECT 
            a.*, 
            e.nik,
            e.full_name, 
            e.email, 
            d.name as division_name,
            l.name as location_name,
            l.latitude as loc_lat_in,
            l.longitude as loc_long_in,
            l.radius_meter as location_radius_in,
            l_out.name as location_out_name,
            l_out.latitude as loc_lat_out,
            l_out.longitude as loc_long_out,
            l_out.radius_meter as location_radius_out
        FROM attendances a
        JOIN employees e ON a.user_id = e.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN locations l ON a.location_id = l.id
        LEFT JOIN locations l_out ON a.location_id_out = l_out.id
        $where
        ORDER BY a.date DESC, a.time_in DESC
    ";
} else {
    $query = "
        SELECT 
            a.*, 
            e.nik,
            e.full_name, 
            e.email, 
            d.name as division_name,
            l.name as location_name,
            l.latitude as loc_lat_in,
            l.longitude as loc_long_in,
            l.radius_meter as location_radius_in,
            l.name as location_out_name,
            l.latitude as loc_lat_out,
            l.longitude as loc_long_out,
            l.radius_meter as location_radius_out
        FROM attendances a
        JOIN employees e ON a.user_id = e.id
        LEFT JOIN divisions d ON e.division_id = d.id
        LEFT JOIN locations l ON a.location_id = l.id
        $where
        ORDER BY a.date DESC, a.time_in DESC
    ";
}

$stmt = $conn->prepare($query);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Riwayat Absensi');

// Set Title Block
$sheet->setCellValue('A1', 'LAPORAN RIWAYAT ABSENSI PEGAWAI');
$sheet->mergeCells('A1:N1');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('1E293B'));
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$subtitle = "Tanggal Cetak: " . date('d-m-Y H:i');
if ($start_date || $end_date) {
    $subtitle .= " | Periode: " . ($start_date ?: '-') . " s/d " . ($end_date ?: '-');
}
$sheet->setCellValue('A2', $subtitle);
$sheet->mergeCells('A2:N2');
$sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));

// Table Headers
$headers = [
    'No.',
    'NIK',
    'Nama Pegawai',
    'Email',
    'Bidang / Divisi',
    'Tanggal',
    'Jam Masuk',
    'Status Masuk',
    'Lokasi Kantor Masuk',
    'Jarak Masuk (Batas Radius)',
    'Jam Pulang',
    'Status Pulang',
    'Lokasi Kantor Pulang',
    'Jarak Pulang (Batas Radius)'
];

$headerRow = 4;
$col = 'A';
foreach ($headers as $h) {
    $sheet->setCellValue($col . $headerRow, $h);
    $col++;
}

// Header Styling
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '0284C7'] // Cyan-600
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '0284C7']
        ]
    ]
];
$sheet->getStyle('A4:N4')->applyFromArray($headerStyle);
$sheet->getRowDimension(4)->setRowHeight(28);

// Populate Data Rows
$days = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$rowNum = 5;

foreach ($logs as $index => $log) {
    $dayName = date('l', strtotime($log['date']));
    $formattedDate = ($days[$dayName] ?? $dayName) . ', ' . date('d-m-Y', strtotime($log['date']));
    
    $locInName = $log['location_name'] ?? '-';
    $locOutName = !empty($log['time_out']) ? ($log['location_out_name'] ?? $log['location_name'] ?? '-') : '-';
    
    $distIn = calcDistanceMeters($log['lat_in'], $log['long_in'], $log['loc_lat_in'], $log['loc_long_in']);
    $distOut = !empty($log['lat_out']) ? calcDistanceMeters($log['lat_out'], $log['long_out'], $log['loc_lat_out'] ?? $log['loc_lat_in'], $log['loc_long_out'] ?? $log['loc_long_in']) : null;
    
    $radInStr = ($distIn !== null) ? $distIn . " m (Batas: " . ($log['location_radius_in'] ?? 300) . "m)" : "-";
    $radOutStr = (!empty($log['time_out']) && $distOut !== null) ? $distOut . " m (Batas: " . ($log['location_radius_out'] ?? $log['location_radius_in'] ?? 300) . "m)" : "-";

    $sheet->setCellValue('A' . $rowNum, $index + 1);
    $sheet->setCellValueExplicit('B' . $rowNum, $log['nik'] ?? '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C' . $rowNum, $log['full_name']);
    $sheet->setCellValue('D' . $rowNum, $log['email']);
    $sheet->setCellValue('E' . $rowNum, $log['division_name'] ?? '-');
    $sheet->setCellValue('F' . $rowNum, $formattedDate);
    $sheet->setCellValue('G' . $rowNum, $log['time_in'] ? date('H:i:s', strtotime($log['time_in'])) : '-');
    $sheet->setCellValue('H' . $rowNum, $log['status'] ?? '-');
    $sheet->setCellValue('I' . $rowNum, $locInName);
    $sheet->setCellValue('J' . $rowNum, $radInStr);
    $sheet->setCellValue('K' . $rowNum, $log['time_out'] ? date('H:i:s', strtotime($log['time_out'])) : '-');
    $sheet->setCellValue('L' . $rowNum, $log['time_out'] ? ($log['status_out'] ?? 'Pulang') : '-');
    $sheet->setCellValue('M' . $rowNum, $locOutName);
    $sheet->setCellValue('N' . $rowNum, $radOutStr);

    // Row Height & Alignment
    $sheet->getRowDimension($rowNum)->setRowHeight(22);
    
    // Zebra Striping
    if ($index % 2 == 1) {
        $sheet->getStyle("A{$rowNum}:N{$rowNum}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
    }

    $rowNum++;
}

$lastDataRow = max(5, $rowNum - 1);

// Data Borders & Alignment
$dataBorderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'E2E8F0']
        ]
    ]
];
$sheet->getStyle("A5:N{$lastDataRow}")->applyFromArray($dataBorderStyle);
$sheet->getStyle("A5:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("B5:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("F5:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("J5:J{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("K5:L{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle("N5:N{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Auto-fit Column Widths
foreach (range('A', 'N') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Redirect Output to Browser Download
$filename = "Riwayat_Absensi_Pegawai_" . date('Y-m-d_His') . ".xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1'); // Needed for IE
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
