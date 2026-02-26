<?php
// api/get_news.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT 
                n.id, 
                n.title, 
                n.category, 
                n.content, 
                n.image, 
                n.likes_count, 
                n.created_at, 
                e.full_name as author_name,
                p.name as author_position
              FROM news n
              LEFT JOIN employees e ON n.author_id = e.id
              LEFT JOIN positions p ON e.position_id = p.id
              ORDER BY n.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format full image URL
    // Format full image URL
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/api/get_news.php');
    $baseUrl = $protocol . "://" . $host . $scriptDir . "/../uploads/news/";
    
    foreach ($news as &$item) {
        if (!empty($item['image'])) {
            $item['image_url'] = $baseUrl . $item['image'];
        } else {
            $item['image_url'] = null;
        }
    }

    echo json_encode([
        "success" => true,
        "data" => $news
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
