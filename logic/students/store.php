<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $class_grade = trim($_POST['class_grade']);

    if (!empty($name) && !empty($class_grade)) {
        $db = new Database();
        $conn = $db->getConnection();

        try {
            $stmt = $conn->prepare("INSERT INTO students (name, class_grade) VALUES (:name, :class_grade)");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':class_grade', $class_grade);
            $stmt->execute();
            header("Location: ../../views/students/index.php?success=Student Added");
        } catch (PDOException $e) {
            header("Location: ../../views/students/index.php?error=Error Adding Student");
        }
    } else {
        header("Location: ../../views/students/index.php?error=All fields are required");
    }
}
