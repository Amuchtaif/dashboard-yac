<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $lat = $_POST['office_lat'];
    $long = $_POST['office_long'];

    $db = new Database();
    $conn = $db->getConnection();

    try {
        $sql = "INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value";
        $stmt = $conn->prepare($sql);

        // Update Lat
        $stmt->execute([':key' => 'office_lat', ':value' => $lat]);
        // Update Long
        $stmt->execute([':key' => 'office_long', ':value' => $long]);

        header("Location: ../../views/settings/index.php?success=Settings+Updated");
    } catch (PDOException $e) {
        header("Location: ../../views/settings/index.php?error=Update+Failed");
    }
}
