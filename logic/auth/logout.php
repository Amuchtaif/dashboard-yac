<?php
require_once '../../config/app.php';

Logger::auth('LOGOUT', 'User logged out successfully');

setcookie('redirect_after_login', '', time() - 3600, '/', '', false, true);

session_unset();
session_destroy();

redirect('views/auth/login.php');
