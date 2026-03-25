<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $radius_meter = $_POST['radius_meter'] ?? 100;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($name && $latitude && $longitude) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $sql = "INSERT INTO locations (name, latitude, longitude, radius_meter, is_active) 
                    VALUES (:name, :lat, :long, :rad, :active)";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':name' => $name,
                ':lat' => $latitude,
                ':long' => $longitude,
                ':rad' => $radius_meter,
                ':active' => $is_active
            ]);

            if ($result) {
        header("Location: ../../views/settings/locations.php?success=Lokasi+berhasil+ditambahkan");
                exit;
            }
        } catch (PDOException $e) {
            header("Location: ../../views/settings/location_form.php?error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: ../../views/settings/location_form.php?error=Harap+isi+semua+bidang");
        exit;
    }
}
