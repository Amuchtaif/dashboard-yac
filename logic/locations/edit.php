<?php
require_once '../../config/app.php';
require_once '../../config/database.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $radius_meter = $_POST['radius_meter'] ?? 100;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($id && $name && $latitude && $longitude) {
        try {
            $db = new Database();
            $conn = $db->getConnection();

            $sql = "UPDATE locations SET 
                    name = :name, 
                    latitude = :lat, 
                    longitude = :long, 
                    radius_meter = :rad, 
                    is_active = :active 
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':name' => $name,
                ':lat' => $latitude,
                ':long' => $longitude,
                ':rad' => $radius_meter,
                ':active' => $is_active,
                ':id' => $id
            ]);

            if ($result) {
        header("Location: ../../views/settings/locations.php?success=Lokasi+berhasil+diperbarui");
                exit;
            }
        } catch (PDOException $e) {
            header("Location: ../../views/settings/location_form.php?id=$id&error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: ../../views/settings/location_form.php?id=$id&error=Harap isi semua bidang");
        exit;
    }
}
