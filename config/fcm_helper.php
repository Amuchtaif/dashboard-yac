<?php
// config/fcm_helper.php

class FcmHelper
{
    private $serviceAccountPath;
    private $projectId;

    public function __construct()
    {
        $this->serviceAccountPath = __DIR__ . '/service-account.json';
        if (!file_exists($this->serviceAccountPath)) {
            error_log("FCM Error: service-account.json not found in " . $this->serviceAccountPath);
            return;
        }
        $json = json_decode(file_get_contents($this->serviceAccountPath), true);
        $this->projectId = $json['project_id'];
    }

    private function getAccessToken()
    {
        if (!file_exists($this->serviceAccountPath))
            return null;

        $key = json_decode(file_get_contents($this->serviceAccountPath), true);

        // Manual JWT Generation for Google Auth
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $claims = json_encode([
            'iss' => $key['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Claims = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claims));

        $signatureInput = $base64Header . "." . $base64Claims;
        $signature = '';
        openssl_sign($signatureInput, $signature, $key['private_key'], 'SHA256');
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $signatureInput . "." . $base64Signature;

        // Exchange JWT for Access Token
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

        $jsonResp = json_decode($response, true);
        return $jsonResp['access_token'] ?? null;
    }

    public function sendNotification($token, $title, $body, $data = [])
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            error_log("FCM Error: Failed to get Access Token");
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => $data // Data payload must be strings
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        if ($result === FALSE) {
            error_log("FCM Curl Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);

        return json_decode($result, true);
    }
}
?>