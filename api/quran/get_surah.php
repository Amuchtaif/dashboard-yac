<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$nomor = isset($_GET['nomor']) ? (int)$_GET['nomor'] : 1;

if ($nomor < 1 || $nomor > 114) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid surah number"]);
    exit;
}

$filename = "suras/" . $nomor . ".json";

if (file_exists($filename)) {
    echo file_get_contents($filename);
} else {
    // Fetch from equran.id
    $url = "https://equran.id/api/v2/surat/" . $nomor;
    $content = @file_get_contents($url);
    if ($content) {
        file_put_contents($filename, $content);
        echo $content;
    } else {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "Surah not found"]);
    }
}
?>
