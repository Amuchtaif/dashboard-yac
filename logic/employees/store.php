<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
    $return_filters = isset($_POST['return_filters']) ? $_POST['return_filters'] : '';
    $redirect_qs = $return_filters ? "&" . $return_filters : "";
    
    $profile_photo = null;

    // Handle File Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_photo']['tmp_name'];
        $file_name = $_FILES['profile_photo']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
            $upload_path = BASE_PATH . '/uploads/profile_photos/' . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                $profile_photo = $new_file_name;
            }
        }
    }

    if (!empty($full_name) && !empty($email) && !empty($password) && !empty($phone) && !empty($address) && !empty($nik)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Check email uniqueness
        $check = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            header("Location: ../../views/employees/form.php?error=Email sudah terdaftar" . $redirect_qs);
            exit;
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare("INSERT INTO employees (nik, full_name, email, phone_number, address, password, division_id, unit_id, schedule_id, position_id, profile_photo) VALUES (:nik, :name, :email, :phone, :address, :pass, :div, :unit, :sched, :pos, :photo)");
            $stmt->bindParam(':nik', $nik);
            $stmt->bindParam(':name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':pass', $hashed_password);
            $stmt->bindParam(':div', $division_id);
            $stmt->bindParam(':unit', $unit_id);
            $stmt->bindParam(':sched', $schedule_id);
            $stmt->bindParam(':pos', $position_id);
            $stmt->bindParam(':photo', $profile_photo);
            $stmt->execute();

            header("Location: ../../views/employees/index.php?success=Data pegawai berhasil ditambahkan" . $redirect_qs);
        } catch (PDOException $e) {
            header("Location: ../../views/employees/form.php?error=Kesalahan Database: " . $e->getMessage() . $redirect_qs);
        }
    } else {
        header("Location: ../../views/employees/form.php?error=Mohon lengkapi semua bidang wajib" . $redirect_qs);
    }
}
