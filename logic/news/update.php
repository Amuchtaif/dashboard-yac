<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('manage_news');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $content = $_POST['content'];

    if (empty($id) || empty($title) || empty($category) || empty($content)) {
        header("Location: ../../views/news/form.php?id=$id&error=Semua kolom wajib diisi.");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Fetch existing image
    $stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $existing_news = $stmt->fetch(PDO::FETCH_ASSOC);
    $image_name = $existing_news['image'];

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/news/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            // Delete old image if exists
            if ($image_name && file_exists($upload_dir . $image_name)) {
                unlink($upload_dir . $image_name);
            }

            $image_name = time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
    }

    try {
        $sql = "UPDATE news SET title = :title, category = :category, content = :content, image = :image WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':category' => $category,
            ':content' => $content,
            ':image' => $image_name,
            ':id' => $id
        ]);

        header("Location: ../../views/news/index.php?success=Berita+berhasil+diperbarui.");
    } catch (PDOException $e) {
        header("Location: ../../views/news/form.php?id=$id&error=Database Error: " . $e->getMessage());
    }
}
