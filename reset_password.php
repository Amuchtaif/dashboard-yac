<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    $password = 'password123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $email = 'admin@example.com';

    $stmt = $conn->prepare("UPDATE employees SET password = :password WHERE email = :email");
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':email', $email);

    if ($stmt->execute()) {
        echo "<h1>Password Reset Successfully</h1>";
        echo "<p>Email: <b>" . $email . "</b></p>";
        echo "<p>New Password: <b>" . $password . "</b></p>";
        echo "<p><a href='views/auth/login.php'>Go to Login</a></p>";
    } else {
        echo "Error updating password.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>