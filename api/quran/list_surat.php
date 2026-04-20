<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$filename = "surat.json";

if (file_exists($filename)) {
    echo file_get_contents($filename);
} else {
    // Try to fetch if not exists
    $url = "https://equran.id/api/v2/surat";
    $content = file_get_contents($url);
    if ($content) {
        file_put_contents($filename, $content);
        echo $content;
    } else {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Surat list not found"]);
    }
}
?>
