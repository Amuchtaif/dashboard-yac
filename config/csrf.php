<?php
/**
 * CSRF Protection Helper
 * Provides CSRF token generation, input rendering, and request validation.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate or get existing CSRF token from session
 *
 * @return string
 */
function csrf_token()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Output hidden input field with CSRF token
 *
 * @return string
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate incoming CSRF token against session
 *
 * @param string|null $token Custom token to validate, or null to auto-detect from POST/Header
 * @return bool
 */
function verify_csrf($token = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    if ($token === null) {
        // Try to get token from POST body
        if (!empty($_POST['csrf_token'])) {
            $token = $_POST['csrf_token'];
        } elseif (!empty($_GET['csrf_token'])) {
            // Try GET parameter (used in secure confirmation links)
            $token = $_GET['csrf_token'];
        } elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            // Try X-CSRF-TOKEN header
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
        } elseif (!empty($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            // Try X-XSRF-TOKEN header
            $token = $_SERVER['HTTP_X_XSRF_TOKEN'];
        } else {
            // Try JSON body if applicable
            $rawInput = file_get_contents('php://input');
            if ($rawInput) {
                $decoded = json_decode($rawInput, true);
                if (is_array($decoded) && !empty($decoded['csrf_token'])) {
                    $token = $decoded['csrf_token'];
                }
            }
        }
    }

    if (empty($token) || !is_string($token)) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

/**
 * Enforce CSRF check for POST requests. Terminate with 403 if invalid.
 *
 * @param string|null $errorMessage
 * @return void
 */
function require_csrf($errorMessage = 'Akses ditolak: Token CSRF tidak valid atau telah kedaluwarsa.')
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf()) {
            if (class_exists('Logger')) {
                Logger::auth('CSRF_ERROR', 'CSRF validation failed for URI: ' . ($_SERVER['REQUEST_URI'] ?? ''));
            }

            // Return JSON if AJAX or JSON request
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

            if ($isAjax) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]);
                exit;
            }

            http_response_code(403);
            die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>403 Forbidden</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8fafc;color:#334155;} .box{background:#fff;padding:2rem;border-radius:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.05);max-width:480px;text-align:center;} h1{color:#e11d48;font-size:1.5rem;margin-bottom:0.5rem;} p{font-size:0.95rem;line-height:1.5;color:#64748b;} a{display:inline-block;margin-top:1.5rem;padding:0.6rem 1.2rem;background:#0284c7;color:#fff;border-radius:0.5rem;text-decoration:none;font-weight:600;font-size:0.875rem;}</style></head><body><div class="box"><h1>403 - Akses Ditolak</h1><p>' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</p><a href="javascript:history.back()">&larr; Kembali</a></div></body></html>');
        }
    }
}
