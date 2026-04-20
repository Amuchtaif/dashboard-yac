<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$type = isset($_GET['type']) ? $_GET['type'] : 'pagi';
$filename = "data/dzikir-" . $type . ".json";

if (file_exists($filename)) {
    $raw = json_decode(file_get_contents($filename), true);
    
    if ($raw === null) {
         echo json_encode(["status" => 500, "message" => "Error parsing local JSON"]);
         exit;
    }

    $transformed = [];
    foreach ($raw as $item) {
        $transformed[] = [
            '_id' => (string)($item['id'] ?? ''),
            'arab' => $item['arabic'] ?? '',
            'indo' => $item['translation'] ?? '',
            'type' => $type,
            'ulang' => $item['read'] ?? ''
        ];
    }
    
    echo json_encode([
        "status" => 200, 
        "data" => $transformed
    ]);
} else {
    http_response_code(404);
    echo json_encode(["status" => 404, "message" => "Dzikir type not found"]);
}
?>
