<?php
require_once '../../config/app.php';

session_unset();
session_destroy();

redirect('views/auth/login.php');
