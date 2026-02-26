<?php
// api/submit_news.php
error_reporting(0);
ini_set('display_errors', 0);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
date_default_timezone_set('Asia/Jakarta');

include_once '../config/database.php';
include_once '../config/permission.php';

$database = new Database();
$conn = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    exit();
}

$user_id = $_POST['user_id'] ?? '';
$title = $_POST['title'] ?? '';
$category = $_POST['category'] ?? '';
$content = $_POST['content'] ?? '';

if (empty($user_id) || empty($title) || empty($category) || empty($content)) {
    echo json_encode(["status" => "error", "message" => "Semua kolom wajib diisi."]);
    exit();
}

// Permission Check
if (!hasPermission($user_id, 'manage_news')) {
    echo json_encode(["status" => "error", "message" => "Anda tidak memiliki hak akses untuk mengelola berita."]);
    exit();
}

// Handle File Upload
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExtension, $allowedfileExtensions)) {
        $uploadFileDir = '../uploads/news/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }

        $newFileName = time() . '_' . rand(1000, 9999) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $imageName = $newFileName;
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal upload gambar."]);
            exit();
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Format file harus JPG, PNG, atau WebP."]);
        exit();
    }
}

try {
    $sql = "INSERT INTO news (title, category, content, image, author_id) 
            VALUES (:title, :category, :content, :image, :author_id)";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':content', $content);
    $stmt->bindParam(':image', $imageName);
    $stmt->bindParam(':author_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "message" => "Berita berhasil diterbitkan!",
            "data" => [
                "id" => $conn->lastInsertId(),
                "title" => $title
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan ke database."]);
    }

} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
