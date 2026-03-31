<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $nik = trim($_POST['nik']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone_number']);
    $address = trim($_POST['address']);
    $password = trim($_POST['password']);
    $division_id = $_POST['division_id'] ?: null;
    $unit_id = $_POST['unit_id'] ?: null;
    $position_id = $_POST['position_id'] ?: null;
    $schedule_id = !empty($_POST['schedule_id']) ? $_POST['schedule_id'] : null;

    if (!empty($full_name) && !empty($email) && !empty($id) && !empty($nik)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Check email uniqueness (exclude self)
        $check = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->rowCount() > 0) {
            header("Location: ../../views/employees/edit.php?id=$id&error=Email already exists");
            exit;
        }

        try {
            // Build Query dynamically based on password presence
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE employees SET nik = :nik, full_name = :name, email = :email, phone_number = :phone, address = :address, password = :pass, division_id = :div, unit_id = :unit, position_id = :pos, schedule_id = :sched WHERE id = :id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':pass', $hashed_password);
            } else {
                $sql = "UPDATE employees SET nik = :nik, full_name = :name, email = :email, phone_number = :phone, address = :address, division_id = :div, unit_id = :unit, position_id = :pos, schedule_id = :sched WHERE id = :id";
                $stmt = $conn->prepare($sql);
            }

            $stmt->bindParam(':nik', $nik);
            $stmt->bindParam(':name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':div', $division_id);
            $stmt->bindParam(':unit', $unit_id);
            $stmt->bindParam(':pos', $position_id);
            $stmt->bindParam(':sched', $schedule_id);
            $stmt->bindParam(':id', $id);

            $stmt->execute();

        header("Location: ../../views/employees/index.php?success=Employee+Updated");
        } catch (PDOException $e) {
            header("Location: ../../views/employees/edit.php?id=$id&error=Database Error: " . $e->getMessage());
        }
    } else {
        header("Location: ../../views/employees/edit.php?id=$id&error=Required fields missing");
    }
}
?>
