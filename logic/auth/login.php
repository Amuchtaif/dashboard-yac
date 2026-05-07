<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

            header("Location: ../../views/dashboard/index.php");
            exit;
        } else {
            header("Location: ../../views/auth/login.php?error=Email+atau+password+salah");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../../views/auth/login.php?error=Terjadi+kesalahan+sistem");
    }
} else {
    redirect('views/auth/login.php');
}
