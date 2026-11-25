<?php
// api/app_version.php
// Endpoint pengecekan versi aplikasi Flutter & pelaporan versi user
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// 1. Terima pelaporan versi dari perangkat Flutter (jika dikirim via GET/POST/JSON)
$input = json_decode(file_get_contents("php://input"), true) ?: [];
$userId = (int)($_POST['employee_id'] ?? $_GET['employee_id'] ?? $input['employee_id'] ?? $input['user_id'] ?? 0);
$clientVersion = trim($_POST['app_version'] ?? $_GET['app_version'] ?? $input['app_version'] ?? '');
$clientBuild = (int)($_POST['build_number'] ?? $_GET['build_number'] ?? $input['build_number'] ?? 0);

if ($userId > 0 && ($clientVersion !== '' || $clientBuild > 0)) {
    try {
        $stmtUpdate = $conn->prepare("
            UPDATE employees 
            SET app_version = :ver, 
                app_build_number = :build, 
                last_mobile_activity = NOW() 
            WHERE id = :id
        ");
        $stmtUpdate->execute([
            ':ver' => $clientVersion ?: null,
            ':build' => $clientBuild > 0 ? $clientBuild : null,
            ':id' => $userId
        ]);
    } catch (\Throwable $e) {
        // shortcut: silent fallback jika update gagal agar tidak memutus respon versi
    }
}

// 2. Ambil konfigurasi versi terbaru dari database
$settingsKeys = ['app_latest_version', 'app_latest_build', 'app_min_build_required', 'app_apk_url', 'app_changelog'];
$placeholders = implode(',', array_fill(0, count($settingsKeys), '?'));
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($placeholders)");
$stmt->execute($settingsKeys);
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$latestVersion = $settings['app_latest_version'] ?? '1.0.0';
$latestBuild = (int)($settings['app_latest_build'] ?? 1);
$minBuild = (int)($settings['app_min_build_required'] ?? 1);
$apkUrl = $settings['app_apk_url'] ?? '';
$changelog = $settings['app_changelog'] ?? 'Pembaruan aplikasi berkala dan stabilitas sistem.';

// Normalisasi URL APK jika disimpan dalam path relatif lokal
if (!empty($apkUrl) && !preg_match('/^https?:\/\//i', $apkUrl)) {
    $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);
    $protocol = $isHttps ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'dashboard.assunnahcirebon.com';
    $apkUrl = rtrim($protocol . "://" . $host, '/') . '/' . ltrim($apkUrl, '/');
}

echo json_encode([
    "success" => true,
    "latest_version" => $latestVersion,
    "latest_build" => $latestBuild,
    "min_build_required" => $minBuild,
    "apk_url" => $apkUrl,
    "changelog" => $changelog,
    "server_time" => date('Y-m-d H:i:s')
]);

