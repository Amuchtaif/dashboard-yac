<?php
require_once '../../config/database.php';
require_once '../../config/app.php';

check_login();
check_permission('manage_news');

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    $db = new Database();
    $conn = $db->getConnection();

    try {
        // Fetch image to delete
        $stmt = $conn->prepare("SELECT image FROM news WHERE id = ?");
        $stmt->execute([$id]);
        $news = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($news) {
            // Delete image file
            if ($news['image']) {
                $file_path = '../../uploads/news/' . $news['image'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            // Delete record
            $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
            $stmt->execute([$id]);

        header("Location: ../../views/news/index.php?success=Berita+berhasil+dihapus.");
        } else {
        header("Location: ../../views/news/index.php?error=Berita+tidak+ditemukan.");
        }
    } catch (PDOException $e) {
        header("Location: ../../views/news/index.php?error=Database Error: " . $e->getMessage());
    }
} else {
        header("Location: ../../views/news/index.php?error=ID+tidak+valid.");
}
