<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if (isset($_GET['id'])) {
    $db = new Database();
    $conn = $db->getConnection();
    $id = $_GET['id'];

    // Get Icon path to delete file
    $stmt = $conn->prepare("SELECT icon FROM education_units WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $unit = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($unit && $unit['icon']) {
        $filePath = '../../uploads/education_units/' . $unit['icon'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // Delete Record
    $query = "DELETE FROM education_units WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        redirect('views/education_units/index.php');
    } else {
        echo "Error deleting education unit.";
    }
}
?>