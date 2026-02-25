<?php
date_default_timezone_set('Asia/Jakarta');
// Base URL configuration
define('BASE_URL', 'http://localhost/dashboard-yac');

// App Name
define('APP_NAME', 'Dashboard YAC');

// Set Session Lifetime to 6 hours (21600 seconds)
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(21600);

// Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Helper to redirect
 */
function redirect($path)
{
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
        header("Location: " . BASE_URL . "/views/dashboard/index.php?error=unauthorized");
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
    echo BASE_URL . '/' . $path;
}

// Error Reporting (Turn off for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
