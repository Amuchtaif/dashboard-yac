<?php
/**
 * Get Performance Leaderboard API
 * Endpoint: GET /api/get_performance_leaderboard.php?user_id=40
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

include_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$my_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

try {
    // Query to get all employees and their points, then rank them in PHP or SQL
    // Using a subquery to calculate points first
    $query = "
        SELECT 
            id,
            full_name,
            total_points
        FROM (
            SELECT 
                e.id,
                e.full_name,
                COALESCE(att.points, 0) + COALESCE(meet.points, 0) as total_points
            FROM employees e
            LEFT JOIN (
                SELECT 
                    user_id, 
                    SUM(
                        CASE 
                            WHEN status = 'Hadir' OR status = 'Tepat Waktu' THEN 10 
                            WHEN status = 'Telat' THEN -5 
                            ELSE 0 
                        END +
                        CASE 
                            WHEN status_out = 'Pulang' OR status_out = 'Tepat Waktu' THEN 10 
                            WHEN status_out = 'Pulang Cepat' THEN -5 
                            ELSE 0 
                        END
                    ) as points 
                FROM attendances 
                GROUP BY user_id
            ) att ON e.id = att.user_id
            LEFT JOIN (
                SELECT 
                    employee_id, 
                    COUNT(*) * 10 as points 
                FROM meeting_participants 
                WHERE status = 'present' 
                GROUP BY employee_id
            ) meet ON e.id = meet.employee_id
            WHERE e.status = 'active'
        ) as summary
        ORDER BY total_points DESC, full_name ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $all_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $leaderboard = [];
    $my_rank_data = null;
    $rank = 1;
    $prev_points = null;
    $display_rank = 0;

    foreach ($all_data as $index => $row) {
        // Handle Tie Ranks
        if ($prev_points === null || $row['total_points'] < $prev_points) {
            $display_rank = $index + 1;
        }
        $prev_points = $row['total_points'];

        $item = [
            "id" => intval($row['id']),
            "full_name" => $row['full_name'],
            "total_points" => intval($row['total_points']),
            "rank" => $display_rank,
            "is_me" => ($my_user_id > 0 && $row['id'] == $my_user_id)
        ];

        // Store current user's rank info
        if ($item['is_me']) {
            $my_rank_data = $item;
        }

        // Only add to leaderboard list (max 10 people)
        if (count($leaderboard) < 10) {
            $leaderboard[] = $item;
        }
    }

    // Split Podium (Top 3)
    $podium = array_slice($leaderboard, 0, 3);
    
    // Remaining (Rank 4 and beyond)
    $others = array_slice($leaderboard, 3);

    echo json_encode([
        "success" => true,
        "data" => [
            "podium" => $podium,
            "others" => $others,
            "my_rank" => $my_rank_data,
            "full_list" => $leaderboard // For easy access if needed
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database Error: " . $e->getMessage()]);
}
?>
