<?php
date_default_timezone_set('Asia/Jakarta');
// Base URL configuration
define('BASE_URL', 'http://localhost/dashboard-yac');

// App Name
define('APP_NAME', 'AMS Admin');

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
