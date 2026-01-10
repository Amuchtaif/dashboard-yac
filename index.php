<?php
require_once 'config/app.php';

// If user is logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    redirect('views/dashboard/index.php');
} else {
    redirect('views/auth/login.php');
}
