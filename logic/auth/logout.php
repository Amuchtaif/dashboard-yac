<?php
require_once '../../config/app.php';

Logger::auth('LOGOUT', 'User logged out successfully');

session_unset();
session_destroy();

redirect('views/auth/login.php');
