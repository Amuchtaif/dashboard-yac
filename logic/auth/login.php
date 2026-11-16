<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!verify_csrf()) {
        Logger::auth('CSRF_ERROR', 'CSRF token validation failed on web login');
        header("Location: ../../views/auth/login.php?error=Sesi+kedaluwarsa+atau+token+keamanan+tidak+valid.+Silakan+coba+lagi.");
        exit;
    }

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        header("Location: ../../views/auth/login.php?error=Semua+kolom+wajib+diisi");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("SELECT e.*, p.name as position_name FROM employees e LEFT JOIN positions p ON e.position_id = p.id WHERE e.email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_photo'] = $user['profile_photo'];
            $_SESSION['position_name'] = $user['position_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['last_activity'] = time();

            Logger::auth('LOGIN', 'User logged in successfully');

            // Redirect ke halaman sebelum sesi habis jika ada
            $redirect_url = '../../views/dashboard/index.php';
            if (!empty($_COOKIE['redirect_after_login'])) {
                $target = $_COOKIE['redirect_after_login'];
                setcookie('redirect_after_login', '', time() - 3600, '/', '', false, true);
                // Validasi agar hanya internal path yang valid dan bukan rute login/logout
                if (strpos($target, '://') === false && substr($target, 0, 1) === '/' && substr($target, 0, 2) !== '//' && strpos($target, 'login') === false && strpos($target, 'logout') === false) {
                    $redirect_url = $target;
                }
            }

            header("Location: " . $redirect_url);
            exit;
        } else {
            Logger::auth('LOGIN_FAILED', "Failed login attempt for email: $email");
            header("Location: ../../views/auth/login.php?error=Email+atau+password+salah");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../../views/auth/login.php?error=Terjadi+kesalahan+sistem");
    }
} else {
    redirect('views/auth/login.php');
}
