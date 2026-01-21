<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();

    $name = $_POST['name'];
    $description = $_POST['description'];
    $operational_unit_id = !empty($_POST['operational_unit_id']) ? $_POST['operational_unit_id'] : null;
    $icon = null;

    // Handle Icon Upload
    if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../uploads/education_units/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileExtension = pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExtension;
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['icon']['tmp_name'], $targetPath)) {
            $icon = $fileName;
        }
    }

    $query = "INSERT INTO education_units (name, description, operational_unit_id, icon) VALUES (:name, :description, :operational_unit_id, :icon)";
    $stmt = $conn->prepare($query);

    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':operational_unit_id', $operational_unit_id);
    $stmt->bindParam(':icon', $icon);

    if ($stmt->execute()) {
        redirect('views/education_units/index.php?success=Unit added successfully');
    } else {
        redirect('views/education_units/create.php?error=Failed to add unit');
    }
}
?>