<?php
class GoogleAccessToken
{
    private $keyFile;

    public function __construct($jsonKeyFilePath)
    {
        if (!file_exists($jsonKeyFilePath)) {
            throw new Exception("File Service Account tidak ditemukan: " . $jsonKeyFilePath);
        }
        $this->keyFile = json_decode(file_get_contents($jsonKeyFilePath), true);
    }

    public function getToken()
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $claim = [
            'iss' => $this->keyFile['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ];

        $base64Header = $this->base64UrlEncode(json_encode($header));
        $base64Claim = $this->base64UrlEncode(json_encode($claim));
        $signatureInput = $base64Header . "." . $base64Claim;

        $signature = '';
        openssl_sign($signatureInput, $signature, $this->keyFile['private_key'], 'SHA256');
        $base64Signature = $this->base64UrlEncode($signature);
        $jwt = $signatureInput . "." . $base64Signature;

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

        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    private function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
?>