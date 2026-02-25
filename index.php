<?php
/**
 * Root Router
 * This file redirects users based on their authentication status.
 */

require_once 'config/app.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // If logged in, redirect to dashboard
    redirect('views/dashboard/index.php');
} else {
    // If not logged in, redirect to login page
    redirect('views/auth/login.php');
}
