<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        header("Location: ../../views/auth/login.php?error=All fields are required");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("SELECT * FROM employees WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];

            header("Location: ../../views/dashboard/index.php");
            exit;
        } else {
            header("Location: ../../views/auth/login.php?error=Invalid Credentials");
            exit;
        }
    } catch (PDOException $e) {
        header("Location: ../../views/auth/login.php?error=System Error");
    }
} else {
    redirect('views/auth/login.php');
}
