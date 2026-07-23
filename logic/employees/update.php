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
    $gender = $_POST['gender'] ?: null;
    $remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == "1";
    $return_filters = isset($_POST['return_filters']) ? $_POST['return_filters'] : '';
    $redirect_qs = $return_filters ? "&" . $return_filters : "";

    if (!empty($full_name) && !empty($email) && !empty($id) && !empty($nik) && !empty($gender)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Get current photo
        $stmtCurrent = $conn->prepare("SELECT profile_photo FROM employees WHERE id = ?");
        $stmtCurrent->execute([$id]);
        $current_photo = $stmtCurrent->fetchColumn();

        // Handle File Upload
        $profile_photo = $current_photo;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_photo']['tmp_name'];
            $file_name = $_FILES['profile_photo']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($file_ext, $allowed_ext)) {
                $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
                $upload_path = BASE_PATH . '/uploads/profile_photos/' . $new_file_name;

                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Delete old photo if exists
                    if ($current_photo && file_exists(BASE_PATH . '/uploads/profile_photos/' . $current_photo)) {
                        unlink(BASE_PATH . '/uploads/profile_photos/' . $current_photo);
                    }
                    $profile_photo = $new_file_name;
                }
            }
        } elseif ($remove_photo) {
            // Remove photo if requested
            if ($current_photo && file_exists(BASE_PATH . '/uploads/profile_photos/' . $current_photo)) {
                unlink(BASE_PATH . '/uploads/profile_photos/' . $current_photo);
            }
            $profile_photo = null;
        }

        $old_stmt = $conn->prepare("SELECT nik, full_name, email, phone_number, division_id, unit_id, position_id FROM employees WHERE id = ? LIMIT 1");
        $old_stmt->execute([$id]);
        $old_emp_data = $old_stmt->fetch(PDO::FETCH_ASSOC);

        // Check email uniqueness (exclude self)
        $check = $conn->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
        $check->execute([$email, $id]);
        if ($check->rowCount() > 0) {
            header("Location: ../../views/employees/form.php?id=$id&error=Email sudah terdaftar" . $redirect_qs);
            exit;
        }

        try {
            // Build Query dynamically based on password presence
            $params = [
                ':nik' => $nik,
                ':name' => $full_name,
                ':email' => $email,
                ':phone' => $phone,
                ':address' => $address,
                ':div' => $division_id,
                ':unit' => $unit_id,
                ':pos' => $position_id,
                ':sched' => $schedule_id,
                ':photo' => $profile_photo,
                ':gender' => $gender,
                ':id' => $id
            ];

            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE employees SET 
                            nik = :nik, 
                            full_name = :name, 
                            email = :email, 
                            phone_number = :phone, 
                            address = :address, 
                            password = :pass, 
                            division_id = :div, 
                            unit_id = :unit, 
                            position_id = :pos, 
                            schedule_id = :sched,
                            profile_photo = :photo,
                            gender = :gender
                        WHERE id = :id";
                $params[':pass'] = $hashed_password;
            } else {
                $sql = "UPDATE employees SET 
                            nik = :nik, 
                            full_name = :name, 
                            email = :email, 
                            phone_number = :phone, 
                            address = :address, 
                            division_id = :div, 
                            unit_id = :unit, 
                            position_id = :pos, 
                            schedule_id = :sched,
                            profile_photo = :photo,
                            gender = :gender
                        WHERE id = :id";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
                $_SESSION['user_photo'] = $profile_photo;
                $_SESSION['user_name'] = $full_name;
            }

            Logger::activity(
                'Pegawai',
                'UPDATE',
                "Mengubah data pegawai '$full_name' (NIK: $nik)",
                [
                    'table' => 'employees',
                    'record_id' => $id,
                    'old_data' => $old_emp_data ?: null,
                    'new_data' => [
                        'nik' => $nik,
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone_number' => $phone,
                        'division_id' => $division_id,
                        'unit_id' => $unit_id,
                        'position_id' => $position_id
                    ]
                ]
            );

            header("Location: ../../views/employees/index.php?success=Data pegawai berhasil diperbarui" . $redirect_qs);
        } catch (PDOException $e) {
            header("Location: ../../views/employees/form.php?id=$id&error=Kesalahan Database: " . $e->getMessage() . $redirect_qs);
        }
    } else {
        header("Location: ../../views/employees/form.php?id=$id&error=Mohon lengkapi semua bidang wajib" . $redirect_qs);
    }
}
?>
