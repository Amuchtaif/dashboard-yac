<?php
// api/agenda/public.php
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';

// Helper to fetch Public Holidays from API
function get_public_holidays($year) {
    if (!$year) $year = (int)date('Y');
    $cache_file = __DIR__ . "/../../tmp/holidays_$year.json";
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if (is_array($cached_data) && isset($cached_data['data'])) {
            return $cached_data['data'];
        }
        return [];
    }
    
    $url = "https://api-hari-libur.vercel.app/api?year=$year";
    $json = @file_get_contents($url);
    if (!$json && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $json = curl_exec($ch);
    }

    if ($json) {
        if (!is_dir(__DIR__ . "/../../tmp")) mkdir(__DIR__ . "/../../tmp", 0777, true);
        file_put_contents($cache_file, $json);
        $decoded_data = json_decode($json, true);
        if (is_array($decoded_data) && isset($decoded_data['data'])) {
            return $decoded_data['data'];
        }
    }
    return [];
}

// Rate Limiting: 60 requests per minute per IP
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$rateLimitFile = __DIR__ . '/../../tmp/rate_limit_' . md5($ip) . '.json';
$now = time();
$window = 60; // 1 minute
$maxRequests = 60;

if (file_exists($rateLimitFile)) {
    $rateData = json_decode(file_get_contents($rateLimitFile), true);
    if ($rateData && ($now - $rateData['start_time']) < $window) {
        if ($rateData['count'] >= $maxRequests) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too Many Requests']);
            exit;
        }
        $rateData['count']++;
    } else {
        $rateData = ['start_time' => $now, 'count' => 1];
    }
} else {
    $rateData = ['start_time' => $now, 'count' => 1];
}
if (!is_dir(__DIR__ . '/../../tmp')) mkdir(__DIR__ . '/../../tmp', 0777, true);
file_put_contents($rateLimitFile, json_encode($rateData));

$db = new Database();
$conn = $db->getConnection();

try {
    // Fetch filters
    $academic_year_id = isset($_GET['academic_year_id']) && $_GET['academic_year_id'] !== '' ? (int)$_GET['academic_year_id'] : null;
    $semester = isset($_GET['semester']) && $_GET['semester'] !== '' && $_GET['semester'] !== 'Semua' ? $_GET['semester'] : null;
    $source_type = isset($_GET['source_type']) && $_GET['source_type'] !== '' && $_GET['source_type'] !== 'Semua' ? $_GET['source_type'] : null;
    $category = isset($_GET['category']) && $_GET['category'] !== '' && $_GET['category'] !== 'Semua' ? $_GET['category'] : null;
    $unit_id = isset($_GET['unit_id']) && $_GET['unit_id'] !== '' && $_GET['unit_id'] !== 'Semua' ? (int)$_GET['unit_id'] : null;
    $month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
    $year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;

    $conditions = ["a.visibility = 'public'"];
    $params = [];

    if ($academic_year_id) {
        $conditions[] = "a.academic_year_id = :academic_year_id";
        $params[':academic_year_id'] = $academic_year_id;
    }
    if ($semester) {
        $conditions[] = "(a.semester = :semester OR a.semester = 'Semua')";
        $params[':semester'] = $semester;
    }
    if ($source_type) {
        $conditions[] = "a.source_type = :source_type";
        $params[':source_type'] = $source_type;
    }
    if ($category) {
        $conditions[] = "a.category = :category";
        $params[':category'] = $category;
    }
    if ($unit_id) {
        $conditions[] = "a.unit_id = :unit_id";
        $params[':unit_id'] = $unit_id;
    }
    if ($month && $year) {
        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $last_day = date('Y-m-t', strtotime($first_day));
        $conditions[] = "(a.start_date <= :last_day AND (a.end_date >= :first_day OR a.end_date IS NULL))";
        $params[':first_day'] = $first_day;
        $params[':last_day'] = $last_day;
    } elseif ($year) {
        $conditions[] = "(YEAR(a.start_date) = :year OR YEAR(a.end_date) = :year)";
        $params[':year'] = $year;
    }

    $whereClause = implode(' AND ', $conditions);
    $sql = "SELECT a.id, a.title, a.description, a.start_date, a.end_date, a.start_time, a.end_time, 
                   a.location, a.category, a.source_type, a.unit_id, a.academic_year_id, a.semester, 
                   a.status, a.color, a.is_holiday, u.name as unit_name, ay.name as academic_year_name 
            FROM academic_calendar a 
            LEFT JOIN education_units u ON a.unit_id = u.id 
            LEFT JOIN academic_years ay ON a.academic_year_id = ay.id 
            WHERE {$whereClause} 
            ORDER BY a.start_date ASC, a.start_time ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $agendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Public Holidays from API if filters allow national holidays
    if (!$unit_id && (!$source_type || $source_type === 'yayasan') && (!$category || $category === 'Libur Nasional')) {
        $targetYear = $year ? $year : (int)date('Y');
        $apiHolidays = get_public_holidays($targetYear);

        foreach ($apiHolidays as $h) {
            if (!isset($h['date']) || !isset($h['description'])) continue;
            $hDate = $h['date'];

            if ($month && $year) {
                $first_day = sprintf('%04d-%02d-01', $year, $month);
                $last_day = date('Y-m-t', strtotime($first_day));
                if ($hDate < $first_day || $hDate > $last_day) continue;
            } elseif ($year) {
                if ((int)date('Y', strtotime($hDate)) !== (int)$year) continue;
            }

            // Check duplicate with DB agendas on same date & title or holiday category
            $isDuplicate = false;
            foreach ($agendas as $dbAgenda) {
                if ($dbAgenda['start_date'] === $hDate && (mb_strtolower($dbAgenda['title']) === mb_strtolower($h['description']) || $dbAgenda['category'] === 'Libur Nasional')) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $agendas[] = [
                    'id' => 'api_' . md5($hDate . $h['description']),
                    'title' => $h['description'],
                    'description' => 'Hari Libur Nasional (API)',
                    'start_date' => $hDate,
                    'end_date' => $hDate,
                    'start_time' => null,
                    'end_time' => null,
                    'location' => 'Nasional',
                    'category' => 'Libur Nasional',
                    'source_type' => 'yayasan',
                    'unit_id' => null,
                    'academic_year_id' => $academic_year_id,
                    'semester' => 'Semua',
                    'status' => 'approved',
                    'color' => '#dc2626',
                    'is_holiday' => 1,
                    'unit_name' => null,
                    'academic_year_name' => null,
                    'is_api' => true
                ];
            }
        }

        // Re-sort agendas by start_date ASC, start_time ASC
        usort($agendas, function($a, $b) {
            $cmp = strcmp($a['start_date'], $b['start_date']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
        });
    }

    // Fetch units & academic years metadata for filter dropdowns
    $unitsStmt = $conn->query("SELECT id, name FROM education_units ORDER BY name ASC");
    $units = $unitsStmt->fetchAll(PDO::FETCH_ASSOC);

    $ayStmt = $conn->query("SELECT id, name, is_active FROM academic_years ORDER BY start_date DESC");
    $academic_years = $ayStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($agendas),
        'data' => $agendas,
        'meta' => [
            'units' => $units,
            'academic_years' => $academic_years
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

