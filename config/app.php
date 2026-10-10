<?php
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/Logger.php';
// Detect HTTPS (Direct HTTPS, Reverse Proxy, Port 443, Cloudflare, or specific production domain)
$isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && !empty($_SERVER['HTTPS']))
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'assunnahcirebon.com') !== false);

// Base URL configuration (AUTO-DETECTED)
if (!defined('BASE_URL')) {
    $protocol = $isHttps ? "https" : "http";
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    
    // Normalize path separators and symlinks for both Windows and Linux hosting
    $docRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT']), '/') : '';
    $projectRoot = rtrim(str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)), '/');
    define('BASE_PATH', $projectRoot);
    
    // Find the relative path from DocumentRoot to project root
    $relativePath = '';
    // On Windows or case-insensitive filesystems, compare normalized paths
    if ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0) {
        $relativePath = substr($projectRoot, strlen($docRoot));
    }
    
    // Ensure relativePath starts with / and ends without /
    $relativePath = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    $relativePath = rtrim($relativePath, '/');
    
    define('BASE_URL', $protocol . "://" . $host . $relativePath);
}

// App Name
define('APP_NAME', 'Dashboard YAC');

// Dedicated Session Directory (mencegah default Garbage Collector XAMPP menghapus sesi di 24 menit)
$sessionDir = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0777, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}

// Inactivity / Idle Timeout Configuration: 1 Jam (3600 detik)
define('SESSION_TIMEOUT_DURATION', 3600);

// Secure Session Initialization
require_once __DIR__ . '/session.php';
ensure_secure_session();

// CSRF Protection Helper
require_once __DIR__ . '/csrf.php';

// CSP Nonce Generator Helper
if (!function_exists('csp_nonce')) {
    function csp_nonce()
    {
        static $nonce = null;
        if ($nonce === null) {
            $nonce = base64_encode(random_bytes(16));
        }
        return $nonce;
    }
}
$cspNonce = csp_nonce();

// Security Headers (CSP, HSTS, Permissions-Policy, Nosniff, Frame Options, Referrer Policy)
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=(self)");
    if ($isHttps) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$cspNonce}' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://unpkg.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: blob: https:; connect-src 'self' https:; frame-src 'self' https:; object-src 'none'; base-uri 'self';");
}

/**
 * Helper to redirect
 */
function redirect($path)
{
    // Hapus .php jika bukan di folder api untuk mendukung clean URL
    if (strpos($path, 'api/') === false) {
        $path = preg_replace('/\.php$/', '', $path);
    }
    header("Location: " . BASE_URL . "/" . $path);
    exit;
}

/**
 * Helper to check if user is logged in with 1-Hour Inactivity Timeout
 */
function check_login()
{
    if (!isset($_SESSION['user_id'])) {
        redirect('views/auth/login.php');
    }

    // Cek Idle / Inactivity Timeout (1 Jam = 3600 detik)
    $timeout_duration = defined('SESSION_TIMEOUT_DURATION') ? SESSION_TIMEOUT_DURATION : 3600;

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        $user_id = $_SESSION['user_id'] ?? null;
        if ($user_id && class_exists('Logger')) {
            Logger::auth('SESSION_TIMEOUT', "Session timed out after 1 hour of inactivity for user ID: {$user_id}");
        }

        // Hapus data sesi
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        $error_msg = urlencode("Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 jam. Silakan masuk kembali.");
        redirect("views/auth/login.php?error={$error_msg}");
    }

    // Perbarui waktu aktivitas terakhir
    $_SESSION['last_activity'] = time();
}

/**
 * Helper to check permission and abort if unauthorized
 */
function check_permission($permission)
{
    check_login();
    require_once __DIR__ . '/permission.php';
    if (!hasPermission($_SESSION['user_id'], $permission)) {
        // Redirect to dashboard with error or show 403
        header("Location: " . BASE_URL . "/views/dashboard/index");
        exit;
    }
}

/**
 * Helper to check permission without aborting
 */
function can($permission)
{
    if (!isset($_SESSION['user_id'])) return false;
    require_once __DIR__ . '/permission.php';
    return hasPermission($_SESSION['user_id'], $permission);
}

/**
 * Helper to get asset url
 */
function asset($path)
{
    echo BASE_URL . '/assets/' . $path;
}

/**
 * Helper to get url
 */
function url($path)
{
    // Hapus .php jika bukan di folder api untuk mendukung clean URL
    if (strpos($path, 'api/') === false) {
        $path = preg_replace('/\.php$/', '', $path);
    }
    echo BASE_URL . '/' . $path;
}

// Error Reporting (Turn off for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * --- INVENTORY HELPERS ---
 */
function generateLocationCode($locName, $parentCode = null, $conn = null, $parent_id = null) {
    if (!$locName) return 'LOC' . rand(100, 999);

    // If we have DB connection and parent_id, generate professional sequential code
    if ($conn && $parent_id) {
        if (!$parentCode) {
            $pStmt = $conn->prepare("SELECT location_code FROM inventory_locations WHERE id = ?");
            $pStmt->execute([$parent_id]);
            $parentCode = $pStmt->fetchColumn();
        }

        // Count existing children to determine the next sequence number
        $cStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_locations WHERE parent_id = ?");
        $cStmt->execute([$parent_id]);
        $count = (int)$cStmt->fetchColumn() + 1;

        return strtoupper($parentCode . "." . str_pad($count, 2, '0', STR_PAD_LEFT));
    }

    // Default/Fallback logic: Use initials
    $words = explode(' ', trim($locName));
    $initials = '';
    foreach ($words as $w) {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $w));
        if ($clean !== '') $initials .= $clean[0];
    }
    
    // For single word, take first 3 chars
    if (strlen($initials) < 2) {
        $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $locName), 0, 3));
    }

    $initials = substr($initials, 0, 4);
    
    if ($parentCode) {
        return strtoupper($parentCode . '-' . $initials);
    }
    return strtoupper($initials);
}

function generateItemCodeV2($conn, $location_id, $itemName, $id) {
    // Get the actual location code from the database
    $locStmt = $conn->prepare("SELECT location_code FROM inventory_locations WHERE id = ?");
    $locStmt->execute([$location_id]);
    $locCode = $locStmt->fetchColumn() ?: 'LOC';

    $namePrefix = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $itemName)), 0, 3);
    
    // Count items in this location to determine sequence
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_items WHERE location_id = ? AND id <= ?");
    $countStmt->execute([$location_id, $id]);
    $sequence = (int)$countStmt->fetchColumn();

    $seqStr = str_pad($sequence, 3, '0', STR_PAD_LEFT);
    return "$locCode-$namePrefix-$seqStr";
}
?>
