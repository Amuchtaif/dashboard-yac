<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number']); // Capture Phone
    $address = trim($_POST['address']); // Capture Address
    $password = trim($_POST['password']);
    $division_id = $_POST['division_id'] ?: null;
    $unit_id = $_POST['unit_id'] ?: null;
    $position_id = $_POST['position_id'] ?: null;
    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;

    if (!empty($full_name) && !empty($email) && !empty($password) && !empty($phone) && !empty($address)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Check email uniqueness
        $check = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
        header("Location: ../../views/employees/create.php?error=Email+already+exists");
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO employees (full_name, email, phone_number, address, password, division_id, unit_id, schedule_id, position_id) VALUES (:name, :email, :phone, :address, :pass, :div, :unit, :sched, :pos)");
            $stmt->bindParam(':name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':pass', $hashed_password);
            $stmt->bindParam(':div', $division_id);
            $stmt->bindParam(':unit', $unit_id);
            $stmt->bindParam(':sched', $schedule_id);
            $stmt->bindParam(':pos', $position_id);
            $stmt->execute();

        header("Location: ../../views/employees/index.php?success=Employee+Added");
        } catch (PDOException $e) {
        header("Location: ../../views/employees/create.php?error=Database+Error");
        }
    } else {
        header("Location: ../../views/employees/create.php?error=Required+fields+missing");
    }
}
