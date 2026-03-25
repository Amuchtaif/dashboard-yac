<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('manage_news');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $category = trim($_POST['category']);
    $content = $_POST['content']; // Expect HTML or long text
    $author_id = $_SESSION['user_id'];

    if (empty($title) || empty($category) || empty($content)) {
        header("Location: ../../views/news/form.php?error=Semua+kolom+wajib+diisi.");
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();

    // Handle Image Upload
    $image_name = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../../uploads/news/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($file_ext, $allowed_ext)) {
            $image_name = time() . '_' . rand(1000, 9999) . '.' . $file_ext;
            move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image_name);
        }
    }

    try {
        $sql = "INSERT INTO news (title, category, content, image, author_id) 
                VALUES (:title, :category, :content, :image, :author_id)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':category' => $category,
            ':content' => $content,
            ':image' => $image_name,
            ':author_id' => $author_id
        ]);

        header("Location: ../../views/news/index.php?success=Berita+berhasil+diterbitkan.");
    } catch (PDOException $e) {
        header("Location: ../../views/news/form.php?error=Database Error: " . $e->getMessage());
    }
}
