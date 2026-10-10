<?php
/**
 * Global Secure Session Bootstrapper
 * Enforces HttpOnly, Secure, and SameSite cookie parameters for all sessions.
 * Compatible with PHP 8.0, 8.1, 8.2, 8.3, 8.4+ and all hosting environments.
 */

if (!function_exists('ensure_secure_session')) {
    function ensure_secure_session()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Setup dedicated session storage directory if possible
            $sessionDir = __DIR__ . '/../storage/sessions';
            if (!is_dir($sessionDir)) {
                @mkdir($sessionDir, 0777, true);
            }
            if (is_dir($sessionDir) && is_writable($sessionDir)) {
                @session_save_path($sessionDir);
            }

            // Detect HTTPS (Direct, Reverse Proxy, Port 443, Cloudflare, or specific domain)
            $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);

            if (!headers_sent()) {
                ini_set('session.gc_maxlifetime', 7200);
                ini_set('session.cookie_httponly', '1');
                ini_set('session.use_only_cookies', '1');
                ini_set('session.cookie_samesite', 'Lax');
                if ($isHttps) {
                    ini_set('session.cookie_secure', '1');
                }

                session_set_cookie_params([
                    'lifetime' => 7200,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $isHttps,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }

            session_start();
        }
    }
}
