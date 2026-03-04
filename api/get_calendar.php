<?php
// api/get_calendar.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Helper to Fetch Public Holidays
function get_public_holidays($year) {
    if (!$year) $year = date('Y');
    $cache_file = dirname(__DIR__) . "/tmp/holidays_$year.json";
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 86400) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        return is_array($cached_data) ? $cached_data : [];
    }
    
    $url = "https://libur.deno.dev/api?year=$year";
    $json = @file_get_contents($url);
    if (!$json && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $json = curl_exec($ch);
        curl_close($ch);
    }

    if ($json) {
        if (!is_dir(dirname(__DIR__) . "/tmp")) mkdir(dirname(__DIR__) . "/tmp", 0777, true);
        file_put_contents($cache_file, $json);
        $decoded_data = json_decode($json, true);
        return is_array($decoded_data) ? $decoded_data : [];
    }
    return [];
}

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    
    // 1. Fetch Local Database Events
    $query = "SELECT id, title, description, start_date, end_date, category, is_holiday, color 
              FROM academic_calendar 
              WHERE YEAR(start_date) = :year OR YEAR(end_date) = :year
              ORDER BY start_date ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':year', $year);
    $stmt->execute();
    
    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            "id" => (int)$row['id'],
            "title" => $row['title'],
            "description" => $row['description'],
            "start_date" => $row['start_date'],
            "end_date" => $row['end_date'],
            "category" => $row['category'],
            "is_holiday" => (bool)$row['is_holiday'],
            "color" => $row['color'],
            "is_api" => false
        ];
    }

    // 2. Fetch Public Holidays from API
    $api_holidays = get_public_holidays($year);
    foreach ($api_holidays as $h) {
        if (isset($h['date']) && isset($h['name'])) {
            $events[] = [
                "id" => "api_" . md5($h['date'] . $h['name']),
                "title" => $h['name'],
                "description" => "Hari Libur Nasional (API)",
                "start_date" => $h['date'],
                "end_date" => $h['date'],
                "category" => "Libur Nasional",
                "is_holiday" => true,
                "color" => "#ef4444",
                "is_api" => true
            ];
        }
    }

    // Sort by date
    usort($events, function($a, $b) {
        return strcmp($a['start_date'], $b['start_date']);
    });

    echo json_encode([
        "success" => true,
        "year" => $year,
        "total" => count($events),
        "data" => $events
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
