<?php
// api/get_news.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $user_id = $_GET['user_id'] ?? null;

    // 1. Auto-migration (Ensure columns exist)
    try {
        $db->exec("ALTER TABLE news ADD COLUMN IF NOT EXISTS likes_count INT DEFAULT 0");
        $db->exec("ALTER TABLE news ADD COLUMN IF NOT EXISTS views_count INT DEFAULT 0 AFTER likes_count");
    } catch (Exception $e) {
        try {
            $check = $db->query("SHOW COLUMNS FROM news LIKE 'likes_count'");
            if (!$check->fetch()) { $db->exec("ALTER TABLE news ADD COLUMN likes_count INT DEFAULT 0"); }
        } catch (Exception $e2) {}
        try {
            $check = $db->query("SHOW COLUMNS FROM news LIKE 'views_count'");
            if (!$check->fetch()) { $db->exec("ALTER TABLE news ADD COLUMN views_count INT DEFAULT 0 AFTER likes_count"); }
        } catch (Exception $e2) {}
    }

    // 2. Query with robust joins and check for user like
    $hasEmployees = false;
    try {
        $checkEmp = $db->query("SELECT 1 FROM employees LIMIT 1");
        $hasEmployees = true;
    } catch (Exception $e) {}

    $select_liked = $user_id ? ", (SELECT count(*) FROM news_likes WHERE news_id = n.id AND user_id = " . intval($user_id) . ") as is_liked" : ", 0 as is_liked";

    if ($hasEmployees) {
        $query = "SELECT 
                    n.* $select_liked, 
                    e.full_name as author_name,
                    p.name as author_position
                  FROM news n
                  LEFT JOIN employees e ON n.author_id = e.id
                  LEFT JOIN positions p ON e.position_id = p.id
                  ORDER BY n.created_at DESC";
    } else {
        $hasUsers = false;
        try {
            $checkUsers = $db->query("SELECT 1 FROM users LIMIT 1");
            $hasUsers = true;
        } catch (Exception $e) {}

        if ($hasUsers) {
            $query = "SELECT 
                        n.* $select_liked, 
                        u.full_name as author_name,
                        'Staff' as author_position
                      FROM news n
                      LEFT JOIN users u ON n.author_id = u.id
                      ORDER BY n.created_at DESC";
        } else {
            $query = "SELECT n.* $select_liked, 'Unknown' as author_name, '' as author_position FROM news n ORDER BY n.created_at DESC";
        }
    }

    $stmt = $db->prepare($query);
    $stmt->execute();
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format Response
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = str_replace('/api/get_news.php', '', $scriptName);
    if (empty($basePath) || $basePath == '/') $basePath = '/dashboard-yac';
    
    $imageUrlBase = $protocol . "://" . $host . $basePath . "/uploads/news/";
    
    foreach ($news as &$item) {
        $item['likes_count'] = (int)($item['likes_count'] ?? 0);
        $item['views_count'] = (int)($item['views_count'] ?? 0);
        $item['is_liked'] = (int)($item['is_liked'] ?? 0) > 0;
        
        if (!empty($item['image'])) {
            $item['image_url'] = $imageUrlBase . $item['image'];
        } else {
            $item['image_url'] = null;
        }
    }

    echo json_encode([
        "success" => true,
        "count" => count($news),
        "data" => $news
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false, 
        "message" => "Critical Error: " . $e->getMessage()
    ]);
}
?>
