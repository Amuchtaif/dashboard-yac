<?php
// api/test_push.php
header('Content-Type: application/json');

// --- KONFIGURASI ---
$serviceAccountPath = 'service-account.json'; // Pastikan file ini ada!
$projectId = 'GANTI_DENGAN_PROJECT_ID_DI_JSON_ANDA'; // <--- WAJIB GANTI INI!!

// Token HP Anda (Saya ambil dari log yang Anda kirim)
$targetToken = 'dBZdcG_FT0y9pCOuy9KTnu:APA91bGvYfUVoz1_zI1Q8lWYBYuIAKdiAzBcsRyc3Qh8Lwh6DI1vVAe5Y-ZQO6zHhY58GNFbBg4ASU-xxpuRiq1skLcG-zjOxCVuiAMM_5A3BTCUjLRaACU';

// --- 1. Load Service Account ---
if (!file_exists($serviceAccountPath)) {
    die(json_encode(["error" => "File service-account.json tidak ditemukan di folder api!"]));
}

$credentials = json_decode(file_get_contents($serviceAccountPath), true);
$clientEmail = $credentials['client_email'];
$privateKey = $credentials['private_key'];

// Jika lupa ganti Project ID, kita coba ambil otomatis dari JSON
if ($projectId == 'GANTI_DENGAN_PROJECT_ID_DI_JSON_ANDA') {
    $projectId = $credentials['project_id'];
}

// --- 2. Generate Google Access Token (JWT Manual) ---
function base64UrlEncode($data)
{
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
}

$header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
$now = time();
$payload = json_encode([
    'iss' => $clientEmail,
    'sub' => $clientEmail,
    'aud' => 'https://oauth2.googleapis.com/token',
    'iat' => $now,
    'exp' => $now + 3600,
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
]);

$base64Header = base64UrlEncode($header);
$base64Payload = base64UrlEncode($payload);
$signatureInput = $base64Header . "." . $base64Payload;

$signature = '';
openssl_sign($signatureInput, $signature, $privateKey, 'SHA256');
$jwt = $signatureInput . "." . base64UrlEncode($signature);

// Tukar JWT dengan Access Token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
    'assertion' => $jwt
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);
if (!isset($tokenData['access_token'])) {
    die(json_encode(["error" => "Gagal dapat Access Token Google", "details" => $response]));
}
$accessToken = $tokenData['access_token'];

// --- 3. Kirim Notifikasi (FCM V1) ---
$fcmUrl = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

$payloadData = [
    'message' => [
        'token' => $targetToken,
        'notification' => [
            'title' => 'Tes Masuk!',
            'body' => 'Jika ini muncul, berarti server PHP sudah benar.'
        ],
        'data' => [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'screen' => 'dashboard'
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fcmUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(["error" => "Curl Error: " . curl_error($ch)]);
} else {
    echo "Respon Google: " . $result;
}
curl_close($ch);
?>