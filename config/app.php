<?php
date_default_timezone_set('Asia/Jakarta');
// Base URL configuration (AUTO-DETECTED)
if (!defined('BASE_URL')) {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Normalize path separators for both Windows and Linux
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $projectRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');
    define('BASE_PATH', $projectRoot);
    
    // Find the relative path from DocumentRoot to project root
    $relativePath = '';
    // On Windows, drive letters might be different cases, so we case-normalize for path comparison
    if (stripos($projectRoot, $docRoot) === 0) {
        $relativePath = substr($projectRoot, strlen($docRoot));
    }
    
    // Ensure relativePath starts with / and ends without /
    $relativePath = '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    $relativePath = rtrim($relativePath, '/');
    
    define('BASE_URL', $protocol . "://" . $host . $relativePath);
}

// App Name
define('APP_NAME', 'Dashboard YAC');

// Set Session Lifetime to 6 hours (21600 seconds)
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(['lifetime' => 21600]);

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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
 * Helper to check if user is logged in
 */
function check_login()
{
    if (!isset($_SESSION['user_id'])) {
        redirect('views/auth/login.php');
    }
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
function generateLocationCode($locName, $parentCode = null) {
    if (!$locName) return 'LOC' . rand(100, 999);
    
    // Clean and get initials
    $words = explode(' ', trim($locName));
    $initials = '';
    foreach ($words as $w) {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $w));
        if ($clean !== '') $initials .= $clean[0];
    }
    
    // Limit initials length
    $initials = substr($initials, 0, 4);
    
    if ($parentCode) {
        return strtoupper($parentCode . '-' . $initials);
    }
    return strtoupper($initials);
}

function generateItemCodeV2($conn, $location_id, $locName, $itemName, $id) {
    $locCode = generateLocationCode($locName);
    $namePrefix = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $itemName)), 0, 3);
    
    // Count items in this location to determine sequence
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM inventory_items WHERE location_id = ? AND id <= ?");
    $countStmt->execute([$location_id, $id]);
    $sequence = (int)$countStmt->fetchColumn();

    $seqStr = str_pad($sequence, 3, '0', STR_PAD_LEFT);
    return "$locCode-$namePrefix-$seqStr";
}
?>
